<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\AssignmentLog;
use App\Models\User;
use App\Services\NotifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Modul Assignment — penugasan bebas antar-user. Setiap user yang login bisa
 * membuat tugas untuk user lain (keputusan bisnis: "semua user -> siapa saja").
 * Tugas muncul di Todo List penerimanya. Riwayat tiap tugas (termasuk
 * komentar) dicatat di AssignmentLog.
 *
 * Alur status — lihat App\Models\Assignment untuk diagramnya:
 *   PENDING -> READ (otomatis saat penerima membuka detail, lihat logs())
 *           -> ON_PROGRESS (penerima menekan "Mulai Dikerjakan")
 *           -> CLOSED (penerima/pemberi/superadmin menandai selesai)
 *   (PENDING/READ/ON_PROGRESS) -> CANCELLED (pemberi/superadmin)
 *   CLOSED/CANCELLED -> PENDING (reopen, pemberi/superadmin)
 *
 * Tidak ada layer baru (Policy/FormRequest): otorisasi in-controller + validasi
 * inline, sama seperti controller lain di aplikasi ini.
 */
class AssignmentController extends Controller
{
    /** Validasi lampiran — disamakan dengan lampiran Cost Request. */
    private const ATTACHMENT_RULES = 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx';

    private const MSG_STATUS_BERUBAH = 'Status sudah berubah, muat ulang.';

    public function index(Request $request)
    {
        $user = Auth::user();

        [$assignedToMe, $createdByMe] = $this->listsFor($user, semua: true);

        $assignees = User::where('status', 'ACTIVE')
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('assignment.index', compact('assignedToMe', 'createdByMe', 'assignees'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:5000',
            'assignee_id'   => 'required|exists:users,id',
            'due_date'      => 'nullable|date|after_or_equal:today',
            'priority'      => 'required|in:' . implode(',', Assignment::PRIORITIES),
            'attachments'   => 'nullable|array',
            'attachments.*' => self::ATTACHMENT_RULES,
        ]);

        if ((int) $data['assignee_id'] === $user->id) {
            return back()->withInput()->with('error', 'Tugas tidak bisa ditugaskan ke diri sendiri.');
        }

        // Penerima harus user aktif (tidak bisa menugaskan ke akun nonaktif).
        $assignee = User::where('status', 'ACTIVE')->find($data['assignee_id']);
        if (! $assignee) {
            return back()->withInput()->with('error', 'Penerima tugas tidak ditemukan atau tidak aktif.');
        }

        $assignment = DB::transaction(function () use ($data, $request) {
            $assignment = Assignment::create([
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'assigner_id' => Auth::id(),
                'assignee_id' => $data['assignee_id'],
                'due_date'    => $data['due_date'] ?? null,
                'priority'    => $data['priority'],
                'status'      => 'PENDING',
            ]);

            $this->saveAttachments($assignment, $request);

            AssignmentLog::record($assignment, 'create');

            return $assignment;
        });

        $this->notify(
            $assignee,
            'Tugas baru untuk Anda',
            $user->name . ' memberi Anda tugas: ' . $assignment->title
        );

        return redirect()->route('assignment.index')->with('success', 'Tugas berhasil dibuat.');
    }

    public function update(Request $request, Assignment $assignment)
    {
        $user = Auth::user();

        // Hanya pemberi tugas (atau superadmin) yang boleh mengubah isinya,
        // dan hanya selama tugas belum berstatus akhir.
        if (! $this->canManage($assignment)) {
            abort(403);
        }
        if (! in_array($assignment->status, Assignment::OPEN_STATUSES, true)) {
            return back()->with('error', 'Tugas yang sudah closed/cancelled tidak bisa diubah.');
        }

        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:5000',
            'assignee_id'   => 'required|exists:users,id',
            'due_date'      => 'nullable|date',
            'priority'      => 'required|in:' . implode(',', Assignment::PRIORITIES),
            'attachments'   => 'nullable|array',
            'attachments.*' => self::ATTACHMENT_RULES,
        ]);

        // Tanggal lama boleh dipertahankan walau sudah lewat; hanya tanggal
        // yang diubah yang tidak boleh di masa lalu.
        if (! empty($data['due_date'])) {
            $dueBaru = \Illuminate\Support\Carbon::parse($data['due_date'])->startOfDay();
            $berubah = ! $assignment->due_date || ! $dueBaru->isSameDay($assignment->due_date);
            if ($berubah && $dueBaru->lt(today())) {
                return back()->withInput()->with('error', 'Tenggat tidak boleh di masa lalu.');
            }
        }

        if ((int) $data['assignee_id'] === $assignment->assigner_id) {
            return back()->withInput()->with('error', 'Tugas tidak bisa ditugaskan ke pemberi tugasnya sendiri.');
        }

        $assigneeBaru = User::where('status', 'ACTIVE')->find($data['assignee_id']);
        if (! $assigneeBaru) {
            return back()->withInput()->with('error', 'Penerima tugas tidak ditemukan atau tidak aktif.');
        }

        $penerimaLama = $assignment->assignee_id;
        $diganti      = (int) $data['assignee_id'] !== $penerimaLama;

        $berhasil = DB::transaction(function () use ($assignment, $data, $request, $diganti) {
            $atribut = [
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'assignee_id' => $data['assignee_id'],
                'due_date'    => $data['due_date'] ?? null,
                'priority'    => $data['priority'],
            ];
            // Penerima baru memulai dari awal: belum dibaca, belum dikerjakan.
            if ($diganti) {
                $atribut += ['status' => 'PENDING', 'read_at' => null, 'started_at' => null];
            }

            if (! $this->transisi($assignment, Assignment::OPEN_STATUSES, $atribut)) {
                return false;
            }

            $this->saveAttachments($assignment, $request);

            AssignmentLog::record($assignment, $diganti ? 'reassign' : 'update');

            return true;
        });

        if (! $berhasil) {
            return back()->with('error', self::MSG_STATUS_BERUBAH);
        }

        // Beri tahu penerima (baru maupun yang lama, kalau penerima dipindah).
        $this->notify(
            $assigneeBaru,
            $diganti ? 'Tugas baru untuk Anda' : 'Tugas diperbarui',
            $user->name . ($diganti ? ' memberi Anda tugas: ' : ' memperbarui tugas: ') . $assignment->title
        );
        if ($diganti) {
            $this->notify(
                User::find($penerimaLama),
                'Tugas dialihkan',
                'Tugas "' . $assignment->title . '" tidak lagi ditugaskan kepada Anda.'
            );
        }

        return back()->with('success', 'Tugas berhasil diperbarui.');
    }

    /**
     * Penerima tugas menekan "Mulai Dikerjakan": READ -> ON_PROGRESS.
     * Hanya penerima yang boleh — makna statusnya adalah "sedang ia kerjakan".
     */
    public function startProgress(Assignment $assignment)
    {
        $user = Auth::user();

        if (! $user || $user->id !== $assignment->assignee_id) {
            abort(403);
        }
        if ($assignment->status !== 'READ') {
            return back()->with('error', 'Tugas ini tidak sedang menunggu dikerjakan.');
        }

        if (! $this->transisi($assignment, ['READ'], ['status' => 'ON_PROGRESS', 'started_at' => now()])) {
            return back()->with('error', self::MSG_STATUS_BERUBAH);
        }
        AssignmentLog::record($assignment, 'start_progress');

        $this->notify($assignment->assigner, 'Tugas mulai dikerjakan', $user->name . ' mulai mengerjakan tugas: ' . $assignment->title);

        return back()->with('success', 'Tugas ditandai sedang dikerjakan.');
    }

    public function markDone(Request $request, Assignment $assignment)
    {
        $user = Auth::user();

        // Yang menyelesaikan: penerima tugas, pemberi, atau superadmin.
        if (! $this->canComplete($assignment)) {
            abort(403);
        }
        if (! in_array($assignment->status, Assignment::OPEN_STATUSES, true)) {
            return back()->with('error', 'Tugas ini sudah tidak terbuka.');
        }

        $notes = $request->validate(['notes' => 'nullable|string|max:500'])['notes'] ?? null;

        if (! $this->transisi($assignment, Assignment::OPEN_STATUSES, ['status' => 'CLOSED', 'done_at' => now()])) {
            return back()->with('error', self::MSG_STATUS_BERUBAH);
        }
        AssignmentLog::record($assignment, 'close', $notes);

        // Kabari pemberi tugas kalau yang menyelesaikan adalah penerima.
        if ($user->id !== $assignment->assigner_id) {
            $this->notify(
                $assignment->assigner,
                'Tugas selesai',
                $user->name . ' menyelesaikan tugas: ' . $assignment->title
            );
        }

        return back()->with('success', 'Tugas ditandai selesai.');
    }

    public function cancel(Request $request, Assignment $assignment)
    {
        $user = Auth::user();

        // Pembatalan milik pemberi tugas (atau superadmin).
        if (! $this->canManage($assignment)) {
            abort(403);
        }
        if (! in_array($assignment->status, Assignment::OPEN_STATUSES, true)) {
            return back()->with('error', 'Tugas ini sudah tidak terbuka.');
        }

        $notes = $request->validate(['notes' => 'nullable|string|max:500'])['notes'] ?? null;

        if (! $this->transisi($assignment, Assignment::OPEN_STATUSES, ['status' => 'CANCELLED', 'cancelled_at' => now()])) {
            return back()->with('error', self::MSG_STATUS_BERUBAH);
        }
        AssignmentLog::record($assignment, 'cancel', $notes);

        if ($user->id !== $assignment->assignee_id) {
            $this->notify(
                $assignment->assignee,
                'Tugas dibatalkan',
                'Tugas "' . $assignment->title . '" dibatalkan oleh ' . $user->name . '.'
            );
        }

        return back()->with('success', 'Tugas dibatalkan.');
    }

    public function reopen(Request $request, Assignment $assignment)
    {
        // Membuka kembali tugas closed/cancelled — hak pemberi tugas.
        if (! $this->canManage($assignment)) {
            abort(403);
        }
        if (in_array($assignment->status, Assignment::OPEN_STATUSES, true)) {
            return back()->with('error', 'Tugas ini masih terbuka.');
        }

        $dibuka = $this->transisi($assignment, ['CLOSED', 'CANCELLED'], [
            'status'       => 'PENDING',
            'read_at'      => null,
            'started_at'   => null,
            'done_at'      => null,
            'cancelled_at' => null,
        ]);
        if (! $dibuka) {
            return back()->with('error', self::MSG_STATUS_BERUBAH);
        }
        AssignmentLog::record($assignment, 'reopen');

        $this->notify($assignment->assignee, 'Tugas dibuka kembali', 'Tugas "' . $assignment->title . '" dibuka kembali.');

        return back()->with('success', 'Tugas dibuka kembali.');
    }

    /**
     * Tambah komentar pada timeline. Diizinkan untuk pemberi & penerima
     * (atau superadmin) selama tugas belum berstatus akhir (CLOSED/CANCELLED).
     */
    public function comment(Request $request, Assignment $assignment)
    {
        if (! $this->canView($assignment)) {
            abort(403);
        }
        if (! $assignment->can_comment) {
            return back()->with('error', 'Komentar tidak bisa ditambahkan pada tugas yang sudah closed/cancelled.');
        }

        $data = $request->validate(['body' => 'required|string|max:500']);

        AssignmentLog::record($assignment, 'comment', $data['body']);

        $lawan = Auth::id() === $assignment->assigner_id ? $assignment->assignee : $assignment->assigner;
        $this->notify($lawan, 'Komentar baru', Auth::user()->name . ' berkomentar pada tugas: ' . $assignment->title);

        // Dikirim via fetch AJAX dari modal detail (lihat assignment/_board.blade.php)
        // — back() akan mengikuti redirect dan mengembalikan HTML halaman penuh,
        // bukan yang diinginkan pemanggilnya.
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Komentar ditambahkan.');
    }

    /**
     * Timeline satu tugas (partial HTML untuk modal/drawer). Saat penerima
     * tugas membuka ini untuk pertama kali (status masih PENDING), transisi
     * otomatis ke READ dicatat di sini — inilah yang dimaksud "dibaca".
     */
    public function logs(Request $request, Assignment $assignment)
    {
        $user = Auth::user();

        if (! $this->canView($assignment)) {
            abort(403);
        }

        // Prefetch browser tidak boleh menandai "dibaca" — hanya pembukaan nyata.
        $prefetch = str_contains(strtolower($request->header('Sec-Purpose', '') . ' ' . $request->header('Purpose', '')), 'prefetch');

        if (! $prefetch && $assignment->status === 'PENDING' && $user->id === $assignment->assignee_id) {
            $assignment->update(['status' => 'READ', 'read_at' => now()]);
            AssignmentLog::record($assignment, 'read');
        }

        $assignment->load(['logs.actor', 'attachments', 'assigner', 'assignee']);

        return view('assignment._detail', compact('assignment'));
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /** Batas hari tugas CLOSED/CANCELLED yang masih ditampilkan di Todo List. */
    public const RIWAYAT_HARI = 30;

    /**
     * Daftar tugas untuk Todo List / halaman index: [ditugaskan ke saya, dibuat saya].
     * Tugas terbuka selalu tampil; yang CLOSED/CANCELLED dibatasi RIWAYAT_HARI
     * hari terakhir kecuali $semua (halaman /assignment).
     */
    public function listsFor(User $user, bool $semua = false): array
    {
        $batasi = function ($q) use ($semua) {
            if ($semua) {
                return;
            }
            $q->where(function ($w) {
                $w->whereIn('status', Assignment::OPEN_STATUSES)
                  ->orWhereRaw('COALESCE(done_at, cancelled_at, updated_at) >= ?', [now()->subDays(self::RIWAYAT_HARI)]);
            });
        };

        $urutanStatus = "FIELD(status, 'ON_PROGRESS', 'READ', 'PENDING', 'CLOSED', 'CANCELLED')";

        $assignedToMe = Assignment::with(['assigner', 'assignee', 'attachments'])
            ->where('assignee_id', $user->id)
            ->tap($batasi)
            ->orderByRaw($urutanStatus)
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('id')
            ->get();

        $createdByMe = Assignment::with(['assigner', 'assignee', 'attachments'])
            ->where('assigner_id', $user->id)
            ->tap($batasi)
            ->orderByRaw($urutanStatus)
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('id')
            ->get();

        return [$assignedToMe, $createdByMe];
    }

    /**
     * Update bersyarat: hanya berhasil bila status baris MASIH salah satu dari
     * $dari (mencegah dua aksi bersamaan sama-sama lolos dari pengecekan di
     * memori). Mengembalikan true bila tepat satu baris berubah; model ikut
     * disegarkan supaya log/notifikasi memakai keadaan terbaru.
     */
    private function transisi(Assignment $assignment, array $dari, array $atribut): bool
    {
        $baris = Assignment::whereKey($assignment->getKey())
            ->whereIn('status', $dari)
            ->update($atribut);

        if ($baris === 1) {
            $assignment->refresh();
        }

        return $baris === 1;
    }

    private function saveAttachments(Assignment $assignment, Request $request): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('assignment_attachments', 'secure');

            AssignmentAttachment::create([
                'assignment_id' => $assignment->id,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path'          => $path,
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => Auth::id(),
            ]);
        }
    }

    private function notify(?User $recipient, string $title, string $message): void
    {
        if (! $recipient) {
            return;
        }

        NotifyService::send([$recipient], $title, $message, route('assignment.index'), 'assignment');
    }

    /** Pemberi tugas atau superadmin — boleh edit/cancel/reopen. */
    private function canManage(Assignment $assignment): bool
    {
        $user = Auth::user();

        return $user && ($user->id === $assignment->assigner_id || $user->hasRole('superadmin'));
    }

    /** Boleh menandai selesai: penerima, pemberi, atau superadmin. */
    private function canComplete(Assignment $assignment): bool
    {
        $user = Auth::user();

        return $user && (
            $user->id === $assignment->assignee_id
            || $user->id === $assignment->assigner_id
            || $user->hasRole('superadmin')
        );
    }

    /** Boleh melihat detail & berkomentar: penerima, pemberi, atau superadmin. */
    private function canView(Assignment $assignment): bool
    {
        return $this->canComplete($assignment);
    }
}

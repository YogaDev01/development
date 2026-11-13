<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterCategory;
use App\Models\LetterLog;
use App\Models\LetterSignature;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\DocumentSequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Letter Management (HRIS) — tahap 1.
 *
 * Gerbang: route menu:letter-management. Approve/reject DINAMIS menurut
 * struktur Team: reviewer = leader team pembuat surat + leader semua team
 * induknya (lihat approverIds()). Pembuat tidak boleh meng-approve suratnya
 * sendiri. Superadmin selalu boleh. Bila pembuat tak punya atasan di struktur
 * team, FALLBACK_APPROVER_ROLES yang boleh (agar surat tidak buntu).
 * Kategori hanya dikelola MANAGER_ROLES. Invoice BUKAN bagian modul ini.
 */
class LetterController extends Controller
{
    private const FALLBACK_APPROVER_ROLES = ['hr', 'finance_manager', 'approver'];
    private const MANAGER_ROLES  = ['superadmin', 'hr'];
    private const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /** Template isian per kode kategori (sama dengan mockup). */
    public const TEMPLATES = [
        'SPH'  => ['items' => true, 'tax' => 11, 'unit' => 'Paket', 'metaL' => ['NO PERMINTAAN'], 'metaR' => ['TANGGAL SURAT', 'MASA BERLAKU', 'TERMIN PEMBAYARAN'],
            'note' => 'Harga di atas belum termasuk biaya perjalanan di luar Jabodetabek.',
            'closing' => 'Demikian penawaran ini kami sampaikan. Atas perhatian dan kerja samanya kami ucapkan terima kasih.'],
        'PO'   => ['items' => true, 'tax' => 11, 'unit' => 'Unit', 'metaL' => ['NO PENAWARAN'], 'metaR' => ['TANGGAL PO', 'TANGGAL KIRIM', 'TERMIN PEMBAYARAN'],
            'note' => 'Barang dikirim ke Operational Office PT TEREX pada jam kerja 08.00 - 17.00 WIB.',
            'closing' => 'Mohon konfirmasi penerimaan Purchase Order ini paling lambat 2 hari kerja.'],
        'BAST' => ['items' => false, 'tax' => 0, 'unit' => '', 'metaL' => ['NO KONTRAK / SPK'], 'metaR' => ['TANGGAL SERAH TERIMA', 'LOKASI', 'PERIODE PEKERJAAN'],
            'note' => '', 'closing' => 'Demikian berita acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.'],
        'SK'   => ['items' => false, 'tax' => 0, 'unit' => '', 'metaL' => ['TENTANG'], 'metaR' => ['TANGGAL DITETAPKAN', 'BERLAKU MULAI'],
            'note' => '', 'closing' => 'Apabila di kemudian hari terdapat kekeliruan dalam keputusan ini, akan diadakan perbaikan sebagaimana mestinya.'],
        'SPK'  => ['items' => false, 'tax' => 0, 'unit' => '', 'metaL' => ['DASAR PENUGASAN'], 'metaR' => ['TANGGAL SPK', 'PERIODE PELAKSANAAN', 'LOKASI'],
            'note' => '', 'closing' => 'Harap dilaksanakan dengan penuh tanggung jawab dan melaporkan hasilnya kepada atasan langsung.'],
    ];

    public static function template(?string $code): array
    {
        return self::TEMPLATES[$code] ?? ['items' => false, 'tax' => 0, 'unit' => '', 'metaL' => [], 'metaR' => ['TANGGAL SURAT'], 'note' => '', 'closing' => ''];
    }

    /* ------------------------------------------------------------ DAFTAR */

    public function index(Request $request)
    {
        $q = Letter::with(['category', 'creator', 'customer', 'employee', 'team', 'department']);

        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if (in_array($request->query('type'), ['internal', 'external'], true)) {
            $q->where('type', $request->query('type'));
        }
        if ($c = $request->query('category')) {
            $q->where('category_id', (int) $c);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('letter_no', 'like', "%{$term}%")
                ->orWhere('reference_no', 'like', "%{$term}%"));
        }

        $statusCounts = Letter::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('letter.index', [
            // Disaring/diurutkan/dipaginasi di browser (DataTable + stepper); dibatasi 1.000 baris terbaru.
            'letters'      => $q->latest('updated_at')->limit(1000)->get(),
            'statusCounts' => $statusCounts,
            'categories'   => LetterCategory::orderBy('code')->get(),
            'form'         => $this->formOptions(),
        ]);
    }

    private function formOptions(): array
    {
        return [
            'customers'   => Customer::orderBy('name')->get(['id', 'name']),
            'employees'   => Employee::orderBy('name')->get(['id', 'name']),
            'teams'       => Team::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ];
    }

    /* ------------------------------------------------------------ BUAT/EDIT */

    private function validateInfo(Request $request): array
    {
        $data = $request->validate([
            'category_id'   => 'required|exists:letter_categories,id',
            'type'          => 'required|in:internal,external',
            'reference_no'  => 'nullable|string|max:100',
            'customer_id'   => 'nullable|exists:customers,id',
            'employee_id'   => 'nullable|exists:employees,id',
            'team_id'       => 'nullable|exists:teams,id',
            'department_id' => 'required|exists:departments,id',
        ]);

        if ($data['type'] === 'external' && empty($data['customer_id'])) {
            throw ValidationException::withMessages(['customer_id' => 'Surat external wajib memilih customer.']);
        }
        // Surat external tidak membawa karyawan; internal tidak membawa customer.
        if ($data['type'] === 'external') {
            $data['employee_id'] = null;
        } else {
            $data['customer_id'] = null;
        }

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->validateInfo($request);

        $letter = DB::transaction(function () use ($data) {
            $category = LetterCategory::findOrFail($data['category_id']);
            $letter = Letter::create($data + [
                'user_id'   => Auth::id(),
                'status'    => 'draft',
                'letter_no' => $this->nextNumber($category, $data['type']),
            ]);

            $t = self::template($category->code);
            $letter->detail()->create([
                'meta'    => collect(array_merge($t['metaL'], $t['metaR']))->map(fn ($k) => [$k, ''])->all(),
                'items'   => $t['items'] ? [['desc' => '', 'price' => 0, 'qty' => 1, 'unit' => $t['unit']]] : [],
                'tax'     => $t['tax'],
                'note'    => $t['note'],
                'closing' => $t['closing'],
            ]);
            $this->log($letter, 'created', 'Surat dibuat dengan nomor ' . $letter->letter_no);

            return $letter;
        });

        return redirect()->route('letter.show', [$letter, 'tab' => 'isi'])
            ->with('success', 'Draft dibuat dengan nomor ' . $letter->letter_no);
    }

    public function updateInfo(Request $request, Letter $letter)
    {
        abort_unless($letter->status === 'draft', 403, 'Informasi hanya bisa diubah saat Draft.');
        $letter->update($this->validateInfo($request)); // nomor surat tidak diubah
        $this->log($letter, 'updated');

        return back()->with('success', 'Surat diperbarui.');
    }

    /**
     * Nomor otomatis: NNN/KODE/INT|EXT/ROMAWI/TAHUN. Urutan dari DocumentSequence
     * (tidak memakai ulang nomor yang sudah dihapus).
     */
    private function nextNumber(LetterCategory $c, string $type): string
    {
        $now = now();

        $n = DocumentSequence::next("letter:{$c->code}:{$type}:" . $now->year, fn () => 0);

        return sprintf('%03d/%s/%s/%s/%d', $n, $c->code, $type === 'internal' ? 'INT' : 'EXT', self::ROMAN[$now->month - 1], $now->year);
    }

    /* ------------------------------------------------------------ DETAIL */

    public function show(Letter $letter)
    {
        $letter->load(['category', 'creator', 'customer', 'employee', 'team', 'department', 'detail',
            'attachments', 'signatures.signer', 'logs.user']);

        return view('letter.show', [
            'letter'   => $letter,
            'tab'      => request('tab', 'surat'),
            'form'     => $this->formOptions(),
            'tpl'      => self::template($letter->category->code),
            'users'    => User::orderBy('name')->get(['id', 'name']),
            'canApprove' => $this->canApprove($letter),
            'approvers'  => User::whereIn('id', $this->approverIds($letter))->orderBy('name')->pluck('name'),
        ]);
    }

    public function print(Letter $letter)
    {
        $letter->load(['category', 'customer', 'employee', 'team', 'department', 'detail', 'signatures.signer']);

        return view('letter.print', ['letter' => $letter, 'tpl' => self::template($letter->category->code)]);
    }

    public function saveDetail(Request $request, Letter $letter)
    {
        abort_unless($letter->canEditContent(), 403, 'Isi surat hanya dapat diubah saat Draft atau Rejected.');

        $data = $request->validate([
            'header'         => 'nullable|string|max:2000',
            'body'           => 'nullable|string|max:20000',
            'note'           => 'nullable|string|max:2000',
            'closing'        => 'nullable|string|max:255',
            'tax'            => 'nullable|numeric|min:0|max:100',
            'meta'           => 'nullable|array|max:20',
            'meta.*.0'       => 'nullable|string|max:100',
            'meta.*.1'       => 'nullable|string|max:255',
            'items'          => 'nullable|array|max:100',
            'items.*.desc'   => 'nullable|string|max:500',
            'items.*.price'  => 'nullable|integer|min:0',
            'items.*.qty'    => 'nullable|numeric|min:0',
            'items.*.unit'   => 'nullable|string|max:30',
        ]);

        $letter->detail()->updateOrCreate([], [
            'header'  => $data['header'] ?? null,
            'body'    => $data['body'] ?? null,
            'note'    => $data['note'] ?? null,
            'closing' => $data['closing'] ?? null,
            'tax'     => $data['tax'] ?? 0,
            'meta'    => collect($data['meta'] ?? [])->filter(fn ($m) => ($m[0] ?? '') !== '' || ($m[1] ?? '') !== '')->values()->all(),
            'items'   => collect($data['items'] ?? [])->filter(fn ($i) => ($i['desc'] ?? '') !== '')
                ->map(fn ($i) => ['desc' => $i['desc'], 'price' => (int) ($i['price'] ?? 0), 'qty' => (float) ($i['qty'] ?? 0), 'unit' => $i['unit'] ?? ''])
                ->values()->all(),
        ]);
        $letter->touch();
        $this->log($letter, 'detail_updated');

        return redirect()->route('letter.show', [$letter, 'tab' => 'isi'])->with('success', 'Isi surat disimpan.');
    }

    /* ------------------------------------------------------------ ALUR */

    /**
     * User di atas pembuat surat dalam struktur Team: leader tiap team ACTIVE
     * tempat pembuat menjadi anggota ACTIVE + leader semua team induknya.
     * Pembuat sendiri dikeluarkan (leader team yang membuat surat harus naik
     * ke team induk).
     *
     * @return int[]
     */
    public function approverIds(Letter $letter): array
    {
        $ids = [];
        $teamIds = TeamMember::where('user_id', $letter->user_id)->where('status', 'ACTIVE')->pluck('team_id');
        $seen = [];

        foreach (Team::whereIn('id', $teamIds)->where('status', 'ACTIVE')->get() as $team) {
            // Rantai ke atas; $seen mencegah putaran bila data induk saling menunjuk.
            while ($team && ! isset($seen[$team->id])) {
                $seen[$team->id] = true;
                if ($team->leader_id) {
                    $ids[] = (int) $team->leader_id;
                }
                $team = $team->parent_team_id ? Team::find($team->parent_team_id) : null;
            }
        }

        return array_values(array_diff(array_unique($ids), [(int) $letter->user_id]));
    }

    public function canApprove(Letter $letter): bool
    {
        $user = Auth::user();
        if ($user->hasRole('superadmin')) {
            return true;
        }
        if ((int) $letter->user_id === (int) $user->id) {
            return false;
        }

        $above = $this->approverIds($letter);

        return $above ? in_array((int) $user->id, $above, true) : $user->hasRole(self::FALLBACK_APPROVER_ROLES);
    }

    public function action(Request $request, Letter $letter, string $action)
    {
        abort_unless(in_array($action, $letter->allowedActions(), true) && in_array($action, ['submit', 'approve', 'reject', 'cancel', 'sent', 'complete', 'redraft', 'delete'], true), 403, 'Aksi tidak tersedia pada status ini.');

        if (in_array($action, ['approve', 'reject'], true)) {
            // Pembuat tidak memproses suratnya sendiri; Superadmin dikecualikan (canApprove()).
            abort_if((int) $letter->user_id === (int) Auth::id() && ! Auth::user()->hasRole('superadmin'),
                403, 'Anda tidak dapat menyetujui atau menolak surat Anda sendiri.');
            abort_unless($this->canApprove($letter), 403, 'Anda bukan atasan pembuat surat ini dalam struktur team.');
        }

        $note = null;
        if (in_array($action, ['reject', 'cancel'], true)) {
            $note = $request->validate(['note' => 'required|string|max:1000'], [
                'note.required' => 'Alasan wajib diisi.',
            ])['note'];
        }

        if ($action === 'submit' && ! trim((string) optional($letter->detail)->body)) {
            return redirect()->route('letter.show', [$letter, 'tab' => 'isi'])
                ->with('error', 'Isi surat belum diisi, lengkapi dulu sebelum submit.');
        }

        if ($action === 'delete') {
            DB::transaction(function () use ($letter) {
                foreach ($letter->attachments as $a) {
                    Storage::disk('secure')->delete($a->path);
                }
                $letter->delete(); // detail/lampiran/ttd/log ikut (cascade)
            });

            return redirect()->route('letter.index')->with('success', 'Surat dihapus.');
        }

        $to = [
            'submit' => 'submitted', 'approve' => 'approved', 'reject' => 'rejected', 'cancel' => 'cancelled',
            'sent' => 'sent', 'complete' => 'complete', 'redraft' => 'draft',
        ][$action];

        DB::transaction(function () use ($letter, $to, $action, $note) {
            // Kunci baris + cek ulang status: dua klik bersamaan tidak boleh lolos dua kali.
            $row = Letter::lockForUpdate()->findOrFail($letter->id);
            abort_unless(in_array($action, $row->allowedActions(), true), 409, 'Status surat sudah berubah.');
            $row->update(['status' => $to]);
            $this->log($row, $action === 'submit' ? 'submitted' : ($action === 'redraft' ? 'redraft' : $to), $note);
        });

        return back()->with('success', 'Status surat: ' . Letter::STATUSES[$to][0] . '.');
    }

    /* ------------------------------------------------------------ LAMPIRAN */

    public function attachmentStore(Request $request, Letter $letter)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'kind' => 'nullable|in:lampiran,balasan',
        ]);

        $file = $request->file('file');
        $path = $file->store('letters/' . $letter->id, 'secure');
        $letter->attachments()->create([
            'path' => $path, 'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(), 'size' => $file->getSize(),
            'kind' => $request->input('kind', 'lampiran'), 'uploaded_by' => Auth::id(),
        ]);
        $this->log($letter, 'attach_added', $file->getClientOriginalName());

        return redirect()->route('letter.show', [$letter, 'tab' => 'lampiran'])->with('success', 'Lampiran ditambahkan.');
    }

    public function attachmentDownload(Letter $letter, LetterAttachment $attachment)
    {
        abort_unless($attachment->letter_id === $letter->id, 404);

        return Storage::disk('secure')->response($attachment->path, $attachment->original_name);
    }

    public function attachmentDestroy(Letter $letter, LetterAttachment $attachment)
    {
        abort_unless($attachment->letter_id === $letter->id, 404);
        Storage::disk('secure')->delete($attachment->path);
        $attachment->delete();
        $this->log($letter, 'attach_removed', $attachment->original_name);

        return redirect()->route('letter.show', [$letter, 'tab' => 'lampiran'])->with('success', 'Lampiran dihapus.');
    }

    /* ------------------------------------------------------------ TANDA TANGAN */

    public function signatureStore(Request $request, Letter $letter)
    {
        $data = $request->validate([
            'signer_id' => 'required|exists:users,id',
            'sign_as'   => 'required|string|max:100',
        ]);
        $letter->signatures()->create($data);
        $this->log($letter, 'sign_added', User::find($data['signer_id'])->name . ' sebagai ' . $data['sign_as']);

        return redirect()->route('letter.show', [$letter, 'tab' => 'ttd'])->with('success', 'Penandatangan ditambahkan.');
    }

    /** Tanda tangan hanya oleh penandatangan itu sendiri (online). */
    public function signatureSign(Letter $letter, LetterSignature $signature)
    {
        abort_unless($signature->letter_id === $letter->id, 404);
        abort_unless($signature->signer_id === Auth::id() || Auth::user()->hasRole('superadmin'), 403, 'Hanya penandatangan yang dapat menandatangani.');

        $signature->update(['signed_at' => now(), 'signed_by' => 'digital']);
        $this->log($letter, 'signed', $signature->signer->name);

        return redirect()->route('letter.show', [$letter, 'tab' => 'ttd'])->with('success', 'Tanda tangan tersimpan.');
    }

    public function signatureUnsign(Letter $letter, LetterSignature $signature)
    {
        abort_unless($signature->letter_id === $letter->id, 404);
        abort_unless($signature->signer_id === Auth::id() || Auth::user()->hasRole('superadmin'), 403);

        $signature->update(['signed_at' => null, 'signed_by' => null]);
        $this->log($letter, 'unsigned', $signature->signer->name);

        return redirect()->route('letter.show', [$letter, 'tab' => 'ttd'])->with('success', 'Tanda tangan dibatalkan.');
    }

    public function signatureDestroy(Letter $letter, LetterSignature $signature)
    {
        abort_unless($signature->letter_id === $letter->id, 404);
        $this->log($letter, 'sign_removed', $signature->signer->name);
        $signature->delete();

        return redirect()->route('letter.show', [$letter, 'tab' => 'ttd'])->with('success', 'Penandatangan dihapus.');
    }

    /* ------------------------------------------------------------ KATEGORI */

    public function categoryStore(Request $request)
    {
        abort_unless(Auth::user()->hasRole(self::MANAGER_ROLES), 403);
        $data = $request->validate([
            'code' => 'required|alpha_num|max:10|unique:letter_categories,code',
            'name' => 'required|string|max:100',
        ]);
        LetterCategory::create(['code' => strtoupper($data['code']), 'name' => $data['name']]);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function categoryDestroy(LetterCategory $category)
    {
        abort_unless(Auth::user()->hasRole(self::MANAGER_ROLES), 403);
        if ($category->letters()->exists()) {
            return back()->with('error', 'Kategori masih dipakai surat, tidak bisa dihapus.');
        }
        $category->delete();

        return back()->with('success', 'Kategori dihapus.');
    }

    private function log(Letter $letter, string $action, ?string $note = null): void
    {
        LetterLog::create(['letter_id' => $letter->id, 'user_id' => Auth::id(), 'action' => $action, 'note' => $note]);
    }
}

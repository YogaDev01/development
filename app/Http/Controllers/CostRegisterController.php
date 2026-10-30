<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CostRegister;
use App\Models\CostRegisterLog;
use App\Models\Project;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\NotifyService;
use App\Services\RoleDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CostRegisterController extends Controller
{
    use \App\Http\Controllers\Concerns\CapsLargeLists;

    /*
    |--------------------------------------------------------------------------
    | INDEX — header daftar (filter tanggal input) + ringkasan per status
    |--------------------------------------------------------------------------
    | Alur = Cost Payment (Non-CA) di Cost Request, tanpa tahap Checked:
    | DRAFT -> VERIFIED ("Submitted") -> APPROVED -> QUEUED ("Process") -> CLOSED.
    */

    public function index(Request $request)
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);

        // Tanpa withCount()/select tambahan: query ini di-clone untuk agregat GROUP BY di bawah,
        // dan kolom ekstra membuat MySQL (only_full_group_by) menolaknya.
        $query = CostRegister::with(['project.customer', 'attachments', 'inputBy', 'beneficiary'])
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        /*
        | Ringkasan dihitung AGREGAT, bukan dari koleksi baris (lihat catatan lama):
        | daftar dipotong CapsLargeLists, angka ringkasan tidak boleh ikut terpotong.
        | Rentang tanggal yang sama dengan daftar, supaya stepper dan tabel konsisten.
        */
        $perStatus = (clone $query)->toBase()
            ->selectRaw('status, COUNT(*) as jumlah, COALESCE(SUM(amount),0) as total')
            ->groupBy('status')
            ->get();

        $perTipe = (clone $query)->toBase()
            ->selectRaw('type, COALESCE(SUM(amount),0) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $hitung = fn (string $st) => (int) ($perStatus->firstWhere('status', $st)->jumlah ?? 0);
        $summary = [
            'total'     => (int) $perStatus->sum('jumlah'),
            'fixed'     => (int) ($perTipe['FIXED_COST'] ?? 0),
            'overhead'  => (int) ($perTipe['OVERHEAD'] ?? 0),
            'draft'     => $hitung('DRAFT'),
            'verified'  => $hitung('VERIFIED'),
            'approved'  => $hitung('APPROVED'),
            'queued'    => $hitung('QUEUED'),
            'closed'    => $hitung('CLOSED'),
            'cancelled' => $hitung('CANCELLED'),
        ];

        // Baris dibatasi (Concerns\CapsLargeLists): daftar dirender penuh ke DOM lalu
        // diurus DataTables di peramban, jadi ribuan baris membekukan halaman.
        [$registers, $truncated, $total] = $this->cappedRows($query, 'id');
        $maxRows = $this->maxRows;

        return view('cost-register.index', compact(
            'registers', 'summary', 'truncated', 'total', 'maxRows', 'dateFrom', 'dateTo'
        ));
    }

    /** Rentang tanggal input (default: awal bulan ini s.d. hari ini, sama dengan Treasury). */
    private function dateRange(Request $request): array
    {
        $dateFrom = $request->filled('date_from') ? $request->date_from : now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $request->filled('date_to')   ? $request->date_to   : now()->format('Y-m-d');

        return [substr((string) $dateFrom, 0, 10), substr((string) $dateTo, 0, 10)];
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE / STORE
    |--------------------------------------------------------------------------
    | Dua mode:
    | - single  : satu register untuk satu PO (Fixed Cost atau Overhead), dengan
    |             rincian per item (akun, deskripsi, qty, harga).
    | - batch   : satu pool Overhead dibagi ke beberapa PO sekaligus (rata atau
    |             nominal manual per PO), ditandai batch_code yang sama; tiap
    |             register mendapat satu baris rincian otomatis.
    | Nominal header = jumlah total rincian (dihitung ulang di server).
    */

    public function create()
    {
        $projects = Project::with('customer:id,name')
            ->get(['id', 'code', 'name', 'customer_id'])
            ->sortBy(fn ($p) => strtolower(($p->customer->name ?? '') . '-' . $p->code))
            ->values();

        // MN-09 — tujuan transfer & rekening sumber, supaya Cost Register bisa
        // benar-benar dibayarkan lewat Treasury.
        $beneficiaries = \App\Models\Beneficiary::where('status', 'ACTIVE')
            ->orderBy('nickname')->get(['id', 'nickname', 'complete_name']);

        $sourceFunds = \App\Models\SourceFund::where('status', 'ACTIVE')
            ->orderBy('name')->get(['id', 'name']);

        // Akun rincian: kategori Fixed Cost / Overhead (sama dengan tipe Cost Register).
        $accounts = Account::where('status', 'ACTIVE')
            ->whereIn('category', ['Fixed Cost', 'Overhead'])
            ->orderBy('category')->orderBy('name')->get(['id', 'name', 'category']);

        return view('cost-register.create', compact('projects', 'beneficiaries', 'sourceFunds', 'accounts'));
    }

    public function store(Request $request)
    {
        $mode = $request->input('mode', 'single');

        if ($mode === 'batch') {
            return $this->storeBatch($request);
        }

        $request->validate([
            'project_id'   => 'required|exists:projects,id',
            'type'         => 'required|in:' . implode(',', CostRegister::TYPES),
            'period'       => 'nullable|date',
            'notes'        => 'required|string|max:2000',
            'beneficiary_id' => 'nullable|exists:beneficiaries,id',
            'source_fund_id' => 'nullable|exists:source_funds,id',
            'details'                => 'required|array|min:1|max:100',
            'details.*.account_id'   => 'nullable|exists:accounts,id',
            'details.*.description'  => 'required|string|max:500',
            'details.*.qty'          => 'required|integer|min:1|max:1000000',
            'details.*.unit_price'   => 'required',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $rows = $this->normalizeDetails((array) $request->input('details', []));
        if ($rows === null) {
            return back()->withInput()->with('error', 'Harga satuan setiap rincian harus lebih dari 0.');
        }
        $amount = (int) array_sum(array_column($rows, 'total'));

        $register = DB::transaction(function () use ($request, $amount, $rows) {
            $cr = CostRegister::create([
                'project_id' => $request->project_id,
                'type'       => $request->type,
                'amount'     => $amount,
                'period'     => $request->period,
                'notes'          => $request->notes,
                'beneficiary_id' => $request->beneficiary_id,
                'source_fund_id' => $request->source_fund_id,
                // Selalu mulai dari DRAFT; pengajuan adalah aksi terpisah yang eksplisit.
                'status'         => 'DRAFT',
                'input_by'       => Auth::id(),
            ]);

            $cr->details()->createMany($rows);
            $cr->addLog(null, 'DRAFT', 'created', null);
            $this->storeAttachments($cr, $request);

            return $cr;
        });

        UserActivityLog::record('cost_register', 'create', 'Membuat Cost Register untuk PO #' . ($register->project->code ?? '-'), $register);

        return redirect(route('cost-register.index'))
            ->with('success', 'Cost Register berhasil disimpan untuk ' . ($register->project->code ?? '-') . '.');
    }

    /**
     * Rincian dari form -> baris siap simpan (total = qty x harga). null bila ada
     * harga <= 0. Total dihitung di server; angka dari browser tidak dipercaya.
     */
    private function normalizeDetails(array $details): ?array
    {
        $rows = [];
        foreach ($details as $d) {
            $qty   = max(1, (int) ($d['qty'] ?? 1));
            $price = $this->normalizeMoney($d['unit_price'] ?? 0);
            if ($price <= 0) {
                return null;
            }
            $rows[] = [
                'account_id'  => ! empty($d['account_id']) ? (int) $d['account_id'] : null,
                'description' => trim((string) ($d['description'] ?? '')),
                'qty'         => $qty,
                'unit_price'  => $price,
                'total'       => $qty * $price,
            ];
        }

        return $rows;
    }

    private function storeBatch(Request $request)
    {
        $request->validate([
            'project_ids'      => 'required|array|min:1',
            'project_ids.*'    => 'exists:projects,id',
            'total_amount'     => 'required',
            'distribution'     => 'required|in:equal,manual',
            'manual_amount'    => 'nullable|array',
            'period'           => 'nullable|date',
            'notes'            => 'required|string|max:2000',
            // MN-09 — satu alokasi batch berasal dari SATU pengeluaran ke satu
            // pihak, lalu dibagi ke beberapa PO. Jadi Beneficiary & rekeningnya
            // ada di level batch, bukan per baris.
            'beneficiary_id'   => 'nullable|exists:beneficiaries,id',
            'source_fund_id'   => 'nullable|exists:source_funds,id',
            'attachments.*'    => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $projectIds  = $request->project_ids;
        $totalAmount = $this->normalizeMoney($request->total_amount);
        $batchCode   = 'OH-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));

        $amounts = [];
        if ($request->distribution === 'equal') {
            $count = count($projectIds);
            $base  = intdiv($totalAmount, $count);
            $remainder = $totalAmount - ($base * $count);
            foreach ($projectIds as $i => $pid) {
                // Sisa pembagian (kalau tidak habis dibagi rata) ditambahkan ke baris pertama.
                $amounts[$pid] = $base + ($i === 0 ? $remainder : 0);
            }
        } else {
            $manual = $request->input('manual_amount', []);
            $sum = 0;
            foreach ($projectIds as $pid) {
                $amounts[$pid] = $this->normalizeMoney($manual[$pid] ?? 0);
                $sum += $amounts[$pid];
            }
            if ($sum !== $totalAmount) {
                return back()->withInput()->with('error',
                    'Total nominal manual per PO (' . number_format($sum, 0, ',', '.') . ') harus sama dengan Total Overhead (' . number_format($totalAmount, 0, ',', '.') . ').'
                );
            }
        }

        DB::transaction(function () use ($request, $projectIds, $amounts, $batchCode) {
            foreach ($projectIds as $pid) {
                $cr = CostRegister::create([
                    'project_id'     => $pid,
                    'type'           => 'OVERHEAD',
                    'amount'         => $amounts[$pid],
                    'period'         => $request->period,
                    'notes'          => $request->notes,
                    'batch_code'     => $batchCode,
                    // Beneficiary & rekening diturunkan dari level batch ke setiap
                    // barisnya, supaya tiap baris bisa langsung diajukan tanpa
                    // diedit satu per satu dulu.
                    'beneficiary_id' => $request->beneficiary_id ?: null,
                    'source_fund_id' => $request->source_fund_id ?: null,
                    'status'         => 'DRAFT',
                    'input_by'       => Auth::id(),
                ]);

                // Satu baris rincian otomatis, supaya register hasil alokasi punya
                // header + rincian seperti register lainnya.
                $cr->details()->create([
                    'description' => 'Alokasi Overhead ' . $batchCode,
                    'qty'         => 1,
                    'unit_price'  => $amounts[$pid],
                    'total'       => $amounts[$pid],
                ]);
                $cr->addLog(null, 'DRAFT', 'created', 'Alokasi batch ' . $batchCode);

                $this->storeAttachments($cr, $request);
            }
        });

        $pesan = 'Alokasi Overhead berhasil disimpan ke ' . count($projectIds) . ' PO (batch ' . $batchCode . ').';

        if (!$request->beneficiary_id) {
            $pesan .= ' Beneficiary belum diisi — lengkapi dulu sebelum baris ini bisa diajukan.';
        }

        return redirect(route('cost-register.index'))->with('success', $pesan);
    }

    private function storeAttachments(CostRegister $cr, Request $request): void
    {
        foreach ($request->file('attachments', []) as $file) {
            if (!$file->isValid()) continue;

            $path = $file->store('cost_register_attachments', 'secure');
            $cr->attachments()->create([
                'title'       => $file->getClientOriginalName(),
                'file_path'   => $path,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function normalizeMoney($value): int
    {
        if (blank($value)) return 0;
        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — hanya DRAFT (Verified/Approved dibatalkan, bukan dihapus)
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $cr = CostRegister::findOrFail($id);

        // Yang sudah diajukan tidak boleh dihapus: approval, Treasury, dan laporan
        // biaya PO sudah (atau akan) merujuk baris ini.
        if (!$cr->isEditable()) {
            return back()->with('error',
                'Cost Register berstatus ' . $cr->status_label . ' tidak bisa dihapus.');
        }

        $crId = $cr->id;
        $cr->delete(); // rincian, log, dan lampiran ikut terhapus lewat FK cascade

        UserActivityLog::record('cost_register', 'delete', 'Menghapus Cost Register #' . $crId);

        return back()->with('success', 'Cost Register berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | ALUR: DRAFT -> VERIFIED -> APPROVED  (QUEUED & CLOSED milik Treasury)
    |--------------------------------------------------------------------------
    */

    /** DRAFT -> VERIFIED (tahap Checked dilewati; tampil sebagai "Submitted"). */
    public function submit($id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::SUBMIT_ROLES), 403);

        $cr = CostRegister::with('project')->findOrFail($id);

        if (!$cr->isDraft()) {
            return back()->with('error', 'Hanya Cost Register berstatus DRAFT yang bisa diajukan.');
        }

        if (!$cr->beneficiary_id) {
            return back()->with('error', 'Isi dulu Beneficiary — Treasury perlu tahu tujuan transfernya.');
        }

        $cr->update([
            'status'       => 'VERIFIED',
            'submitted_at' => now(),
            'verified_by'  => Auth::id(),
            'verified_at'  => now(),
        ]);
        $cr->addLog('DRAFT', 'VERIFIED', 'verified', null);

        NotifyService::send(
            app(RoleDirectory::class)->approvers(),
            'Cost Register menunggu approval',
            Auth::user()->name . ' mengajukan Cost Register ' . ($cr->project->code ?? '-') . ' (' . $cr->type_label . ') senilai ' . $cr->amount_formatted . '.',
            route('cost-register.index'),
            'workflow'
        );

        UserActivityLog::record('cost_register', 'submit', 'Mengajukan Cost Register #' . $cr->id, $cr);

        return back()->with('success', 'Cost Register diajukan (menunggu approval).');
    }

    /** VERIFIED -> APPROVED (Approver/CEO). Setelah ini Treasury bisa memasukkannya ke batch. */
    public function approve(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::APPROVE_ROLES), 403);

        $request->validate(['notes' => 'nullable|string|max:500']);

        $cr = CostRegister::with(['project', 'inputBy'])->findOrFail($id);

        if (!$cr->isVerified()) {
            return back()->with('error', 'Hanya Cost Register berstatus Submitted yang dapat di-approve.');
        }

        $cr->update([
            'status'      => 'APPROVED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        $cr->addLog('VERIFIED', 'APPROVED', 'approved', $request->notes);

        if ($cr->inputBy) {
            NotifyService::send(
                [$cr->inputBy],
                'Cost Register disetujui',
                'Cost Register ' . ($cr->project->code ?? '-') . ' (' . $cr->type_label . ') disetujui dan siap dibatch Treasury.',
                route('cost-register.index'),
                'workflow'
            );
        }

        UserActivityLog::record('cost_register', 'approve', 'Menyetujui Cost Register #' . $cr->id, $cr, $request->notes);

        return back()->with('success', 'Cost Register disetujui.');
    }

    /** VERIFIED -> DRAFT (Approver mengembalikan untuk direvisi; catatan wajib). */
    public function revise(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::APPROVE_ROLES), 403);

        $request->validate(['notes' => 'required|string|max:500']);

        $cr = CostRegister::with(['project', 'inputBy'])->findOrFail($id);

        if (!$cr->isVerified()) {
            return back()->with('error', 'Hanya Cost Register berstatus Submitted yang dapat dikembalikan.');
        }

        $cr->update(['status' => 'DRAFT', 'verified_by' => null, 'verified_at' => null]);
        $cr->addLog('VERIFIED', 'DRAFT', 'revised', $request->notes);

        if ($cr->inputBy) {
            NotifyService::send(
                [$cr->inputBy],
                'Cost Register perlu direvisi',
                'Cost Register ' . ($cr->project->code ?? '-') . ' dikembalikan untuk direvisi: ' . $request->notes,
                route('cost-register.index'),
                'workflow'
            );
        }

        UserActivityLog::record('cost_register', 'revise', 'Mengembalikan Cost Register #' . $cr->id . ' untuk revisi', $cr, $request->notes);

        return back()->with('success', 'Cost Register dikembalikan ke Draft.');
    }

    public function cancel(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::SUBMIT_ROLES), 403);

        // Alasan opsional (modal Batalkan mengirim 'reason', maxlength 500).
        $request->validate(['reason' => 'nullable|string|max:500']);

        $cr = CostRegister::findOrFail($id);

        // Selama belum QUEUED/CLOSED: yang sudah di antrean Treasury dibatalkan lewat batch-nya.
        if (!$cr->isCancellable()) {
            return back()->with('error',
                'Cost Register berstatus ' . $cr->status_label . ' tidak bisa dibatalkan dari sini.');
        }

        $dari = $cr->status;
        $cr->update(['status' => 'CANCELLED']);
        $cr->addLog($dari, 'CANCELLED', 'cancelled', $request->reason ?? $request->notes);

        UserActivityLog::record('cost_register', 'cancel', 'Membatalkan Cost Register #' . $cr->id, $cr, $request->reason ?? $request->notes);

        return back()->with('success', 'Cost Register dibatalkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL JSON (drawer) + LOG CHAT
    |--------------------------------------------------------------------------
    */

    public function showJson($id)
    {
        $cr = CostRegister::with([
            'project.customer', 'inputBy', 'beneficiary.bank', 'sourceFund',
            'verifiedBy', 'approvedBy', 'attachments', 'details.account', 'logs',
        ])->findOrFail($id);

        $avatars = User::avatarUrlMap($cr->logs->pluck('user_id'));
        $user    = Auth::user();

        return response()->json([
            'id'               => $cr->id,
            'project_code'     => $cr->project->code ?? '-',
            'project_name'     => $cr->project->name ?? '-',
            'customer'         => $cr->project->customer->name ?? '-',
            'type'             => $cr->type,
            'type_label'       => $cr->type_label,
            'status'           => $cr->status,
            'status_label'     => $cr->status_label,
            'status_tone'      => \App\Support\WorkflowStatus::tone($cr->status, 'cost-register'),
            'amount'           => $cr->amount,
            'amount_formatted' => $cr->amount_formatted,
            'period'           => $cr->period?->format('M Y'),
            'notes'            => $cr->notes,
            'batch_code'       => $cr->batch_code,
            'beneficiary'      => $cr->beneficiary?->complete_name ?? $cr->beneficiary?->nickname ?? '-',
            'beneficiary_bank' => $cr->beneficiary
                ? trim(($cr->beneficiary->bank->name ?? '') . ' ' . ($cr->beneficiary->account_number ?? ''))
                : '-',
            'source_fund'      => $cr->sourceFund?->name ?? '-',
            'input_by'         => $cr->inputBy->name ?? '-',
            'created_at'       => $cr->created_at?->format('d M Y H:i'),
            'verified_by'      => $cr->verifiedBy->name ?? '-',
            'verified_at'      => $cr->verified_at?->format('d M Y H:i'),
            'approved_by'      => $cr->approvedBy->name ?? '-',
            'approved_at'      => $cr->approved_at?->format('d M Y H:i'),
            'paid_at'          => $cr->paid_at?->format('d M Y H:i'),

            // Rincian per item; register lama tidak punya rincian (daftar kosong).
            'details' => $cr->details->map(fn ($d) => [
                'account'    => $d->account->name ?? '-',
                'description' => $d->description,
                'qty'        => $d->qty,
                'unit_price' => number_format($d->unit_price, 0, ',', '.'),
                'total'      => number_format($d->total, 0, ',', '.'),
            ]),

            'attachments' => $cr->attachments->map(fn ($a) => [
                'title' => $a->title,
                'path'  => $a->file_path,
            ]),

            'logs' => $cr->logs->map(fn ($l) => [
                'kind'         => $l->action === 'note' ? 'note' : 'activity',
                'avatar'       => $avatars[$l->user_id] ?? null,
                'action_label' => $l->action_label,
                'action_color' => $l->action_color,
                'status_from'  => $l->status_from ? CostRegister::statusLabelFor($l->status_from) : null,
                'status_to'    => $l->status_to ? CostRegister::statusLabelFor($l->status_to) : null,
                'actor'        => $l->user_name ?? '-',
                'user_id'      => $l->user_id,
                'role'         => $l->role ?? '-',
                'notes'        => $l->notes,
                'date'         => $l->created_at->format('d M Y H:i'),
            ]),
            'can_post_log_note' => $this->canPostLogNote($cr),

            // Gerbang aksi untuk tombol di drawer (sama dengan route + controller).
            'can_submit'  => $cr->isDraft() && $user->hasRole(CostRegister::SUBMIT_ROLES),
            'can_approve' => $cr->isVerified() && $user->hasRole(CostRegister::APPROVE_ROLES),
            'can_cancel'  => $cr->isCancellable() && $user->hasRole(CostRegister::SUBMIT_ROLES),
        ]);
    }

    /** Catatan chat dikunci begitu CLOSED atau CANCELLED (seperti CR yang CLOSED). */
    private function canPostLogNote(CostRegister $cr): bool
    {
        return ! in_array($cr->status, ['CLOSED', 'CANCELLED'], true);
    }

    public function storeLogNote(Request $request, $id)
    {
        $cr = CostRegister::findOrFail($id);

        $data = $request->validate(['notes' => 'required|string|max:1000']);

        if (! $this->canPostLogNote($cr)) {
            return response()->json(['message' => 'Cost Register sudah ' . $cr->status_label . '; catatan baru tidak dapat ditambahkan.'], 422);
        }

        $user = Auth::user();
        $cr->addLog(null, null, 'note', trim($data['notes']));
        $log = $cr->logs()->first(); // terbaru (urut id desc)

        return response()->json([
            'ok'  => true,
            'log' => [
                'kind'         => 'note',
                'avatar'       => $user?->avatar_url,
                'action_label' => 'Catatan',
                'action_color' => 'secondary',
                'status_from'  => null,
                'status_to'    => null,
                'actor'        => $log->user_name,
                'user_id'      => $log->user_id,
                'role'         => $log->role ?? '-',
                'notes'        => $log->notes,
                'date'         => now()->format('d M Y H:i'),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT — mengikuti rentang tanggal input atau daftar id hasil filter tabel
    |--------------------------------------------------------------------------
    */

    public function export(Request $request)
    {
        [$dateFrom, $dateTo] = $this->dateRange($request);

        // Daftar id dari filter aktif di tabel (public/js/tesys-export-filtered.js).
        // Dengan id, rentang tanggal TIDAK ikut membatasi (id berasal dari tabel yang
        // rentangnya sama). Tanpa id: seluruh data pada rentang tanggal.
        $ids = collect((array) $request->input('ids', []))
            ->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v);

        $registers = CostRegister::with(['project.customer', 'inputBy', 'beneficiary', 'details.account'])
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids))
            ->when($ids->isEmpty(), fn ($q) => $q
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('id')->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cost Register');
        $kolom = ['Tanggal Input', 'No. PO', 'Customer', 'Tipe', 'Status', 'Beneficiary', 'Periode',
                  'Nominal', 'Catatan', 'Diinput Oleh', 'Batch Alokasi'];
        $this->tulisHeader($sheet, $kolom);

        $row = 2;
        foreach ($registers as $cr) {
            $sheet->fromArray([
                $cr->created_at?->format('d-m-Y'),
                null, // diisi eksplisit sebagai teks di bawah
                $cr->project->customer->name ?? '-',
                $cr->type_label,
                $cr->status_label,
                $cr->beneficiary->nickname ?? '-',
                $cr->period ? $cr->period->format('M Y') : '-',
                (int) $cr->amount,
                $cr->notes,
                $cr->inputBy->name ?? '-',
                $cr->batch_code ?? '-',
            ], null, 'A' . $row);
            // Kode PO ditulis sebagai teks (deretan digit), bukan angka.
            $sheet->setCellValueExplicit('B' . $row, (string) ($cr->project->code ?? '-'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $row++;
        }
        foreach (range('A', 'K') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        // Sheet kedua: rincian per item.
        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Rincian');
        $this->tulisHeader($detail, ['No. PO', 'Tipe', 'Status', 'Akun', 'Deskripsi', 'Qty', 'Harga Satuan', 'Total']);
        $r = 2;
        foreach ($registers as $cr) {
            foreach ($cr->details as $d) {
                $detail->setCellValueExplicit('A' . $r, (string) ($cr->project->code ?? '-'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $detail->fromArray([$cr->type_label, $cr->status_label, $d->account->name ?? '-', $d->description,
                    $d->qty, (int) $d->unit_price, (int) $d->total], null, 'B' . $r);
                $detail->getStyle('G' . $r . ':H' . $r)->getNumberFormat()->setFormatCode('#,##0');
                $r++;
            }
        }
        foreach (range('A', 'H') as $c) {
            $detail->getColumnDimension($c)->setAutoSize(true);
        }
        $spreadsheet->setActiveSheetIndex(0);

        UserActivityLog::record('cost_register', 'export', 'Export Cost Register (' . $registers->count() . ' baris)');

        $filename = 'cost_register_' . $dateFrom . '_' . $dateTo . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function tulisHeader($sheet, array $kolom): void
    {
        $sheet->fromArray($kolom, null, 'A1');
        $akhir = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($kolom));
        $range = 'A1:' . $akhir . '1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
    }
}

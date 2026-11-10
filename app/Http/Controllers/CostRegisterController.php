<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CostCenter;
use App\Models\CostRegister;
use App\Models\CostRegisterLog;
use App\Models\Project;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\NotifyService;
use App\Services\RoleDirectory;
use App\Support\ExpenseAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CostRegisterController extends Controller
{
    use \App\Http\Controllers\Concerns\CapsLargeLists;

    /*
    |--------------------------------------------------------------------------
    | EXPENSE (dulu Cost Register; tabel tetap cost_registers, nama route tetap
    | cost-register.*). Hanya Finance Manager (input/ajukan) & CEO (approve).
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
        // details.project/costCenter: kolom Tujuan (multi-PO / Cost Center) Expense baru.
        $query = CostRegister::with(['project.customer', 'attachments', 'inputBy', 'beneficiary', 'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code'])
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

        $hitung = fn (string $st) => (int) ($perStatus->firstWhere('status', $st)->jumlah ?? 0);
        $summary = [
            'total'     => (int) $perStatus->sum('jumlah'),
            'draft'     => $hitung('DRAFT'),
            'verified'  => $hitung('VERIFIED'),
            'approved'  => $hitung('APPROVED'),
            'queued'    => $hitung('QUEUED'),
            // Expense Cash Advance (Fase 2).
            'paid'             => $hitung('PAID'),
            'settle_submitted' => $hitung('SETTLE_SUBMITTED'),
            'settle_rejected'  => $hitung('SETTLE_REJECTED'),
            'rejected'         => $hitung('REJECTED'),
            'settle_approved'  => $hitung('SETTLE_APPROVED'),
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
    | CREATE / STORE — Expense (sejak 2026-11-10)
    |--------------------------------------------------------------------------
    | Satu Expense = header (beneficiary, periode pembebanan, catatan, lampiran) + rincian.
    | Tiap rincian memilih Akun (kategori Variable Cost / Fixed Cost / Overhead)
    | dan pembebanan: Variable Cost -> satu PO; Fixed Cost / Overhead -> satu PO,
    | PO terpilih, seluruh PO satu tahun (proporsional nilai PO), atau Cost Center.
    | Tanpa budgeting. Nominal header = jumlah total rincian (dihitung ulang di server).
    | Nilai bisa dibebankan ke beberapa bulan (bagi rata / manual, cost_register_periods).
    | Rekening sumber tidak diisi di sini — ditentukan Treasury saat menyusun batch.
    */

    public function create()
    {
        return view('cost-register.create', $this->formOptions() + ['cr' => null, 'prefill' => [], 'periodPrefill' => []]);
    }

    /** Edit — hanya Expense DRAFT (termasuk yang dikembalikan dari Rejected lewat Revisi). */
    public function edit($id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::SUBMIT_ROLES), 403);

        $cr = CostRegister::with(['details.projects:projects.id', 'periods', 'attachments'])->findOrFail($id);

        if (! $cr->isEditable() || $cr->isLegacy()) {
            return redirect(route('cost-register.index'))
                ->with('error', 'Hanya Expense berstatus Draft yang bisa diedit.');
        }

        $prefill = $cr->details->map(fn ($d) => $this->prefillRow($d))->values();
        $periodPrefill = $cr->periods->mapWithKeys(fn ($p) => [$p->period->format('Y-m') => $p->amount])->all();

        return view('cost-register.edit', $this->formOptions() + compact('cr', 'prefill', 'periodPrefill'));
    }

    /** Pilihan form Expense: rincian + beneficiary. */
    private function formOptions(): array
    {
        // MN-09 — tujuan transfer, supaya Expense bisa benar-benar dibayarkan lewat Treasury.
        // Rekening sumber TIDAK dipilih di sini: ditentukan Treasury saat menyusun batch.
        $beneficiaries = \App\Models\Beneficiary::with('bank:id,name')
            ->where('status', 'ACTIVE')
            ->orderBy('nickname')->get(['id', 'nickname', 'complete_name', 'bank_id', 'account_number']);

        return $this->detailOptions() + compact('beneficiaries');
    }

    /** Baris prefill form (rincian pengajuan / realisasi). */
    private function prefillRow($d): array
    {
        return [
            'account_id'  => $d->account_id,
            'target'      => $d->formTarget(),
            'project_ids' => $d->selectedProjectIds(),
            'description' => $d->description,
            'qty'         => $d->qty,
            'unit_price'  => $d->unit_price,
        ];
    }

    /**
     * Pilihan rincian — dipakai form Expense & form settlement:
     *   projects       semua PO (tujuan PO tunggal; wajib untuk Variable Cost)
     *   eligibleProjects  PO disetujui & bernilai > 0 (boleh dipilih untuk "PO terpilih")
     *   annualYears    tahun yang punya PO penerima pembagian tahunan + ringkasannya
     */
    private function detailOptions(): array
    {
        $projects = Project::with('customer:id,name')
            ->get(['id', 'code', 'name', 'customer_id', 'value', 'status', 'created_at'])
            ->sortBy(fn ($p) => strtolower(($p->customer->name ?? '') . '-' . $p->code))
            ->values();

        $eligibleProjects = $projects->filter(fn ($p) =>
            in_array($p->status, Project::EXPENSE_ALLOCATION_STATUSES, true) && (int) $p->value > 0)->values();

        $annualYears = $eligibleProjects
            ->groupBy(fn ($p) => (int) $p->created_at->format('Y'))
            ->map(fn ($g, $th) => ['year' => (int) $th, 'count' => $g->count(), 'value' => (int) $g->sum('value')])
            ->sortKeysDesc()->values();

        $costCenters = CostCenter::active()->orderBy('code')->get(['id', 'code', 'name']);

        // COA fleksibel: semua kategori akun aktif (Variable Cost / Fixed Cost / Overhead).
        $accounts = Account::where('status', 'ACTIVE')
            ->whereIn('category', Account::CATEGORIES)
            ->orderBy('category')->orderBy('name')->get(['id', 'name', 'category']);

        return compact('projects', 'eligibleProjects', 'annualYears', 'costCenters', 'accounts');
    }

    /** Aturan validasi rincian (form Expense & form settlement). */
    private function detailRules(): array
    {
        return [
            'details'                 => 'required|array|min:1|max:100',
            'details.*.account_id'    => 'required|exists:accounts,id',
            'details.*.target'        => ['required', 'string', 'regex:/^((po|cc|annual):\d+|selected)$/'],
            'details.*.project_ids'   => 'nullable|array|max:500',
            'details.*.project_ids.*' => 'integer',
            'details.*.description'   => 'required|string|max:500',
            'details.*.qty'           => 'required|integer|min:1|max:1000000',
            'details.*.unit_price'    => 'required',
        ];
    }

    private const DETAIL_MESSAGES = [
        'details.*.account_id.required' => 'Akun setiap rincian wajib dipilih.',
        'details.*.target.required'     => 'Pembebanan (PO / Cost Center) setiap rincian wajib dipilih.',
        'details.*.target.regex'        => 'Pembebanan rincian tidak valid.',
    ];

    /** Aturan header Expense (form tambah & edit). */
    private function headerRules(): array
    {
        return [
            'is_cash_advance' => 'nullable|boolean',
            'period_start'    => 'required|date_format:Y-m',
            'period_end'      => 'nullable|date_format:Y-m',
            'period_method'   => 'nullable|in:equal,manual',
            'period_amounts'  => 'nullable|array',
            'notes'           => 'required|string|max:2000',
            'beneficiary_id'  => 'nullable|exists:beneficiaries,id',
            'attachments.*'   => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ];
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::SUBMIT_ROLES), 403);

        $request->validate($this->headerRules() + $this->detailRules(), self::DETAIL_MESSAGES + [
            'period_start.required' => 'Periode awal wajib diisi.',
        ]);

        $rows = $this->normalizeDetails((array) $request->input('details', []));
        if (is_string($rows)) {
            return back()->withInput()->with('error', $rows);
        }
        $amount = (int) array_sum(array_column($rows, 'total'));

        $periode = $this->normalizePeriods($request, $amount);
        if (is_string($periode)) {
            return back()->withInput()->with('error', $periode);
        }

        $register = DB::transaction(function () use ($request, $amount, $rows, $periode) {
            $cr = CostRegister::create([
                'expense_no' => CostRegister::nextExpenseNo(),
                // Header tidak terikat satu PO/tipe: pembebanan & kategori ada di rincian.
                'project_id' => null,
                'type'       => null,
                // Cash Advance: setelah dibayar wajib settlement (realisasi) — Fase 2.
                'is_cash_advance' => $request->boolean('is_cash_advance'),
                'amount'     => $amount,
                'notes'          => $request->notes,
                'beneficiary_id' => $request->beneficiary_id,
                // Selalu mulai dari DRAFT; pengajuan adalah aksi terpisah yang eksplisit.
                'status'         => 'DRAFT',
                'input_by'       => Auth::id(),
            ] + $periode['header']);

            $this->saveLines($cr->details(), $rows);
            $cr->periods()->createMany($periode['rows']);
            $cr->addLog(null, 'DRAFT', 'created', null);
            $this->storeAttachments($cr, $request);

            return $cr;
        });

        UserActivityLog::record('cost_register', 'create', 'Membuat Expense ' . $register->expense_no, $register);

        return redirect(route('cost-register.index'))
            ->with('success', 'Expense ' . $register->expense_no . ' berhasil disimpan sebagai Draft.');
    }

    /** Simpan perubahan Expense DRAFT: rincian, pembebanan periode, dan lampiran diganti/ditambah. */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::SUBMIT_ROLES), 403);

        $request->validate($this->headerRules() + $this->detailRules() + [
            'remove_attachments'   => 'nullable|array',
            'remove_attachments.*' => 'integer',
        ], self::DETAIL_MESSAGES + [
            'period_start.required' => 'Periode awal wajib diisi.',
        ]);

        $rows = $this->normalizeDetails((array) $request->input('details', []));
        if (is_string($rows)) {
            return back()->withInput()->with('error', $rows);
        }
        $amount = (int) array_sum(array_column($rows, 'total'));

        $periode = $this->normalizePeriods($request, $amount);
        if (is_string($periode)) {
            return back()->withInput()->with('error', $periode);
        }

        $hapusFile = [];
        try {
            $cr = DB::transaction(function () use ($request, $id, $amount, $rows, $periode, &$hapusFile) {
                $cr = CostRegister::whereKey($id)->lockForUpdate()->firstOrFail();
                if (! $cr->isEditable() || $cr->isLegacy()) {
                    throw new \DomainException('Status Expense sudah berubah — hanya Draft yang bisa diedit.');
                }

                $cr->update([
                    'is_cash_advance' => $request->boolean('is_cash_advance'),
                    'amount'          => $amount,
                    'notes'           => $request->notes,
                    'beneficiary_id'  => $request->beneficiary_id,
                ] + $periode['header']);

                // Rincian lama diganti seluruhnya (pivot PO terpilih ikut terhapus lewat FK cascade).
                $cr->details()->delete();
                $this->saveLines($cr->details(), $rows);
                $cr->periods()->delete();
                $cr->periods()->createMany($periode['rows']);

                $ids = collect((array) $request->input('remove_attachments', []))->map(fn ($v) => (int) $v);
                if ($ids->isNotEmpty()) {
                    $lampiran = $cr->attachments()->whereIn('id', $ids)->get();
                    $hapusFile = $lampiran->pluck('file_path')->all();
                    $cr->attachments()->whereIn('id', $lampiran->pluck('id'))->delete();
                }
                $this->storeAttachments($cr, $request);

                $cr->addLog('DRAFT', 'DRAFT', 'updated', null);

                return $cr;
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // File fisik dihapus SETELAH transaksi berhasil (rollback tidak bisa mengembalikan file).
        foreach ($hapusFile as $path) {
            \Illuminate\Support\Facades\Storage::disk('secure')->delete($path);
        }

        UserActivityLog::record('cost_register', 'update', 'Mengubah Expense ' . $cr->display_code, $cr);

        return redirect(route('cost-register.index'))
            ->with('success', 'Expense ' . $cr->display_code . ' berhasil diperbarui.');
    }

    /**
     * Rincian dari form -> baris siap simpan (total = qty x harga). Mengembalikan pesan
     * galat (string) bila data tidak sah. Total dihitung di server; angka dari browser
     * tidak dipercaya. Kategori biaya = snapshot kategori akun.
     *
     * Pembebanan (target):
     *   po:ID       satu PO — WAJIB untuk akun Variable Cost (selalu terikat PO).
     *   annual:YYYY seluruh PO disetujui yang dibuat tahun itu (Fixed Cost / Overhead).
     *   selected    minimal 2 PO terpilih yang disetujui & bernilai > 0 (Fixed Cost / Overhead).
     *   cc:ID       Cost Center aktif (Fixed Cost / Overhead).
     */
    private function normalizeDetails(array $details): array|string
    {
        $accountIds = collect($details)->pluck('account_id')->filter()->map(fn ($v) => (int) $v)->unique();
        $accounts = Account::whereIn('id', $accountIds)->where('status', 'ACTIVE')
            ->whereIn('category', Account::CATEGORIES)
            ->pluck('category', 'id');

        $targets = collect($details)->pluck('target')->map(fn ($t) => explode(':', (string) $t));
        $poIds = Project::whereIn('id', $targets->where(0, 'po')->pluck(1)->map(fn ($v) => (int) $v))->pluck('id')->flip();
        $ccIds = CostCenter::active()->whereIn('id', $targets->where(0, 'cc')->pluck(1)->map(fn ($v) => (int) $v))->pluck('id')->flip();
        $dipilih = Project::whereIn('id', collect($details)->pluck('project_ids')->flatten()->map(fn ($v) => (int) $v)->unique())
            ->get(['id', 'code', 'value', 'status'])->keyBy('id');

        $rows = [];
        foreach (array_values($details) as $i => $d) {
            $no    = $i + 1;
            $qty   = max(1, (int) ($d['qty'] ?? 1));
            $price = $this->normalizeMoney($d['unit_price'] ?? 0);
            if ($price <= 0) {
                return "Harga satuan rincian #{$no} harus lebih dari 0.";
            }

            $accountId = (int) ($d['account_id'] ?? 0);
            if (! isset($accounts[$accountId])) {
                return "Akun rincian #{$no} tidak aktif atau belum punya kategori biaya.";
            }
            $kategori = $accounts[$accountId];

            $target = (string) ($d['target'] ?? '');
            [$jenis, $nilai] = array_pad(explode(':', $target), 2, null);
            $nilai = (int) $nilai;

            if ($kategori === 'Variable Cost' && $jenis !== 'po') {
                return "Rincian #{$no}: akun Variable Cost wajib dibebankan ke satu PO.";
            }

            $baris = [
                'account_id'      => $accountId,
                'cost_category'   => $kategori,
                'project_id'      => null,
                'cost_center_id'  => null,
                'allocation'      => null,
                'allocation_year' => null,
                'project_ids'     => [],
                'description'     => trim((string) ($d['description'] ?? '')),
                'qty'             => $qty,
                'unit_price'      => $price,
                'total'           => $qty * $price,
            ];

            switch ($jenis) {
                case 'po':
                    if (! isset($poIds[$nilai])) {
                        return "Rincian #{$no}: PO tidak ditemukan.";
                    }
                    $baris['allocation'] = 'po';
                    $baris['project_id'] = $nilai;
                    break;

                case 'cc':
                    if (! isset($ccIds[$nilai])) {
                        return "Rincian #{$no}: Cost Center tidak ditemukan atau tidak aktif.";
                    }
                    $baris['allocation'] = 'cc';
                    $baris['cost_center_id'] = $nilai;
                    break;

                case 'annual':
                    if ($nilai < 2000 || $nilai > 2100) {
                        return "Rincian #{$no}: tahun pembebanan tidak valid.";
                    }
                    if (! ExpenseAllocation::annualPool($nilai)) {
                        return "Rincian #{$no}: tidak ada PO disetujui (bernilai) yang dibuat tahun {$nilai}.";
                    }
                    $baris['allocation'] = 'annual';
                    $baris['allocation_year'] = $nilai;
                    break;

                case 'selected':
                    $ids = collect($d['project_ids'] ?? [])->map(fn ($v) => (int) $v)->filter()->unique()->values();
                    if ($ids->count() < 2) {
                        return "Rincian #{$no}: pilih minimal 2 PO (untuk satu PO, pilih PO-nya langsung).";
                    }
                    foreach ($ids as $pid) {
                        $p = $dipilih[$pid] ?? null;
                        if (! $p || ! in_array($p->status, Project::EXPENSE_ALLOCATION_STATUSES, true) || (int) $p->value <= 0) {
                            return "Rincian #{$no}: PO " . ($p->code ?? '#' . $pid) . ' belum disetujui atau tidak punya nilai PO.';
                        }
                    }
                    $baris['allocation'] = 'selected';
                    $baris['project_ids'] = $ids->all();
                    break;

                default:
                    return "Rincian #{$no}: pembebanan tidak valid.";
            }

            $rows[] = $baris;
        }

        return $rows;
    }

    /** Simpan baris rincian (pengajuan / realisasi) + pivot PO terpilih. */
    private function saveLines($relasi, array $rows): void
    {
        foreach ($rows as $baris) {
            $ids = $baris['project_ids'];
            unset($baris['project_ids']);
            $line = $relasi->create($baris);
            if ($ids) {
                $line->projects()->sync($ids);
            }
        }
    }

    /**
     * Pembebanan multi-periode dari form. Mengembalikan pesan galat (string), atau
     * ['header' => kolom header, 'rows' => [['period' => 'Y-m-01', 'amount' => int], ...]].
     *   equal  : dibagi rata; sisa rupiah ditambahkan ke bulan-bulan awal.
     *   manual : nominal per bulan dari user; tiap bulan > 0 dan jumlahnya = nilai Expense.
     * Satu bulan saja: seluruh nilai ke bulan itu (metode dicatat 'equal').
     */
    private function normalizePeriods(Request $request, int $total): array|string
    {
        $awal  = \Carbon\Carbon::createFromFormat('!Y-m', (string) $request->period_start)->startOfMonth();
        $akhir = $request->filled('period_end')
            ? \Carbon\Carbon::createFromFormat('!Y-m', (string) $request->period_end)->startOfMonth()
            : $awal->copy();

        if ($akhir->lt($awal)) {
            return 'Periode akhir tidak boleh sebelum periode awal.';
        }

        $bulan = [];
        for ($b = $awal->copy(); $b->lte($akhir); $b->addMonth()) {
            $bulan[] = $b->copy();
            if (count($bulan) > CostRegister::MAX_PERIODS) {
                return 'Pembebanan maksimal ' . CostRegister::MAX_PERIODS . ' bulan.';
            }
        }
        $n = count($bulan);
        $metode = $n === 1 ? 'equal' : (string) ($request->period_method ?: 'equal');

        if ($metode === 'equal') {
            $nominal = CostRegister::splitEqual($total, $n);
            if ($nominal === null) {
                return "Nilai Expense terlalu kecil untuk dibagi ke {$n} periode (setiap periode minimal Rp 1).";
            }
        } else {
            $input = (array) $request->input('period_amounts', []);
            $nominal = [];
            foreach ($bulan as $b) {
                $v = $this->normalizeMoney($input[$b->format('Y-m')] ?? 0);
                if ($v <= 0) {
                    return 'Nominal periode ' . $b->format('M Y') . ' wajib diisi dan tidak boleh 0.';
                }
                $nominal[] = $v;
            }
            $jumlah = array_sum($nominal);
            if ($jumlah !== $total) {
                return 'Jumlah pembagian manual (' . number_format($jumlah, 0, ',', '.') . ') harus sama dengan nilai Expense ('
                    . number_format($total, 0, ',', '.') . ').';
            }
        }

        return [
            'header' => [
                'period'        => $awal->toDateString(),
                'period_start'  => $awal->toDateString(),
                'period_end'    => $akhir->toDateString(),
                'period_method' => $metode,
            ],
            'rows' => array_map(fn ($b, $v) => ['period' => $b->toDateString(), 'amount' => $v], $bulan, $nominal),
        ];
    }

    private function storeAttachments(CostRegister $cr, Request $request, bool $settlement = false): void
    {
        foreach ($request->file('attachments', []) as $file) {
            if (!$file->isValid()) continue;

            $path = $file->store($settlement ? 'cost_register_settlement_attachments' : 'cost_register_attachments', 'secure');
            ($settlement ? $cr->settlementAttachments() : $cr->attachments())->create([
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
        abort_unless(Auth::user()->hasRole(CostRegister::SUBMIT_ROLES), 403);

        $cr = CostRegister::findOrFail($id);

        // Yang sudah diajukan tidak boleh dihapus: approval, Treasury, dan laporan
        // biaya PO sudah (atau akan) merujuk baris ini.
        if (!$cr->isEditable()) {
            return back()->with('error',
                'Expense berstatus ' . $cr->status_label . ' tidak bisa dihapus.');
        }

        $crId = $cr->id;
        DB::transaction(function () use ($cr) {
            // Pembebanan periode ber-FK RESTRICT -> dihapus eksplisit dulu.
            $cr->periods()->delete();
            $cr->delete(); // rincian (+ pivot PO terpilih), log, dan lampiran ikut terhapus lewat FK cascade
        });

        UserActivityLog::record('cost_register', 'delete', 'Menghapus Expense #' . $crId);

        return back()->with('success', 'Expense berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | ALUR: DRAFT -> VERIFIED -> APPROVED  (QUEUED & CLOSED milik Treasury)
    |       VERIFIED -> REJECTED (CEO) -> DRAFT (Revisi, FM) | CANCELLED (FM)
    |--------------------------------------------------------------------------
    */

    /** DRAFT -> VERIFIED (tahap Checked dilewati; tampil sebagai "Submitted"). */
    public function submit($id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::SUBMIT_ROLES), 403);

        $cr = CostRegister::with(['project', 'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code'])->findOrFail($id);

        if (!$cr->isDraft()) {
            return back()->with('error', 'Hanya Expense berstatus DRAFT yang bisa diajukan.');
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
            'Expense menunggu approval',
            Auth::user()->name . ' mengajukan Expense ' . $cr->display_code . ' (' . $cr->target_label . ') senilai ' . $cr->amount_formatted . '.',
            route('cost-register.index'),
            'workflow'
        );

        UserActivityLog::record('cost_register', 'submit', 'Mengajukan Expense ' . $cr->display_code, $cr);

        return back()->with('success', 'Expense diajukan (menunggu approval).');
    }

    /** VERIFIED -> APPROVED (Approver/CEO). Setelah ini Treasury bisa memasukkannya ke batch. */
    public function approve(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::APPROVE_ROLES), 403);

        $request->validate(['notes' => 'nullable|string|max:500']);

        $cr = CostRegister::with(['project', 'inputBy', 'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code'])->findOrFail($id);

        if (!$cr->isVerified()) {
            return back()->with('error', 'Hanya Expense berstatus Submitted yang dapat di-approve.');
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
                'Expense disetujui',
                'Expense ' . $cr->display_code . ' (' . $cr->target_label . ') disetujui dan siap dibatch Treasury.',
                route('cost-register.index'),
                'workflow'
            );
        }

        UserActivityLog::record('cost_register', 'approve', 'Menyetujui Expense ' . $cr->display_code, $cr, $request->notes);

        return back()->with('success', 'Expense disetujui.');
    }

    /**
     * VERIFIED -> REJECTED (CEO; catatan wajib). Finance Manager lalu memilih Revisi
     * (kembali ke Draft untuk diedit) atau Batalkan — pola Cost Request.
     */
    public function reject(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::APPROVE_ROLES), 403);

        $request->validate(['notes' => 'required|string|max:500']);

        try {
            $cr = DB::transaction(function () use ($request, $id) {
                $cr = CostRegister::whereKey($id)->lockForUpdate()->firstOrFail();
                if (! $cr->isVerified()) {
                    throw new \DomainException('Hanya Expense berstatus Submitted yang dapat ditolak.');
                }
                $cr->update(['status' => 'REJECTED']);
                $cr->addLog('VERIFIED', 'REJECTED', 'rejected', $request->notes);

                return $cr;
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $cr->load('inputBy');
        if ($cr->inputBy) {
            NotifyService::send(
                [$cr->inputBy],
                'Expense ditolak',
                'Expense ' . $cr->display_code . ' ditolak CEO: ' . $request->notes . '. Revisi ke Draft atau batalkan.',
                route('cost-register.index'),
                'workflow'
            );
        }

        UserActivityLog::record('cost_register', 'reject', 'Menolak Expense ' . $cr->display_code, $cr, $request->notes);

        return back()->with('success', 'Expense ditolak.');
    }

    /** REJECTED -> DRAFT (Finance Manager merevisi: bisa diedit lalu diajukan ulang). */
    public function reopen(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::SUBMIT_ROLES), 403);

        try {
            $cr = DB::transaction(function () use ($id) {
                $cr = CostRegister::whereKey($id)->lockForUpdate()->firstOrFail();
                if (! $cr->isRejected()) {
                    throw new \DomainException('Hanya Expense berstatus Rejected yang dapat direvisi.');
                }
                $cr->update(['status' => 'DRAFT', 'verified_by' => null, 'verified_at' => null]);
                $cr->addLog('REJECTED', 'DRAFT', 'revised', null);

                return $cr;
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        UserActivityLog::record('cost_register', 'revise', 'Merevisi Expense ' . $cr->display_code . ' (kembali ke Draft)', $cr);

        return redirect(route('cost-register.edit', $cr->id))
            ->with('success', 'Expense ' . $cr->display_code . ' kembali ke Draft — silakan perbaiki lalu ajukan ulang.');
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
                'Expense berstatus ' . $cr->status_label . ' tidak bisa dibatalkan dari sini.');
        }

        $dari = $cr->status;
        $cr->update(['status' => 'CANCELLED']);
        $cr->addLog($dari, 'CANCELLED', 'cancelled', $request->reason ?? $request->notes);

        UserActivityLog::record('cost_register', 'cancel', 'Membatalkan Expense ' . $cr->display_code, $cr, $request->reason ?? $request->notes);

        return back()->with('success', 'Expense dibatalkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | SETTLEMENT — Expense Cash Advance (Fase 2)
    |--------------------------------------------------------------------------
    | PAID / SETTLE_REJECTED -> SETTLE_SUBMITTED (Finance Manager input realisasi)
    | SETTLE_SUBMITTED -> CEO approve:
    |     realisasi <= nominal dibayar -> CLOSED (kelebihan dicatat refund_amount)
    |     realisasi >  nominal dibayar -> SETTLE_APPROVED + Second Payment PENDING
    |                                     (dibayar lewat batch Treasury -> CLOSED)
    | SETTLE_SUBMITTED -> CEO tolak (catatan wajib) -> SETTLE_REJECTED (FM perbaiki & ajukan ulang).
    | Saat disetujui, pembebanan periode diskala ulang ke nilai realisasi.
    | Transisi memakai kunci baris + cek status di dalam transaksi.
    */

    public function settleForm($id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::SETTLE_ROLES), 403);

        $cr = CostRegister::with([
            'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code',
            'settlementDetails.projects:projects.id,projects.code', 'settlementAttachments', 'beneficiary',
        ])->findOrFail($id);

        if (! $cr->canInputSettlement()) {
            return redirect(route('cost-register.index'))
                ->with('error', 'Settlement hanya untuk Expense Cash Advance berstatus Paid / Settle Rejected.');
        }

        // Prefill: realisasi yang pernah diajukan (bila ditolak CEO), selain itu rincian pengajuan.
        $sumber = $cr->settlementDetails->isNotEmpty() ? $cr->settlementDetails : $cr->details;
        $prefill = $sumber->map(fn ($d) => $this->prefillRow($d))->values();

        $penolakan = $cr->logs()->where('action', 'settle_rejected')->first();

        return view('cost-register.settle', $this->detailOptions() + compact('cr', 'prefill', 'penolakan'));
    }

    public function settleStore(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::SETTLE_ROLES), 403);

        $request->validate([
            'settle_notes'  => 'nullable|string|max:1000',
            'attachments.*' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ] + $this->detailRules(), self::DETAIL_MESSAGES);

        $rows = $this->normalizeDetails((array) $request->input('details', []));
        if (is_string($rows)) {
            return back()->withInput()->with('error', $rows);
        }
        $realisasi = (int) array_sum(array_column($rows, 'total'));

        try {
            $cr = DB::transaction(function () use ($request, $id, $rows, $realisasi) {
                $cr = CostRegister::whereKey($id)->lockForUpdate()->firstOrFail();
                if (! $cr->canInputSettlement()) {
                    throw new \DomainException('Status Expense sudah berubah — settlement hanya untuk Cash Advance berstatus Paid / Settle Rejected.');
                }
                $dari = $cr->status;

                $adaBukti = $cr->settlementAttachments()->exists()
                    || collect($request->file('attachments', []))->filter()->isNotEmpty();
                if (! $adaBukti) {
                    throw new \DomainException('Lampirkan minimal satu bukti realisasi.');
                }

                // Realisasi sebelumnya (yang ditolak CEO) diganti seluruhnya (pivot ikut terhapus).
                $cr->settlementDetails()->delete();
                $this->saveLines($cr->settlementDetails(), $rows);
                $this->storeAttachments($cr, $request, true);

                $cr->update([
                    'status'              => 'SETTLE_SUBMITTED',
                    'settled_amount'      => $realisasi,
                    'refund_amount'       => max(0, $cr->amount - $realisasi),
                    'shortfall_amount'    => max(0, $realisasi - $cr->amount),
                    'settle_submitted_by' => Auth::id(),
                    'settle_submitted_at' => now(),
                ]);
                $cr->addLog($dari, 'SETTLE_SUBMITTED', 'settle_submitted',
                    'Realisasi ' . number_format($realisasi, 0, ',', '.') . ($request->settle_notes ? ' — ' . $request->settle_notes : ''));

                return $cr;
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        NotifyService::send(
            app(RoleDirectory::class)->approvers(),
            'Settlement Expense menunggu approval',
            Auth::user()->name . ' mengajukan settlement Expense ' . $cr->display_code . ' senilai '
                . number_format($realisasi, 0, ',', '.') . ' (dibayar ' . $cr->amount_formatted . ').',
            route('cost-register.index'),
            'workflow'
        );

        UserActivityLog::record('cost_register', 'settle', 'Mengajukan settlement Expense ' . $cr->display_code, $cr);

        return redirect(route('cost-register.index'))
            ->with('success', 'Settlement Expense ' . $cr->display_code . ' diajukan (menunggu approval CEO).');
    }

    public function settleApprove(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::APPROVE_ROLES), 403);

        $request->validate(['notes' => 'nullable|string|max:500']);

        try {
            $cr = DB::transaction(function () use ($request, $id) {
                $cr = CostRegister::whereKey($id)->lockForUpdate()->firstOrFail();
                if (! $cr->isSettleSubmitted()) {
                    throw new \DomainException('Hanya settlement berstatus Settle Submitted yang dapat disetujui.');
                }

                $kurang = max(0, (int) $cr->settled_amount - (int) $cr->amount);
                $tujuan = $kurang > 0 ? 'SETTLE_APPROVED' : 'CLOSED';

                $cr->update([
                    'status'             => $tujuan,
                    'refund_amount'      => max(0, (int) $cr->amount - (int) $cr->settled_amount),
                    'shortfall_amount'   => $kurang,
                    'settle_approved_by' => Auth::id(),
                    'settle_approved_at' => now(),
                ]);

                if ($kurang > 0) {
                    // Kekurangan bayar dibayar HANYA lewat batch Treasury.
                    $cr->secondPayments()->create(['amount' => $kurang, 'status' => 'PENDING']);
                }

                // Pembebanan periode mengikuti nilai realisasi (proporsional porsi lama).
                $periode = $cr->periods()->get();
                if ($periode->isNotEmpty() && (int) $periode->sum('amount') !== (int) $cr->settled_amount) {
                    $baru = CostRegister::rescale($periode->pluck('amount')->all(), (int) $cr->settled_amount);
                    if ($baru === null) {
                        throw new \DomainException('Nilai realisasi terlalu kecil untuk dibebankan ke ' . $periode->count() . ' periode.');
                    }
                    foreach ($periode->values() as $i => $p) {
                        $p->update(['amount' => $baru[$i]]);
                    }
                }

                $cr->addLog('SETTLE_SUBMITTED', $tujuan, 'settle_approved', trim(
                    ($kurang > 0
                        ? 'Kekurangan bayar ' . number_format($kurang, 0, ',', '.') . ' menunggu Treasury.'
                        : ((int) $cr->refund_amount > 0 ? 'Kelebihan (refund) ' . number_format($cr->refund_amount, 0, ',', '.') . '.' : ''))
                    . ' ' . ($request->notes ?? '')
                ) ?: null);

                return $cr;
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $cr->load('inputBy');
        if ($cr->inputBy) {
            NotifyService::send(
                [$cr->inputBy],
                'Settlement Expense disetujui',
                'Settlement Expense ' . $cr->display_code . ' disetujui'
                    . ($cr->status === 'SETTLE_APPROVED' ? '; kekurangan bayar ' . number_format($cr->shortfall_amount, 0, ',', '.') . ' menunggu batch Treasury.' : ' dan ditutup.'),
                route('cost-register.index'),
                'workflow'
            );
        }

        UserActivityLog::record('cost_register', 'settle_approve', 'Menyetujui settlement Expense ' . $cr->display_code, $cr, $request->notes);

        return back()->with('success', $cr->status === 'CLOSED'
            ? 'Settlement disetujui — Expense ditutup.'
            : 'Settlement disetujui — kekurangan bayar menunggu batch Treasury.');
    }

    public function settleReject(Request $request, $id)
    {
        abort_unless(Auth::user()->hasRole(CostRegister::APPROVE_ROLES), 403);

        $request->validate(['notes' => 'required|string|max:500']);

        try {
            $cr = DB::transaction(function () use ($request, $id) {
                $cr = CostRegister::whereKey($id)->lockForUpdate()->firstOrFail();
                if (! $cr->isSettleSubmitted()) {
                    throw new \DomainException('Hanya settlement berstatus Settle Submitted yang dapat ditolak.');
                }

                // Rincian realisasi dibiarkan agar Finance Manager cukup memperbaikinya.
                $cr->update(['status' => 'SETTLE_REJECTED']);
                $cr->addLog('SETTLE_SUBMITTED', 'SETTLE_REJECTED', 'settle_rejected', $request->notes);

                return $cr;
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $cr->load('inputBy');
        if ($cr->inputBy) {
            NotifyService::send(
                [$cr->inputBy],
                'Settlement Expense ditolak',
                'Settlement Expense ' . $cr->display_code . ' ditolak CEO: ' . $request->notes,
                route('cost-register.index'),
                'workflow'
            );
        }

        UserActivityLog::record('cost_register', 'settle_reject', 'Menolak settlement Expense ' . $cr->display_code, $cr, $request->notes);

        return back()->with('success', 'Settlement ditolak — Finance Manager dapat memperbaiki dan mengajukan ulang.');
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
            'details.project', 'details.costCenter', 'details.projects:projects.id,projects.code', 'periods',
            'settlementDetails.account', 'settlementDetails.project', 'settlementDetails.costCenter',
            'settlementDetails.projects:projects.id,projects.code',
            'settlementAttachments', 'secondPayments', 'settleSubmittedBy', 'settleApprovedBy',
        ])->findOrFail($id);

        $petaRincian = fn ($d) => [
            'account'    => $d->account->name ?? '-',
            'category'   => $d->cost_category ?? '-',
            'target'     => $d->allocation_label,
            'description' => $d->description,
            'qty'        => $d->qty,
            'unit_price' => number_format($d->unit_price, 0, ',', '.'),
            'total'      => number_format($d->total, 0, ',', '.'),
        ];
        $rupiah = fn ($v) => $v === null ? null : number_format((int) $v, 0, ',', '.');

        $avatars = User::avatarUrlMap($cr->logs->pluck('user_id'));
        $user    = Auth::user();

        return response()->json([
            'id'               => $cr->id,
            'code'             => $cr->display_code,
            'is_legacy'        => $cr->isLegacy(),
            'target_label'     => $cr->target_label,
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
            'period'           => $cr->period_label,
            'periods'          => $cr->periods->map(fn ($p) => [
                'period' => $p->period->format('M Y'),
                'amount' => number_format($p->amount, 0, ',', '.'),
            ]),
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

            // Expense Cash Advance (Fase 2).
            'is_cash_advance'     => $cr->isCashAdvance(),
            'settled_amount'      => $rupiah($cr->settled_amount),
            'refund_amount'       => $rupiah($cr->refund_amount),
            'shortfall_amount'    => $rupiah($cr->shortfall_amount),
            'settle_submitted_by' => $cr->settleSubmittedBy->name ?? '-',
            'settle_submitted_at' => $cr->settle_submitted_at?->format('d M Y H:i'),
            'settle_approved_by'  => $cr->settleApprovedBy->name ?? '-',
            'settle_approved_at'  => $cr->settle_approved_at?->format('d M Y H:i'),
            'settlement_details'  => $cr->settlementDetails->map($petaRincian),
            'settlement_attachments' => $cr->settlementAttachments->map(fn ($a) => [
                'title' => $a->title,
                'path'  => $a->file_path,
            ]),
            'second_payments' => $cr->secondPayments->map(fn ($sp) => [
                'amount'  => $sp->amount_formatted,
                'status'  => $sp->status,
                'paid_at' => $sp->paid_at?->format('d M Y H:i'),
            ]),

            // Rincian per item; register lama tidak punya rincian (daftar kosong).
            // Tujuan rincian Expense; rincian Cost Register lama ikut PO header.
            'details' => $cr->details->map($petaRincian),

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
            'can_reject'  => $cr->isVerified() && $user->hasRole(CostRegister::APPROVE_ROLES),
            'can_reopen'  => $cr->isRejected() && $user->hasRole(CostRegister::SUBMIT_ROLES),
            'can_edit'    => $cr->isEditable() && ! $cr->isLegacy() && $user->hasRole(CostRegister::SUBMIT_ROLES),
            'can_settle'  => $cr->canInputSettlement() && $user->hasRole(CostRegister::SETTLE_ROLES),
            'can_approve_settle' => $cr->isSettleSubmitted() && $user->hasRole(CostRegister::APPROVE_ROLES),
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
            return response()->json(['message' => 'Expense sudah ' . $cr->status_label . '; catatan baru tidak dapat ditambahkan.'], 422);
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

        $registers = CostRegister::with(['project.customer', 'inputBy', 'beneficiary', 'details.account', 'details.project', 'details.costCenter',
                'details.projects:projects.id,projects.code', 'periods'])
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids))
            ->when($ids->isEmpty(), fn ($q) => $q
                ->whereDate('created_at', '>=', $dateFrom)
                ->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('id')->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Expense');
        $kolom = ['Tanggal Input', 'No. Expense', 'Tujuan', 'Tipe', 'Status', 'Beneficiary', 'Periode',
                  'Nominal', 'Catatan', 'Diinput Oleh', 'Batch Alokasi'];
        $this->tulisHeader($sheet, $kolom);

        $teks = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
        $row = 2;
        foreach ($registers as $cr) {
            $sheet->fromArray([
                $cr->created_at?->format('d-m-Y'),
                $cr->display_code,
                null, // diisi eksplisit sebagai teks di bawah
                $cr->type_label,
                $cr->status_label,
                $cr->beneficiary->nickname ?? '-',
                $cr->period_label,
                (int) $cr->amount,
                $cr->notes,
                $cr->inputBy->name ?? '-',
                $cr->batch_code ?? '-',
            ], null, 'A' . $row);
            // Kode PO ditulis sebagai teks (deretan digit), bukan angka.
            $sheet->setCellValueExplicit('C' . $row, $cr->target_label, $teks);
            $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $row++;
        }
        foreach (range('A', 'K') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        // Sheet kedua: rincian per item beserta cara pembebanannya.
        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Rincian');
        $this->tulisHeader($detail, ['No. Expense', 'Pembebanan', 'Kategori', 'Status', 'Akun', 'Deskripsi', 'Harga Satuan', 'Qty', 'Total']);
        $r = 2;
        foreach ($registers as $cr) {
            foreach ($cr->details as $d) {
                $detail->setCellValueExplicit('A' . $r, $cr->display_code, $teks);
                $detail->setCellValueExplicit('B' . $r, $d->allocation_label, $teks);
                $detail->fromArray([
                    $d->cost_category ?? $cr->type_label,
                    $cr->status_label, $d->account->name ?? '-', $d->description,
                    (int) $d->unit_price, $d->qty, (int) $d->total,
                ], null, 'C' . $r);
                $detail->getStyle('G' . $r)->getNumberFormat()->setFormatCode('#,##0');
                $detail->getStyle('I' . $r)->getNumberFormat()->setFormatCode('#,##0');
                $r++;
            }
        }
        foreach (range('A', 'I') as $c) {
            $detail->getColumnDimension($c)->setAutoSize(true);
        }

        // Sheet ketiga: pembebanan per bulan.
        $per = $spreadsheet->createSheet();
        $per->setTitle('Periode');
        $this->tulisHeader($per, ['No. Expense', 'Periode', 'Nominal', 'Metode', 'Status']);
        $r = 2;
        foreach ($registers as $cr) {
            foreach ($cr->periods as $p) {
                $per->setCellValueExplicit('A' . $r, $cr->display_code, $teks);
                $per->fromArray([$p->period->format('M Y'), (int) $p->amount,
                    CostRegister::PERIOD_METHODS[$cr->period_method] ?? '-', $cr->status_label], null, 'B' . $r);
                $per->getStyle('C' . $r)->getNumberFormat()->setFormatCode('#,##0');
                $r++;
            }
        }
        foreach (range('A', 'E') as $c) {
            $per->getColumnDimension($c)->setAutoSize(true);
        }
        $spreadsheet->setActiveSheetIndex(0);

        UserActivityLog::record('cost_register', 'export', 'Export Expense (' . $registers->count() . ' baris)');

        $filename = 'expense_' . $dateFrom . '_' . $dateTo . '.xlsx';

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

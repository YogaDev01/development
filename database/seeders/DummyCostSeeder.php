<?php

namespace Database\Seeders;

use App\Support\BudgetConsumption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data dummy sisi biaya: Budget Plan -> Cost Request -> batch Treasury
 * (Daily Transaction) -> Settlement/Second Payment.
 *
 * Pelengkap DummyChainSeeder (sisi pendapatan: PO -> Invoice -> Surat Jalan ->
 * Collection). Sama seperti seeder itu: menulis lewat query builder supaya hook
 * model tidak ikut mengubah status yang sudah disusun, dan semua nilai turunan
 * dihitung mengikuti rumus aplikasi:
 *
 *   budget_plans.amount   = SUM(budget_plan_details.total)      BudgetPlan::recalculateAmount()
 *   budget_request.amount = SUM(request_detail.total) non-TAKEOUT BudgetRequest::recalculateAmount()
 *   is_nob/nob_amount     = akumulasi CR berstatus COMMITTED     BudgetRequestController::isNOB()
 *   captured_amount batch = SUM(amount CR yang INCLUDED)         DailyTransaction::getTotalAmountAttribute()
 *
 * Aturan alur yang dijaga (lihat DailyTransactionController & BudgetRequest::
 * getAllowedNextStatuses()):
 *   - CR QUEUED selalu punya item INCLUDED di batch PENDING_APPROVER/DONE.
 *   - CR PAID ke atas selalu lewat batch yang sudah PAID.
 *   - Hanya tipe Cash Advance yang punya tahap realisasi (PAID -> REALIZED ->
 *     SETTLE_CHECKED -> SETTLE_VERIFIED -> CLOSED); tipe lain QUEUED -> CLOSED.
 *   - Second Payment cuma ada kalau Settlement melebihi nilai pengajuan, dan
 *     harus sudah PAID sebelum CR boleh CLOSED.
 *   - Cabang Expense Claim (selisih > 0) lewat SETTLE_VERIFIED ->
 *     SETTLE_APPROVED -> SECOND_APPROVED; selisihnya masih PENDING di situ.
 *     Karena itu CLOSED_SETTLED sengaja dibuat TANPA selisih - dengan klaim
 *     > 0, SETTLE_VERIFIED -> CLOSED bukan transisi yang sah.
 *
 * Bersifat MENAMBAH dan aman dijalankan berulang: jumlah CR yang dibuat =
 * target CR_TARGET per TIPE x status dikurangi yang sudah ada di DB (minimal
 * MIN_PER_COMBINATION per kombinasi), dan nomor urut kode melanjutkan kode
 * yang sudah ada (existingCodeSeq()).
 */
class DummyCostSeeder extends Seeder
{
    private Carbon $today;
    private string $now;

    /** Rentang tanggal yang dipaksakan untuk pass kedua (bulan berjalan). */
    private ?Carbon $windowFrom = null;
    private ?Carbon $windowTo   = null;

    private array $actors = [];
    private array $userNames = [];
    private array $userRoles = [];

    /** @var array<string,array<int>> kategori account => id */
    private array $accounts = [];
    private array $beneficiaryIds = [];
    /** @var array<int,string> id cluster => code */
    private array $clusters = [];
    /** @var array<int,string> id request_type => nama */
    private array $types = [];

    private const UNITS = ['unit', 'pcs', 'lot', 'set', 'site', 'orang', 'bulan', 'hari', 'team', 'package', 'lumpsum', 'trip'];

    /** Source Fund operasional (BUKAN Main Account) — dipakai batch Cash Out. */
    private const OPERATIONAL_FUNDS = [1, 2, 4, 5];
    /** Main Account — asal dana Top Up. */
    private const MAIN_FUND = 3;

    private const CR_DESCRIPTIONS = [
        'BIAYA OPERASIONAL TIM LAPANGAN',
        'PEMBELIAN MATERIAL PENDUKUNG INSTALASI',
        'AKOMODASI DAN TRANSPORT TIM SURVEY',
        'SEWA ALAT BERAT UNTUK ERECTION',
        'BIAYA PENGIRIMAN MATERIAL KE SITE',
        'UANG MUKA PEKERJAAN SIPIL',
        'REIMBURSE BIAYA PERIZINAN WARGA',
        'PEMBAYARAN JASA FREELANCE TEKNISI',
        'KONSUMSI DAN LEMBUR TIM INSTALASI',
        'PEMBELIAN APD DAN TOOLS LAPANGAN',
        'BIAYA KOMUNIKASI DAN INTERNET SITE',
        'PETTYCASH OPERASIONAL MINGGUAN',
        'PEMBAYARAN SEWA GUDANG MATERIAL',
        'BIAYA ADMINISTRASI DAN LEGALITAS',
        'ALLOWANCE TIM PROYEK LUAR KOTA',
        'PEMBELIAN SPARE PART PERANGKAT',
        'BIAYA ENTERTAINMENT MEETING CUSTOMER',
        'PEMBAYARAN VENDOR PENARIKAN KABEL',
    ];

    private const BP_LINE_DESCRIPTIONS = [
        'Material utama instalasi',
        'Aksesoris dan konektor',
        'Jasa instalasi vendor',
        'Transport dan mobilisasi tim',
        'Akomodasi tim lapangan',
        'Sewa alat dan perancah',
        'Pengiriman material ke site',
        'Perizinan dan koordinasi warga',
        'Konsumsi dan lembur',
        'APD, tools dan perlengkapan',
        'Biaya komunikasi lapangan',
        'Freelance teknisi pendukung',
        'Administrasi dan dokumentasi',
        'Cadangan biaya tak terduga',
    ];

    private const BP_CC_DESCRIPTIONS = [
        'BUDGET OPERASIONAL KANTOR',
        'BUDGET UTILITAS DAN SEWA',
        'BUDGET PERLENGKAPAN KANTOR',
        'BUDGET PERJALANAN DINAS',
        'BUDGET PAJAK DAN RETRIBUSI',
        'BUDGET GAJI DAN TUNJANGAN',
        'BUDGET PEMELIHARAAN INVENTARIS',
        'BUDGET LEGAL DAN PERIZINAN',
    ];

    /*
    |--------------------------------------------------------------------------
    | ENTRY POINT
    |--------------------------------------------------------------------------
    */

    public function run(): void
    {
        mt_srand(20260919);
        $this->today = Carbon::parse('2026-09-19')->startOfDay();
        $this->now   = now()->toDateTimeString();

        DB::transaction(function () {
            $this->loadMaster();

            /*
            | Daftar kerja dihitung LEBIH DULU supaya run yang tidak perlu
            | menambah CR tidak ikut membuat Budget Plan dan batch Top Up baru
            | - dulu keduanya selalu dibuat, jadi seeder ini menggandakan ratusan
            | Budget Plan tanpa CR setiap kali dijalankan ulang.
            */
            $workload = $this->costRequestWorkload();
            $topUps   = $this->topUpWorkload();

            if ($workload === [] && $topUps === 0) {
                $this->command?->info('Target data dummy biaya sudah terpenuhi, tidak ada yang ditambahkan.');

                return;
            }

            $crs = [];

            if ($workload !== []) {
                $plans = $this->seedBudgetPlans();
                $crs   = $this->seedCostRequests($plans, $workload);
            }

            $this->seedTreasury($crs, $topUps);
            $this->syncSequences();

            $this->command?->info(
                'Dibuat ' . count($crs) . ' Cost Request dan ' . $topUps . ' batch Top Up.'
            );
        });

        $this->seedCurrentMonth();
    }

    /**
     * Pass kedua: Cost Request bertanggal BULAN BERJALAN (1 s/d akhir bulan).
     *
     * Pass pertama menyusun riwayat berbulan-bulan ke belakang, jadi tidak ada
     * satu pun CR di bulan berjalan - padahal daftar Cost Request default-nya
     * memfilter bulan berjalan, sehingga halamannya tampak kosong. Di sini tiap
     * kombinasi tipe x status ditambah sampai minimal MIN_PER_COMBINATION CR
     * yang tanggalnya masuk rentang itu.
     *
     * Budget Plan TIDAK dibuat lagi - memakai yang sudah ada di DB, lengkap
     * dengan akumulasi committed/used-nya supaya penanda NOB tetap benar.
     *
     * Catatan: rantai approval sampai settlement normalnya makan belasan
     * minggu. Dipaksa muat dalam satu bulan, jadi beberapa tahap bisa jatuh di
     * tanggal yang berdekatan - wajar untuk data contoh, bukan data nyata.
     */
    private function seedCurrentMonth(): void
    {
        mt_srand(20261004);

        $this->windowFrom = Carbon::now()->startOfMonth()->startOfDay();
        $this->windowTo   = Carbon::now()->endOfMonth()->startOfDay();
        $this->today      = $this->windowTo->copy();

        DB::transaction(function () {
            $this->loadMaster();

            $workload = $this->costRequestWorkload($this->windowFrom, $this->windowTo);

            if ($workload === []) {
                $this->command?->info('Data dummy bulan berjalan sudah terpenuhi.');

                return;
            }

            $plans = $this->loadExistingPlans();

            if ($plans === []) {
                $this->command?->warn('Tidak ada Budget Plan aktif, CR bulan berjalan dilewati.');

                return;
            }

            $crs = $this->seedCostRequests($plans, $workload);
            $this->seedTreasury($crs);
            $this->syncSequences();

            $this->command?->info(
                'Dibuat ' . count($crs) . ' Cost Request bertanggal '
                . $this->windowFrom->format('d/m/Y') . ' - ' . $this->windowTo->format('d/m/Y') . '.'
            );
        });

        $this->windowFrom = null;
        $this->windowTo   = null;
    }

    /**
     * Budget Plan yang SUDAH ada di DB, dibentuk ulang jadi struktur yang
     * dipakai buildCostRequest(). committed & used dihitung dari CR yang ada
     * memakai konstanta BudgetConsumption yang sama dengan aplikasi.
     */
    private function loadExistingPlans(): array
    {
        $rows = DB::table('budget_plans')
            ->whereIn('status', ['ACTIVE', 'CLOSING', 'CLOSED'])
            ->get(['id', 'code', 'amount', 'project_id', 'cost_center_id', 'approved_at', 'created_at']);

        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('id')->all();

        // committed per budget = SUM(amount) CR berstatus COMMITTED.
        $committed = DB::table('budget_request')->whereNull('deleted_at')
            ->whereIn('budget_plan_id', $ids)
            ->whereIn('status', BudgetConsumption::COMMITTED)
            ->selectRaw('budget_plan_id, SUM(amount) AS total')
            ->groupBy('budget_plan_id')->pluck('total', 'budget_plan_id');

        // used per sub-item = SUM(total) rincian CR yang sudah cair.
        $used = DB::table('request_detail')
            ->join('budget_request', 'budget_request.id', '=', 'request_detail.budget_request_id')
            ->whereNull('budget_request.deleted_at')
            ->whereIn('budget_request.status', BudgetConsumption::DISBURSED)
            ->where('request_detail.status', '!=', 'TAKEOUT')
            ->whereNotNull('request_detail.budget_detail_id')
            ->selectRaw('request_detail.budget_detail_id, SUM(request_detail.total) AS total')
            ->groupBy('request_detail.budget_detail_id')->pluck('total', 'budget_detail_id');

        $details = [];
        foreach (DB::table('budget_plan_details')->whereIn('budget_plan_id', $ids)
            ->get(['id', 'budget_plan_id', 'total', 'account_id', 'deskripsi']) as $d) {
            $details[(int) $d->budget_plan_id][] = [
                'id'         => (int) $d->id,
                'total'      => (int) $d->total,
                'account_id' => (int) $d->account_id,
                'deskripsi'  => (string) $d->deskripsi,
                'used'       => (int) ($used[$d->id] ?? 0),
            ];
        }

        $out = [];

        foreach ($rows as $p) {
            if (empty($details[(int) $p->id])) {
                continue;
            }

            $out[] = [
                'id'         => (int) $p->id,
                'amount'     => (int) $p->amount,
                'details'    => $details[(int) $p->id],
                'projectId'  => $p->project_id ? (int) $p->project_id : null,
                'isProject'  => $p->project_id !== null,
                'sourceCode' => $this->planSourceCode([
                    'project_id'     => $p->project_id,
                    'cost_center_id' => $p->cost_center_id,
                ]),
                'from'       => Carbon::parse($p->approved_at ?? $p->created_at),
                'committed'  => (int) ($committed[$p->id] ?? 0),
            ];
        }

        return $out;
    }
    /*
    |--------------------------------------------------------------------------
    | MASTER
    |--------------------------------------------------------------------------
    */

    private function loadMaster(): void
    {
        foreach (DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.status', 'ACTIVE')
            ->get(['users.id', 'users.name', 'roles.name as role']) as $u) {
            $this->actors[$u->role][] = $u->id;
            $this->userNames[$u->id]  = $u->name;
            $this->userRoles[$u->id]  = $u->role;
        }

        $fallback = DB::table('users')->orderBy('id')->value('id');
        foreach (['head', 'finance', 'finance_manager', 'accounting', 'collection',
                  'approver', 'manager', 'staff', 'treasury', 'cost_control'] as $role) {
            if (empty($this->actors[$role])) {
                $this->actors[$role] = [$fallback];
            }
        }

        foreach (['Variable Cost', 'Fixed Cost', 'Overhead'] as $cat) {
            $this->accounts[$cat] = DB::table('accounts')
                ->where('status', 'ACTIVE')->where('category', $cat)->pluck('id')->toArray();
        }

        $this->beneficiaryIds = DB::table('beneficiaries')->where('status', 'ACTIVE')->pluck('id')->toArray();
        $this->clusters       = DB::table('clusters')->where('status', 'ACTIVE')->pluck('code', 'id')->toArray();
        $this->types          = DB::table('request_type')->pluck('name', 'id')->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER
    |--------------------------------------------------------------------------
    */

    private function pick(array $arr)
    {
        return $arr[array_rand($arr)];
    }

    private function actor(string ...$roles): int
    {
        $pool = [];
        foreach ($roles as $r) {
            $pool = array_merge($pool, $this->actors[$r] ?? []);
        }
        return $this->pick($pool);
    }

    private function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    /** Jam kerja acak pada sebuah tanggal, dijamin setelah $after (lihat DummyChainSeeder). */
    private function atTimeAfter(Carbon $date, ?Carbon $after = null): Carbon
    {
        $limit = $this->today->copy()->setTime(23, 45, 0);

        $d = $date->copy()->setTime(mt_rand(8, 17), mt_rand(0, 59), mt_rand(0, 59));

        if ($after && $d->lte($after)) {
            $d = $after->copy()->addMinutes(mt_rand(5, 150));
        }
        if ($d->gt($limit)) {
            $d = $limit->copy();
        }
        if ($after && $d->lte($after)) {
            $d = $after->copy()->addMinute();
        }

        return $d;
    }

    private function fwd(Carbon $from, int $min, int $max): Carbon
    {
        $d = $from->copy()->startOfDay()->addDays(mt_rand($min, $max));
        return $d->gt($this->today) ? $this->today->copy() : $d;
    }

    private function step(Carbon $cursor, int $minDays, int $maxDays): Carbon
    {
        return $this->atTimeAfter($this->fwd($cursor, $minDays, $maxDays), $cursor);
    }

    private function maxTs(Carbon $a, ?Carbon $b): Carbon
    {
        return $b && $b->gt($a) ? $b->copy() : $a->copy();
    }

    /** Bagi rata dengan sisa ke bagian terakhir; jumlah tetap persis $total. */
    private function splitEven(int $total, int $parts, int $round = 1000): array
    {
        $out = [];
        $sum = 0;
        for ($i = 0; $i < $parts - 1; $i++) {
            $v = (int) (round($total / $parts / $round) * $round);
            $out[] = $v;
            $sum  += $v;
        }
        $out[] = $total - $sum;

        return $out;
    }

    /**
     * Nomor urut terakhir per prefix kode yang SUDAH ada di DB.
     *
     * Kode dokumen di sini dibentuk prefix + 4 digit urut, dan $codeSeq yang
     * dulu selalu mulai dari 0 membuat seeder ini bentrok dengan datanya
     * sendiri begitu dijalankan dua kali (unique budget_plans.code /
     * budget_request.code). Dibaca dari DB supaya seeder benar-benar bersifat
     * MENAMBAH seperti DummyChainSeeder, bukan sekali pakai.
     */
    private function existingCodeSeq(string $table, int $seqLength = 4, ?int $prefixLength = null): array
    {
        $out = [];

        foreach (DB::table($table)->pluck('code') as $code) {
            $code = (string) $code;
            if (strlen($code) <= $seqLength) {
                continue;
            }

            // Kode batch Treasury memakai prefix pendek (Ym) yang BUKAN seluruh
            // sisa kode - di antaranya masih ada jumlah item 3 digit.
            $prefix = $prefixLength === null
                ? substr($code, 0, -$seqLength)
                : substr($code, 0, $prefixLength);
            $seq    = (int) substr($code, -$seqLength);

            if ($seq > ($out[$prefix] ?? 0)) {
                $out[$prefix] = $seq;
            }
        }

        return $out;
    }

    /** Pecah $total jadi qty x harga satuan bulat. */
    private function unitize(int $total): array
    {
        $qty = $this->pick([1, 1, 1, 2, 3, 4, 5, 10]);
        while ($qty > 1 && $total % $qty !== 0) {
            $qty--;
        }

        return [(int) ($total / max(1, $qty)), $qty];
    }

    /*
    |--------------------------------------------------------------------------
    | BUDGET PLAN
    |--------------------------------------------------------------------------
    | Sumber dana wajib tepat satu: project_id ATAU cost_center_id
    | (lihat guard di BudgetPlan::booted()).
    */

    private function seedBudgetPlans(): array
    {
        $plans   = [];
        $codeSeq = $this->existingCodeSeq("budget_plans");

        // --- Budget proyek -------------------------------------------------
        $projects = DB::table('projects')->where('id', '>', 26)
            ->orderBy('created_at')
            ->get(['id', 'code', 'status', 'value', 'contract', 'created_at', 'approved_at']);

        foreach ($projects as $p) {
            if (in_array($p->status, ['DRAFT', 'SUBMITTED']) && ! $this->chance(50)) {
                continue;
            }

            $status  = $this->planStatusFor($p->status);
            $base    = Carbon::parse($p->approved_at ?? $p->created_at);
            $created = $this->atTimeAfter($this->fwd($base, 1, 25), $base);

            // Budget ~55-80% nilai PO (sisanya margin).
            $target = (int) round(((int) ($p->value ?: $p->contract ?: 400000000)) * mt_rand(55, 80) / 100);
            $target = max(50000000, (int) (round($target / 1000) * 1000));

            $prefix = 'BD-' . $created->format('m') . $created->format('y')
                . str_pad((string) $p->code, 4, '0', STR_PAD_LEFT);

            $plans[] = $this->buildPlan(
                $prefix,
                $codeSeq,
                $status,
                $created,
                $target,
                'one_time',
                (int) $p->id,
                null,
                'Variable Cost',
                strtoupper($this->pick(self::BP_LINE_DESCRIPTIONS)) . ' - PO ' . $p->code,
                (int) $p->id
            );
        }

        // --- Budget cost center (overhead / payroll / pajak) ----------------
        $costCenters = DB::table('cost_centers')->where('status', 'ACTIVE')->get(['id', 'code', 'name', 'type']);

        foreach ($costCenters as $cc) {
            $isPayroll = strtoupper($cc->type) === 'PAYROLL';
            $category  = $isPayroll ? 'Fixed Cost' : 'Overhead';
            $ccNum     = str_pad(preg_replace('/\D/', '', $cc->code) ?: '0', 4, '0', STR_PAD_LEFT);
            $prefix2   = $isPayroll ? 'PB-' : 'OB-';

            foreach (range(1, mt_rand(3, 5)) as $i) {
                $created = $this->atTimeAfter($this->today->copy()->subDays(mt_rand(20, 340)));
                $status  = $this->pick([
                    'ACTIVE', 'ACTIVE', 'ACTIVE', 'ACTIVE', 'ACTIVE',
                    'CLOSED', 'CLOSING', 'DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'REJECTED',
                ]);

                $prefix = $prefix2 . $created->format('m') . $created->format('y') . $ccNum;

                $plans[] = $this->buildPlan(
                    $prefix,
                    $codeSeq,
                    $status,
                    $created,
                    (int) (round(mt_rand(40000000, 600000000) / 1000) * 1000),
                    'recurring',
                    null,
                    (int) $cc->id,
                    $category,
                    $this->pick(self::BP_CC_DESCRIPTIONS) . ' - ' . $cc->name
                        . ' ' . $created->translatedFormat('M Y'),
                    null
                );
            }
        }

        return $this->persistPlans($plans);
    }

    /**
     * Status Budget Plan harus sejalan dengan status PO-nya:
     * begitu PO PAID, Project::autoCloseBudgetPlans() memindahkan budget ACTIVE
     * ke CLOSING — jadi budget ACTIVE di bawah PO yang sudah lunas/ditutup
     * adalah kombinasi yang tidak pernah ada di aplikasi.
     */
    private function planStatusFor(string $projectStatus): string
    {
        switch ($projectStatus) {
            case 'DRAFT':
            case 'SUBMITTED':
                return $this->pick(['DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'REJECTED']);
            case 'WAITING_PO':
                return $this->pick(['ACTIVE', 'VERIFIED', 'CHECKED', 'SUBMITTED']);
            case 'ACTIVE':
            case 'PROGRESS_DONE':
                return $this->pick([
                    'ACTIVE', 'ACTIVE', 'ACTIVE', 'ACTIVE', 'ACTIVE', 'ACTIVE',
                    'DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'REJECTED',
                ]);
            case 'PAID':
                return $this->pick(['CLOSING', 'CLOSING', 'CLOSED']);
            case 'CLOSING':
                return $this->pick(['CLOSING', 'CLOSED']);
            default: // CLOSED
                return 'CLOSED';
        }
    }

    private function buildPlan(
        string $prefix,
        array &$codeSeq,
        string $status,
        Carbon $created,
        int $target,
        string $type,
        ?int $projectId,
        ?int $costCenterId,
        string $accountCategory,
        string $description,
        ?int $ownerProjectId
    ): array {
        $seq  = ($codeSeq[$prefix] = ($codeSeq[$prefix] ?? 0) + 1);
        $code = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        // Rincian budget — kategori & type memang dipaku aplikasi
        // (BudgetPlanController::syncDetails), account-nya yang mengikuti sumber.
        $lines   = [];
        $pool    = $this->accounts[$accountCategory] ?: $this->accounts['Variable Cost'];
        $descs   = (array) array_rand(array_flip(self::BP_LINE_DESCRIPTIONS), $n = mt_rand(3, 8));

        foreach ($this->splitEven($target, $n, 10000) as $i => $lineTotal) {
            [$amount, $qty] = $this->unitize($lineTotal);

            $lines[] = [
                'type'       => $i === 0 ? 'main' : ($this->chance(15) ? 'additional' : 'main'),
                'category'   => 'Variable Cost',
                'account_id' => $this->pick($pool),
                'deskripsi'  => $descs[$i] ?? $this->pick(self::BP_LINE_DESCRIPTIONS),
                'qty'        => $qty,
                'unit'       => $this->pick(self::UNITS),
                'amount'     => $amount,
                'total'      => $amount * $qty,
                'notes'      => null,
            ];
        }

        $amount = array_sum(array_column($lines, 'total'));

        // --- garis waktu approval ------------------------------------------
        $order = ['DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'ACTIVE', 'CLOSING', 'CLOSED'];
        $level = $status === 'REJECTED' ? 1 : (int) array_search($status, $order, true);

        $inputBy = $this->actor('head', 'manager', 'finance');
        $cursor  = $created;
        $logs    = [$this->planLog('DRAFT', 'DRAFT', 'created', null, $inputBy, $cursor)];

        $approvedBy = null;
        $approvedAt = null;

        if ($level >= 1) {
            $cursor = $this->step($cursor, 0, 6);
            $logs[] = $this->planLog('DRAFT', 'SUBMITTED', 'submitted', null, $inputBy, $cursor);
        }
        if ($status === 'REJECTED') {
            $cursor = $this->step($cursor, 1, 8);
            $logs[] = $this->planLog('SUBMITTED', 'REJECTED', 'rejected',
                $this->pick([
                    'Rincian budget belum sesuai BOQ, mohon dilengkapi.',
                    'Harga satuan material terlalu tinggi dibanding vendor lain.',
                    'Scope pekerjaan belum jelas, tolong dipisah per cluster.',
                ]),
                $this->actor('finance', 'finance_manager'), $cursor);
        }
        if ($level >= 2) {
            $cursor = $this->step($cursor, 1, 5);
            $logs[] = $this->planLog('SUBMITTED', 'CHECKED', 'checked', null, $this->actor('head'), $cursor);
        }
        if ($level >= 3) {
            $cursor = $this->step($cursor, 1, 5);
            $logs[] = $this->planLog('CHECKED', 'VERIFIED', 'verified', null, $this->actor('finance', 'cost_control'), $cursor);
        }
        if ($level >= 4) {
            $cursor     = $this->step($cursor, 1, 6);
            $approvedBy = $this->actor('approver', 'finance_manager');
            $approvedAt = $cursor;
            $logs[]     = $this->planLog('VERIFIED', 'ACTIVE', 'approved', 'Budget disetujui dan aktif.', $approvedBy, $cursor);
        }

        return [
            'row' => [
                'code'           => $code,
                'type'           => $type,
                'description'    => strtoupper($description),
                'amount'         => $amount,
                'project_id'     => $projectId,
                'cost_center_id' => $costCenterId,
                'input_by'       => $inputBy,
                'status'         => $status,
                'notes'          => $this->chance(30) ? $this->pick([
                    'Budget mengikuti BOQ final yang disepakati customer.',
                    'Termasuk cadangan 5% untuk pekerjaan tambah.',
                    'Realisasi dipantau per cluster tiap bulan.',
                ]) : null,
                'approver_notes' => $approvedAt && $this->chance(25) ? 'Disetujui dengan catatan: pantau realisasi material.' : null,
                'approved_by'    => $approvedBy,
                'approved_at'    => $approvedAt?->toDateTimeString(),
                'created_at'     => $created->toDateTimeString(),
                'updated_at'     => $cursor->toDateTimeString(),
            ],
            'lines'      => $lines,
            'logs'       => $logs,
            'status'     => $status,
            'activeFrom' => $approvedAt ?? $cursor,
            'projectId'  => $ownerProjectId,
        ];
    }

    private function planLog(?string $from, ?string $to, string $action, ?string $notes, int $userId, Carbon $at): array
    {
        return [
            'status_from' => $from,
            'status_to'   => $to,
            'action'      => $action,
            'role'        => $this->userRoles[$userId] ?? null,
            'user_id'     => $userId,
            'user_name'   => $this->userNames[$userId] ?? 'System',
            'notes'       => $notes,
            'created_at'  => $at->toDateTimeString(),
            'updated_at'  => $at->toDateTimeString(),
        ];
    }

    /** @return array<int,array> plan siap dipakai Cost Request (sudah punya id) */
    private function persistPlans(array $plans): array
    {
        $out = [];

        foreach ($plans as $plan) {
            $planId = DB::table('budget_plans')->insertGetId($plan['row']);

            $detailIds = [];
            foreach ($plan['lines'] as $line) {
                $detailIds[] = [
                    'id' => DB::table('budget_plan_details')->insertGetId($line + [
                        'budget_plan_id' => $planId,
                        'created_at'     => $plan['row']['created_at'],
                        'updated_at'     => $plan['row']['created_at'],
                    ]),
                    'total'      => $line['total'],
                    'account_id' => $line['account_id'],
                    'deskripsi'  => $line['deskripsi'],
                    'used'       => 0,
                ];
            }

            foreach ($plan['logs'] as $log) {
                DB::table('budget_plan_logs')->insert($log + ['budget_plan_id' => $planId]);
            }

            // Hanya budget yang pernah aktif yang bisa punya Cost Request.
            if (in_array($plan['status'], ['ACTIVE', 'CLOSING', 'CLOSED'])) {
                $out[] = [
                    'id'         => $planId,
                    'amount'     => $plan['row']['amount'],
                    'details'    => $detailIds,
                    'projectId'  => $plan['projectId'],
                    'isProject'  => $plan['row']['project_id'] !== null,
                    'sourceCode' => $this->planSourceCode($plan['row']),
                    'from'       => $plan['activeFrom'],
                    'committed'  => 0,
                ];
            }
        }

        return $out;
    }

    /** Slot "sumber" pada kode CR: kode PO untuk budget proyek, angka kode CC untuk cost center. */
    private function planSourceCode(array $row): string
    {
        if ($row['project_id']) {
            return (string) DB::table('projects')->where('id', $row['project_id'])->value('code');
        }

        $code = DB::table('cost_centers')->where('id', $row['cost_center_id'])->value('code');
        $num  = preg_replace('/\D/', '', (string) $code);

        return $num !== '' ? $num : '0000';
    }

    /*
    |--------------------------------------------------------------------------
    | COST REQUEST
    |--------------------------------------------------------------------------
    */

    /**
     * Target jumlah CR per TIPE PENGAJUAN x status, bukan per status saja.
     *
     * Status yang bisa dicapai berbeda per tipe (BudgetRequest::
     * getAllowedNextStatuses()): hanya Cash Advance yang punya tahap realisasi
     * dan settlement, sedangkan tipe lain berhenti di QUEUED -> CLOSED. Dulu
     * tipe dipilih acak, jadi Payment/Reimbursement bisa kosong di banyak
     * status; sekarang tiap kombinasi dijamin terisi.
     *
     * CLOSED dipecah dua karena jalannya beda: CLOSED_SETTLED lewat settlement
     * (Cash Advance), CLOSED_DIRECT langsung dari batch Treasury (tipe lain).
     *
     * Angkanya TARGET, bukan jumlah yang selalu dibuat: seedCostRequests()
     * mengurangi jumlah yang sudah ada di DB, jadi seeder ini aman dijalankan
     * berulang dan tidak akan menggandakan data.
     */
    private const MIN_PER_COMBINATION = 3;

    private const CR_TARGET = [
        // Cash Advance - alur penuh sampai settlement & Expense Claim.
        'Cash Advance' => [
            'DRAFT'           => 6,
            'SUBMITTED'       => 6,
            'CHECKED'         => 5,
            'VERIFIED'        => 5,
            'APPROVED'        => 6,
            'QUEUED'          => 8,
            'PAID'            => 16,
            'REALIZED'        => 14,
            'SETTLE_CHECKED'  => 12,
            'SETTLE_VERIFIED' => 16,
            'SETTLE_APPROVED' => 6,
            'SECOND_APPROVED' => 6,
            'CLOSED_SETTLED'  => 30,
            'REJECTED'        => 5,
            'CANCELLED'       => 4,
        ],
        // Payment & Reimbursement - tanpa realisasi, QUEUED -> CLOSED.
        'Payment' => [
            'DRAFT'         => 4,
            'SUBMITTED'     => 4,
            'CHECKED'       => 4,
            'VERIFIED'      => 4,
            'APPROVED'      => 4,
            'QUEUED'        => 6,
            'CLOSED_DIRECT' => 10,
            'REJECTED'      => 4,
            'CANCELLED'     => 3,
        ],
        'Reimbursement' => [
            'DRAFT'         => 4,
            'SUBMITTED'     => 4,
            'CHECKED'       => 4,
            'VERIFIED'      => 4,
            'APPROVED'      => 4,
            'QUEUED'        => 6,
            'CLOSED_DIRECT' => 10,
            'REJECTED'      => 4,
            'CANCELLED'     => 3,
        ],
    ];

    /** Status akhir di DB untuk tiap kunci rencana di CR_TARGET. */
    private const PLAN_TO_STATUS = [
        'CLOSED_SETTLED' => 'CLOSED',
        'CLOSED_DIRECT'  => 'CLOSED',
    ];

    /**
     * Kunci rencana yang uangnya sudah cair (CR lewat batch Treasury yang
     * sudah PAID) dan karena itu masuk alur realisasi/settlement.
     */
    private const SETTLEMENT_PLAN_STATUSES = [
        'PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED',
        'SETTLE_APPROVED', 'SECOND_APPROVED', 'CLOSED_SETTLED',
    ];

    /**
     * Kunci rencana pada cabang Expense Claim — realisasi MELEBIHI nilai
     * pengajuan, jadi kekurangannya wajib lewat approve Finance Manager
     * (SETTLE_APPROVED) lalu Approver (SECOND_APPROVED) sebelum Treasury
     * membayarnya. Lihat BudgetRequest::getAllowedNextStatuses().
     */
    private const CLAIM_PLAN_STATUSES = ['SETTLE_APPROVED', 'SECOND_APPROVED'];

    /**
     * Daftar kerja CR: [typeId, kunci rencana] sebanyak KEKURANGAN tiap
     * kombinasi tipe x status terhadap CR_TARGET (minimal MIN_PER_COMBINATION).
     *
     * Dihitung dari DB supaya seeder bersifat MENAMBAH dan idempotent: kalau
     * sebuah kombinasi sudah memenuhi target, tidak ada CR baru yang dibuat.
     * CLOSED_SETTLED vs CLOSED_DIRECT dibedakan dari tipenya, karena di DB
     * keduanya tersimpan sebagai status CLOSED yang sama.
     */
    /** Target jumlah batch Top Up (daily_transactions bertipe TOP_UP). */
    private const TOP_UP_TARGET = 22;

    /** Kekurangan batch Top Up terhadap TOP_UP_TARGET. */
    private function topUpWorkload(): int
    {
        $have = (int) DB::table('daily_transactions')->where('type', 'TOP_UP')->count();

        return max(0, self::TOP_UP_TARGET - $have);
    }
    /**
     * id RequestType "Cash Advance". Dicocokkan dari NAMA, sama seperti
     * BudgetRequest::CASH_ADVANCE_TYPE_NAMES - bukan id yang dipaku.
     */
    private function cashAdvanceTypeId(): int
    {
        return (int) (array_search('Cash Advance', $this->types, true) ?: 1);
    }
    private function costRequestWorkload(?Carbon $from = null, ?Carbon $to = null): array
    {
        // Dengan rentang tanggal, yang dihitung hanya CR di rentang itu -
        // dipakai pass bulan berjalan supaya riwayat lama tidak ikut menutupi
        // kekurangan di bulan ini.
        $query = DB::table('budget_request')->whereNull('deleted_at');

        if ($from && $to) {
            $query->whereBetween('created_at', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ]);
        }

        $existing = [];
        foreach ($query->selectRaw('type_id, status, COUNT(*) AS n')
            ->groupBy('type_id', 'status')->get() as $row) {
            $existing[(int) $row->type_id][$row->status] = (int) $row->n;
        }

        // Di dalam satu bulan target volume penuh tidak masuk akal; yang
        // dijaga cuma minimal per kombinasi.
        $windowed = $from !== null;

        $work = [];

        foreach (self::CR_TARGET as $typeName => $targets) {
            $typeId = (int) (array_search($typeName, $this->types, true) ?: 0);

            // Tipe yang tidak ada di master dilewati, jangan ditempel ke tipe
            // lain - nama RequestType yang menentukan perilaku Cash Advance.
            if ($typeId === 0) {
                $this->command?->warn("RequestType \"{$typeName}\" tidak ada, dilewati.");
                continue;
            }

            foreach ($targets as $plannedStatus => $target) {
                $status = self::PLAN_TO_STATUS[$plannedStatus] ?? $plannedStatus;
                $have   = $existing[$typeId][$status] ?? 0;
                $need   = ($windowed ? self::MIN_PER_COMBINATION : max(self::MIN_PER_COMBINATION, $target)) - $have;

                for ($i = 0; $i < $need; $i++) {
                    $work[] = [$typeId, $plannedStatus];
                }
            }
        }

        return $work;
    }

    private function seedCostRequests(array $plans, array $workload): array
    {
        if (empty($plans)) {
            return [];
        }

        $statuses = $workload;
        shuffle($statuses);

        $crs     = [];
        $codeSeq = $this->existingCodeSeq("budget_request");

        foreach ($statuses as [$typeId, $plannedStatus]) {
            // Budget yang baru aktif kemarin tidak punya ruang untuk rantai
            // approval -> batch -> settlement; coba budget lain, jangan
            // diam-diam membuang CR-nya.
            for ($try = 0; $try < 12; $try++) {
                $planIdx = array_rand($plans);
                $cr      = $this->buildCostRequest($plans[$planIdx], $plannedStatus, $typeId, $codeSeq);

                if ($cr === null) {
                    continue;
                }

                // Akumulasi pemakaian budget diteruskan ke CR berikutnya di plan
                // yang sama — dasar perhitungan NOB & "Used" per sub-item.
                $plans[$planIdx] = $cr['plan'];
                $crs[] = $cr;
                break;
            }
        }

        // Kode CR mengikuti urutan tanggal, seperti penomoran aplikasi.
        usort($crs, fn ($a, $b) => $a['created']->timestamp <=> $b['created']->timestamp);

        foreach ($crs as $i => $cr) {
            $prefix = 'CR-' . $cr['created']->format('m') . $cr['created']->format('y')
                . str_pad($cr['sourceCode'], 4, '0', STR_PAD_LEFT)
                . str_pad($cr['clusterCode'], 4, '0', STR_PAD_LEFT);

            $seq = ($codeSeq[$prefix] = ($codeSeq[$prefix] ?? 0) + 1);
            $crs[$i]['row']['code'] = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        }

        return $crs;
    }

    private function buildCostRequest(array $plan, string $plannedStatus, int $typeId, array &$codeSeq): ?array
    {
        // Tipe sudah ditentukan pemanggil (CR_TARGET), bukan diundi di sini:
        // kombinasi tipe x status yang sah ikut aturan getAllowedNextStatuses().
        $settlementFlow = in_array($plannedStatus, self::SETTLEMENT_PLAN_STATUSES, true);

        $status = self::PLAN_TO_STATUS[$plannedStatus] ?? $plannedStatus;

        /*
        | Rentang tanggal dibatasi sisa umur budget-nya, bukan dipangkas
        | belakangan: fwd() memotong tanggal yang lewat hari ini menjadi hari
        | ini, jadi memakai rentang tetap 2-220 hari membuat puluhan CR
        | menumpuk di tanggal yang sama persis.
        */
        $runway = (int) $plan['from']->copy()->startOfDay()->diffInDays($this->today);

        if ($this->windowFrom && $this->windowTo) {
            /*
            | Pass bulan berjalan: tanggal dipaksa masuk rentang window, dan
            | syarat runway tidak dipakai - budget-nya memang sudah lama aktif,
            | yang dipendekkan justru rantai approval-nya.
            |
            | Rantai settlement dapat jatah hari lebih banyak (dibuat di awal
            | bulan) supaya tahap-tahapnya tidak menumpuk di tanggal terakhir.
            */
            $start = $this->maxTs($this->windowFrom, $plan['from']->copy()->startOfDay());

            if ($start->gt($this->windowTo)) {
                return null;
            }

            $span  = (int) $start->copy()->startOfDay()->diffInDays($this->windowTo);
            $limit = $settlementFlow ? (int) floor($span * 0.3) : (int) floor($span * 0.8);

            $created = $this->atTimeAfter(
                $start->copy()->startOfDay()->addDays(mt_rand(0, max(0, $limit))),
                $plan['from']
            );
        } else {
            // Rantai settlement butuh belasan minggu (approval -> batch -> bayar ->
            // realisasi -> 3 tahap settle); budget yang baru jalan sebulan tidak
            // mungkin punya CR yang sudah selesai settle.
            if ($runway < ($settlementFlow ? 70 : 3)) {
                return null;
            }

            $maxOffset = max(2, min(220, $runway - ($settlementFlow ? 60 : 0)));

            $created = $this->atTimeAfter(
                $plan['from']->copy()->startOfDay()->addDays(mt_rand(2, $maxOffset)),
                $plan['from']
            );
        }

        // --- rincian pengajuan, dipetakan ke sub-item budget ----------------
        $lineCount = min(count($plan['details']), mt_rand(1, 4));
        $keys      = (array) array_rand($plan['details'], $lineCount);
        $details   = [];

        foreach ($keys as $k) {
            $bd    = $plan['details'][$k];
            $slice = (int) (round($bd['total'] * mt_rand(8, 55) / 100 / 1000) * 1000);
            if ($slice < 100000) {
                $slice = 100000;
            }

            [$amount, $qty] = $this->unitize($slice);
            $total = $amount * $qty;

            // NOB per baris = melebihi sisa sub-item budget SAAT pengajuan dibuat
            // (BudgetPlanDetail::remainingAmount(), hanya CR yang sudah cair).
            $remaining = $bd['total'] - $bd['used'];
            $rowNob    = $total > $remaining;

            $details[] = [
                'budget_detail_id' => $bd['id'],
                'account_id'       => $bd['account_id'],
                'description'      => $bd['deskripsi'],
                'amount'           => $amount,
                'qty'              => $qty,
                'total'            => $total,
                'notes'            => $this->pick([
                    'Sesuai kebutuhan lapangan minggu ini.',
                    'Harga mengacu penawaran vendor terlampir.',
                    'Untuk kebutuhan 2 site sekaligus.',
                    'Termasuk ongkos kirim ke lokasi.',
                ]),
                'is_nob'     => $rowNob,
                'nob_amount' => $rowNob ? max(0, $total - $remaining) : 0,
                'status'     => 'INCLUDED',
                '_planKey'   => $k,
            ];
        }

        // Satu item di-takeout oleh salah satu tahap approval — item ini tidak
        // ikut nilai pengajuan (BudgetRequest::recalculateAmount()).
        $takeoutStages = ['SUBMITTED' => 'head', 'CHECKED' => 'finance', 'VERIFIED' => 'approver'];
        $passedStages  = $this->stagesPassed($status);
        if (count($details) > 1 && $passedStages && $this->chance(12)) {
            $stage  = $this->pick($passedStages);
            $actor  = $this->actor($takeoutStages[$stage]);
            $idx    = count($details) - 1;

            $details[$idx]['status']          = 'TAKEOUT';
            $details[$idx]['takeout_by']      = $actor;
            $details[$idx]['takeout_by_name'] = $this->userNames[$actor] ?? null;
            $details[$idx]['takeout_role']    = $this->userRoles[$actor] ?? null;
            $details[$idx]['takeout_stage']   = $stage;
            $details[$idx]['takeout_notes']   = $this->pick([
                'Item ini sudah tercover pengajuan sebelumnya.',
                'Belum urgent, ditunda ke pengajuan berikutnya.',
                'Harga perlu dinegosiasi ulang dengan vendor.',
            ]);
        }

        $amount = 0;
        foreach ($details as $d) {
            if ($d['status'] !== 'TAKEOUT') {
                $amount += $d['total'];
            }
        }

        // NOB header = akumulasi CR COMMITTED pada budget ini + pengajuan ini.
        $committed = $plan['committed'];
        $isNob     = ($committed + $amount) > $plan['amount'];
        $nobAmount = max(0, ($committed + $amount) - $plan['amount']);

        if (in_array($status, BudgetConsumption::COMMITTED, true)) {
            $plan['committed'] = $committed + $amount;
        }

        // "Used" per sub-item baru bertambah kalau CR-nya sudah cair.
        if (in_array($status, BudgetConsumption::DISBURSED, true)) {
            foreach ($details as $d) {
                if ($d['status'] === 'TAKEOUT') {
                    continue;
                }
                $plan['details'][$d['_planKey']]['used'] += $d['total'];
            }
        }

        $isCashAdvance = $typeId === $this->cashAdvanceTypeId();
        $applicant     = $isCashAdvance ? $this->actor('manager') : $this->actor('manager', 'staff', 'head');

        $row = [
            'code'           => 'TEMP',
            'description'    => $this->pick(self::CR_DESCRIPTIONS),
            'amount'         => $amount,
            'is_nob'         => $isNob ? 1 : 0,
            'nob_amount'     => $nobAmount,
            'notes'          => $this->chance(40) ? $this->pick([
                'Mohon segera diproses, tim sudah standby di lokasi.',
                'Dana ditransfer ke rekening penerima terlampir.',
                'Pengajuan untuk kebutuhan minggu depan.',
                'Sudah dikoordinasikan dengan PM proyek.',
            ]) : null,
            'email'          => $this->chance(35)
                ? strtolower(str_replace(' ', '.', $this->userNames[$applicant] ?? 'user')) . '@terex-oms.com'
                : null,
            'applicant_id'   => $applicant,
            'beneficiary_id' => $this->pick($this->beneficiaryIds),
            'type_id'        => $typeId,
            'cluster_id'     => null,
            'budget_plan_id' => $plan['id'],
            'status'         => $status,
        ];

        $clusterCode = '0000';
        if ($plan['isProject']) {
            $clusterId = $this->pick(array_keys($this->clusters));
            $row['cluster_id'] = $clusterId;
            $clusterCode = $this->clusters[$clusterId];
        }

        return [
            'row'           => $row,
            'details'       => $details,
            'plan'          => $plan,
            'plannedStatus' => $plannedStatus,
            'status'        => $status,
            'amount'        => $amount,
            'isCashAdvance' => $isCashAdvance,
            'applicant'     => $applicant,
            'created'       => $created,
            'sourceCode'    => $plan['sourceCode'],
            'clusterCode'   => $clusterCode,
        ];
    }

    /** Tahap approval mana saja yang sudah dilewati CR dengan status ini. */
    private function stagesPassed(string $status): array
    {
        $order = ['DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED'];
        $idx   = array_search($status, $order, true);

        if ($idx === false) {
            return ['SUBMITTED', 'CHECKED', 'VERIFIED']; // sudah lewat semua
        }

        return array_slice($order, 1, max(0, $idx));
    }

    /*
    |--------------------------------------------------------------------------
    | TREASURY (Daily Transaction) + penulisan CR
    |--------------------------------------------------------------------------
    | CR baru bisa QUEUED/PAID lewat batch, jadi tanggal batch-lah yang
    | menentukan paid_at tiap CR di dalamnya — batch disusun dulu, baru
    | tanggal lanjutan CR diturunkan darinya.
    */

    private function seedTreasury(array $crs, int $topUps = 0): void
    {
        // 1) Garis waktu sampai APPROVED (belum menyentuh batch).
        foreach ($crs as $i => $cr) {
            $crs[$i]['ts'] = $this->approvalTimeline($cr);
        }

        // 2) Kelompokkan CR ke batch menurut nasib akhirnya.
        $groups = ['PAID' => [], 'ACTIVE' => [], 'REJECTED' => [], 'TAKEOUT' => []];

        foreach ($crs as $i => $cr) {
            $target = match (true) {
                in_array($cr['plannedStatus'], self::SETTLEMENT_PLAN_STATUSES, true)
                || $cr['plannedStatus'] === 'CLOSED_DIRECT'                          => 'PAID',
                $cr['plannedStatus'] === 'QUEUED'                                   => 'ACTIVE',
                $cr['plannedStatus'] === 'APPROVED' && $this->chance(45)            => 'REJECTED',
                $cr['plannedStatus'] === 'REJECTED' && $this->chance(35)            => 'TAKEOUT',
                default                                                             => null,
            };

            if ($target) {
                $groups[$target][] = $i;
            }
        }

        $batches = [];
        foreach ($groups as $kind => $idxs) {
            usort($idxs, fn ($a, $b) => $crs[$a]['ts']['approved']->timestamp <=> $crs[$b]['ts']['approved']->timestamp);

            while ($idxs) {
                $chunk = array_splice($idxs, 0, $kind === 'TAKEOUT' ? 1 : $this->pick([2, 3, 3, 4, 5, 6]));
                $batches[] = ['kind' => $kind, 'members' => $chunk];
            }
        }

        // Batch TAKEOUT = CR yang ditolak CEO saat batch masih berjalan; item
        // lain di batch itu tetap INCLUDED, jadi digabung ke batch aktif.
        $batches = $this->mergeTakeoutBatches($batches, $crs);

        // 3) Tanggal batch + turunan tanggal CR sesudah pembayaran.
        foreach ($batches as $i => $batch) {
            $latest = null;
            foreach ($batch['members'] as $idx) {
                $latest = $this->maxTs($crs[$idx]['ts']['approved'], $latest);
            }
            foreach ($batch['takeouts'] ?? [] as $idx) {
                $latest = $this->maxTs($crs[$idx]['ts']['approved'], $latest);
            }

            $createdTs = $this->step($latest, 0, 6);
            $status    = $this->batchStatusFor($batch['kind']);

            $approvedTs = in_array($status, ['DONE', 'PAID', 'REJECTED']) ? $this->step($createdTs, 1, 5) : null;
            $paidTs     = $status === 'PAID' ? $this->step($approvedTs, 1, 8) : null;

            $batches[$i] += [
                'status'     => $status,
                'createdTs'  => $createdTs,
                'approvedTs' => $approvedTs,
                'paidTs'     => $paidTs,
            ];

            foreach ($batch['members'] as $idx) {
                $crs[$idx]['ts']['queued'] = $createdTs;
                if ($paidTs) {
                    $crs[$idx]['ts']['paid'] = $paidTs;
                }
            }
            foreach ($batch['takeouts'] ?? [] as $idx) {
                $crs[$idx]['ts']['queued']     = $createdTs;
                $crs[$idx]['ts']['rejectedAt'] = $approvedTs ?? $this->step($createdTs, 1, 5);
            }
        }

        // 4) Tulis CR (+ rincian + log), lalu batch (+ item).
        $ids = $this->persistCostRequests($crs, $batches);
        $this->persistBatches($batches, $crs, $ids, $topUps);
    }

    /**
     * Batch yang isinya cuma CR takeout tidak realistis — item takeout selalu
     * berdampingan dengan item lain yang tetap diproses. Digabung ke batch aktif.
     */
    private function mergeTakeoutBatches(array $batches, array $crs): array
    {
        $takeouts = [];
        $rest     = [];

        foreach ($batches as $b) {
            if ($b['kind'] === 'TAKEOUT') {
                $takeouts = array_merge($takeouts, $b['members']);
            } else {
                $rest[] = $b;
            }
        }

        $hosts = array_keys(array_filter($rest, fn ($b) => $b['kind'] === 'ACTIVE'));

        foreach ($takeouts as $i => $idx) {
            if (empty($hosts)) {
                break;
            }
            $host = $hosts[$i % count($hosts)];
            $rest[$host]['takeouts'][] = $idx;
        }

        return $rest;
    }

    private function batchStatusFor(string $kind): string
    {
        return match ($kind) {
            'PAID'     => 'PAID',
            'REJECTED' => 'REJECTED',
            default    => $this->pick(['PENDING_APPROVER', 'PENDING_APPROVER', 'DONE', 'DONE', 'DONE']),
        };
    }

    /** Tanggal tiap tahap approval CR, menaik dari tanggal dibuat. */
    private function approvalTimeline(array $cr): array
    {
        $order = ['DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'APPROVED'];
        $status = $cr['status'];

        $level = match (true) {
            $status === 'REJECTED', $status === 'CANCELLED' => 3,
            in_array($status, $order, true)                 => (int) array_search($status, $order, true),
            default                                         => 4, // QUEUED ke atas
        };

        $ts = ['created' => $cr['created'], 'submitted' => null, 'checked' => null, 'verified' => null, 'approved' => null];
        $cursor = $cr['created'];

        if ($level >= 1) {
            $cursor = $this->step($cursor, 0, 4);
            $ts['submitted'] = $cursor;
        }
        if ($level >= 2) {
            $cursor = $this->step($cursor, 1, 5);
            $ts['checked'] = $cursor;
        }
        if ($level >= 3) {
            $cursor = $this->step($cursor, 1, 5);
            $ts['verified'] = $cursor;
        }
        if ($level >= 4) {
            $cursor = $this->step($cursor, 1, 6);
            $ts['approved'] = $cursor;
        }

        $ts['approved'] = $ts['approved'] ?? $cursor;

        return $ts;
    }

    /** @return array<int,int> index CR -> id baris budget_request */
    private function persistCostRequests(array $crs, array $batches): array
    {
        $ids = [];

        foreach ($crs as $i => $cr) {
            $ts     = $cr['ts'];
            $status = $cr['status'];
            $row    = $cr['row'];

            $paidAt     = null;
            $realizedAt = null;
            $closedAt   = null;
            $logs       = [];

            $applicant = $cr['applicant'];
            $log = function (?string $from, string $to, string $action, ?string $notes, int $by, Carbon $at) use (&$logs) {
                $logs[] = [
                    'status_from' => $from,
                    'status_to'   => $to,
                    'action_type' => $action,
                    'notes'       => $notes,
                    'acted_by'    => $by,
                    'actor_name'  => $this->userNames[$by] ?? 'System',
                    'actor_role'  => $this->userRoles[$by] ?? 'system',
                    'created_at'  => $at->toDateTimeString(),
                ];
            };

            $log('DRAFT', 'DRAFT', 'created', null, $applicant, $ts['created']);

            if ($ts['submitted']) {
                $log('DRAFT', 'SUBMITTED', 'submitted', null, $applicant, $ts['submitted']);
            }
            if ($ts['checked']) {
                $log('SUBMITTED', 'CHECKED', 'checked', null, $this->actor('head'), $ts['checked']);
            }
            if ($ts['verified']) {
                $log('CHECKED', 'VERIFIED', 'verified', null, $this->actor('finance', 'cost_control'), $ts['verified']);
            }

            // --- ditolak / dibatalkan ---------------------------------------
            if (in_array($status, ['REJECTED', 'CANCELLED'])) {
                $rejectedAt = $ts['rejectedAt'] ?? $this->step($ts['verified'] ?? $ts['created'], 1, 6);
                $from       = isset($ts['rejectedAt']) ? 'QUEUED' : $this->pick(['SUBMITTED', 'CHECKED', 'VERIFIED']);
                $reason     = $this->pick([
                    'Budget sub-item sudah habis, ajukan revisi nilai.',
                    'Lampiran bukti pendukung belum lengkap.',
                    'Nominal tidak sesuai kesepakatan dengan vendor.',
                    'Pengajuan ganda dengan CR sebelumnya.',
                    'Rekening penerima tidak sesuai data beneficiary.',
                ]);

                $log($from, 'REJECTED', isset($ts['rejectedAt']) ? 'ceo_rejected' : 'rejected',
                    $reason, $this->actor('head', 'finance', 'approver'), $rejectedAt);

                if ($status === 'CANCELLED') {
                    $cancelAt = $this->step($rejectedAt, 1, 10);
                    $log('REJECTED', 'CANCELLED', 'cancelled', 'Pengajuan dibatalkan oleh pengaju.', $applicant, $cancelAt);
                }
            } else {
                if ($ts['approved'] && ! in_array($status, ['DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED'])) {
                    $log('VERIFIED', 'APPROVED', 'approved', null, $this->actor('approver', 'finance_manager'), $ts['approved']);
                }

                if (isset($ts['queued'])) {
                    $log('APPROVED', 'QUEUED', 'batched', 'Masuk batch pembayaran Treasury.',
                        $this->actor('treasury', 'finance'), $ts['queued']);
                }

                if (isset($ts['paid'])) {
                    $paidAt = $ts['paid'];

                    if ($cr['isCashAdvance']) {
                        $log('QUEUED', 'PAID', 'paid', 'Dana ditransfer ke penerima.', $this->actor('treasury', 'finance'), $paidAt);
                    } else {
                        $closedAt = $paidAt;
                        $log('QUEUED', 'CLOSED', 'paid', 'Dana ditransfer, CR langsung ditutup (bukan Cash Advance).',
                            $this->actor('treasury', 'finance'), $paidAt);
                    }
                }
            }

            $crs[$i]['paidAt'] = $paidAt;

            // --- settlement (khusus Cash Advance) ---------------------------
            $realizations = [];
            $secondPayment = null;

            if (in_array($cr['plannedStatus'], self::SETTLEMENT_PLAN_STATUSES, true)
                && $cr['plannedStatus'] !== 'PAID') {
                $realizedAt   = $this->step($paidAt, 3, 30);
                /*
                | Cabang Expense Claim WAJIB realisasi melebihi pengajuan -
                | tanpa selisih, SETTLE_APPROVED/SECOND_APPROVED tidak pernah
                | bisa dicapai (BudgetRequest::getAllowedNextStatuses() hanya
                | membuka cabang itu kalau expenseClaimAmount() > 0).
                |
                | Sebaliknya CLOSED_SETTLED harus TIDAK melebihi: dengan klaim
                | > 0, jalan sahnya SETTLE_VERIFIED -> SETTLE_APPROVED, bukan
                | langsung CLOSED.
                */
                $claimFlow = in_array($cr['plannedStatus'], self::CLAIM_PLAN_STATUSES, true);
                $overage   = match (true) {
                    $claimFlow                                 => true,
                    $cr['plannedStatus'] === 'CLOSED_SETTLED'  => false,
                    default                                    => $this->chance(55),
                };
                $realizations = $this->buildRealizations($cr, $overage);
                $totalReal    = array_sum(array_column($realizations, 'total'));

                $log('PAID', 'REALIZED', 'realization_submitted',
                    'Total realisasi: ' . number_format($totalReal, 0, ',', '.'),
                    $applicant, $realizedAt);

                $cursor = $realizedAt;

                if ($cr['plannedStatus'] !== 'REALIZED') {
                    $cursor = $this->step($cursor, 1, 7);
                    $log('REALIZED', 'SETTLE_CHECKED', 'settlement_checked', null, $this->actor('head'), $cursor);
                }
                if (! in_array($cr['plannedStatus'], ['REALIZED', 'SETTLE_CHECKED'], true)) {
                    $cursor = $this->step($cursor, 1, 7);
                    $verifiedAt = $cursor;
                    $log('SETTLE_CHECKED', 'SETTLE_VERIFIED', 'settlement_verified', null,
                        $this->actor('finance', 'cost_control'), $cursor);

                    // Selisih di atas nilai pengajuan jadi Second Payment.
                    if ($totalReal > $cr['amount']) {
                        /*
                        | Pada cabang klaim, selisihnya BELUM dibayar: CR masih
                        | menunggu approve (SETTLE_APPROVED) atau menunggu
                        | Treasury membuat batch (SECOND_APPROVED), jadi
                        | Second Payment-nya tetap PENDING.
                        */
                        $paid     = $claimFlow ? false : $this->chance(35);
                        $spPaidAt = $paid ? $this->step($verifiedAt, 1, 10) : null;

                        $secondPayment = [
                            'amount'     => $totalReal - $cr['amount'],
                            'status'     => $paid ? 'PAID' : 'PENDING',
                            'paid_by'    => $paid ? $this->actor('treasury', 'finance') : null,
                            'paid_at'    => $spPaidAt?->toDateTimeString(),
                            'notes'      => $paid ? 'Pelunasan selisih settlement.' : null,
                            'created_at' => $verifiedAt->toDateTimeString(),
                            'updated_at' => ($spPaidAt ?? $verifiedAt)->toDateTimeString(),
                        ];

                        if ($spPaidAt) {
                            $cursor = $spPaidAt;
                        }
                    }

                    // Approve klaim dua lapis: Finance Manager lalu Approver.
                    if ($claimFlow) {
                        $cursor = $this->step($cursor, 1, 6);
                        $log('SETTLE_VERIFIED', 'SETTLE_APPROVED', 'settlement_approved',
                            'Expense Claim disetujui Finance Manager.',
                            $this->actor('finance_manager'), $cursor);
                    }
                    if ($cr['plannedStatus'] === 'SECOND_APPROVED') {
                        $cursor = $this->step($cursor, 1, 6);
                        $log('SETTLE_APPROVED', 'SECOND_APPROVED', 'second_approved',
                            'Pembayaran selisih settlement disetujui Approver.',
                            $this->actor('approver'), $cursor);
                    }
                }
                if ($cr['plannedStatus'] === 'CLOSED_SETTLED') {
                    $cursor   = $this->step($cursor, 1, 10);
                    $closedAt = $cursor;
                    $log('SETTLE_VERIFIED', 'CLOSED', 'settlement_closed', 'Settlement disetujui dan ditutup.',
                        $this->actor('finance_manager'), $cursor);
                }
            }

            $row['submitted_at'] = $ts['submitted']?->toDateTimeString();
            $row['approved_at']  = in_array($status, ['DRAFT', 'SUBMITTED', 'CHECKED', 'VERIFIED', 'REJECTED', 'CANCELLED'])
                ? null : $ts['approved']?->toDateTimeString();
            $row['paid_at']      = $paidAt?->toDateTimeString();
            $row['realized_at']  = $realizedAt?->toDateTimeString();
            $row['closed_at']    = $closedAt?->toDateTimeString();
            $row['created_at']   = $ts['created']->toDateTimeString();
            $row['updated_at']   = end($logs)['created_at'];

            $crId    = DB::table('budget_request')->insertGetId($row);
            $ids[$i] = $crId;

            $detailIds = [];
            foreach ($cr['details'] as $d) {
                unset($d['_planKey']);
                $detailIds[] = DB::table('request_detail')->insertGetId($d + [
                    'budget_request_id' => $crId,
                    'takeout_at'        => isset($d['takeout_by']) ? $ts['submitted']?->toDateTimeString() : null,
                    'created_at'        => $row['created_at'],
                    'updated_at'        => $row['updated_at'],
                ]);
            }

            foreach ($logs as $l) {
                DB::table('request_status_logs')->insert($l + ['budget_request_id' => $crId]);
            }

            foreach ($realizations as $j => $r) {
                unset($r['_detailIdx']);
                DB::table('budget_request_realizations')->insert($r + [
                    'budget_request_id' => $crId,
                    'request_detail_id' => $detailIds[$realizations[$j]['_detailIdx'] ?? 0] ?? null,
                    'created_at'        => $realizedAt?->toDateTimeString() ?? $row['created_at'],
                    'updated_at'        => $realizedAt?->toDateTimeString() ?? $row['created_at'],
                ]);
            }

            if ($secondPayment) {
                DB::table('budget_request_second_payments')->insert($secondPayment + ['budget_request_id' => $crId]);
            }
        }

        return $ids;
    }

    /** Baris realisasi — satu per rincian CR yang masih INCLUDED. */
    private function buildRealizations(array $cr, bool $overage): array
    {
        $included = [];
        foreach ($cr['details'] as $idx => $d) {
            if ($d['status'] !== 'TAKEOUT') {
                $included[$idx] = $d;
            }
        }

        // Realisasi di atas/di bawah nilai pengajuan — dasar Second Payment (lebih)
        // atau refund (kurang).
        $factor = $overage ? mt_rand(104, 135) : mt_rand(62, 99);
        $target = (int) (round($cr['amount'] * $factor / 100 / 1000) * 1000);
        $parts  = $this->splitEven($target, count($included));

        $rows = [];
        $i    = 0;
        foreach ($included as $idx => $d) {
            $amount = max(1000, $parts[$i]);

            $rows[] = [
                '_detailIdx'  => $idx,
                'description' => $d['description'],
                'account_id'  => $d['account_id'],
                'amount'      => $amount,
                'qty'         => 1,
                'total'       => $amount,
                'notes'       => $this->chance(50) ? $this->pick([
                    'Bukti nota terlampir.',
                    'Realisasi sesuai pemakaian lapangan.',
                    'Sisa dana dikembalikan ke kas.',
                    'Ada tambahan biaya tak terduga di lokasi.',
                ]) : null,
            ];
            $i++;
        }

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | BATCH TREASURY
    |--------------------------------------------------------------------------
    */

    private function persistBatches(array $batches, array $crs, array $ids, int $topUps = 0): void
    {
        // Top Up tidak punya CR — batch transfer antar rekening sendiri.
        // Jumlahnya KEKURANGAN terhadap TOP_UP_TARGET, bukan selalu 22.
        for ($i = 1; $i <= $topUps; $i++) {
            $batches[] = [
                'kind'      => 'TOP_UP',
                'members'   => [],
                'status'    => $this->pick(['PAID', 'PAID', 'PAID', 'DONE', 'PENDING_APPROVER', 'REJECTED']),
                'createdTs' => $this->atTimeAfter($this->today->copy()->subDays(mt_rand(5, 330))),
                'topUp'     => (int) (round(mt_rand(50000000, 900000000) / 1000000) * 1000000),
            ];
        }

        usort($batches, fn ($a, $b) => $a['createdTs']->timestamp <=> $b['createdTs']->timestamp);

        // Nomor urut per bulan dilanjutkan dari batch yang sudah ada, bukan
        // dari 0 - kode daily_transactions unique.
        $seq = $this->existingCodeSeq('daily_transactions', 4, 6);

        foreach ($batches as $batch) {
            $createdTs = $batch['createdTs'];
            $isTopUp   = $batch['kind'] === 'TOP_UP';
            $status    = $batch['status'];

            if ($isTopUp) {
                $approvedTs = in_array($status, ['DONE', 'PAID', 'REJECTED']) ? $this->step($createdTs, 1, 4) : null;
                $paidTs     = $status === 'PAID' ? $this->step($approvedTs, 1, 5) : null;
            } else {
                $approvedTs = $batch['approvedTs'];
                $paidTs     = $batch['paidTs'];
            }

            $included = $batch['members'];
            $takeouts = $batch['takeouts'] ?? [];

            // Batch REJECTED: semua item ditolak, CR dikembalikan ke APPROVED —
            // captured_amount TIDAK dihitung ulang (lihat rejectBatch()).
            $captured = $isTopUp
                ? $batch['topUp']
                : array_sum(array_map(fn ($i) => $crs[$i]['amount'], $included));

            $count = $isTopUp ? 0 : count($included);
            $key   = $createdTs->format('Ym');
            $n     = ($seq[$key] = ($seq[$key] ?? 0) + 1);

            $code = $createdTs->format('Ymd')
                . str_pad((string) $count, 3, '0', STR_PAD_LEFT)
                . str_pad((string) $n, 4, '0', STR_PAD_LEFT);

            $batchId = DB::table('daily_transactions')->insertGetId([
                'code'              => $code,
                'date'              => $createdTs->toDateString(),
                'description'       => $isTopUp
                    ? 'TOP UP OPERASIONAL ' . $createdTs->translatedFormat('M Y')
                    : strtoupper($this->pick([
                        'PEMBAYARAN COST REQUEST BATCH',
                        'PENCAIRAN CASH ADVANCE TIM PROYEK',
                        'PEMBAYARAN REIMBURSEMENT KARYAWAN',
                        'PENCAIRAN PETTYCASH OPERASIONAL',
                        'PEMBAYARAN VENDOR DAN SUPPLIER',
                    ])),
                'type'              => $isTopUp ? 'TOP_UP' : 'CASH_OUT',
                'source_fund_id'    => $isTopUp ? self::MAIN_FUND : $this->pick(self::OPERATIONAL_FUNDS),
                'to_source_fund_id' => $isTopUp ? $this->pick(self::OPERATIONAL_FUNDS) : null,
                'charge'            => $paidTs ? (int) (round(mt_rand(0, 150000) / 2500) * 2500) : 0,
                'captured_amount'   => $captured,
                'status'            => $status,
                'notes'             => $this->chance(35) ? $this->pick([
                    'Transfer dilakukan pagi hari setelah cut-off bank.',
                    'Mohon dicek kembali nomor rekening penerima.',
                    'Batch gabungan beberapa proyek.',
                ]) : null,
                'created_by'        => $this->actor('treasury', 'finance'),
                'approved_by'       => $approvedTs ? $this->actor('approver') : null,
                'approved_at'       => $approvedTs?->toDateTimeString(),
                'paid_by'           => $paidTs ? $this->actor('treasury', 'finance') : null,
                'paid_at'           => $paidTs?->toDateTimeString(),
                'payment_notes'     => $paidTs && $this->chance(45) ? 'Transfer berhasil, bukti terlampir.' : null,
                'created_at'        => $createdTs->toDateTimeString(),
                'updated_at'        => ($paidTs ?? $approvedTs ?? $createdTs)->toDateTimeString(),
            ]);

            foreach ($included as $idx) {
                DB::table('daily_transaction_items')->insert([
                    'daily_transaction_id' => $batchId,
                    'budget_request_id'    => $ids[$idx],
                    // Batch ditolak -> seluruh itemnya ikut REJECTED.
                    'status'               => $status === 'REJECTED' ? 'REJECTED' : 'INCLUDED',
                    'notes'                => $status === 'REJECTED'
                        ? 'Batch ditolak Approver, CR dikembalikan ke status Approved.' : null,
                    'created_at'           => $createdTs->toDateTimeString(),
                    'updated_at'           => ($approvedTs ?? $createdTs)->toDateTimeString(),
                ]);
            }

            foreach ($takeouts as $idx) {
                DB::table('daily_transaction_items')->insert([
                    'daily_transaction_id' => $batchId,
                    'budget_request_id'    => $ids[$idx],
                    'status'               => 'REJECTED',
                    'notes'                => 'Di-takeout Approver dari batch ini.',
                    'created_at'           => $createdTs->toDateTimeString(),
                    'updated_at'           => ($approvedTs ?? $createdTs)->toDateTimeString(),
                ]);
            }
        }
    }

    /**
     * Penghitung nomor dokumen — lihat catatan yang sama di DummyChainSeeder.
     */
    private function syncSequences(): void
    {
        $seq = [];

        foreach (DB::table('budget_request')->selectRaw("SUBSTRING(code, 1, 19) p, COUNT(*) c")
            ->groupBy('p')->get() as $r) {
            $seq['cr:' . $r->p] = (int) $r->c;
        }
        foreach (DB::table('daily_transactions')
            ->selectRaw("DATE_FORMAT(created_at, '%Y%m') p, COUNT(*) c")->groupBy('p')->get() as $r) {
            $seq['daily-transaction:' . $r->p] = (int) $r->c;
        }

        foreach ($seq as $key => $counter) {
            DB::table('document_sequences')->updateOrInsert(
                ['key' => $key],
                ['counter' => $counter, 'updated_at' => $this->now, 'created_at' => $this->now],
            );
        }
    }
}

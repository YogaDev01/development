<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data dummy untuk analisa: rantai penuh Purchase Order -> No. PO/BAST ->
 * Invoice -> Surat Jalan -> Collection (Cash Receipt).
 *
 * Bersifat MENAMBAH (tidak menghapus data yang sudah ada). Semua nilai turunan
 * dihitung sendiri di sini - subtotal/PPN/captured_total invoice, value PO dari
 * SUM(nominal) No. PO, status invoice dari akumulasi Cash Receipt - supaya
 * hasilnya sama persis dengan yang dihitung aplikasi saat data diinput lewat
 * UI. Penulisan lewat query builder (bukan Eloquent) supaya hook model tidak
 * ikut jalan dan mengubah status yang sudah sengaja disusun di sini.
 */
class DummyChainSeeder extends Seeder
{
    private Carbon $today;
    private string $now;

    /** @var array<int,string> id customer => initial */
    private array $customers = [];
    private array $customerIds = [];
    private array $clusterIds = [];
    /** @var array<string,int> nama stage => id */
    private array $stage = [];
    private array $implStages = [];
    /** @var array<string,array<int>> nama role => id user */
    private array $actors = [];
    /** @var array<int,string> id user => nama */
    private array $userNames = [];
    private array $userRoles = [];

    /** "PT TEREX" - satu-satunya Source Fund yang lolos filter Invoice/Cash Receipt. */
    private const SOURCE_FUND_ID = 1;

    private const SCOPES = [
        'FTTH DEPLOYMENT', 'COLLOCATION TOWER', 'DISMANTLE & RELOKASI', 'SWAP POLE',
        'PLTS HYBRID', 'INSTALASI ODC', 'MANAGE SERVICE', 'FABRIKASI PAGAR BRC',
        'CCTV SURVEILLANCE', 'ERECTION TOWER', 'MAINTENANCE PREVENTIF',
        'PENARIKAN KABEL FO', 'INSTALASI GATE BARRIER', 'REINSTALL ANTENNA',
        'SURVEY & TSSR', 'RENEWAL PERANGKAT', 'BOOMRING MOUNTING', 'INSTALASI TRISULA',
    ];

    private const AREAS = [
        'JABODETABEK', 'BANDUNG RAYA', 'SEMARANG', 'SURABAYA', 'MEDAN', 'MAKASSAR',
        'BALI', 'PALEMBANG', 'YOGYAKARTA', 'BALIKPAPAN', 'PEKANBARU', 'BATAM',
        'MALANG', 'SOLO', 'LAMPUNG', 'PONTIANAK', 'MANADO', 'JAMBI',
    ];

    private const ITEM_DESCRIPTIONS = [
        ['Jasa Instalasi & Commissioning', 'site'],
        ['Material Kabel Fiber Optic 24 Core', 'meter'],
        ['Mobilisasi Tim & Peralatan', 'team'],
        ['Jasa Survey dan TSSR', 'site'],
        ['Pengadaan Material Pendukung', 'lot'],
        ['Jasa Pemasangan Pole 7 Meter', 'unit'],
        ['Biaya Perizinan & Koordinasi Warga', 'lumpsum'],
        ['Jasa Penarikan Kabel Distribusi', 'meter'],
        ['Pekerjaan Sipil & Pondasi', 'lot'],
        ['Jasa Integrasi & Testing OTDR', 'site'],
        ['Sewa Alat Berat', 'hari'],
        ['Jasa Maintenance Preventif Bulanan', 'bulan'],
        ['Pengadaan Panel Surya 450 WP', 'pcs'],
        ['Jasa Dismantle & Pengembalian Material', 'site'],
        ['Fabrikasi & Painting Rangka', 'kg'],
    ];

    private const TERMS = [
        'Net 14' => 14, 'Net 30' => 30, 'Net 45' => 45, 'Net 60' => 60,
        '30 Hari Setelah BAST' => 30, '45 Hari Setelah Invoice Diterima' => 45,
    ];

    private const ROMAN = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /** Status milestone (project_details) -> status kolom header monitoring. */
    private const MONITORING_MAP = [
        'NOT_STARTED' => 'NOT_YET',
        'ON_GOING'    => 'ON_GOING',
        'DONE'        => 'DONE',
    ];

    /*
    |--------------------------------------------------------------------------
    | ENTRY POINT
    |--------------------------------------------------------------------------
    */

    public function run(): void
    {
        mt_srand(20260918); // hasil dapat diulang persis
        $this->today = Carbon::parse('2026-09-18')->startOfDay();
        $this->now   = now()->toDateTimeString();

        DB::transaction(function () {
            $this->loadMaster();
            $this->seedUsers();

            $projects = $this->buildProjects();
            $this->persist($projects);
            $this->syncSequences();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA
    |--------------------------------------------------------------------------
    */

    private function loadMaster(): void
    {
        $this->customers   = DB::table('customers')->whereNotNull('initial')->pluck('initial', 'id')->toArray();
        $this->customerIds = array_keys($this->customers);

        $this->clusterIds = DB::table('clusters')->where('status', 'ACTIVE')->pluck('id')->toArray();

        $stages = DB::table('stages')->pluck('id', 'name')->toArray();
        foreach (['PO Received', 'BAST', 'Invoice', 'Payment'] as $name) {
            $this->stage[$name] = $stages[$name];
        }
        foreach (['Survey', 'Implementasi', 'Pengadaan Material', 'Delivery', 'Installation',
                  'Quality Control', 'Drawing', 'ATP', 'Punchlist', 'Monitoring'] as $name) {
            if (isset($stages[$name])) {
                $this->implStages[] = $stages[$name];
            }
        }
    }

    /**
     * User pelaku workflow. Dengan satu user saja (superadmin) semua kolom
     * created_by/verified_by/approved_by jadi seragam dan tidak bisa dianalisa,
     * jadi tiap peran diberi pemerannya sendiri.
     */
    private function seedUsers(): void
    {
        $roles = DB::table('roles')->pluck('id', 'name')->toArray();
        $hash  = Hash::make('password123');

        $spec = [
            ['Andi Nugroho',     'head.andi',      'head'],
            ['Sari Wulandari',   'head.sari',      'head'],
            ['Budi Santoso',     'finance.budi',   'finance'],
            ['Citra Maharani',   'finance.citra',  'finance'],
            ['Dewi Anggraini',   'fm.dewi',        'finance_manager'],
            ['Eka Prasetyo',     'acct.eka',       'accounting'],
            ['Fajar Ramadhan',   'acct.fajar',     'accounting'],
            ['Gita Puspita',     'coll.gita',      'collection'],
            ['Hadi Kurniawan',   'coll.hadi',      'collection'],
            ['Indra Wijaya',     'appr.indra',     'approver'],
            ['Joko Susilo',      'mgr.joko',       'manager'],
            ['Kartika Sari',     'mgr.kartika',    'manager'],
            ['Lina Hapsari',     'mgr.lina',       'manager'],
            ['Rizky Ardiansyah', 'staff.rizky',    'staff'],
        ];

        foreach ($spec as [$name, $username, $role]) {
            if (! isset($roles[$role])) {
                continue;
            }

            $id = DB::table('users')->where('username', $username)->value('id');
            if (! $id) {
                $id = DB::table('users')->insertGetId([
                    'name'              => $name,
                    'username'          => $username,
                    'email'             => $username . '@terex-oms.com',
                    'phone'             => '08' . mt_rand(1111111111, 9999999999),
                    'email_verified_at' => $this->now,
                    'password'          => $hash,
                    'role_id'           => $roles[$role],
                    'status'            => 'ACTIVE',
                    'created_at'        => $this->now,
                    'updated_at'        => $this->now,
                ]);
            }
            $this->actors[$role][] = $id;
        }

        // Cadangan kalau sebuah peran tidak ada di tabel roles.
        $fallback = DB::table('users')->orderBy('id')->value('id');
        foreach (['head', 'finance', 'finance_manager', 'accounting', 'collection', 'approver', 'manager', 'staff'] as $role) {
            if (empty($this->actors[$role])) {
                $this->actors[$role] = [$fallback];
            }
        }

        $this->userNames = DB::table('users')->pluck('name', 'id')->toArray();
        $this->userRoles = DB::table('users')->join('roles', 'users.role_id', '=', 'roles.id')
            ->pluck('roles.name', 'users.id')->toArray();
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

    private function money(int $min, int $max, int $round = 500000): int
    {
        return (int) (round(mt_rand($min, $max) / $round) * $round);
    }

    private function daysAgo(int $min, int $max): Carbon
    {
        return $this->today->copy()->subDays(mt_rand($min, $max));
    }

    /** Maju beberapa hari (tanggal saja), tidak pernah melewati hari ini. */
    private function fwd(Carbon $from, int $min, int $max): Carbon
    {
        $d = $from->copy()->startOfDay()->addDays(mt_rand($min, $max));
        return $d->gt($this->today) ? $this->today->copy() : $d;
    }

    /**
     * Jam kerja acak pada sebuah tanggal, DIJAMIN setelah $after.
     *
     * Tanpa jaminan itu dua tahap yang jatuh di hari yang sama bisa mendapat
     * jam acak yang terbalik (verified 09:00 tapi submitted 15:00), dan setiap
     * laporan yang mengurutkan atau menghitung selisih antar tahap ikut salah.
     */
    private function atTimeAfter(Carbon $date, ?Carbon $after = null): Carbon
    {
        $limit = $this->today->copy()->setTime(23, 45, 0);

        $d = $date->copy()->setTime(mt_rand(8, 17), mt_rand(0, 59), mt_rand(0, 59));

        if ($after && $d->lte($after)) {
            $d = $after->copy()->addMinutes(mt_rand(5, 150));
        }
        if ($d->gt($limit)) {
            $d = $after && $after->lt($limit) ? $limit->copy() : $limit->copy();
        }
        if ($after && $d->lte($after)) {
            $d = $after->copy()->addMinute();
        }

        return $d;
    }

    /** Tahap berikutnya sebuah rantai workflow: maju N hari, jam tetap menaik. */
    private function step(Carbon $cursor, int $minDays, int $maxDays): Carbon
    {
        return $this->atTimeAfter($this->fwd($cursor, $minDays, $maxDays), $cursor);
    }

    /** Pecah nominal jadi beberapa termin yang jumlahnya persis sama dengan total. */
    private function split(int $total, int $parts): array
    {
        if ($parts <= 1) {
            return [$total];
        }

        $patterns = [
            2 => [[30, 70], [40, 60], [50, 50], [25, 75]],
            3 => [[30, 40, 30], [20, 40, 40], [25, 35, 40], [40, 30, 30]],
        ];
        $weights = $this->pick($patterns[$parts]);

        $out = [];
        $sum = 0;
        for ($i = 0; $i < $parts - 1; $i++) {
            $v = (int) (round($total * $weights[$i] / 100 / 1000) * 1000);
            $out[] = $v;
            $sum += $v;
        }
        $out[] = $total - $sum;

        return $out;
    }

    /** Bagi rata dengan sisa dilempar ke bagian terakhir, jumlah tetap persis. */
    private function splitEven(int $total, int $parts): array
    {
        $out = [];
        $sum = 0;
        for ($i = 0; $i < $parts - 1; $i++) {
            $v = (int) (round($total / $parts / 1000) * 1000);
            $out[] = $v;
            $sum  += $v;
        }
        $out[] = $total - $sum;

        return $out;
    }

    /*
    |--------------------------------------------------------------------------
    | PEMBANGUNAN DATA
    |--------------------------------------------------------------------------
    */

    /** Sebaran status PO - menumpuk di tahap tengah/akhir seperti data nyata. */
    private const PROJECT_PLAN = [
        'DRAFT'         => 5,
        'SUBMITTED'     => 5,
        'WAITING_PO'    => 5,
        'ACTIVE'        => 20,
        'PROGRESS_DONE' => 12,
        'PAID'          => 8,
        'CLOSING'       => 5,
        'CLOSED'        => 8,
    ];

    /** Rentang umur PO (hari lalu) per status - yang sudah Closed pasti lebih tua. */
    private const AGE_WINDOW = [
        'DRAFT'         => [3, 45],
        'SUBMITTED'     => [10, 70],
        'WAITING_PO'    => [20, 100],
        'ACTIVE'        => [60, 250],
        'PROGRESS_DONE' => [120, 300],
        'PAID'          => [150, 330],
        'CLOSING'       => [170, 345],
        'CLOSED'        => [200, 360],
    ];

    private function buildProjects(): array
    {
        $nextCode = (int) DB::table('projects')->whereNotNull('code')
            ->orderByRaw('CAST(code AS UNSIGNED) DESC')->value('code');

        $statuses = [];
        foreach (self::PROJECT_PLAN as $status => $count) {
            $statuses = array_merge($statuses, array_fill(0, $count, $status));
        }
        shuffle($statuses);

        $projects = [];
        foreach ($statuses as $status) {
            $projects[] = $this->buildProject($status, str_pad((string) (++$nextCode), 4, '0', STR_PAD_LEFT));
        }

        return $projects;
    }

    private function buildProject(string $status, string $code): array
    {
        [$minAge, $maxAge] = self::AGE_WINDOW[$status];
        $createdTs  = $this->atTimeAfter($this->daysAgo($minAge, $maxAge));
        $customerId = $this->pick($this->customerIds);
        $term       = array_rand(self::TERMS);
        $termDays   = self::TERMS[$term];

        $inputBy   = $this->actor('head', 'finance');
        $managerId = $this->actor('manager');

        // --- garis waktu workflow header ---------------------------------
        $submittedTs  = null;
        $approvedTs   = null;
        $approvedBy   = null;
        $closedTs     = null;
        $closedBy     = null;
        $closingNotes = null;

        if ($status !== 'DRAFT') {
            $submittedTs = $this->step($createdTs, 2, 10);
        }
        if (! in_array($status, ['DRAFT', 'SUBMITTED'])) {
            $approvedTs = $this->step($submittedTs, 1, 8);
            $approvedBy = $this->actor('finance', 'finance_manager');
        }

        // --- No. PO --------------------------------------------------------
        $poCount    = $status === 'WAITING_PO' ? 0 : $this->pick([1, 1, 2, 2, 3, 3]);
        $poStatuses = $this->poStatusesFor($status, $poCount);

        $poNumbers = [];
        $value     = 0;
        foreach ($poStatuses as $i => $poStatus) {
            $po = $this->buildPoNumber($poStatus, $createdTs, $approvedTs, $customerId, $i + 1, $termDays);
            $value += $po['row']['nominal'];
            $poNumbers[] = $po;
        }

        if (in_array($status, ['CLOSING', 'CLOSED'])) {
            $closingNotes = $this->pick([
                'Seluruh pekerjaan selesai, dokumen BAST & invoice lengkap.',
                'Closing diajukan setelah pembayaran termin terakhir diterima.',
                'Retensi sudah dicairkan, tidak ada punchlist tersisa.',
            ]);
        }
        if ($status === 'CLOSED') {
            $lastBast = $this->lastBastTs($poNumbers);
            $closedTs = $this->step($lastBast && $lastBast->gt($approvedTs) ? $lastBast : $approvedTs, 5, 30);
            $closedBy = $this->actor('finance_manager', 'finance');
        }

        $contract = $poCount > 0 ? $value : $this->money(150000000, 1200000000);
        $poQty    = $poCount === 0 ? null : $this->pick([$poCount, $poCount, $poCount, null, $poCount + 1]);

        $milestones = $this->buildMilestones($status);
        $updatedTs  = $closedTs ?? $approvedTs ?? $submittedTs ?? $createdTs;

        $row = [
            'code'        => $code,
            'po_number'   => $poNumbers[0]['row']['po_number'] ?? null,
            'financing'   => $this->pick(['NL', 'NL', 'NL', 'PL', 'IL']),
            'value'       => $value,
            'po_quantity' => $poQty,
            'contract'    => $contract,
            'overhead'    => 0,
            'fixed_cost'  => 0,
            'term_of_payment' => $term,
            'name'        => $this->pick(self::SCOPES) . ' - ' . $this->pick(self::AREAS)
                             . ' (' . mt_rand(2, 45) . ' SITE)',
            'customer_id' => $customerId,
            'input_by'    => $inputBy,
            'manager_id'  => $managerId,
            'status'      => $status,
            // Kolom header memakai enum sendiri (NOT_YET/ON_GOING/DONE) - lihat
            // Project::DETAIL_TO_MONITORING_STATUS.
            'monitoring_po_received_status' => self::MONITORING_MAP[$milestones['monitoring']['PO Received']],
            'monitoring_bast_status'        => self::MONITORING_MAP[$milestones['monitoring']['BAST']],
            'monitoring_invoice_status'     => self::MONITORING_MAP[$milestones['monitoring']['Invoice']],
            'monitoring_payment_status'     => self::MONITORING_MAP[$milestones['monitoring']['Payment']],
            'notes' => $this->chance(35) ? $this->pick([
                'PO lanjutan dari kontrak payung tahun sebelumnya.',
                'Scope termasuk material, dikirim bertahap per cluster.',
                'Customer minta laporan progress mingguan.',
                'Retensi 5% ditahan sampai masa pemeliharaan selesai.',
            ]) : null,
            'submitted_at'  => $submittedTs?->toDateTimeString(),
            'approved_at'   => $approvedTs?->toDateTimeString(),
            'closed_at'     => $closedTs?->toDateTimeString(),
            'approved_by'   => $approvedBy,
            'closed_by'     => $closedBy,
            'closing_notes' => $closingNotes,
            'created_at'    => $createdTs->toDateTimeString(),
            'updated_at'    => $updatedTs->toDateTimeString(),
        ];

        return [
            'row'       => $row,
            'poNumbers' => $poNumbers,
            'details'   => $milestones['details'],
            'logs'      => $this->buildLogs($status, $createdTs, $submittedTs, $approvedTs, $closedTs, $inputBy, $approvedBy, $closedBy),
        ];
    }

    /**
     * Status tiap No. PO harus konsisten dengan status header - lihat
     * Project::syncStatusFromPoNumbers(): header baru PROGRESS_DONE kalau SEMUA
     * No. PO sudah BAST ke atas, dan baru PAID kalau SEMUA sudah Paid.
     */
    private function poStatusesFor(string $projectStatus, int $count): array
    {
        if ($count === 0) {
            return [];
        }

        switch ($projectStatus) {
            case 'DRAFT':
            case 'SUBMITTED':
                return array_fill(0, $count, 'RELEASED');

            case 'ACTIVE':
                // Minimal satu No. PO belum BAST, kalau tidak header naik sendiri.
                $out = [$this->pick(['RELEASED', 'PARTIAL', 'PARTIAL'])];
                for ($i = 1; $i < $count; $i++) {
                    $out[] = $this->pick(['RELEASED', 'PARTIAL', 'BAST_SUBMITTED', 'BAST_SUBMITTED', 'INVOICED']);
                }
                shuffle($out);
                return $out;

            case 'PROGRESS_DONE':
                // Semua sudah BAST ke atas, tapi belum semua Paid.
                $out = [$this->pick(['BAST_SUBMITTED', 'INVOICED'])];
                for ($i = 1; $i < $count; $i++) {
                    $out[] = $this->pick(['BAST_SUBMITTED', 'BAST_SUBMITTED', 'INVOICED', 'INVOICED', 'PAID']);
                }
                shuffle($out);
                return $out;

            default: // PAID / CLOSING / CLOSED
                return array_fill(0, $count, 'PAID');
        }
    }

    private function buildPoNumber(
        string $status,
        Carbon $projectCreatedTs,
        ?Carbon $approvedTs,
        int $customerId,
        int $seq,
        int $termDays
    ): array {
        $base      = $approvedTs ?? $projectCreatedTs;
        $release   = $this->fwd($base, 0, 20);
        $releaseTs = $this->atTimeAfter($release, $base);
        $nominal   = $this->money(75000000, 1850000000);
        $initial   = $this->customers[$customerId] ?? 'GEN';

        $poNumber = $this->chance(70)
            ? sprintf(
                '%03d/%s/PO-%s/%s/%s',
                mt_rand(1, 250),
                $initial,
                $this->pick(['DN', 'TRX', 'FO', 'CV', 'MS']),
                self::ROMAN[(int) $release->format('n')],
                $release->format('y')
            )
            : (string) mt_rand(62600000, 62899999);

        $row = [
            'po_number'         => $poNumber,
            'nominal'           => $nominal,
            'release_date'      => $release->toDateString(),
            'status'            => $status,
            'no_bast'           => null,
            'tanggal_bast'      => null,
            'nilai_bast'        => null,
            'bast_notes'        => null,
            'bast_submitted_by' => null,
            'bast_submitted_at' => null,
            'input_by'          => $this->actor('finance', 'head'),
            'created_at'        => $releaseTs->toDateTimeString(),
            'updated_at'        => $releaseTs->toDateTimeString(),
        ];

        $invoices = [];
        $bastTs   = null;

        if ($status !== 'RELEASED') {
            $bastDate = $this->fwd($release, 20, 120);
            $bastTs   = $this->atTimeAfter($bastDate, $releaseTs);

            $row['no_bast']      = $poNumber . '-BA' . $seq;
            $row['tanggal_bast'] = $bastDate->toDateString();
            $row['nilai_bast']   = $status === 'PARTIAL'
                ? (int) (round($nominal * mt_rand(40, 80) / 100 / 1000) * 1000)
                : $nominal;

            if ($status !== 'PARTIAL') {
                // PARTIAL = BAST sudah diupload tapi belum ditandai lengkap.
                $row['bast_submitted_by'] = $this->actor('manager', 'head');
                $row['bast_submitted_at'] = $bastTs->toDateTimeString();
            }
            $row['bast_notes'] = $this->chance(30) ? 'BAST ditandatangani PIC customer di lokasi.' : null;
            $row['updated_at'] = $bastTs->toDateTimeString();

            $invoices = $this->buildInvoices($status, $nominal, $bastTs, $customerId, $termDays);
        }

        return ['row' => $row, 'invoices' => $invoices, 'bast_ts' => $bastTs];
    }

    /**
     * Invoice per No. PO. Total billed_amount yang tidak Cancelled harus:
     *   PARTIAL / BAST_SUBMITTED -> kurang dari nominal (masih ada sisa tagihan)
     *   INVOICED / PAID          -> persis sama dengan nominal (tertagih penuh)
     * Lihat ProjectPoNumber::remainingValue() & syncInvoiceStatus().
     */
    private function buildInvoices(string $poStatus, int $nominal, Carbon $bastTs, int $customerId, int $termDays): array
    {
        $fullyBilled = in_array($poStatus, ['INVOICED', 'PAID']);
        $amounts     = $fullyBilled
            ? $this->split($nominal, $this->pick([1, 1, 2, 2, 3]))
            : [(int) (round($nominal * mt_rand(25, 65) / 100 / 1000) * 1000)];

        $invoices = [];
        $date     = $this->fwd($bastTs, 1, 20);

        foreach ($amounts as $i => $amount) {
            $isLast = $i === count($amounts) - 1;

            if (count($amounts) === 1) {
                $termType = $this->pick(['Full Payment', 'Full Payment', 'Tagihan', 'Termin']);
                $terminNo = $termType === 'Termin' ? 1 : null;
            } elseif ($i === 0) {
                $termType = 'Down Payment';
                $terminNo = null;
            } elseif ($isLast) {
                $termType = 'Final Payment';
                $terminNo = null;
            } else {
                $termType = 'Termin';
                $terminNo = $i + 1;
            }

            $invoices[] = $this->buildInvoice(
                $amount,
                $date,
                $bastTs,
                $customerId,
                $termDays,
                $termType,
                $terminNo,
                $this->invoiceStatusFor($poStatus),
                // Persentase penagihan dibulatkan, jadi hanya dipakai di No. PO
                // yang memang belum tertagih penuh - di No. PO INVOICED/PAID
                // selisih satu rupiah saja membuat remainingValue() != 0.
                ! $fullyBilled
            );

            $date = $this->fwd($date, 20, 60);
        }

        // Invoice batal - tidak menambah billed (difilter status != CANCELLED),
        // jadi aman ditempel di No. PO manapun yang sudah BAST.
        if ($this->chance(14)) {
            $invoices[] = $this->buildInvoice(
                (int) (round($nominal * mt_rand(20, 50) / 100 / 1000) * 1000),
                $this->fwd($bastTs, 1, 15),
                $bastTs,
                $customerId,
                $termDays,
                $this->pick(['Termin', 'Tagihan']),
                1,
                'CANCELLED',
                true
            );
        }

        return $invoices;
    }

    /**
     * Status invoice yang mungkin, dibatasi status No. PO induknya.
     *
     * PAID hanya boleh muncul di No. PO yang juga PAID: Invoice::syncPaymentStatus()
     * langsung menaikkan No. PO ke PAID begitu SATU invoice-nya lunas, jadi
     * "No. PO Invoiced + ada invoice Paid" adalah kombinasi yang tidak pernah
     * bisa terjadi lewat aplikasi.
     */
    private function invoiceStatusFor(string $poStatus): string
    {
        switch ($poStatus) {
            // Belum ditagih penuh - invoice boleh berhenti di tahap manapun.
            case 'PARTIAL':
                return $this->pick(['DRAFT', 'DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED']);
            case 'BAST_SUBMITTED':
                return $this->pick([
                    'DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'APPROVED',
                    'SENT', 'SENT', 'RECEIVED', 'RECEIVED', 'PARTIALLY_PAID',
                ]);
            // Tertagih penuh tapi belum lunas.
            case 'INVOICED':
                return $this->pick([
                    'SUBMITTED', 'VERIFIED', 'APPROVED', 'APPROVED',
                    'SENT', 'SENT', 'SENT', 'RECEIVED', 'RECEIVED', 'RECEIVED',
                    'PARTIALLY_PAID', 'PARTIALLY_PAID',
                ]);
            // Lunas -> semua invoice harus PAID (lihat Invoice::syncPaymentStatus()).
            default:
                return 'PAID';
        }
    }

    private function buildInvoice(
        int $billedTarget,
        Carbon $invoiceDate,
        Carbon $notBefore,
        int $customerId,
        int $termDays,
        string $termType,
        ?int $terminNo,
        string $status,
        bool $allowPercentage
    ): array {
        $taxRate = $this->chance(75) ? '11.00' : '12.00';

        // 25% invoice memakai persentase penagihan; sisanya menagih penuh baris detail.
        $percentage = ($allowPercentage && $this->chance(25)) ? $this->pick([25, 30, 40, 50, 60, 70, 75]) : null;
        $target     = $percentage !== null ? (int) round($billedTarget * 100 / $percentage) : $billedTarget;

        $details   = $this->buildInvoiceDetails($target);
        $subtotal  = array_sum(array_column($details, 'total'));
        $deduction = array_sum(array_column($details, 'deduction'));
        $billed    = $percentage !== null ? (int) round($subtotal * $percentage / 100) : $subtotal;

        $tax      = (int) round($billed * ((float) $taxRate) / 100);
        $captured = $billed + $tax;

        $createdTs = $this->atTimeAfter($invoiceDate, $notBefore);
        $workflow  = $this->invoiceWorkflowStamps($status, $createdTs);

        $row = [
            'faktur_number' => $this->chance(85)
                ? sprintf('010.000-%s.%08d', $invoiceDate->format('y'), mt_rand(1, 99999999))
                : '-',
            'source_fund_id'     => self::SOURCE_FUND_ID,
            'payment_term_type'  => $termType,
            'termin_number'      => $terminNo,
            'invoice_date'       => $invoiceDate->toDateString(),
            'due_date'           => $invoiceDate->copy()->addDays($termDays)->toDateString(),
            'actual_due_date'    => null,
            'tax_rate'           => $taxRate,
            'billing_percentage' => $percentage !== null ? number_format($percentage, 2, '.', '') : null,
            'subtotal'           => $subtotal,
            'billed_amount'      => $billed,
            'tax_amount'         => $tax,
            'captured_total'     => $captured,
            'total_deduction'    => $deduction,
            'is_additional'      => 0,
            'additional_amount'  => 0,
            'remark' => $this->chance(25) ? $this->pick([
                'Tagihan sesuai BAST terlampir.',
                'Mohon pembayaran ditransfer ke rekening PT TEREX.',
                'Termasuk pekerjaan tambah sesuai instruksi lapangan.',
                'Retensi 5% ditahan customer.',
            ]) : null,
            'status'     => $status,
            'created_by' => $this->actor('finance', 'collection'),
            'created_at' => $createdTs->toDateTimeString(),
            'updated_at' => ($workflow['last'] ?? $createdTs)->toDateTimeString(),
        ] + $workflow['stamps'];

        return [
            'row'          => $row,
            'details'      => $details,
            'status'       => $status,
            'customer_id'  => $customerId,
            'invoice_date' => $invoiceDate,
            'term_days'    => $termDays,
            'captured'     => $captured,
            'billed'       => $billed,
            'approved_ts'  => $workflow['approved'],
        ];
    }

    /** Baris detail invoice: total = (harga satuan x qty) - potongan. */
    private function buildInvoiceDetails(int $target): array
    {
        $lines = $this->pick([1, 2, 2, 3, 3, 4]);
        $rows  = [];

        foreach ($this->splitEven($target, $lines) as $part) {
            [$desc, $unit] = $this->pick(self::ITEM_DESCRIPTIONS);

            $deduction = $this->chance(18) ? (int) (round($part * mt_rand(2, 5) / 100 / 1000) * 1000) : 0;
            $gross     = $part + $deduction;

            // qty dipilih supaya harga satuan tetap bilangan bulat.
            $qty = $this->pick([1, 1, 1, 2, 4, 5]);
            while ($qty > 1 && $gross % $qty !== 0) {
                $qty--;
            }
            $unitPrice = (int) ($gross / max(1, $qty));

            $rows[] = [
                'description' => $desc,
                'unit_price'  => $unitPrice,
                'qty'         => $qty,
                'unit'        => $unit,
                'deduction'   => $deduction,
                'total'       => $unitPrice * $qty - $deduction,
            ];
        }

        return $rows;
    }

    private function lastBastTs(array $poNumbers): ?Carbon
    {
        $last = null;
        foreach ($poNumbers as $po) {
            if ($po['bast_ts'] && (! $last || $po['bast_ts']->gt($last))) {
                $last = $po['bast_ts']->copy();
            }
        }
        return $last;
    }

    /** Cap waktu tiap tahap workflow invoice, menaik dari tanggal invoice. */
    private function invoiceWorkflowStamps(string $status, Carbon $createdTs): array
    {
        $order = ['DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'SENT', 'RECEIVED', 'PARTIALLY_PAID', 'PAID'];
        $level = $status === 'CANCELLED' ? 3 : (int) array_search($status, $order, true);

        $stamps = [
            'submitted_by' => null, 'submitted_at' => null,
            'verified_by'  => null, 'verified_at'  => null,
            'approved_by'  => null, 'approved_at'  => null,
            'sent_by'      => null, 'sent_at'      => null,
            'received_by'  => null, 'received_at'  => null,
            'cancelled_by' => null, 'cancelled_at' => null, 'cancel_reason' => null,
        ];

        $cursor   = $createdTs;
        $approved = null;

        if ($level >= 1) {
            $cursor = $this->step($cursor, 0, 3);
            $stamps['submitted_by'] = $this->actor('finance', 'collection');
            $stamps['submitted_at'] = $cursor->toDateTimeString();
        }
        if ($level >= 2) {
            $cursor = $this->step($cursor, 1, 4);
            $stamps['verified_by'] = $this->actor('accounting');
            $stamps['verified_at'] = $cursor->toDateTimeString();
        }
        if ($level >= 3) {
            $cursor = $this->step($cursor, 1, 5);
            $stamps['approved_by'] = $this->actor('approver', 'finance_manager');
            $stamps['approved_at'] = $cursor->toDateTimeString();
            $approved = $cursor;
        }
        if ($status === 'CANCELLED') {
            $cursor = $this->step($cursor, 1, 20);
            $stamps['cancelled_by']  = $this->actor('finance_manager');
            $stamps['cancelled_at']  = $cursor->toDateTimeString();
            $stamps['cancel_reason'] = $this->pick([
                'Nominal tidak sesuai BAST, invoice diterbitkan ulang.',
                'Customer menolak faktur pajak, perlu revisi NPWP.',
                'Double input dengan invoice termin sebelumnya.',
                'Scope pekerjaan berubah setelah invoice terbit.',
            ]);
            $approved = null;
        }

        return ['stamps' => $stamps, 'approved' => $approved, 'last' => $cursor];
    }

    /*
    |--------------------------------------------------------------------------
    | MILESTONE (project_details) & LOG
    |--------------------------------------------------------------------------
    */

    private function buildMilestones(string $projectStatus): array
    {
        switch ($projectStatus) {
            case 'DRAFT':
            case 'SUBMITTED':
                $map = ['PO Received' => 'NOT_STARTED', 'BAST' => 'NOT_STARTED', 'Invoice' => 'NOT_STARTED', 'Payment' => 'NOT_STARTED'];
                break;
            case 'WAITING_PO':
                $map = ['PO Received' => 'ON_GOING', 'BAST' => 'NOT_STARTED', 'Invoice' => 'NOT_STARTED', 'Payment' => 'NOT_STARTED'];
                break;
            case 'ACTIVE':
                $map = ['PO Received' => 'DONE', 'BAST' => 'ON_GOING', 'Invoice' => $this->pick(['NOT_STARTED', 'ON_GOING']), 'Payment' => 'NOT_STARTED'];
                break;
            case 'PROGRESS_DONE':
                $map = ['PO Received' => 'DONE', 'BAST' => 'DONE', 'Invoice' => 'ON_GOING', 'Payment' => $this->pick(['NOT_STARTED', 'ON_GOING'])];
                break;
            default:
                $map = ['PO Received' => 'DONE', 'BAST' => 'DONE', 'Invoice' => 'DONE', 'Payment' => 'DONE'];
        }

        $details = [];
        foreach ($map as $name => $st) {
            $details[] = ['stage_id' => $this->stage[$name], 'status' => $st, 'weight' => 15];
        }

        // Milestone implementasi - sisa bobot 40% dibagi rata.
        $implCount  = mt_rand(2, 4);
        $implStages = (array) array_rand(array_flip($this->implStages), $implCount);
        $weight     = (int) floor(40 / $implCount);

        foreach ($implStages as $stageId) {
            if (in_array($projectStatus, ['DRAFT', 'SUBMITTED', 'WAITING_PO'])) {
                $st = 'NOT_STARTED';
            } elseif (in_array($projectStatus, ['PAID', 'CLOSING', 'CLOSED'])) {
                $st = 'DONE';
            } elseif ($projectStatus === 'PROGRESS_DONE') {
                $st = $this->pick(['DONE', 'DONE', 'ON_GOING']);
            } else {
                $st = $this->pick(['NOT_STARTED', 'ON_GOING', 'ON_GOING', 'DONE']);
            }

            $details[] = ['stage_id' => $stageId, 'status' => $st, 'weight' => $weight];
        }

        return ['details' => $details, 'monitoring' => $map];
    }

    private function buildLogs(
        string $status,
        Carbon $createdTs,
        ?Carbon $submittedTs,
        ?Carbon $approvedTs,
        ?Carbon $closedTs,
        int $inputBy,
        ?int $approvedBy,
        ?int $closedBy
    ): array {
        $log = fn ($from, $to, $action, $notes, $userId, Carbon $at) => [
            'status_from' => $from,
            'status_to'   => $to,
            'action'      => $action,
            'notes'       => $notes,
            'user_id'     => $userId,
            'user_name'   => $this->userNames[$userId] ?? 'System',
            'role'        => $this->userRoles[$userId] ?? null,
            'created_at'  => $at->toDateTimeString(),
            'updated_at'  => $at->toDateTimeString(),
        ];

        $logs = [$log('DRAFT', 'DRAFT', 'created', null, $inputBy, $createdTs)];

        if ($submittedTs) {
            $logs[] = $log('DRAFT', 'SUBMITTED', 'submitted', null, $inputBy, $submittedTs);
        }
        if ($approvedTs && $approvedBy) {
            $waiting = $status === 'WAITING_PO';
            $logs[] = $log(
                'SUBMITTED',
                $waiting ? 'WAITING_PO' : 'ACTIVE',
                $waiting ? 'approved_waiting' : 'approved_active',
                $waiting ? 'Menunggu No. PO terbit dari customer.' : 'No. PO sudah lengkap.',
                $approvedBy,
                $approvedTs
            );
        }
        if ($approvedTs && in_array($status, ['PROGRESS_DONE', 'PAID', 'CLOSING', 'CLOSED'])) {
            $d = $this->step($approvedTs, 30, 120);
            $logs[] = $log('ACTIVE', 'PROGRESS_DONE', 'bast_submitted', 'Semua No. PO sudah BAST (otomatis).', $inputBy, $d);

            if (in_array($status, ['PAID', 'CLOSING', 'CLOSED'])) {
                $p = $this->step($d, 20, 90);
                $logs[] = $log('PROGRESS_DONE', 'PAID', 'invoice_paid', 'Semua No. PO sudah Paid (otomatis).', $inputBy, $p);

                if (in_array($status, ['CLOSING', 'CLOSED'])) {
                    $c = $closedTs && $closedTs->gt($p) ? $this->atTimeAfter($p->copy(), $p) : $this->step($p, 3, 25);
                    if ($closedTs && $c->gte($closedTs)) {
                        $c = $p;
                    }
                    $logs[] = $log('PAID', 'CLOSING', 'closing_submitted', 'Pengajuan closing PO.', $this->actor('finance'), $c);

                    if ($closedTs && $closedBy) {
                        $logs[] = $log('CLOSING', 'CLOSED', 'closed', 'Closing PO disetujui.', $closedBy, $closedTs);
                    }
                }
            }
        }

        return $logs;
    }

    /*
    |--------------------------------------------------------------------------
    | PENULISAN KE DATABASE
    |--------------------------------------------------------------------------
    */

    private function persist(array $projects): void
    {
        $flatInvoices = [];

        foreach ($projects as $project) {
            $projectId = DB::table('projects')->insertGetId($project['row']);
            $created   = Carbon::parse($project['row']['created_at']);

            foreach ($project['details'] as $d) {
                $done = $d['status'] === 'DONE';

                DB::table('project_details')->insert([
                    'project_id'   => $projectId,
                    'cluster_id'   => $this->pick($this->clusterIds),
                    'stage_id'     => $d['stage_id'],
                    'weight'       => $d['weight'],
                    'status'       => $d['status'],
                    'submitted_done_by' => $done ? $this->actor('manager', 'head') : null,
                    'submitted_done_at' => $done ? $project['row']['updated_at'] : null,
                    'plan_start'   => $created->copy()->addDays(mt_rand(1, 20))->toDateString(),
                    'plan_end'     => $created->copy()->addDays(mt_rand(30, 160))->toDateString(),
                    'actual_start' => $d['status'] !== 'NOT_STARTED'
                        ? $created->copy()->addDays(mt_rand(2, 30))->toDateString() : null,
                    'actual_end'   => $done ? $created->copy()->addDays(mt_rand(35, 180))->toDateString() : null,
                    'notes'        => null,
                    'created_at'   => $project['row']['created_at'],
                    'updated_at'   => $project['row']['updated_at'],
                ]);
            }

            foreach ($project['logs'] as $l) {
                DB::table('project_logs')->insert($l + ['project_id' => $projectId]);
            }

            foreach ($project['poNumbers'] as $po) {
                $poId = DB::table('project_po_numbers')->insertGetId($po['row'] + ['project_id' => $projectId]);

                foreach ($po['invoices'] as $inv) {
                    $inv['po_id']    = $projectId;
                    $inv['po_no_id'] = $poId;
                    $flatInvoices[]  = $inv;
                }
            }
        }

        $this->persistInvoices($flatInvoices);
    }

    private function persistInvoices(array $invoices): void
    {
        // Kode invoice mengikuti urutan tanggal, sama seperti penomoran aplikasi.
        usort($invoices, fn ($a, $b) => $a['invoice_date']->timestamp <=> $b['invoice_date']->timestamp);

        $globalSeq = [];
        $custSeq   = [];
        foreach (DB::table('invoices')->selectRaw('YEAR(invoice_date) y, COUNT(*) c')->groupBy('y')->get() as $r) {
            $globalSeq[(int) $r->y] = (int) $r->c;
        }
        foreach (DB::table('invoices')->join('projects', 'invoices.po_id', '=', 'projects.id')
            ->selectRaw('YEAR(invoice_date) y, projects.customer_id cid, COUNT(*) c')
            ->groupBy('y', 'cid')->get() as $r) {
            $custSeq[$r->y . ':' . $r->cid] = (int) $r->c;
        }

        $sentPool = [];

        foreach ($invoices as $inv) {
            $date = $inv['invoice_date'];
            $year = (int) $date->format('Y');
            $cid  = $inv['customer_id'];

            $g = ($globalSeq[$year] = ($globalSeq[$year] ?? 0) + 1);
            $c = ($custSeq[$year . ':' . $cid] = ($custSeq[$year . ':' . $cid] ?? 0) + 1);

            $row = $inv['row'];
            $row['code'] = sprintf(
                '%04d/%s-%04d/%s/%s',
                $g,
                $this->customers[$cid] ?? 'GEN',
                $c,
                $date->format('m'),
                $date->format('y')
            );
            $row['po_id']                = $inv['po_id'];
            $row['project_po_number_id'] = $inv['po_no_id'];

            $invoiceId = DB::table('invoices')->insertGetId($row);

            foreach ($inv['details'] as $d) {
                DB::table('invoice_details')->insert($d + [
                    'invoice_id' => $invoiceId,
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['created_at'],
                ]);
            }

            if (in_array($inv['status'], ['SENT', 'RECEIVED', 'PARTIALLY_PAID', 'PAID'])) {
                $sentPool[] = [
                    'id'          => $invoiceId,
                    'customer_id' => $cid,
                    'status'      => $inv['status'],
                    'captured'    => $inv['captured'],
                    'billed'      => $inv['billed'],
                    'term_days'   => $inv['term_days'],
                    'approved_ts' => $inv['approved_ts'],
                    // Surat Jalan RECEIVED otomatis menandai semua invoice di
                    // dalamnya diterima, jadi invoice SENT tidak boleh ikut.
                    'received'    => $inv['status'] === 'RECEIVED'
                        || ($inv['status'] !== 'SENT' && $this->chance(75)),
                ];
            }
        }

        $this->persistDeliveryNotes($sentPool);
    }

    /*
    |--------------------------------------------------------------------------
    | SURAT JALAN + COLLECTION
    |--------------------------------------------------------------------------
    | Invoice hanya bisa jadi SENT lewat pembuatan Surat Jalan
    | (DeliveryNoteController::store), jadi setiap invoice SENT ke atas di sini
    | dipastikan punya Surat Jalan. Satu Surat Jalan = satu customer.
    */

    private function persistDeliveryNotes(array $pool): void
    {
        $grouped = [];
        foreach ($pool as $inv) {
            $grouped[$inv['customer_id'] . '|' . ($inv['received'] ? 'R' : 'S')][] = $inv;
        }

        $notes = [];
        foreach ($grouped as $key => $items) {
            usort($items, fn ($a, $b) => $a['approved_ts']->timestamp <=> $b['approved_ts']->timestamp);
            [$customerId, $flag] = explode('|', $key);

            while ($items) {
                $chunk = array_splice($items, 0, $this->pick([1, 1, 2, 2, 3]));

                // Surat Jalan tidak mungkin terbit sebelum invoice terakhir di
                // dalamnya di-approve.
                $latest = $chunk[0]['approved_ts'];
                foreach ($chunk as $i) {
                    if ($i['approved_ts']->gt($latest)) {
                        $latest = $i['approved_ts'];
                    }
                }

                $notes[] = [
                    'customer_id' => (int) $customerId,
                    'received'    => $flag === 'R',
                    'sent_ts'     => $this->step($latest, 0, 5),
                    'items'       => $chunk,
                ];
            }
        }

        usort($notes, fn ($a, $b) => $a['sent_ts']->timestamp <=> $b['sent_ts']->timestamp);

        $dnSeq = [];
        foreach (DB::table('delivery_notes')->selectRaw('YEAR(created_at) y, COUNT(*) c')->groupBy('y')->get() as $r) {
            $dnSeq[(int) $r->y] = (int) $r->c;
        }

        foreach ($notes as $note) {
            $sentTs    = $note['sent_ts'];
            $year      = (int) $sentTs->format('Y');
            $seq       = ($dnSeq[$year] = ($dnSeq[$year] ?? 0) + 1);
            $createdBy = $this->actor('collection', 'finance');

            $receivedTs = $note['received'] ? $this->step($sentTs, 1, 14) : null;
            $receivedBy = $note['received'] ? $this->actor('collection') : null;

            $dnId = DB::table('delivery_notes')->insertGetId([
                'code'        => sprintf('SJ-%04d/%s/%s', $seq, $sentTs->format('m'), $sentTs->format('y')),
                'customer_id' => $note['customer_id'],
                'status'      => $note['received'] ? 'RECEIVED' : 'SENT',
                'notes' => $this->chance(40) ? $this->pick([
                    'Dikirim via kurir internal, diterima bagian finance customer.',
                    'Dokumen invoice + faktur pajak + BAST dalam satu amplop.',
                    'Pengiriman menyusul hardcopy BAST asli.',
                ]) : null,
                'created_by'  => $createdBy,
                'sent_at'     => $sentTs->toDateTimeString(),
                'received_by' => $receivedBy,
                'received_at' => $receivedTs?->toDateTimeString(),
                'created_at'  => $sentTs->toDateTimeString(),
                'updated_at'  => ($receivedTs ?? $sentTs)->toDateTimeString(),
            ]);

            DB::table('delivery_note_logs')->insert([
                'delivery_note_id' => $dnId,
                'action'           => 'SENT',
                'actor_id'         => $createdBy,
                'actor_name'       => $this->userNames[$createdBy] ?? null,
                'notes'            => null,
                'created_at'       => $sentTs->toDateTimeString(),
                'updated_at'       => $sentTs->toDateTimeString(),
            ]);

            if ($receivedTs) {
                DB::table('delivery_note_logs')->insert([
                    'delivery_note_id' => $dnId,
                    'action'           => 'RECEIVED',
                    'actor_id'         => $receivedBy,
                    'actor_name'       => $this->userNames[$receivedBy] ?? null,
                    'notes'            => $this->chance(50) ? 'Diterima dan ditandatangani PIC customer.' : null,
                    'created_at'       => $receivedTs->toDateTimeString(),
                    'updated_at'       => $receivedTs->toDateTimeString(),
                ]);
            }

            foreach ($note['items'] as $inv) {
                DB::table('delivery_note_items')->insert([
                    'delivery_note_id' => $dnId,
                    'invoice_id'       => $inv['id'],
                    'created_at'       => $sentTs->toDateTimeString(),
                    'updated_at'       => $sentTs->toDateTimeString(),
                ]);

                $update = [
                    'sent_by'    => $createdBy,
                    'sent_at'    => $sentTs->toDateTimeString(),
                    'updated_at' => ($receivedTs ?? $sentTs)->toDateTimeString(),
                ];

                if ($receivedTs) {
                    $update['received_by'] = $receivedBy;
                    $update['received_at'] = $receivedTs->toDateTimeString();
                    // actual_due_date = tanggal diterima + panjang termin asli
                    // (lihat Invoice::syncActualDueDate()).
                    $update['actual_due_date'] = $receivedTs->copy()->startOfDay()
                        ->addDays($inv['term_days'])->toDateString();
                }

                DB::table('invoices')->where('id', $inv['id'])->update($update);

                $this->persistCashReceipts($inv, $receivedTs ?? $sentTs);
            }
        }
    }

    /**
     * Cash Receipt (Collection). Invoice::totalReceived() = SUM(amount) +
     * SUM(withholding_tax), jadi untuk invoice PAID keduanya harus berjumlah
     * persis captured_total - kalau tidak, statusnya tidak konsisten dengan
     * yang akan dihitung ulang aplikasi.
     */
    private function persistCashReceipts(array $inv, Carbon $from): void
    {
        if (! in_array($inv['status'], ['PARTIALLY_PAID', 'PAID'])) {
            return;
        }

        $captured = $inv['captured'];
        $target   = $inv['status'] === 'PAID'
            ? $captured
            : (int) (round($captured * mt_rand(20, 70) / 100 / 1000) * 1000);

        // PPh Pasal 4(2) 2% dari DPP - hanya sebagian invoice yang lunas.
        $wht = ($inv['status'] === 'PAID' && $this->chance(30))
            ? (int) round($inv['billed'] * 0.02)
            : 0;
        if ($wht >= $target) {
            $wht = 0;
        }

        $cash  = $target - $wht;
        $count = $inv['status'] === 'PAID' ? $this->pick([1, 1, 1, 2, 2, 3]) : $this->pick([1, 1, 2]);
        $parts = $this->splitEven($cash, $count);
        $ts    = $this->step($from, 3, 45);

        foreach ($parts as $i => $amount) {
            if ($amount <= 0) {
                continue;
            }

            DB::table('cash_receipts')->insert([
                'invoice_id'      => $inv['id'],
                'received_date'   => $ts->toDateString(),
                'amount'          => $amount,
                'withholding_tax' => $i === count($parts) - 1 ? $wht : 0,
                'source_fund_id'  => self::SOURCE_FUND_ID,
                'proof_file'      => null,
                'notes' => $this->chance(55) ? $this->pick([
                    'Transfer masuk rekening PT TEREX.',
                    'Pembayaran termin sesuai jadwal.',
                    'Pelunasan setelah rekonsiliasi faktur pajak.',
                    'Pembayaran sebagian, sisanya menyusul bulan depan.',
                    'Dibayar via virtual account customer.',
                ]) : null,
                'input_by'   => $this->actor('collection', 'accounting'),
                'created_at' => $ts->toDateTimeString(),
                'updated_at' => $ts->toDateTimeString(),
            ]);

            $ts = $this->step($ts, 10, 45);
        }
    }

    /**
     * Penghitung nomor dokumen (App\Support\DocumentSequence) disemai dari
     * data akhir. Tanpa ini penghitung menyemai diri dari COUNT(*) saat dokumen
     * pertama dibuat lewat UI - dan karena data dummy ini tidak bernomor rapat,
     * nomor hasil semaian itu bisa menabrak kode yang sudah terpakai (kolom
     * code unik -> invoice gagal disimpan).
     */
    private function syncSequences(): void
    {
        $seq = [];

        foreach (DB::table('invoices')->selectRaw('YEAR(invoice_date) y, COUNT(*) c')->groupBy('y')->get() as $r) {
            $seq['invoice:' . $r->y] = (int) $r->c;
        }
        foreach (DB::table('invoices')->join('projects', 'invoices.po_id', '=', 'projects.id')
            ->selectRaw('YEAR(invoice_date) y, projects.customer_id cid, COUNT(*) c')
            ->groupBy('y', 'cid')->get() as $r) {
            $seq['invoice:' . $r->y . ':customer:' . $r->cid] = (int) $r->c;
        }
        foreach (DB::table('delivery_notes')->selectRaw('YEAR(created_at) y, COUNT(*) c')->groupBy('y')->get() as $r) {
            $seq['delivery-note:' . $r->y] = (int) $r->c;
        }

        foreach ($seq as $key => $counter) {
            DB::table('document_sequences')->updateOrInsert(
                ['key' => $key],
                ['counter' => $counter, 'updated_at' => $this->now, 'created_at' => $this->now],
            );
        }
    }
}

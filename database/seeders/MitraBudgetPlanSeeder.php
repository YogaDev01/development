<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanDetail;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\MasterPic;
use App\Models\Project;
use App\Models\RequestType;
use App\Models\Team;
use App\Models\TroubleTicket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Budget Plan untuk customer PT MITRA SISTEMATIKA GLOBAL.
 *
 * Dibuat supaya tiket Trouble Ticket milik customer ini punya sumber dana di
 * modal Create Plan (daftarnya disaring per customer, lihat
 * TroubleTicketController::show()), dan supaya Cost Request yang lahir dari
 * plan itu — maupun yang diketik manual — punya pagu untuk disandari.
 *
 * SATU budget plan melayani Cash Advance MAUPUN Cost Payment: jenis pengajuan
 * ditentukan di Cost Request (`request_type`), bukan di budget plan. Yang
 * membedakan hanyalah sub-item yang dipilih, jadi sub-itemnya sengaja dipisah
 * menjadi dua kelompok:
 *
 *   - "CA ..."  : biaya yang biasanya diuangmukakan ke teknisi (transport,
 *                 makan, penginapan, BBM/tol/parkir)
 *   - "PAY ..." : biaya yang dibayarkan ke pihak ketiga (jasa vendor,
 *                 material/sparepart, sewa alat)
 *
 * Seeder ini AMAN DIULANG (idempoten): setiap baris dicari dulu lewat kode /
 * nama, dibuat hanya bila belum ada. Baris buatannya ditandai
 * notes = 'MITRA_BP_SEED' supaya mudah ditemukan kembali.
 *
 * Yang dibuat/dilengkapi bila belum ada, supaya alur TT x Finance bisa
 * dicoba utuh dari Create Plan sampai Cost Request draft:
 *
 *   1. Customer "PT MITRA SISTEMATIKA GLOBAL"
 *   2. Project status ACTIVE (syarat Project::BUDGETABLE_STATUSES) + Cluster
 *      aktif (wajib untuk CR bersumber budget proyek)
 *   3. COA/Account, Budget Plan status ACTIVE + sub-itemnya
 *   4. RequestType "Cash Advance" dan "Cost Payment"
 *   5. customer_id pada team ACTIVE yang BELUM terisi — inilah yang membuat
 *      Budget Plan muncul di Create Plan (daftarnya disaring lewat
 *      TroubleTicketController::teamCustomerIds()). Team yang customer-nya
 *      sudah diisi TIDAK diubah.
 *   6. Beneficiary INTERNAL untuk PIC teknisi tiket customer ini — syarat
 *      approve plan: tanpa itu, pembuatan Cost Request ditolak.
 *
 * Jalankan:  php artisan db:seed --class=MitraBudgetPlanSeeder
 * Hapus:     delete from budget_plan_details where budget_plan_id in
 *              (select id from budget_plans where notes = 'MITRA_BP_SEED');
 *            delete from budget_plans where notes = 'MITRA_BP_SEED';
 */
class MitraBudgetPlanSeeder extends Seeder
{
    private const PENANDA = 'MITRA_BP_SEED';

    private const CUSTOMER = 'PT MITRA SISTEMATIKA GLOBAL';

    /** Kode project 4 digit — ikut menyusun kode budget plan & Cost Request. */
    private const PROJECT_CODE = '9001';

    /**
     * Sub-item budget: [nama COA, kategori COA, deskripsi, harga satuan, qty, unit].
     *
     * Harga satuan = rupiah penuh (uang selalu integer rupiah). Pagu plan =
     * jumlah seluruh total sub-item, dihitung di bawah — tidak diketik ulang.
     */
    private const SUB_ITEM = [
        // Dipakai pengajuan CASH ADVANCE (uang muka ke teknisi).
        ['TRANSPORT',        'Variable Cost', 'CA - Transport & ojek PP kunjungan site', 150_000,   40, 'trip'],
        ['UANG MAKAN',       'Variable Cost', 'CA - Uang makan teknisi saat visit',       50_000,   80, 'orang'],
        ['PENGINAPAN',       'Variable Cost', 'CA - Penginapan teknisi luar kota',       350_000,   20, 'hari'],
        ['BBM TOL PARKIR',   'Variable Cost', 'CA - BBM, tol, dan parkir perjalanan',    100_000,   40, 'trip'],

        // Dipakai pengajuan COST PAYMENT (dibayarkan ke pihak ketiga).
        ['JASA VENDOR',      'Variable Cost', 'PAY - Jasa teknisi/vendor pihak ketiga', 2_500_000,  10, 'site'],
        ['MATERIAL',         'Variable Cost', 'PAY - Material & sparepart perangkat',   1_500_000,  20, 'unit'],
        ['SEWA ALAT',        'Variable Cost', 'PAY - Sewa alat kerja & genset',           750_000,  10, 'hari'],
    ];

    public function run(): void
    {
        $customer = Customer::whereRaw('UPPER(TRIM(name)) = ?', [self::CUSTOMER])->first()
            ?? Customer::create([
                'name'     => self::CUSTOMER,
                'initial'  => Customer::suggestInitial(self::CUSTOMER),
                'status'   => 'ACTIVE',
                'input_by' => User::query()->min('id'),
            ]);

        DB::transaction(function () use ($customer) {
            $project = $this->project($customer->id);
            $this->cluster($project);
            $this->requestTypes();
            $this->teamCustomer($customer->id);
            $this->beneficiaries($customer->id);

            $plan = BudgetPlan::where('project_id', $project->id)
                ->where('notes', self::PENANDA)
                ->first();

            if (! $plan) {
                $plan = BudgetPlan::create([
                    'code'           => $this->kodeBudget($project),
                    'project_id'     => $project->id,
                    'cost_center_id' => null,
                    'description'    => 'BUDGET OPERASIONAL TROUBLE TICKET - ' . self::CUSTOMER,
                    'type'           => 'one_time',
                    'amount'         => 0,
                    // ACTIVE: hanya pagu yang sudah berjalan yang boleh jadi
                    // sumber dana di Create Plan maupun Cost Request.
                    'status'         => 'ACTIVE',
                    'plan_finish'    => now()->endOfYear()->toDateString(),
                    'notes'          => self::PENANDA,
                    'input_by'       => User::query()->min('id'),
                ]);
            }

            $total = 0;

            foreach (self::SUB_ITEM as [$coa, $kategori, $deskripsi, $harga, $qty, $unit]) {
                $account = $this->account($coa, $kategori);
                $barisTotal = $harga * $qty;
                $total += $barisTotal;

                BudgetPlanDetail::updateOrCreate(
                    [
                        'budget_plan_id' => $plan->id,
                        'deskripsi'      => $deskripsi,
                    ],
                    [
                        'type'       => 'main',
                        'category'   => $kategori,
                        'account_id' => $account->id,
                        'amount'     => $harga,
                        'qty'        => $qty,
                        'unit'       => $unit,
                        'total'      => $barisTotal,
                        'notes'      => self::PENANDA,
                    ],
                );
            }

            // Pagu plan = jumlah sub-item, bukan angka yang diketik terpisah.
            $plan->update(['amount' => $total]);

            $this->command?->info('Budget Plan ' . $plan->code . ' siap: Rp ' . number_format($total, 0, ',', '.')
                . ' (' . count(self::SUB_ITEM) . ' sub-item, CA & Payment).');
        });
    }

    /**
     * Jenis pengajuan Cost Request.
     *
     * Hanya "Cash Advance" yang punya arti di kode (nama-namanya di
     * BudgetRequest::CASH_ADVANCE_TYPE_NAMES, dipakai approve plan). "Cost
     * Payment" BUKAN nama RequestType melainkan label tab untuk semua jenis
     * SELAIN Cash Advance, jadi tidak dikarang di sini — cukup dilaporkan
     * apakah sudah ada jenis lain yang bisa dipakai.
     */
    private function requestTypes(): void
    {
        $adaCa = RequestType::whereRaw('LOWER(TRIM(name)) IN (?, ?, ?)', ['cash advance', 'cash-advance', 'cashadvance'])
            ->exists();

        if (! $adaCa) {
            RequestType::create(['name' => 'Cash Advance']);
            $this->command?->info('RequestType "Cash Advance" dibuat.');
        }

        $lain = RequestType::whereRaw('LOWER(TRIM(name)) NOT IN (?, ?, ?)', ['cash advance', 'cash-advance', 'cashadvance'])
            ->pluck('name');

        if ($lain->isEmpty()) {
            $this->command?->warn('Belum ada jenis selain Cash Advance — pengajuan "Cost Payment" butuh satu jenis lain di Master Data > Request Type.');
        } else {
            $this->command?->info('Jenis non-Cash Advance (tab Cost Payment): ' . $lain->implode(', ') . '.');
        }
    }

    /**
     * Team ACTIVE yang customer-nya masih kosong diisi customer ini — tanpa
     * itu Budget Plan tidak muncul di Create Plan, karena daftarnya disaring
     * lewat customer team si pengguna. Team yang sudah punya customer SENGAJA
     * tidak disentuh: itu data yang sudah diputuskan manusia.
     */
    private function teamCustomer(int $customerId): void
    {
        if (! Team::supportsCustomer()) {
            $this->command?->warn('Kolom teams.customer_id belum ada — jalankan migrasi dulu.');

            return;
        }

        $kosong = Team::where('status', 'ACTIVE')->whereNull('customer_id')->pluck('id');
        Team::whereIn('id', $kosong)->update(['customer_id' => $customerId]);

        if (Team::supportsCustomerList()) {
            DB::table('team_customers')->insertOrIgnore(
                $kosong->map(fn ($id) => ['team_id' => $id, 'customer_id' => $customerId])->all()
            );
        }

        $sudah = Team::where('status', 'ACTIVE')
            ->when(
                Team::supportsCustomerList(),
                fn ($q) => $q->whereHas('customers', fn ($c) => $c->where('customers.id', $customerId)),
                fn ($q) => $q->where('customer_id', $customerId),
            )
            ->count();

        $this->command?->info("Team ACTIVE dengan customer ini: {$sudah} ({$kosong->count()} baru diisi).");
    }

    /**
     * Beneficiary INTERNAL untuk tiap PIC teknisi yang dipakai tiket customer
     * ini. Approve plan mencari beneficiary INTERNAL ACTIVE dengan pic_id =
     * PIC teknisi tiket; tanpa itu Cost Request tidak bisa dibuat.
     */
    private function beneficiaries(int $customerId): void
    {
        $picIds = TroubleTicket::where('customer_id', $customerId)
            ->whereNotNull('pic_id')
            ->distinct()
            ->pluck('pic_id');

        // Tidak ada tiket? Pakai seluruh PIC internal supaya trial tetap jalan.
        if ($picIds->isEmpty()) {
            $picIds = MasterPic::where('type', 'internal')->pluck('id');
        }

        $dibuat = 0;

        foreach (MasterPic::whereIn('id', $picIds)->get() as $pic) {
            $ada = Beneficiary::where('pic_id', $pic->id)
                ->where('category', 'INTERNAL')
                ->where('status', 'ACTIVE')
                ->exists();

            if ($ada) {
                continue;
            }

            Beneficiary::create([
                'nickname'      => strtoupper(substr(preg_replace('/\s+/', '', (string) $pic->pic), 0, 20)) ?: ('PIC' . $pic->id),
                'complete_name' => strtoupper((string) $pic->pic),
                'category'      => 'INTERNAL',
                'pic_id'        => $pic->id,
                'contact'       => $pic->phone,
                'status'        => 'ACTIVE',
                'input_by'      => User::query()->min('id'),
            ]);
            $dibuat++;
        }

        $this->command?->info("Beneficiary INTERNAL dilengkapi: {$dibuat} baru dari {$picIds->count()} PIC.");
    }

    /**
     * Project milik customer ini. Statusnya ACTIVE supaya masuk
     * Project::BUDGETABLE_STATUSES — syarat sebuah project boleh dibuatkan
     * budget plan.
     */
    private function project(int $customerId): Project
    {
        $project = Project::where('customer_id', $customerId)
            ->whereIn('status', Project::BUDGETABLE_STATUSES)
            ->orderBy('id')
            ->first();

        if ($project) {
            return $project;
        }

        return Project::create([
            // projects.code unik 4 karakter: dipakai apa adanya bila masih
            // bebas, selain itu dicari nomor berikutnya yang belum terpakai.
            'code'        => $this->kodeProjectBebas(),
            'name'        => 'OPERASIONAL & MAINTENANCE - ' . self::CUSTOMER,
            'po_number'   => 'PO-' . self::PROJECT_CODE . '/TT',
            'customer_id' => $customerId,
            'status'      => 'ACTIVE',
            'value'       => 0,
            'notes'       => self::PENANDA,
            'input_by'    => User::query()->min('id'),
        ]);
    }

    /** Kode project 4 digit yang belum terpakai, mulai dari PROJECT_CODE. */
    private function kodeProjectBebas(): string
    {
        $angka = (int) self::PROJECT_CODE;

        while (Project::where('code', str_pad((string) $angka, 4, '0', STR_PAD_LEFT))->exists()) {
            $angka++;
        }

        return str_pad((string) $angka, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Cluster project. Cost Request bersumber budget PROYEK wajib punya
     * cluster; satu cluster aktif berarti approval plan bisa memilihnya
     * sendiri tanpa menanyai supervisor.
     */
    private function cluster(Project $project): Cluster
    {
        $cluster = Cluster::where('project_id', $project->id)
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->first();

        if ($cluster) {
            return $cluster;
        }

        return Cluster::create([
            // clusters.code juga unik, dan project lama bisa saja sudah punya
            // cluster berkode sama — jadi dicari nomor bebasnya sendiri.
            'code'       => $this->kodeClusterBebas($project),
            'name'       => 'CLUSTER OPERASIONAL ' . self::CUSTOMER,
            'project_id' => $project->id,
            'status'     => 'ACTIVE',
            'input_by'   => User::query()->min('id'),
        ]);
    }

    /** Kode cluster yang belum terpakai, mulai dari kode project-nya. */
    private function kodeClusterBebas(Project $project): string
    {
        $angka = (int) preg_replace('/\D/', '', (string) ($project->code ?: self::PROJECT_CODE));
        $angka = $angka ?: (int) self::PROJECT_CODE;

        while (Cluster::where('code', str_pad((string) $angka, 4, '0', STR_PAD_LEFT))->exists()) {
            $angka++;
        }

        return str_pad((string) $angka, 4, '0', STR_PAD_LEFT);
    }

    /** COA dicari lewat nama; dibuat hanya bila memang belum ada. */
    private function account(string $nama, string $kategori): Account
    {
        $account = Account::whereRaw('UPPER(TRIM(name)) = ?', [$nama])->first();

        if ($account) {
            return $account;
        }

        return Account::create([
            'name'     => $nama,
            'category' => $kategori,
            'status'   => 'ACTIVE',
        ]);
    }

    /**
     * Kode budget plan proyek: BD-{MM}{YY}{kodeProject(4)}{urut(4)} — format
     * yang sama dengan BudgetPlanController::generateCode().
     */
    private function kodeBudget(Project $project): string
    {
        $poCode = str_pad($project->code ?? '0000', 4, '0', STR_PAD_LEFT);
        $prefix = 'BD-' . now()->format('m') . now()->format('y') . $poCode;

        $urut = BudgetPlan::where('code', 'like', $prefix . '%')->count();

        do {
            $urut++;
            $code = $prefix . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
        } while (BudgetPlan::where('code', $code)->exists());

        return $code;
    }
}

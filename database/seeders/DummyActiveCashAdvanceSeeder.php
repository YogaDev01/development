<?php

namespace Database\Seeders;

use App\Models\Beneficiary;
use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\Cluster;
use App\Models\RequestType;
use Illuminate\Database\Seeder;

/**
 * Cash Advance aktif untuk menguji kartu "Active Cash Advance"
 * (Summary › Summary Budget).
 *
 * Kartu itu hanya menghitung Cost Request bertipe Cash Advance yang berstatus
 * PAID, SETTLE_CHECKED atau SETTLE_VERIFIED. Di basis data ini cuma ada SATU
 * yang memenuhi syarat, sehingga tabelnya tampil dengan satu baris — tidak ada
 * yang bisa diurutkan, dan pilihan urutan apa pun terlihat sama saja.
 *
 * ANGKA & NAMA DI SINI SENGAJA DIPILIH agar urutan menurut NILAI dan urutan
 * menurut ABJAD menghasilkan susunan yang JELAS BERBEDA di ketiga lapisnya.
 * Kalau penerima dana dengan nilai terbesar kebetulan juga yang paling awal
 * abjadnya, kedua pilihan urutan akan tampak tidak melakukan apa-apa dan
 * pengujiannya jadi tidak membuktikan apa pun:
 *
 *   APRIADMIN  abjad: Abe, Bery, Rohendra, Wilda, Yuni
 *              nilai: Wilda, Rohendra, Yuni, Bery, Abe
 *   HANI21     abjad: Ade, Garka, Imron, Neti, Thoyiban
 *              nilai: Garka, Imron, Neti, Thoyiban, Ade
 *
 * Lapis manager juga terbalik: HANI21 memegang nilai lebih besar, tapi
 * APRIADMIN lebih dulu menurut abjad.
 *
 * Penerima dana dipilih lewat NICKNAME, bukan id, supaya seeder ini tidak
 * bergantung pada nomor baris yang bisa berbeda di instalasi lain.
 *
 * Setiap baris ditandai notes = 'DUMMY_ACA_SEED' — penanda itu tidak tampil di
 * kartu, dan dipakai untuk menghapus seluruh dummy sekaligus.
 *
 * Jalankan:  php artisan db:seed --class=DummyActiveCashAdvanceSeeder
 * Hapus:     delete from budget_request where notes = 'DUMMY_ACA_SEED';
 */
class DummyActiveCashAdvanceSeeder extends Seeder
{
    private const PENANDA = 'DUMMY_ACA_SEED';

    /**
     * [nickname penerima dana, nominal, status, keterangan].
     *
     * Dikelompokkan per manager lewat budget plan yang dipakai, karena manager
     * di kartu itu datang dari budget_plan -> project -> manager, bukan dari
     * kolom di Cost Request-nya sendiri.
     */
    private const DATA = [
        // Budget plan milik project ber-manager APRIADMIN.
        'apriadmin' => [
            ['Wilda',     45_000_000, 'PAID',            'Uang muka mobilisasi tim penarikan kabel'],
            ['Wilda',     22_500_000, 'SETTLE_CHECKED',  'Uang muka sewa crane termin kedua'],
            ['Rohendra',  31_200_000, 'PAID',            'Uang muka pengadaan tiang dan aksesoris'],
            ['Rohendra',   8_400_000, 'SETTLE_VERIFIED', 'Uang muka konsumsi dan akomodasi tim'],
            ['Yuni',      27_800_000, 'PAID',            'Uang muka perizinan galian jalur'],
            ['Bery',      12_750_000, 'SETTLE_CHECKED',  'Uang muka transport material batch 1'],
            ['Abe',        6_300_000, 'PAID',            'Uang muka operasional survey lanjutan'],
        ],
        // Budget plan milik project ber-manager HANI21.
        'hani21' => [
            ['Garka',     52_000_000, 'PAID',            'Uang muka pekerjaan sipil pondasi tower'],
            ['Garka',     18_600_000, 'SETTLE_VERIFIED', 'Uang muka sewa genset dan bahan bakar'],
            ['Imron',     41_500_000, 'PAID',            'Uang muka pengadaan perangkat OLT'],
            ['Neti',      24_000_000, 'SETTLE_CHECKED',  'Uang muka instalasi homepass zona A'],
            ['Neti',      11_300_000, 'PAID',            'Uang muka alat ukur dan kalibrasi'],
            ['Thoyiban',  14_700_000, 'SETTLE_VERIFIED', 'Uang muka pemasangan CCTV lantai 1-3'],
            ['Ade',        9_150_000, 'PAID',            'Uang muka dokumentasi dan drawing'],
        ],
    ];

    public function run(): void
    {
        $sudahAda = BudgetRequest::where('notes', self::PENANDA)->count();

        if ($sudahAda > 0) {
            $this->command?->warn("Sudah ada {$sudahAda} Cash Advance dummy — dilewati.");
            $this->command?->line('Hapus dulu bila ingin dibuat ulang:');
            $this->command?->line("  delete from budget_request where notes = '" . self::PENANDA . "';");
            return;
        }

        $tipe = RequestType::whereRaw('LOWER(name) = ?', ['cash advance'])->first();

        if (! $tipe) {
            $this->command?->error("Tipe 'Cash Advance' tidak ada di tabel request_type — dibatalkan.");
            return;
        }

        $dibuat = 0;

        foreach (self::DATA as $usernameManager => $baris) {
            /*
             | Budget plan dicari lewat manager project-nya, bukan lewat id
             | tetap: kartu Active Cash Advance mengelompokkan berdasarkan
             | project.manager, jadi itu pula yang menentukan baris ini jatuh
             | ke grup manager yang mana.
             */
            $plans = BudgetPlan::whereHas(
                'project.manager',
                fn ($q) => $q->whereRaw('LOWER(username) = ?', [$usernameManager])
            )->with('project')->get();

            if ($plans->isEmpty()) {
                $this->command?->warn("Tidak ada Budget Plan milik manager '{$usernameManager}' — dilewati.");
                continue;
            }

            foreach ($baris as $i => [$nickname, $nominal, $status, $keterangan]) {
                $penerima = Beneficiary::whereRaw('LOWER(nickname) = ?', [strtolower($nickname)])->first();

                if (! $penerima) {
                    $this->command?->warn("Penerima dana '{$nickname}' tidak ada — dilewati.");
                    continue;
                }

                // Disebar ke beberapa budget plan manager yang sama supaya
                // datanya tidak menumpuk di satu PO saja.
                $plan = $plans[$i % $plans->count()];

                $cluster = Cluster::where('project_id', $plan->project_id)
                    ->where('status', 'ACTIVE')
                    ->value('id');

                BudgetRequest::create([
                    'code'           => BudgetRequest::generateCode((string) ($plan->project->code ?? '0000')),
                    'description'    => strtoupper($keterangan),
                    'amount'         => $nominal,
                    'notes'          => self::PENANDA,
                    'applicant_id'   => $plan->project->manager_id,
                    'beneficiary_id' => $penerima->id,
                    'type_id'        => $tipe->id,
                    'cluster_id'     => $cluster,
                    'budget_plan_id' => $plan->id,
                    'status'         => $status,
                    'submitted_at'   => now()->subDays(20),
                    'approved_at'    => now()->subDays(16),
                    'paid_at'        => now()->subDays(12),
                ]);

                $dibuat++;
            }
        }

        $this->command?->newLine();
        $this->command?->info("{$dibuat} Cash Advance dummy dibuat (status PAID / SETTLE_CHECKED / SETTLE_VERIFIED).");
        $this->command?->info('Lihat di: Summary › Summary Budget › Active Cash Advance');
    }
}

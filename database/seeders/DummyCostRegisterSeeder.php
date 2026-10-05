<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| DUMMY COST REGISTER (Fixed Cost & Overhead manual)
|--------------------------------------------------------------------------
| Mengisi halaman /cost-register dengan contoh data yang menyentuh semua
| keadaan yang bisa dilihat/diuji dari modul ini:
|
|   - tipe FIXED_COST dan OVERHEAD (kartu ringkasan per tipe),
|   - status DRAFT (bisa Submit/Hapus/Batal), SUBMITTED (siap diambil
|     Treasury), PAID, dan CANCELLED,
|   - satu DRAFT sengaja TANPA beneficiary, supaya penolakan Submit
|     ("Isi dulu Beneficiary") bisa dicoba,
|   - satu alokasi overhead ber-batch_code: satu pool dibagi ke beberapa PO,
|   - sebagian baris punya lampiran di disk `secure` (uji SecureFileController).
|
| Status QUEUED TIDAK dibuat: status itu ditetapkan Treasury saat baris masuk
| batch (daily_transaction_items.cost_register_id). Menulisnya langsung akan
| memunculkan baris QUEUED tanpa batch — tidak mencerminkan keadaan nyata mana
| pun. Untuk mengujinya: pakai baris SUBMITTED lewat Daily Transaction.
|
| Isi catatan ditulis seperti data sungguhan (tanpa penanda apa pun) supaya
| tampilan layar mewakili keadaan nyata. Penanda data contoh = baris yang
| dibuat seeder ini saja.
*/
class DummyCostRegisterSeeder extends Seeder
{
    public function run(): void
    {
        $projects = DB::table('projects')->orderBy('id')->limit(6)->pluck('id')->all();

        if (empty($projects)) {
            $this->command?->warn('Belum ada project/PO. Jalankan seeder project dulu.');
            return;
        }

        // Role di tabel users = role_id ke tabel roles (nama role), bukan kolom
        // string. Pakai Finance kalau ada, kalau tidak user mana pun.
        $actor = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereIn(DB::raw('LOWER(roles.name)'), ['finance', 'finance_manager', 'superadmin'])
            ->orderByRaw("FIELD(LOWER(roles.name), 'finance', 'finance_manager', 'superadmin')")
            ->value('users.id') ?? DB::table('users')->value('id');

        $beneficiaries = DB::table('beneficiaries')
            ->where('status', 'ACTIVE')->orderBy('id')->limit(4)->pluck('id')->all();

        if (empty($beneficiaries)) {
            $beneficiaries = DB::table('beneficiaries')->orderBy('id')->limit(4)->pluck('id')->all();
        }

        $sourceFund = DB::table('source_funds')->where('status', 'ACTIVE')->value('id')
            ?? DB::table('source_funds')->value('id');

        /*
        | Rencana baris tunggal. amount = rupiah bulat (integer), sesuai aturan
        | uang di proyek ini.
        */
        $rows = [
            ['FIXED_COST', 'DRAFT',     18_500_000, 'Prorate gaji 2 personel engineering 40% waktu di PO ini', true],
            ['FIXED_COST', 'DRAFT',      7_250_000, 'Sewa alat ukur bulanan unit theodolite + total station',  false],
            ['OVERHEAD',   'SUBMITTED', 12_000_000, 'Alokasi overhead kantor pusat bulan lalu',                true],
            ['FIXED_COST', 'SUBMITTED', 25_750_000, 'Prorate gaji site supervisor + tunjangan lapangan',       true],
            ['OVERHEAD',   'PAID',       9_400_000, 'Alokasi listrik & internet workshop',                     true],
            ['FIXED_COST', 'PAID',      31_200_000, 'Prorate gaji tim QC periode penyelesaian PO',             true],
            ['OVERHEAD',   'CANCELLED',  5_000_000, 'Alokasi overhead dibatalkan, PO digeser ke periode berikutnya', true],
        ];

        $dibuat = 0;
        $idsBerlampiran = [];

        DB::transaction(function () use (
            $rows, $projects, $beneficiaries, $sourceFund, $actor, &$dibuat, &$idsBerlampiran
        ) {
            foreach ($rows as $i => [$type, $status, $amount, $alasan, $pakaiBeneficiary]) {
                $period     = Carbon::now()->startOfMonth()->subMonths(($i % 3) + 1);
                $dibuatPada = (clone $period)->addDays(20 + $i);

                $id = DB::table('cost_registers')->insertGetId([
                    'project_id'     => $projects[$i % count($projects)],
                    'type'           => $type,
                    'amount'         => $amount,
                    'period'         => $period->toDateString(),
                    'notes'          => $alasan,
                    'batch_code'     => null,
                    'status'         => $status,
                    'beneficiary_id' => $pakaiBeneficiary && $beneficiaries
                        ? $beneficiaries[$i % count($beneficiaries)]
                        : null,
                    'source_fund_id' => $status === 'DRAFT' ? null : $sourceFund,
                    'submitted_at'   => in_array($status, ['SUBMITTED', 'PAID'], true)
                        ? (clone $dibuatPada)->addDays(2)
                        : null,
                    'paid_at'        => $status === 'PAID' ? (clone $dibuatPada)->addDays(9) : null,
                    'input_by'       => $actor,
                    'created_at'     => $dibuatPada,
                    'updated_at'     => $dibuatPada,
                ]);

                $dibuat++;

                // Lampiran hanya sebagian baris - halaman harus tetap benar
                // untuk baris tanpa lampiran.
                if ($i % 2 === 0) {
                    $idsBerlampiran[] = $id;
                }
            }

            /*
            | Satu form "Alokasi Overhead" batch: pool 24.000.000 dibagi rata ke
            | 3 PO, ditandai batch_code yang sama.
            */
            $batchCode   = 'OH-' . Carbon::now()->format('ymd') . '-' . strtoupper(Str::random(4));
            $batchPeriod = Carbon::now()->startOfMonth()->subMonth();
            $perProject  = (int) floor(24_000_000 / 3);

            foreach (array_slice($projects, 0, 3) as $k => $projectId) {
                DB::table('cost_registers')->insert([
                    'project_id'     => $projectId,
                    'type'           => 'OVERHEAD',
                    'amount'         => $perProject,
                    'period'         => $batchPeriod->toDateString(),
                    'notes'          => 'Alokasi pool overhead Rp 24.000.000 dibagi rata ke 3 PO',
                    'batch_code'     => $batchCode,
                    'status'         => 'DRAFT',
                    'beneficiary_id' => $beneficiaries ? $beneficiaries[$k % count($beneficiaries)] : null,
                    'source_fund_id' => null,
                    'submitted_at'   => null,
                    'paid_at'        => null,
                    'input_by'       => $actor,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $dibuat++;
            }
        });

        // Lampiran: file nyata di disk `secure` supaya tombol unduh benar-benar
        // bisa dicoba lewat SecureFileController.
        $lampiran = 0;

        foreach ($idsBerlampiran as $id) {
            $path = 'cost_register_attachments/dummy-cost-register-' . $id . '.txt';

            Storage::disk('secure')->put(
                $path,
                'Lampiran Cost Register #' . $id . "\nDasar perhitungan prorate/alokasi.\n"
            );

            DB::table('cost_register_attachments')->insert([
                'cost_register_id' => $id,
                'title'            => 'dasar-perhitungan-' . $id . '.txt',
                'file_path'        => $path,
                'uploaded_by'      => $actor,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $lampiran++;
        }

        $this->command?->info('Dummy Cost Register: ' . $dibuat . ' baris, ' . $lampiran . ' lampiran.');
    }
}

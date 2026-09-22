<?php

namespace Database\Seeders;

use App\Models\Beneficiary;
use App\Models\MasterPic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Isi kolom PIC di Beneficiary Master dengan kaitan dummy.
 *
 * YANG KOSONG BUKAN MASTER PIC-NYA. Tabel master_pics sudah berisi 55 PIC
 * sungguhan; yang kosong adalah kolom penghubungnya, beneficiaries.pic_id —
 * 777 dari 778 baris bernilai NULL, sehingga kolom PIC di layar hampir
 * seluruhnya menampilkan "-". Jadi seeder ini TIDAK membuat orang baru:
 * menambah nama karangan ke tabel master hanya mengotori data acuan, dan
 * kolomnya justru jadi tidak enak dipakai untuk mencoba tampilan.
 *
 * Yang dibuat di sini adalah KAITANNYA — PIC yang sudah ada ditunjuk sebagai
 * penanggung jawab beneficiary yang belum punya. Kaitannya karangan, jadi
 * jangan dijadikan acuan siapa menangani siapa; tujuannya supaya kolom PIC
 * terisi dan pengembangan (tampilan, urutan, filter, ekspor) bisa dilanjutkan.
 *
 * Jenis PIC disesuaikan kategori beneficiary-nya: INTERNAL dapat PIC bertipe
 * 'internal', selain itu dapat 'customer'. Tanpa itu daftarnya akan berisi
 * pasangan yang mustahil dan salah membentuk dugaan saat dilihat sekilas.
 *
 * Pembagiannya memakai sisa bagi atas urutan id, bukan acak: hasilnya sama
 * setiap kali dijalankan, jadi tangkapan layar dan catatan uji tetap cocok.
 *
 * Aman diulang: beneficiary yang SUDAH punya pic_id tidak pernah disentuh,
 * termasuk satu-satunya kaitan asli yang ada sekarang (#784 ARKA -> pic 5).
 *
 * Jalankan:  php artisan db:seed --class=DummyBeneficiaryPicSeeder
 * Hapus:     update beneficiaries set pic_id = null where id <> 784;
 *            (784 = satu-satunya baris yang sudah berisi sebelum seeder ini)
 */
class DummyBeneficiaryPicSeeder extends Seeder
{
    public function run(): void
    {
        $picsPerType = MasterPic::where('status', 'ACTIVE')
            ->orderBy('id')
            ->get(['id', 'type'])
            ->groupBy('type')
            ->map(fn ($grup) => $grup->pluck('id')->all());

        if ($picsPerType->isEmpty()) {
            $this->command?->warn('Tidak ada Master PIC berstatus ACTIVE — tidak ada yang bisa dikaitkan.');
            return;
        }

        // Cadangan kalau salah satu tipe tidak punya anggota sama sekali:
        // lebih baik terisi PIC bertipe lain daripada kolomnya tetap kosong.
        $semua = $picsPerType->flatten()->all();

        $belumPunya = Beneficiary::whereNull('pic_id')
            ->orderBy('id')
            ->get(['id', 'category']);

        if ($belumPunya->isEmpty()) {
            $this->command?->info('Semua beneficiary sudah punya PIC — tidak ada yang diubah.');
            return;
        }

        /*
        | Dikelompokkan dulu per pic_id, lalu satu UPDATE per PIC.
        | Meng-update 777 baris satu per satu berarti 777 query untuk hasil
        | yang bisa dicapai dengan 55.
        */
        $perPic = [];
        foreach ($belumPunya->values() as $urutan => $beneficiary) {
            $tipe    = $beneficiary->category === 'INTERNAL' ? 'internal' : 'customer';
            $kandidat = $picsPerType[$tipe] ?? $semua;
            $picId   = $kandidat[$urutan % count($kandidat)];

            $perPic[$picId][] = $beneficiary->id;
        }

        DB::transaction(function () use ($perPic) {
            foreach ($perPic as $picId => $beneficiaryIds) {
                foreach (array_chunk($beneficiaryIds, 500) as $bagian) {
                    Beneficiary::whereIn('id', $bagian)->update(['pic_id' => $picId]);
                }
            }
        });

        $this->command?->info(
            'PIC dummy terpasang: ' . $belumPunya->count() . ' beneficiary '
            . 'dikaitkan ke ' . count($perPic) . ' Master PIC.'
        );
    }
}

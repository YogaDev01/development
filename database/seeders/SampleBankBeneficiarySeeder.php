<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Beneficiary;
use App\Models\MasterPic;
use Illuminate\Database\Seeder;

/**
 * Data contoh: beneficiary ber-rekening untuk PIC yang belum punya satu pun.
 *
 * Kolom Bank di Daftar PIC menandai "In-House" selama PIC itu punya beneficiary
 * terhubung. Dua belas PIC belum punya, jadi kolomnya selalu "-" dan tampilannya
 * tidak bisa dicoba dari sisi itu. Seeder ini mengisi kekurangan tersebut.
 *
 * Nama beneficiary mengikuti nama PIC-nya, bukan nama karangan: begitu dilihat
 * di layar, pasangannya langsung masuk akal dan tidak membentuk dugaan yang
 * salah tentang siapa menangani siapa.
 *
 * KATEGORI disamakan dengan jenis PIC-nya — PIC internal dapat beneficiary
 * INTERNAL, selain itu EXTERNAL. Tanpa itu daftarnya berisi pasangan yang
 * mustahil.
 *
 * BANK dan NOMOR REKENING dibagi memakai sisa bagi atas urutan id, bukan acak:
 * hasilnya sama setiap kali dijalankan, jadi tangkapan layar dan catatan uji
 * tetap cocok. Nomor rekeningnya jelas bukan nomor sungguhan.
 *
 * Aman diulang: PIC yang sudah punya beneficiary tidak pernah disentuh.
 *
 * Jalankan:  php artisan db:seed --class=SampleBankBeneficiarySeeder
 * Hapus:     delete from beneficiaries where account_number like '9%' and ...
 *            (lihat penanda di bawah — semua baris buatan seeder ini memakai
 *            awalan nomor rekening 8800)
 */
class SampleBankBeneficiarySeeder extends Seeder
{
    /** Awalan nomor rekening penanda, supaya baris buatan seeder ini mudah dicari. */
    private const AWALAN_REKENING = '8800';

    public function run(): void
    {
        $banks = Bank::orderBy('id')->get(['id', 'name']);

        if ($banks->isEmpty()) {
            $this->command?->warn('Tabel banks kosong — tidak ada bank yang bisa dipasang.');
            return;
        }

        $pics = MasterPic::doesntHave('beneficiaries')
            ->orderBy('id')
            ->get(['id', 'pic', 'type']);

        if ($pics->isEmpty()) {
            $this->command?->info('Semua PIC sudah punya beneficiary — tidak ada yang ditambahkan.');
            return;
        }

        $dibuat = 0;

        foreach ($pics->values() as $urutan => $pic) {
            $bank = $banks[$urutan % $banks->count()];

            Beneficiary::create([
                'nickname'       => $pic->pic,
                'complete_name'  => $pic->pic,
                'category'       => $pic->type === 'internal' ? 'INTERNAL' : 'EXTERNAL',
                'pic_id'         => $pic->id,
                'contact'        => null,
                'bank_id'        => $bank->id,
                'account_number' => self::AWALAN_REKENING . str_pad((string) $pic->id, 8, '0', STR_PAD_LEFT),
                'status'         => 'ACTIVE',
                'input_by'       => null,
            ]);

            $this->command?->line(sprintf('  %-22s -> %s', $pic->pic, $bank->name));
            $dibuat++;
        }

        $this->command?->info($dibuat . ' beneficiary contoh dibuat; kolom Bank PIC terkait kini "In-House".');
    }
}

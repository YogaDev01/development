<?php

namespace Database\Seeders;

use App\Models\MasterPic;
use App\Models\Site;
use Illuminate\Database\Seeder;

/**
 * PIC dummy bertipe `site_keeper` di Personal Master > PIC.
 *
 * Pilihan Site Keeper di form Tambah/Ubah Site diambil dari master PIC aktif
 * bertipe `site_keeper` (SiteController::keeperOptions()). Saat ini tabel
 * master_pics belum tentu punya baris bertipe itu, sehingga dropdown-nya
 * kosong dan tampilan form tidak bisa dicoba. Seeder ini mengisi kekosongan
 * tersebut dengan sepuluh nama karangan.
 *
 * Datanya KARANGAN - nama, nomor HP, dan email tidak merujuk orang nyata.
 * Jangan dijalankan di database yang dipakai sungguhan.
 *
 * Aman diulang: baris dicocokkan dari nama + tipe, jadi menjalankan seeder
 * dua kali tidak menggandakan data.
 *
 * Jalankan:  php artisan db:seed --class=DummySiteKeeperPicSeeder
 * Hapus:     delete from master_pics where type = 'site_keeper' and phone like '0812345001%';
 *            (awalan nomor HP di bawah hanya dipakai oleh seeder ini)
 */
class DummySiteKeeperPicSeeder extends Seeder
{
    /** Nama penjaga site karangan beserta jabatan dan kontaknya. */
    private const KEEPERS = [
        ['Agus Supriyadi', 'Penjaga Site', '081234500101'],
        ['Budi Hartono', 'Penjaga Site', '081234500102'],
        ['Dedi Kurniawan', 'Satpam Sekolah', '081234500103'],
        ['Eko Prasetyo', 'Penjaga Site', '081234500104'],
        ['Fajar Ramadhan', 'Operator Lab', '081234500105'],
        ['Hendra Gunawan', 'Penjaga Site', '081234500106'],
        ['Iwan Setiawan', 'Satpam Kantor', '081234500107'],
        ['Joko Santoso', 'Penjaga Site', '081234500108'],
        ['Rudi Hermawan', 'Operator Lab', '081234500109'],
        ['Slamet Riyadi', 'Penjaga Site', '081234500110'],
    ];

    public function run(): void
    {
        foreach (self::KEEPERS as [$name, $position, $phone]) {
            MasterPic::updateOrCreate(
                ['type' => Site::KEEPER_PIC_TYPE, 'pic' => $name],
                [
                    'position' => $position,
                    'phone' => $phone,
                    'status' => 'ACTIVE',
                ],
            );
        }

        $this->command?->info('Site Keeper dummy: '.count(self::KEEPERS).' PIC siap dipakai di form Site.');
    }
}

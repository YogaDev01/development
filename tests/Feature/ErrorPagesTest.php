<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Halaman galat harus SELALU berhasil tampil.
 *
 * Itu bukan basa-basi: layouts.app memanggil Auth::user()->effectiveRole()
 * tanpa penjaga null, jadi halaman galat yang mewarisinya akan ikut melempar
 * galat untuk pengunjung yang belum login - dan yang tampil ke pengguna justru
 * halaman bawaan Laravel, persis keadaan yang ingin diperbaiki. Uji ini yang
 * menjaga supaya percabangan @extends di tiap halaman galat tidak diam-diam
 * rusak lagi.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase, MakesTesysData;

    public static function halamanGalat(): array
    {
        return [
            '403' => [403, 'Akses ditolak'],
            '404' => [404, 'Halaman tidak ditemukan'],
            '419' => [419, 'Sesi kedaluwarsa'],
            '429' => [429, 'Terlalu banyak permintaan'],
            '500' => [500, 'Terjadi kesalahan pada sistem'],
        ];
    }

    // PHPUnit 12 tidak lagi membaca anotasi @dataProvider — hanya atribut.
    #[DataProvider('halamanGalat')]
    public function test_halaman_galat_tampil_untuk_tamu(int $kode, string $judul): void
    {
        $html = view("errors.{$kode}", [
            'exception' => new \Symfony\Component\HttpKernel\Exception\HttpException($kode),
        ])->render();

        $this->assertStringContainsString($judul, $html);
        $this->assertStringContainsString((string) $kode, $html);

        /*
        | Tamu diarahkan ke login, bukan ke dashboard yang akan menolaknya.
        |
        | 500 DIKECUALIKAN dengan sengaja: halaman itu tidak memanggil
        | Auth::check() sama sekali, karena pada galat internal sesi atau basis
        | datanya bisa jadi justru yang rusak - memanggilnya berisiko melempar
        | galat kedua saat merender halaman galat. Harganya kecil: tamu yang
        | menekan "Ke Dashboard" hanya dialihkan ke login satu langkah lagi.
        */
        if ($kode !== 500) {
            $this->assertStringContainsString('login', $html);
        }
    }

    #[DataProvider('halamanGalat')]
    public function test_halaman_galat_tampil_untuk_pengguna_login(int $kode, string $judul): void
    {
        $this->actingAs($this->buatUser());

        $html = view("errors.{$kode}", [
            'exception' => new \Symfony\Component\HttpKernel\Exception\HttpException($kode),
        ])->render();

        $this->assertStringContainsString($judul, $html);
    }

    /**
     * Pesan dari abort(403, '...') harus SAMPAI ke pengguna.
     *
     * Seluruh aplikasi memanggil abort() dengan pesan berbahasa Indonesia yang
     * ditulis dengan baik ("Anda tidak memiliki akses ke Cost Request ini.").
     * Kalau halaman 403 mengabaikannya dan menampilkan teks umum, informasi
     * paling berguna di halaman itu justru hilang.
     */
    public function test_pesan_abort_ditampilkan_pada_403(): void
    {
        $pesan = 'Anda tidak memiliki akses ke Cost Request ini.';

        $html = view('errors.403', [
            'exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(403, $pesan),
        ])->render();

        $this->assertStringContainsString($pesan, $html);
    }

    /**
     * Sebaliknya: 403 tanpa pesan tidak boleh menampilkan baris kosong.
     */
    public function test_403_tanpa_pesan_memakai_teks_bawaan(): void
    {
        $html = view('errors.403', [
            'exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(403, ''),
        ])->render();

        $this->assertStringContainsString('tidak memiliki hak', $html);
    }

    /** Membuat satu user beserta role-nya, memakai helper suite yang sudah ada. */
    private function buatUser(): User
    {
        return $this->userWithRole('superadmin');
    }
}

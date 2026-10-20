<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Membuat akun `users` untuk orang-orang di Struktur Organisasi
 * (sumber data: TeamStrukturOrganisasiSeeder::STRUKTUR) yang belum punya akun,
 * supaya mereka bisa dipasang sebagai anggota team.
 *
 * Aturan:
 *   - Orang yang akunnya SUDAH ADA (dicocokkan lewat email, lalu nama) dilewati
 *     — seeder ini tidak pernah mengubah akun yang sudah ada.
 *   - Email kosong di struktur diisi alamat penampung `<username>@noemail.terex.id`
 *     supaya kolom unik `email` tetap terisi. Alamat ini TIDAK bisa menerima surat;
 *     ganti lewat menu User & Access begitu alamat aslinya diketahui.
 *   - Password awal sama untuk semua akun baru: lihat PASSWORD_AWAL di bawah.
 *     Wajib diganti oleh masing-masing orang.
 *   - Role ditentukan dari level struktur: 1 manager, 2 supervisor, 3 staff,
 *     4 teknisi.
 *
 * Aman dijalankan ulang.
 *
 *   php artisan db:seed --class=UserStrukturOrganisasiSeeder
 */
class UserStrukturOrganisasiSeeder extends Seeder
{
    private const PASSWORD_AWAL = 'Terex#2026';

    private const ROLE_PER_LEVEL = [
        1 => 'manager',
        2 => 'supervisor',
        3 => 'staff',
        4 => 'teknisi',
    ];

    public function run(): void
    {
        $roles = Role::pluck('id', 'name');

        foreach (self::ROLE_PER_LEVEL as $level => $namaRole) {
            if (! isset($roles[$namaRole])) {
                $this->command?->error("Role '{$namaRole}' (level {$level}) tidak ada di tabel roles. Seeder dihentikan.");

                return;
            }
        }

        $dibuat = [];
        $dilewati = 0;

        foreach (TeamStrukturOrganisasiSeeder::STRUKTUR as $anggota) {
            foreach ($anggota as [$nama, $email, $jabatan, $level]) {
                if ($this->cariUser($nama, $email)) {
                    $dilewati++;

                    continue;
                }

                $username = $this->usernameUnik($nama);

                $user = User::create([
                    'name' => $nama,
                    'username' => $username,
                    'email' => $email ?: $username.'@noemail.terex.id',
                    'password' => Hash::make(self::PASSWORD_AWAL),
                    'role_id' => $roles[self::ROLE_PER_LEVEL[$level]],
                    'status' => 'ACTIVE',
                ]);

                $dibuat[] = $user->username.' ('.$nama.', '.self::ROLE_PER_LEVEL[$level].')';
            }
        }

        $this->command?->info('Akun baru: '.count($dibuat).', sudah ada (dilewati): '.$dilewati);

        foreach ($dibuat as $baris) {
            $this->command?->line('  + '.$baris);
        }

        if ($dibuat) {
            $this->command?->warn('Password awal semua akun baru: '.self::PASSWORD_AWAL.' — wajib diganti.');
        }
    }

    /** Email lebih dipercaya daripada nama; nama dicocokkan tanpa beda huruf besar/kecil. */
    private function cariUser(string $nama, ?string $email): ?User
    {
        if ($email) {
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

            if ($user) {
                return $user;
            }
        }

        return User::whereRaw('LOWER(name) = ?', [mb_strtolower($nama)])->first();
    }

    /** `rizky.saepul`, lalu `rizky.saepul2`, dst. bila sudah terpakai. */
    private function usernameUnik(string $nama): string
    {
        $dasar = Str::slug($nama, '.');
        $username = $dasar;
        $n = 1;

        while (User::where('username', $username)->exists()) {
            $username = $dasar.(++$n);
        }

        return $username;
    }
}

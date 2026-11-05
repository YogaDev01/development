<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Data contoh tab Organisasi di halaman Team: 50 user fiktif, 10 team,
 * 4 department. HANYA untuk lokal — seeder menolak berjalan di environment
 * lain karena membuat akun ACTIVE dengan password yang diketahui.
 *
 * Susunan sengaja memakai semua aturan TeamController::buildOrgChart():
 *   - Leader yang juga anggota team lain: Finance, HR & GA, Operation di bawah
 *     Direksi; Treasury di bawah Finance; ketiga team Field di bawah Operation.
 *   - Induk Team (parent_team_id): NOC dan Logistik di bawah Operation.
 *   - Satu leader memimpin dua team: Teguh Santoso (Field Sumatera & Jawa).
 *   - Anggota di dua team (garis putus-putus): Laila Rahmawati, Deni Lubis.
 *
 * Team primary = team pertama tempat orang itu muncul di TEAMS.
 * Tidak ada team yang ditautkan ke Access Group, jadi tidak ada akses PO baru.
 *
 * Aman dijalankan ulang: user dicari lewat username (akun yang sudah ada tidak
 * diubah), team & department lewat code, anggota lewat pasangan (team, user).
 *
 * Penanda untuk dibersihkan: username `dmy.*`, email `*@dummy.tesys.test`,
 * code team `DMY-*`, code department `DMY-*`. Nomor telepon sengaja kosong
 * supaya tidak ada WhatsApp terkirim.
 *
 * Login: username (mis. dmy.bambang.wicaksono), password "password123".
 *
 *   php artisan db:seed --class=DummyTeamOrganisasiSeeder
 */
class DummyTeamOrganisasiSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    private const EMAIL_DOMAIN = 'dummy.tesys.test';

    /** Role per kelompok; dipakai nama pertama yang ada di tabel roles. */
    private const ROLE_KANDIDAT = [
        'manager'    => ['manager'],
        'supervisor' => ['supervisor', 'manager'],
        'finance'    => ['finance', 'staff'],
        'staff'      => ['staff'],
        'teknisi'    => ['teknisi', 'technician', 'staff'],
    ];

    private const DEPARTMENTS = [
        'DMY-DIR' => 'Direksi (Dummy)',
        'DMY-FIN' => 'Finance (Dummy)',
        'DMY-HR'  => 'Human Capital (Dummy)',
        'DMY-OPS' => 'Operation (Dummy)',
    ];

    /**
     * Urutan penting: team induk ditulis lebih dulu daripada anaknya.
     * members: [nama, jabatan, level, kelompok role]; level kecil = lebih tinggi.
     */
    public const TEAMS = [
        [
            'code' => 'DMY-01', 'name' => 'Direksi', 'department' => 'DMY-DIR',
            'parent' => null, 'leader' => 'Bambang Wicaksono',
            'members' => [
                ['Bambang Wicaksono', 'Direktur Utama', 1, 'manager'],
                ['Ratna Kusumawati', 'Direktur Operasional', 2, 'manager'],
                ['Hendro Prasetyo', 'Direktur Keuangan', 2, 'manager'],
                ['Lestari Handayani', 'Kepala HR & GA', 2, 'manager'],
            ],
        ],
        [
            'code' => 'DMY-02', 'name' => 'Finance & Accounting', 'department' => 'DMY-FIN',
            'parent' => null, 'leader' => 'Hendro Prasetyo',
            'members' => [
                ['Hendro Prasetyo', 'Direktur Keuangan', 1, 'manager'],
                ['Dewi Puspitasari', 'Manager Treasury', 2, 'manager'],
                ['Yudi Hartanto', 'Supervisor Accounting', 2, 'supervisor'],
                ['Mega Anggraini', 'Staff Accounting', 3, 'finance'],
                ['Bayu Pamungkas', 'Staff Accounting', 3, 'finance'],
                ['Citra Maharani', 'Cost Control', 3, 'finance'],
                ['Galih Saputro', 'Collection Officer', 3, 'finance'],
            ],
        ],
        [
            'code' => 'DMY-03', 'name' => 'Treasury', 'department' => 'DMY-FIN',
            'parent' => null, 'leader' => 'Dewi Puspitasari',
            'members' => [
                ['Dewi Puspitasari', 'Manager Treasury', 1, 'manager'],
                ['Rangga Wijaya', 'Staff Treasury', 2, 'finance'],
                ['Sari Melati', 'Staff Treasury', 2, 'finance'],
            ],
        ],
        [
            'code' => 'DMY-04', 'name' => 'HR & GA', 'department' => 'DMY-HR',
            'parent' => null, 'leader' => 'Lestari Handayani',
            'members' => [
                ['Lestari Handayani', 'Kepala HR & GA', 1, 'manager'],
                ['Putri Ayuningtyas', 'HR Officer', 2, 'staff'],
                ['Wahyu Setiadi', 'Payroll Officer', 2, 'staff'],
                ['Nining Suryani', 'GA Officer', 2, 'staff'],
            ],
        ],
        [
            'code' => 'DMY-05', 'name' => 'Operation Pusat', 'department' => 'DMY-OPS',
            'parent' => null, 'leader' => 'Ratna Kusumawati',
            'members' => [
                ['Ratna Kusumawati', 'Direktur Operasional', 1, 'manager'],
                ['Teguh Santoso', 'Manager Operation Barat', 2, 'manager'],
                ['Yohanes Manurung', 'Manager Operation Timur', 2, 'manager'],
                ['Intan Permatasari', 'CSO', 3, 'staff'],
                ['Laila Rahmawati', 'Document Control', 3, 'staff'],
            ],
        ],
        [
            'code' => 'DMY-06', 'name' => 'NOC', 'department' => 'DMY-OPS',
            'parent' => 'DMY-05', 'leader' => 'Danang Kurniawan',
            'members' => [
                ['Danang Kurniawan', 'Supervisor NOC', 1, 'supervisor'],
                ['Eka Fitriani', 'NOC Engineer', 2, 'staff'],
                ['Gilang Ramadhan', 'NOC Engineer', 2, 'staff'],
                ['Vina Oktaviani', 'NOC Engineer', 2, 'staff'],
            ],
        ],
        [
            'code' => 'DMY-07', 'name' => 'Logistik', 'department' => 'DMY-OPS',
            'parent' => 'DMY-05', 'leader' => 'Slamet Riyadi',
            'members' => [
                ['Slamet Riyadi', 'Supervisor Logistik', 1, 'supervisor'],
                ['Joko Susilo', 'Staff Logistik', 2, 'staff'],
                ['Agus Wibisono', 'Staff Logistik', 2, 'staff'],
                ['Laila Rahmawati', 'Document Control', 2, 'staff'],
            ],
        ],
        [
            'code' => 'DMY-08', 'name' => 'Field Sumatera', 'department' => 'DMY-OPS',
            'parent' => null, 'leader' => 'Teguh Santoso',
            'members' => [
                ['Teguh Santoso', 'Manager Operation Barat', 1, 'manager'],
                ['Roni Siregar', 'Supervisor Sumatera', 2, 'supervisor'],
                ['Alfian Nasution', 'Teknisi Medan', 3, 'teknisi'],
                ['Deni Lubis', 'Teknisi Padang', 3, 'teknisi'],
                ['Firman Harahap', 'Teknisi Pekanbaru', 3, 'teknisi'],
                ['Zulkifli Rangkuti', 'Teknisi Palembang', 3, 'teknisi'],
                ['Yusuf Hasibuan', 'Teknisi Lampung', 3, 'teknisi'],
            ],
        ],
        [
            'code' => 'DMY-09', 'name' => 'Field Jawa', 'department' => 'DMY-OPS',
            'parent' => null, 'leader' => 'Teguh Santoso',
            'members' => [
                ['Teguh Santoso', 'Manager Operation Barat', 1, 'manager'],
                ['Hari Mulyono', 'Supervisor Jawa', 2, 'supervisor'],
                ['Asep Kurnia', 'Teknisi Bandung', 3, 'teknisi'],
                ['Ujang Permana', 'Teknisi Cirebon', 3, 'teknisi'],
                ['Tri Wibowo', 'Teknisi Semarang', 3, 'teknisi'],
                ['Dodi Setiawan', 'Teknisi Surabaya', 3, 'teknisi'],
                ['Iwan Gunawan', 'Teknisi Malang', 3, 'teknisi'],
                ['Deni Lubis', 'Teknisi Cadangan Jawa', 3, 'teknisi'],
            ],
        ],
        [
            'code' => 'DMY-10', 'name' => 'Field Indonesia Timur', 'department' => 'DMY-OPS',
            'parent' => null, 'leader' => 'Yohanes Manurung',
            'members' => [
                ['Yohanes Manurung', 'Manager Operation Timur', 1, 'manager'],
                ['Marthen Wenda', 'Supervisor Maluku & Papua', 2, 'supervisor'],
                ['Arman Daeng', 'Supervisor Kalimantan & Sulawesi', 2, 'supervisor'],
                ['Petrus Kogoya', 'Teknisi Jayapura', 3, 'teknisi'],
                ['Yosua Rumbiak', 'Teknisi Biak', 3, 'teknisi'],
                ['Markus Latuheru', 'Teknisi Ambon', 3, 'teknisi'],
                ['Stevanus Pattinama', 'Teknisi Tual', 3, 'teknisi'],
                ['Ishak Tuhumury', 'Teknisi Ternate', 3, 'teknisi'],
                ['Syamsul Bahri', 'Teknisi Makassar', 3, 'teknisi'],
                ['Rahmat Hidayat', 'Teknisi Palu', 3, 'teknisi'],
                ['Irfan Mappiasse', 'Teknisi Manado', 3, 'teknisi'],
                ['Normansyah', 'Teknisi Balikpapan', 3, 'teknisi'],
                ['Gusti Ardiansyah', 'Teknisi Pontianak', 3, 'teknisi'],
            ],
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('DummyTeamOrganisasiSeeder hanya untuk environment local. Dihentikan.');

            return;
        }

        if (! Team::supportsStructure()) {
            $this->command?->error('Migrasi struktur team (2026_11_06_000100 & 000200) belum dijalankan. Dihentikan.');

            return;
        }

        $roleIds = $this->roleIds();

        if ($roleIds === null) {
            return;
        }

        $userBaru = 0;

        DB::transaction(function () use ($roleIds, &$userBaru) {
            $departments = [];
            foreach (self::DEPARTMENTS as $code => $nama) {
                $departments[$code] = Department::firstOrCreate(
                    ['code' => $code],
                    ['name' => $nama, 'status' => 'ACTIVE'],
                )->id;
            }

            $password = Hash::make(self::PASSWORD);
            $users    = [];
            $teams    = [];

            foreach (self::TEAMS as $data) {
                $team = Team::firstOrNew(['code' => $data['code']]);
                $team->fill([
                    'name'           => $data['name'],
                    'department_id'  => $departments[$data['department']],
                    'parent_team_id' => $data['parent'] ? $teams[$data['parent']] : null,
                    'status'         => 'ACTIVE',
                ])->save();
                $teams[$data['code']] = $team->id;

                foreach ($data['members'] as [$nama, $jabatan, $level, $kelompok]) {
                    $primary = ! isset($users[$nama]);

                    if ($primary) {
                        $username = self::username($nama);
                        $user = User::firstOrCreate(['username' => $username], [
                            'name'     => $nama,
                            'email'    => $username.'@'.self::EMAIL_DOMAIN,
                            'password' => $password,
                            'role_id'  => $roleIds[$kelompok],
                            'status'   => 'ACTIVE',
                        ]);
                        $users[$nama] = $user->id;
                        $userBaru += $user->wasRecentlyCreated ? 1 : 0;
                    }

                    TeamMember::updateOrCreate(
                        ['team_id' => $team->id, 'user_id' => $users[$nama]],
                        ['position' => $jabatan, 'level' => $level, 'is_primary' => $primary, 'status' => 'ACTIVE'],
                    );
                }

                $team->update(['leader_id' => $users[$data['leader']]]);
            }
        });

        $this->command?->info('Selesai: '.count(self::TEAMS).' team tersusun, '.$userBaru.' user baru.');
        $this->command?->warn('Password semua akun dummy: '.self::PASSWORD);
    }

    public static function username(string $nama): string
    {
        return 'dmy.'.Str::slug($nama, '.');
    }

    /** @return array<string, int>|null  id role per kelompok; null bila ada yang tidak tersedia */
    private function roleIds(): ?array
    {
        $roles = Role::pluck('id', 'name');
        $hasil = [];

        foreach (self::ROLE_KANDIDAT as $kelompok => $kandidat) {
            $nama = collect($kandidat)->first(fn ($r) => isset($roles[$r]));

            if (! $nama) {
                $this->command?->error("Tidak ada role untuk kelompok '{$kelompok}' (dicari: ".implode(', ', $kandidat).'). Dihentikan.');

                return null;
            }

            $hasil[$kelompok] = $roles[$nama];
        }

        return $hasil;
    }
}

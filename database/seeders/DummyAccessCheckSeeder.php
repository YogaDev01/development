<?php

namespace Database\Seeders;

use App\Models\AccessGroup;
use App\Models\AccessGroupDetail;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun dummy untuk MEMERIKSA HAK AKSES dengan mata sendiri.
 *
 * Aturan akses di TESYS hanya benar-benar terlihat kalau dibuka oleh role yang
 * bersangkutan, sementara instalasi ini cuma punya akun superadmin — yang
 * meloloskan semua gerbang, sehingga justru role yang paling tidak bisa dipakai
 * untuk menguji pembatasan. "Ganti Role" di dropdown user memang ada, tapi itu
 * preview milik superadmin: sebagian penjaga memperlakukannya berbeda (lihat
 * CheckRole yang me-redirect alih-alih 403 saat sedang preview), jadi ia tidak
 * bisa menggantikan login sungguhan.
 *
 * BEDA DENGAN DummyChainSeeder: seeder itu ikut menulis PO, Cost Request,
 * Invoice dan seluruh rantai transaksinya. Yang ini HANYA membuat akun — tidak
 * menyentuh satu pun tabel transaksi.
 *
 * Password seragam 'password123', mengikuti DummyChainSeeder.
 *
 * Aman diulang: akun yang sudah ada tidak ditimpa, hanya dilewati.
 *
 * Jalankan:  php artisan db:seed --class=DummyAccessCheckSeeder
 * Hapus:     delete from users where username like 'dummy.%';
 *            delete from access_groups where name = 'DUMMY - Semua PO';
 */
class DummyAccessCheckSeeder extends Seeder
{
    /**
     * [nama, username, role].
     *
     * Awalan 'dummy.' dipakai supaya akun-akun ini bisa dikenali dan dihapus
     * sekaligus tanpa perlu mengingat daftarnya satu per satu.
     */
    private const AKUN = [
        // Yang diminta Instruksi 2: boleh melihat & menambah Beneficiary,
        // tidak boleh mengubah atau menghapus.
        ['Dummy Manager',  'dummy.manager',  'manager'],

        // Role tanpa akses master data sama sekali — dipakai memastikan menu
        // Project Monitoring (Instruksi 3) muncul walau seksi Master Data
        // di sidebarnya kosong, dan memastikan Beneficiary tetap tertutup
        // untuk role yang tidak diberi menunya.
        ['Dummy Treasury', 'dummy.treasury', 'treasury'],
    ];

    /** Nama Access Group dummy — dipakai juga sebagai penanda saat menghapus. */
    private const NAMA_ACCESS_GROUP = 'DUMMY - Semua PO';

    public function run(): void
    {
        $hash = Hash::make('password123');

        foreach (self::AKUN as [$nama, $username, $roleName]) {
            $role = Role::whereRaw('LOWER(name) = ?', [$roleName])->first();

            if (! $role) {
                $this->command?->warn("Role '{$roleName}' tidak ada — {$username} dilewati.");
                continue;
            }

            $user = User::firstOrCreate(
                ['username' => $username],
                [
                    'name'              => $nama,
                    'email'             => $username . '@example.test',
                    'password'          => $hash,
                    'role_id'           => $role->id,
                    'status'            => 'ACTIVE',
                    'email_verified_at' => now(),
                ]
            );

            $this->command?->info(
                ($user->wasRecentlyCreated ? 'Dibuat  ' : 'Sudah ada') . "  {$username}  ({$roleName})"
            );

            if ($roleName === 'manager') {
                $this->beriAksesSeluruhPo($user);
            }
        }

        $this->command?->newLine();
        $this->command?->info('Password semua akun dummy: password123');
    }

    /**
     * Manager melihat PO lewat Access Group, bukan lewat role.
     *
     * Tanpa ini halaman Project Monitoring terbuka tapi KOSONG untuk
     * dummy.manager — semua kartu 0 dan tabelnya tidak berisi apa-apa. Itu
     * bukan kesalahan menu-nya, tapi penyaringan di
     * ProjectMonitoringController::accessibleProjectIds(): role 'manager'
     * tidak termasuk PRIVILEGED_ROLES, jadi ia hanya melihat PO yang ada di
     * Access Group-nya.
     *
     * Dibuatkan grup sendiri, bukan menumpang grup yang sudah ada, supaya
     * menghapusnya nanti tidak ikut mengubah akses siapa pun.
     */
    private function beriAksesSeluruhPo(User $user): void
    {
        $group = AccessGroup::firstOrCreate(['name' => self::NAMA_ACCESS_GROUP]);

        $projectIds = Project::pluck('id');

        if ($projectIds->isEmpty()) {
            $this->command?->warn('  Tidak ada PO di tabel projects — Access Group dibiarkan kosong.');
            return;
        }

        $sudahAda = AccessGroupDetail::where('access_group_id', $group->id)
            ->pluck('project_id')
            ->all();

        foreach ($projectIds->reject(fn ($id) => in_array($id, $sudahAda)) as $projectId) {
            AccessGroupDetail::create([
                'access_group_id' => $group->id,
                'project_id'      => $projectId,
            ]);
        }

        // Jangan rampas access group yang sudah dipasang orang lain ke akun ini.
        if ($user->access_group_id !== $group->id) {
            $user->forceFill(['access_group_id' => $group->id])->save();
        }

        $this->command?->info(
            "  Access Group '" . self::NAMA_ACCESS_GROUP . "' -> {$projectIds->count()} PO, dipasang ke {$user->username}"
        );
    }
}

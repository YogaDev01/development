<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Tambah 4 role baru untuk Todo List Request:
    |   - cost_control : mengambil alih step "Finance: Checked -> Verify" &
    |                     "Realized -> Close" (berdampingan dgn role 'finance'
    |                     yang lama, bukan menggantikan)
    |   - accounting    : disediakan untuk fase berikutnya (jurnal), belum ada
    |                     task nyata di Cost Request
    |   - treasury      : disediakan untuk fase berikutnya (batch/Kopra),
    |                     belum ada task nyata di Cost Request
    |   - collection    : disediakan untuk fase berikutnya (modul Invoice),
    |                     tidak menangani Cost Request sama sekali
    |
    | Seeder ini DEFENSIF terhadap skema (pola sama dgn
    | 2026_08_15_000001_add_hr_finance_manager_roles.php) & idempotent.
    |--------------------------------------------------------------------------
    */

    private array $roles = [
        [
            'name'        => 'cost_control',
            'label'       => 'Cost Control',
            'description' => 'Cost Control — verifikasi Cost Request (Checked -> Verified) & close realisasi',
        ],
        [
            'name'        => 'accounting',
            'label'       => 'Accounting',
            'description' => 'Accounting — input jurnal (belum aktif, fase berikutnya)',
        ],
        [
            'name'        => 'treasury',
            'label'       => 'Treasury',
            'description' => 'Treasury — batch pembayaran & submit Kopra (belum aktif, fase berikutnya)',
        ],
        [
            'name'        => 'collection',
            'label'       => 'Collection',
            'description' => 'Collection — modul Invoice (belum aktif, fase berikutnya)',
        ],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        foreach ($this->roles as $role) {
            $exists = DB::table('roles')->where('name', $role['name'])->exists();
            if ($exists) {
                continue;   // idempotent
            }

            $row = ['name' => $role['name']];

            if (Schema::hasColumn('roles', 'label')) {
                $row['label'] = $role['label'];
            }
            if (Schema::hasColumn('roles', 'description')) {
                $row['description'] = $role['description'];
            }
            if (Schema::hasColumn('roles', 'status')) {
                $row['status'] = 'ACTIVE';
            }
            if (Schema::hasColumn('roles', 'guard_name')) {
                $row['guard_name'] = 'web';
            }
            if (Schema::hasColumn('roles', 'created_at')) {
                $row['created_at'] = now();
                $row['updated_at'] = now();
            }

            DB::table('roles')->insert($row);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $names = array_column($this->roles, 'name');

        $roleIds = DB::table('roles')->whereIn('name', $names)->pluck('id');

        if ($roleIds->isNotEmpty() && Schema::hasTable('role_menu_items')) {
            DB::table('role_menu_items')->whereIn('role_id', $roleIds)->delete();
        }

        DB::table('roles')->whereIn('name', $names)->delete();
    }
};

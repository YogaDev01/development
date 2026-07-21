<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Tambah 2 role baru untuk alur Payroll:
    |   - hr              : membuat & submit payroll (dan lihat budget payroll)
    |   - finance_manager : verifikasi payroll (di atas 'finance' biasa)
    |
    | CEO tetap memakai role 'approver' yang sudah ada.
    |
    | Seeder ini DEFENSIF terhadap skema: hanya mengisi kolom yang memang ada
    | di tabel roles (name wajib; label/description/level/guard_name opsional).
    | Aman dijalankan berulang (idempotent).
    |--------------------------------------------------------------------------
    */

    private array $roles = [
        [
            'name'        => 'hr',
            'label'       => 'HR',
            'description' => 'Human Resource — mengelola payroll',
        ],
        [
            'name'        => 'finance_manager',
            'label'       => 'Finance Manager',
            'description' => 'Manajer Finance — verifikasi payroll (akses data gaji)',
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

            // Isi kolom opsional HANYA bila kolomnya ada di tabel
            if (Schema::hasColumn('roles', 'label')) {
                $row['label'] = $role['label'];
            }
            if (Schema::hasColumn('roles', 'description')) {
                $row['description'] = $role['description'];
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

        // Bersihkan pivot menu bila ada, lalu hapus role
        $roleIds = DB::table('roles')->whereIn('name', $names)->pluck('id');

        if ($roleIds->isNotEmpty() && Schema::hasTable('role_menu_items')) {
            DB::table('role_menu_items')->whereIn('role_id', $roleIds)->delete();
        }

        DB::table('roles')->whereIn('name', $names)->delete();
    }
};
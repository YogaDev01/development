<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
    |--------------------------------------------------------------------------
    | Tambah role 'supervisor'.
    |
    | Belum punya task/menu bawaan — hak aksesnya diatur lewat
    | User & Access > Menu Access seperti role lain. Pola defensif &
    | idempotent mengikuti 2026_09_03_000001_add_todo_list_roles.php.
    |--------------------------------------------------------------------------
    */

    private string $name = 'supervisor';

    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        if (DB::table('roles')->where('name', $this->name)->exists()) {
            return;   // idempotent
        }

        $row = ['name' => $this->name];

        if (Schema::hasColumn('roles', 'label')) {
            $row['label'] = 'Supervisor';
        }
        if (Schema::hasColumn('roles', 'description')) {
            $row['description'] = 'Supervisor — pengawas operasional';
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

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')->where('name', $this->name)->value('id');

        if ($roleId && Schema::hasTable('role_menu_items')) {
            DB::table('role_menu_items')->where('role_id', $roleId)->delete();
        }

        DB::table('roles')->where('name', $this->name)->delete();
    }
};

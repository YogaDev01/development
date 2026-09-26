<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Dummy user untuk SETIAP role yang ada di tabel roles.
 *
 * Dipakai buat menguji menu/permission tiap role tanpa harus bikin user manual
 * satu-satu. Idempotent: memakai username sebagai kunci, jadi aman dijalankan
 * berulang — user yang sudah ada hanya di-update role/status-nya.
 *
 * Login: username di bawah, password "password123".
 *
 *   php artisan db:seed --class=DummyUserPerRoleSeeder
 */
class DummyUserPerRoleSeeder extends Seeder
{
    /**
     * Nama tampilan per role. Role yang tidak terdaftar di sini tetap dibuatkan
     * user dengan nama hasil humanize dari nama role-nya.
     */
    private array $labels = [
        'superadmin'      => 'Dummy Super Admin',
        'admin'           => 'Dummy Admin',
        'head'            => 'Dummy Head',
        'hr'              => 'Dummy HR',
        'finance_manager' => 'Dummy Finance Manager',
        'cost_control'    => 'Dummy Cost Control',
        'accounting'      => 'Dummy Accounting',
        'treasury'        => 'Dummy Treasury',
        'collection'      => 'Dummy Collection',
        'approver'        => 'Dummy Approver',
        'manager'         => 'Dummy Manager',
        'finance'         => 'Dummy Finance',
        'maker'           => 'Dummy Maker',
        'guest'           => 'Dummy Guest',
        'staff'           => 'Dummy Staff',
    ];

    public function run(): void
    {
        $now   = now();
        $hash  = Hash::make('password123');
        $roles = DB::table('roles')->orderBy('id')->get(['id', 'name']);

        if ($roles->isEmpty()) {
            $this->command?->warn('Tabel roles kosong. Jalankan RoleSeeder dulu.');
            return;
        }

        $created = 0;
        $updated = 0;

        foreach ($roles as $role) {
            $slug     = strtolower($role->name);
            $username = 'dummy.' . $slug;
            $name     = $this->labels[$slug] ?? 'Dummy ' . ucwords(str_replace('_', ' ', $slug));

            $existing = DB::table('users')->where('username', $username)->first();

            if ($existing) {
                DB::table('users')->where('id', $existing->id)->update([
                    'name'       => $name,
                    'role_id'    => $role->id,
                    'status'     => 'ACTIVE',
                    'updated_at' => $now,
                ]);
                $updated++;
                continue;
            }

            DB::table('users')->insert([
                'name'              => $name,
                'username'          => $username,
                'email'             => $username . '@example.test',
                'phone'             => '0811' . str_pad((string) $role->id, 8, '0', STR_PAD_LEFT),
                'email_verified_at' => $now,
                'password'          => $hash,
                'role_id'           => $role->id,
                'access_group_id'   => null,
                'status'            => 'ACTIVE',
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $created++;
        }

        $this->command?->info("Dummy user per role: {$created} dibuat, {$updated} diperbarui. Password: password123");
    }
}

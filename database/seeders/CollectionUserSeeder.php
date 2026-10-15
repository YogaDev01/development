<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Akun login untuk role 'collection'.
 *
 * Idempotent: username dipakai sebagai kunci, jadi aman dijalankan berulang —
 * user yang sudah ada hanya di-update role/status/password-nya.
 *
 *   php artisan db:seed --class=CollectionUserSeeder
 */
class CollectionUserSeeder extends Seeder
{
    public function run(): void
    {
        $role = DB::table('roles')->where('name', 'collection')->first(['id']);

        if (!$role) {
            $this->command?->warn("Role 'collection' belum ada di tabel roles. Buat role-nya dulu lewat menu Role, lalu jalankan seeder ini lagi.");
            return;
        }

        $now      = now();
        $username = 'collection';

        $existing = DB::table('users')->where('username', $username)->first(['id']);

        $payload = [
            'name'       => 'Collection',
            'role_id'    => $role->id,
            'status'     => 'ACTIVE',
            'password'   => Hash::make('password123'),
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update($payload);
            $this->command?->info("User '$username' diperbarui (role collection, password direset).");
            return;
        }

        DB::table('users')->insert($payload + [
            'username'   => $username,
            'email'      => 'collection@tesys.local',
            'created_at' => $now,
        ]);

        $this->command?->info("User '$username' dibuat dengan password 'password123'.");
    }
}

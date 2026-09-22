<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1) Pastikan seluruh role kanonik tersedia (idempotent).
        $this->call(RoleSeeder::class);

        // 2) Superadmin awal untuk login pertama di instalasi baru (idempotent).
        //    PENTING: ganti password ini segera setelah login pertama.
        $superadmin = Role::where('name', 'superadmin')->first();

        if ($superadmin) {
            User::firstOrCreate(
                ['email' => 'admin@terexsystem.com'],
                [
                    'name'     => 'Super Admin',
                    'username' => 'superadmin',
                    'password' => Hash::make('password'),
                    'role_id'  => $superadmin->id,
                    'status'   => 'ACTIVE',
                ]
            );
        }
    }
}
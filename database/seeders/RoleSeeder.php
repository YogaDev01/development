<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Daftar role kanonik yang dipakai oleh hasRole() di seluruh aplikasi.
     * Aman dijalankan berulang (idempotent) karena memakai firstOrCreate
     * pada kolom unik 'name' — tidak akan menduplikasi atau menimpa role yang sudah ada.
     */
    public function run(): void
    {
        $roles = [
            'superadmin',
            'admin',
            'approver',
            'manager',
            'finance',
            'maker',
            'guest',
        ];

        foreach ($roles as $name) {
            Role::firstOrCreate(
                ['name' => $name],
                ['status' => 'ACTIVE']
            );
        }
    }
}
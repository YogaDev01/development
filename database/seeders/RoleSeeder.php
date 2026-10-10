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
            // Teknisi lapangan (Trouble Ticket): penugasan tiket per site, bukan PIC
            // position 'teknisi' di master_pics — itu entitas lain.
            'technician',
            // Tidak punya menu access apa pun secara default — cuma bisa akses
            // General Feedback (tidak digerbang menu permission, lihat layouts/app.blade.php).
            'staff',
        ];

        foreach ($roles as $name) {
            Role::firstOrCreate(
                ['name' => $name],
                ['status' => 'ACTIVE']
            );
        }
    }
}
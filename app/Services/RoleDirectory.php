<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Siapa saja pemegang sebuah peran.
 *
 * Empat controller sebelumnya punya salinan helper yang sama —
 * headUsers(), financeUsers(), approverUsers() — masing-masing dengan
 * whereRaw("LOWER(name) = 'head'") sendiri. Konsekuensinya: mengganti nama
 * sebuah peran, atau menambah peran baru yang juga berhak menerima notifikasi
 * tahap tertentu, harus diingat di empat tempat. Yang terlupa tidak
 * memunculkan galat — notifikasinya hanya tidak pernah sampai, dan itu baru
 * ketahuan saat ada yang bertanya kenapa approval-nya tidak diberitahukan.
 *
 * Perbandingan nama peran tetap case-insensitive seperti sebelumnya, karena
 * data peran di basis data tidak dijamin seragam kapitalisasinya.
 */
class RoleDirectory
{
    /** Pemegang satu atau beberapa peran, hanya yang berstatus aktif. */
    public function usersWithRoles(array $roles): Collection
    {
        $roles = array_map(fn ($r) => strtolower(trim($r)), $roles);

        return User::whereHas(
                'role',
                fn ($q) => $q->whereIn(DB::raw('LOWER(name)'), $roles)
            )
            ->where('status', 'ACTIVE')
            ->get();
    }

    /** Head — pemeriksa tahap pertama. */
    public function heads(): Collection
    {
        return $this->usersWithRoles(['head']);
    }

    /**
     * Finance — verifikator. 'cost_control' ikut karena peran itu mengambil
     * alih langkah verifikasi Finance pada sebagian alur.
     */
    public function finance(bool $withCostControl = false): Collection
    {
        return $this->usersWithRoles(
            $withCostControl
                ? ['finance', 'finance_manager', 'cost_control']
                : ['finance', 'finance_manager']
        );
    }

    /** Approver (CEO) — pemberi persetujuan akhir. */
    public function approvers(): Collection
    {
        return $this->usersWithRoles(['approver']);
    }

    /** Manager — pengaju. */
    public function managers(): Collection
    {
        return $this->usersWithRoles(['manager']);
    }
}

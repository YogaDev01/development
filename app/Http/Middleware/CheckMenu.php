<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class CheckMenu
{
    /**
     * Gerbang akses halaman berbasis menu-access (tabel role_menu_items).
     *
     * Dipakai di route seperti:  ->middleware('menu:beneficiaries')
     * Bisa menerima banyak key (lolos bila role punya SALAH SATU),
     * berguna untuk halaman yang dipakai lebih dari satu menu, mis:
     *   ->middleware('menu:finance-budgeting,project-budgeting')
     *
     * Logika pengecekan SAMA PERSIS dengan sidebar ($role->hasMenu($key)),
     * sehingga "menu tampil di sidebar" selalu setara dengan "halaman bisa dibuka".
     *
     * @param  string  ...$keys  satu atau lebih menu key
     */
    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        // Wajib login
        if (! Auth::check()) {
            return redirect('/login');
        }

        /** @var User $user */
        $user = Auth::user();

        // Pastikan relasi role termuat
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        // Wajib punya role
        if (! $user->role) {
            abort(403, 'Unauthorized - No role assigned');
        }

        // Superadmin selalu full access (aman walau pivot belum lengkap)
        if (strtolower($user->role->name) === 'superadmin') {
            return $next($request);
        }

        // Lolos bila role memiliki akses ke SALAH SATU menu key yang diminta
        foreach ($keys as $key) {
            if ($user->role->hasMenu($key)) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized - Insufficient permissions');
    }
}
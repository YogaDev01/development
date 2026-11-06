<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;

/**
 * Mesin permission terpusat — Fase 2 Security & Access Control
 * (docs/plans/2026-11-06-security-access-control.md, keputusan D1).
 *
 * Saat ini HANYA dipakai dalam mode shadow (ShadowPermission middleware):
 * jawabannya dibandingkan dengan gerbang `role:` lama dan tidak pernah
 * memutuskan akses. Gerbang lama tetap berlaku sampai cutover per modul
 * disetujui.
 *
 * Registry ada di config/permissions.php (apa saja yang ADA); kewenangan
 * di tabel role_permissions (SIAPA yang boleh). Default deny: permission
 * yang tidak terdaftar selalu ditolak.
 *
 * Aturan yang disamakan dengan User::hasRole() supaya parity bisa dicapai:
 *  - memakai effectiveRole() (View As superadmin ikut dipreview),
 *  - superadmin TIDAK punya bypass di sini — sama seperti hasRole(); ia
 *    lolos karena ikut terdaftar di seed_roles setiap permission.
 */
final class Authorizer
{
    /** Kunci permission sebuah route: nama route, atau "METHOD /uri" bila tak bernama. */
    public static function routeKey(Route $route): string
    {
        $name = (string) $route->getName();

        if ($name !== '' && ! str_starts_with($name, 'generated::')) {
            return $name;
        }

        $methods = array_values(array_diff($route->methods(), ['HEAD']));

        return implode('|', $methods) . ' /' . ltrim($route->uri(), '/');
    }

    /** @return array<string, array{module:string, label:string, seed_roles:list<string>}> */
    public static function registry(): array
    {
        // Kunci permission mengandung titik, jadi tidak dibaca lewat
        // notasi titik config('permissions.permissions.<kunci>').
        return config('permissions.permissions', []);
    }

    public static function registered(string $key): bool
    {
        return array_key_exists($key, self::registry());
    }

    /** Apakah user (role efektifnya) memegang permission ini di role_permissions? */
    public static function allows(User $user, string $key): bool
    {
        if (! self::registered($key)) {
            return false;
        }

        $role = $user->effectiveRole();
        if (! $role) {
            return false;
        }

        return in_array($key, self::keysForRole((int) $role->getKey()), true);
    }

    /**
     * Jawaban gerbang lama untuk route ini: null bila route tidak punya
     * gerbang role:, selain itu hasil User::hasRole() — persis seperti
     * App\Http\Middleware\CheckRole.
     */
    public static function legacyAllows(User $user, Route $route): ?bool
    {
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m) && str_starts_with($m, 'role:')) {
                return $user->hasRole(explode(',', substr($m, 5)));
            }
        }

        return null;
    }

    /** Kunci permission milik satu role, dimuat sekali per request. */
    public static function keysForRole(int $roleId): array
    {
        $request = request();
        $memo    = 'authz.keys.' . $roleId;

        if ($request->attributes->has($memo)) {
            return $request->attributes->get($memo);
        }

        $keys = DB::table('role_permissions')->where('role_id', $roleId)->whereNull('revoked_at')
            ->pluck('permission_key')->all();
        $request->attributes->set($memo, $keys);

        return $keys;
    }

    /**
     * Isi role_permissions dari seed_roles (parity dengan gerbang lama).
     *
     * Menambah setiap pasangan (role, permission) dari seed_roles yang BELUM
     * PERNAH ada barisnya — termasuk untuk role yang dibuat belakangan atau
     * permission baru. Pasangan yang pernah ada tidak disentuh: hak yang
     * dicabut administrator ditandai revoked_at (barisnya tetap), jadi tidak
     * pernah dihidupkan kembali. Tidak pernah menghapus baris. Role dicocokkan
     * case-insensitive, seperti hasRole().
     *
     * @return array{permissions_seeded:int, rows:int, missing_roles:list<string>}
     */
    public static function syncSeed(bool $apply): array
    {
        $roles = DB::table('roles')->get(['id', 'name'])
            ->mapWithKeys(fn ($r) => [strtolower(trim($r->name)) => (int) $r->id]);

        $sudahAda = DB::table('role_permissions')->get(['role_id', 'permission_key'])
            ->mapWithKeys(fn ($r) => [$r->role_id . '|' . $r->permission_key => true]);

        $baris  = [];
        $kunci  = [];
        $hilang = [];
        $now    = now();

        foreach (self::registry() as $key => $def) {
            foreach ($def['seed_roles'] as $nama) {
                if (! $roles->has($nama)) {
                    $hilang[$nama] = true;
                    continue;
                }
                if ($sudahAda->has($roles[$nama] . '|' . $key)) {
                    continue;
                }
                $baris[]     = ['role_id' => $roles[$nama], 'permission_key' => $key, 'created_at' => $now];
                $kunci[$key] = true;
            }
        }

        if ($apply && $baris) {
            DB::transaction(function () use ($baris) {
                foreach (array_chunk($baris, 500) as $potongan) {
                    DB::table('role_permissions')->insertOrIgnore($potongan);
                }
            });
        }

        $hilang = array_keys($hilang);
        sort($hilang);

        return [
            'permissions_seeded' => count($kunci),
            'rows'               => count($baris),
            'missing_roles'      => $hilang,
        ];
    }
}

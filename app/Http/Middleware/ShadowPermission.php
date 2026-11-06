<?php

namespace App\Http\Middleware;

use App\Support\Authorizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode shadow mesin permission (Fase 2 Security & Access Control).
 *
 * Untuk setiap request user login ke route bergerbang role:, jawaban
 * Authorizer::allows() dibandingkan dengan jawaban gerbang lama
 * (Authorizer::legacyAllows(), sama dengan CheckRole). Bila berbeda,
 * dicatat ke permission_shadow_logs.
 *
 * Middleware ini TIDAK PERNAH memutuskan akses dan tidak mengubah respons:
 * request selalu diteruskan apa adanya, dan setiap error di sini ditelan.
 * Bila config security.permission_mode bukan 'shadow', tidak ada query
 * apa pun yang dijalankan.
 */
class ShadowPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('security.permission_mode') === 'shadow') {
            try {
                $this->compare($request);
            } catch (\Throwable $e) {
                Log::warning('ShadowPermission gagal', ['message' => $e->getMessage()]);
            }
        }

        return $next($request);
    }

    private static function tablesReady(): bool
    {
        static $siap = null;

        return $siap ??= \Illuminate\Support\Facades\Schema::hasTable('role_permissions')
            && \Illuminate\Support\Facades\Schema::hasTable('permission_shadow_logs');
    }

    private function compare(Request $request): void
    {
        $user  = $request->user();
        $route = $request->route();

        if (! $user || ! $route instanceof \Illuminate\Routing\Route) {
            return;
        }

        $lama = Authorizer::legacyAllows($user, $route);
        if ($lama === null) {
            return; // route tanpa gerbang role: — di luar registry
        }

        // Mode shadow dinyalakan sebelum migrasi: diam, jangan membanjiri log.
        if (! self::tablesReady()) {
            return;
        }

        $key  = Authorizer::routeKey($route);
        $baru = Authorizer::allows($user, $key);

        if ($baru === $lama) {
            return;
        }

        // Dedupe lewat tabel log itu sendiri (indeks permission_key+created_at),
        // bukan Cache::add — kunci sekali pakai menumpuk di tabel cache.
        $jendela = (int) config('security.shadow_log_window', 3600);
        $sudah   = DB::table('permission_shadow_logs')
            ->where('permission_key', mb_substr($key, 0, 150))
            ->where('created_at', '>=', now()->subSeconds($jendela))
            ->where('user_id', $user->getKey())
            ->where('legacy_allowed', $lama)
            ->where('engine_allowed', $baru)
            ->exists();
        if ($sudah) {
            return;
        }

        DB::table('permission_shadow_logs')->insert([
            'user_id'        => $user->getKey(),
            'role_name'      => mb_substr((string) $user->effectiveRole()?->name, 0, 100),
            'permission_key' => mb_substr($key, 0, 150),
            'method'         => $request->method(),
            'path'           => mb_substr('/' . ltrim($request->path(), '/'), 0, 255),
            'legacy_allowed' => $lama,
            'engine_allowed' => $baru,
            'created_at'     => now(),
        ]);
    }
}

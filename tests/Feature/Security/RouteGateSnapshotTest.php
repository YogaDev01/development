<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * Baseline Fase 0 — Centralized Security & Access Control
 * (docs/plans/2026-11-06-security-access-control.md).
 *
 * Merekam gerbang middleware SETIAP route (auth / guest / role: / menu: /
 * throttle:) ke tests/Fixtures/security/route-gates.json. Selama mesin
 * permission baru dibangun, gerbang lama tidak boleh berubah diam-diam:
 * setiap selisih (route baru, gerbang dilonggarkan, role ditambah/dicabut)
 * membuat uji ini gagal dan harus diputuskan sadar dengan memperbarui
 * fixture di commit yang sama.
 *
 * Tidak menyentuh basis data.
 */
class RouteGateSnapshotTest extends TestCase
{
    private const FIXTURE = 'tests/Fixtures/security/route-gates.json';

    /**
     * Route yang SENGAJA tanpa login. Menambah route publik = keputusan
     * keamanan (CLAUDE.md §15), jadi daftar ini juga harus diubah sadar.
     */
    private const PUBLIC_ROUTES = [
        'GET /up',
        'GET /api/public/budget-request',
        'GET /api/public/budget-request/detail',
        'GET /api/public/budget-request/realisasi',
    ];

    public function test_gerbang_route_sama_dengan_snapshot(): void
    {
        $this->assertRouteCacheTidakAktif();

        $snapshot = json_decode(file_get_contents(base_path(self::FIXTURE)), true);
        $this->assertIsArray($snapshot, 'Fixture route-gates.json tidak terbaca.');

        $sekarang = $this->gerbangSekarang();

        $baru    = array_diff_key($sekarang, $snapshot);
        $hilang  = array_diff_key($snapshot, $sekarang);
        $berubah = [];
        foreach (array_intersect_key($sekarang, $snapshot) as $kunci => $gerbang) {
            if ($gerbang !== $snapshot[$kunci]) {
                $berubah[$kunci] = ['snapshot' => $snapshot[$kunci], 'sekarang' => $gerbang];
            }
        }

        $this->assertSame(
            [[], [], []],
            [$baru, $hilang, $berubah],
            "Gerbang route berbeda dari snapshot [baru, hilang, berubah]. Bila disengaja, perbarui "
            . self::FIXTURE . ' dalam commit yang sama dan catat alasannya.'
        );
    }

    public function test_setiap_route_non_publik_wajib_login(): void
    {
        $this->assertRouteCacheTidakAktif();

        $tanpaLogin = [];

        foreach ($this->gerbangSekarang() as $kunci => $gerbang) {
            if (in_array('auth', $gerbang, true) || in_array('guest', $gerbang, true)) {
                continue;
            }
            if (! in_array($kunci, self::PUBLIC_ROUTES, true)) {
                $tanpaLogin[] = $kunci;
            }
        }

        $this->assertSame([], $tanpaLogin, 'Route tanpa middleware auth di luar daftar publik.');
    }

    /**
     * Dengan route cache aktif, aplikasi (termasuk uji) membaca route dari
     * bootstrap/cache/routes-v*.php, bukan dari routes/web.php — snapshot
     * akan dibandingkan dengan cache yang bisa saja basi.
     */
    private function assertRouteCacheTidakAktif(): void
    {
        $this->assertFalse(
            $this->app->routesAreCached(),
            'Route cache aktif (' . $this->app->getCachedRoutesPath() . '). Jalankan route:clear '
            . 'secara sadar sebelum uji ini; snapshot dibangun dari source routes/web.php.'
        );
    }

    /** @return array<string, list<string>>  "METHOD /uri" => gerbang */
    private function gerbangSekarang(): array
    {
        $router = app('router');
        $hasil  = [];

        // Sama dengan `route:list` (tanpa -vv): grup middleware tidak diurai,
        // jadi 'web' tetap literal lalu dibuang normalisasi(). Tanpa ini isi
        // grup web (StartSession, SecurityHeaders, ...) ikut masuk daftar
        // gerbang dan tidak cocok dengan fixture. App dibangun ulang per uji.
        $router->flushMiddlewareGroups();

        /** @var Route $route */
        foreach ($router->getRoutes()->getRoutes() as $route) {
            $methods = implode('|', array_values(array_diff($route->methods(), ['HEAD'])));
            $uri     = '/' . ltrim($route->uri(), '/');

            $gerbang = [];
            foreach ($router->gatherRouteMiddleware($route) as $m) {
                $n = is_string($m) ? self::normalisasi($m) : null;
                if ($n !== null) {
                    $gerbang[] = $n;
                }
            }

            $hasil[$methods . ' ' . $uri] = $gerbang;
        }

        ksort($hasil);

        return $hasil;
    }

    /** Harus sama dengan skrip pembuat fixture (alias maupun nama kelas). */
    private static function normalisasi(string $m): ?string
    {
        if ($m === 'web' || $m === 'api') {
            return null;
        }
        if (str_starts_with($m, 'role:') || str_starts_with($m, 'menu:') || str_starts_with($m, 'throttle:')
            || $m === 'auth' || $m === 'guest') {
            return $m;
        }

        $kelas = strstr($m, ':', true) ?: $m;
        $param = str_contains($m, ':') ? substr($m, strpos($m, ':')) : '';
        $peta  = [
            \App\Http\Middleware\CheckRole::class                     => 'role',
            \App\Http\Middleware\CheckMenu::class                     => 'menu',
            \Illuminate\Auth\Middleware\Authenticate::class           => 'auth',
            \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class => 'guest',
            \Illuminate\Routing\Middleware\ThrottleRequests::class    => 'throttle',
        ];

        return ($peta[$kelas] ?? class_basename($kelas)) . $param;
    }
}

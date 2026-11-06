<?php

namespace Tests\Feature\Security;

use App\Support\Authorizer;
use Tests\TestCase;

/**
 * Registry permission (config/permissions.php) harus mencerminkan PERSIS
 * gerbang role: di routes/web.php: setiap route bergerbang role: punya entri
 * dengan seed_roles yang sama, dan tidak ada entri yatim. Tanpa DB.
 * Bila gagal: jalankan scripts/security/permissions-registry.php.
 */
class PermissionRegistryTest extends TestCase
{
    public function test_registry_sama_persis_dengan_gerbang_role_di_route(): void
    {
        $this->assertFalse($this->app->routesAreCached(), 'Route cache aktif — jalankan route:clear secara sadar.');

        $dariRoute = [];
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'role:')) {
                    $roles = array_map(fn ($x) => strtolower(trim($x)), explode(',', substr($m, 5)));
                    sort($roles);
                    $dariRoute[Authorizer::routeKey($route)] = array_values(array_unique($roles));
                }
            }
        }

        $registry = array_map(fn ($p) => $p['seed_roles'], Authorizer::registry());

        ksort($dariRoute);
        ksort($registry);

        $this->assertSame([], array_keys(array_diff_key($dariRoute, $registry)), 'Route bergerbang role: tanpa entri registry.');
        $this->assertSame([], array_keys(array_diff_key($registry, $dariRoute)), 'Entri registry tanpa route.');
        $this->assertSame($dariRoute, $registry, 'seed_roles berbeda dari gerbang role: di route.');
    }

    /** Authorizer::legacyAllows() membaca satu gerbang role:; dua gerbang (AND) tidak didukung. */
    public function test_tidak_ada_route_dengan_dua_gerbang_role(): void
    {
        $ganda = [];
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $n = count(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role:')));
            if ($n > 1) {
                $ganda[] = Authorizer::routeKey($route);
            }
        }

        $this->assertSame([], $ganda);
    }

    public function test_setiap_permission_punya_modul_berlabel(): void
    {
        $modul = config('permissions.modules', []);

        foreach (Authorizer::registry() as $key => $def) {
            $this->assertArrayHasKey($def['module'], $modul, 'Modul tanpa label untuk ' . $key);
            $this->assertNotSame('', (string) $def['label']);
        }
    }
}

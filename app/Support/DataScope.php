<?php

namespace App\Support;

use App\Models\CostCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mesin batas data terpusat — Fase 4 Security & Access Control.
 *
 * Batas per role × modul × dimensi dibaca dari role_data_scopes (registry:
 * config/data_scopes.php). apply() menghasilkan filter query yang IDENTIK
 * dengan fungsi scope lama untuk isi awal (parity, dijaga
 * DataScopeParityTest):
 *   cost_request   ↔ BuildsCrWorklist::scopeByRole()
 *   budget_plan    ↔ ScopesBudgetPlanVisibility::scopeVisibleTo()
 *   purchase_order ↔ ProjectController::accessiblePoIds()
 *   invoice        ↔ ScopesInvoiceVisibility::scopeInvoiceVisibleTo()
 *
 * BELUM dipasang ke controller mana pun: fungsi lama tetap yang menyaring
 * daftar, detail, export, dan lampiran sampai cutover per modul disetujui.
 */
final class DataScope
{
    public static function modules(): array
    {
        return config('data_scopes.modules', []);
    }

    /** Nilai batas satu dimensi untuk user (role sesuai role_source modul). */
    public static function valueFor(User $user, string $module, string $dimension): string
    {
        $def  = self::modules()[$module] ?? null;
        $dim  = $def['dimensions'][$dimension] ?? null;
        if (! $def || ! $dim) {
            return 'none';
        }

        $role = ($def['role_source'] ?? 'effective') === 'real'
            ? ($user->relationLoaded('role') ? $user->role : $user->load('role')->role)
            : $user->effectiveRole();

        if (! $role) {
            return $dim['default'];
        }

        $nilai = self::rowsForRole((int) $role->getKey())[$module . '.' . $dimension] ?? null;

        return $nilai !== null && array_key_exists($nilai, $dim['options']) ? $nilai : $dim['default'];
    }

    /** Batasi $query sesuai batas data user pada modul ini. */
    public static function apply(Builder $query, User $user, string $module): void
    {
        match ($module) {
            'cost_request'   => self::applyCostRequest($query, $user),
            'budget_plan'    => self::applyBudgetPlan($query, $user),
            'purchase_order' => self::applyProjectIds($query, $user, 'id', true),
            'invoice'        => self::applyProjectIds($query, $user, 'po_id', false),
            default          => $query->whereRaw('1 = 0'), // modul tak dikenal: default deny
        };
    }

    private static function applyCostRequest(Builder $query, User $user): void
    {
        $proyek   = self::valueFor($user, 'cost_request', 'project');
        $overhead = self::valueFor($user, 'cost_request', 'overhead');
        $payroll  = self::valueFor($user, 'cost_request', 'payroll');

        // Semua dimensi terbuka = tanpa filter (sama dengan early return role istimewa).
        if ($proyek === 'all' && $overhead === 'all' && $payroll === 'all') {
            return;
        }

        $projectIds = $user->accessibleProjectIds();
        $ccIds      = $user->accessibleCostCenterIds();

        $query->where(function ($q) use ($user, $proyek, $overhead, $payroll, $projectIds, $ccIds) {
            $q->where(function ($qq) use ($user, $proyek, $projectIds) {
                if ($proyek === 'all') {
                    $qq->whereHas('budgetPlan', fn ($b) => $b->whereNotNull('project_id'));
                } elseif ($projectIds->isNotEmpty()) {
                    $qq->whereHas('budgetPlan.project', fn ($p) => $p->whereIn('id', $projectIds));
                } else {
                    $qq->where('applicant_id', $user->id)
                       ->whereHas('budgetPlan', fn ($b) => $b->whereNotNull('project_id'));
                }
            });

            if ($overhead === 'all') {
                $q->orWhereHas('budgetPlan.costCenter', fn ($c) => $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES));
            } elseif ($ccIds->isNotEmpty()) {
                $q->orWhereHas('budgetPlan.costCenter', fn ($c) =>
                    $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES)->whereIn('id', $ccIds));
            }

            if ($payroll === 'all') {
                $q->orWhereHas('budgetPlan.costCenter', fn ($c) => $c->where('type', 'PAYROLL'));
            }
        });
    }

    private static function applyBudgetPlan(Builder $query, User $user): void
    {
        $proyek   = self::valueFor($user, 'budget_plan', 'project');
        $overhead = self::valueFor($user, 'budget_plan', 'overhead');
        $payroll  = self::valueFor($user, 'budget_plan', 'payroll');

        $projectIds = $user->accessibleProjectIds();
        $ccIds      = $user->accessibleCostCenterIds();

        $query->where(function ($q) use ($proyek, $overhead, $payroll, $projectIds, $ccIds) {
            $q->where(function ($qq) use ($proyek, $projectIds) {
                $qq->whereNotNull('project_id');
                if ($proyek !== 'all') {
                    $qq->whereIn('project_id', $projectIds->isEmpty() ? [0] : $projectIds);
                }
            });

            if ($overhead === 'all') {
                $q->orWhereHas('costCenter', fn ($c) => $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES));
            } elseif ($ccIds->isNotEmpty()) {
                $q->orWhereHas('costCenter', fn ($c) =>
                    $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES)->whereIn('id', $ccIds));
            }

            if ($payroll === 'all') {
                $q->orWhereHas('costCenter', fn ($c) => $c->where('type', 'PAYROLL'));
            }
        });
    }

    /**
     * PO & Invoice: satu dimensi 'project'. $kosongNol meniru ProjectController
     * (`whereIn(..., $ids ?: [0])`); Invoice memakai whereIn apa adanya.
     */
    private static function applyProjectIds(Builder $query, User $user, string $kolom, bool $kosongNol): void
    {
        $modul = $kolom === 'id' ? 'purchase_order' : 'invoice';

        if (self::valueFor($user, $modul, 'project') === 'all') {
            return;
        }

        $ids = $user->accessibleProjectIds()->all();
        $query->whereIn($kolom, $kosongNol ? ($ids ?: [0]) : $ids);
    }

    /** Baris role_data_scopes satu role ("modul.dimensi" => scope), sekali per request. */
    private static function rowsForRole(int $roleId): array
    {
        $request = request();
        $memo    = 'datascope.' . $roleId;

        if ($request->attributes->has($memo)) {
            return $request->attributes->get($memo);
        }

        $rows = DB::table('role_data_scopes')->where('role_id', $roleId)->get(['module', 'dimension', 'scope'])
            ->mapWithKeys(fn ($r) => [$r->module . '.' . $r->dimension => $r->scope])->all();
        $request->attributes->set($memo, $rows);

        return $rows;
    }

    /** Nilai isi awal untuk satu role pada satu dimensi (seed bila disebut, selain itu default). */
    public static function seedValue(string $roleName, array $dim): string
    {
        foreach ($dim['seed'] ?? [] as $nilai => $roles) {
            if (in_array(strtolower(trim($roleName)), $roles, true)) {
                return $nilai;
            }
        }

        return $dim['default'];
    }

    /**
     * Tambah baris role_data_scopes yang BELUM ada (role × modul × dimensi)
     * dengan nilai seed. Tidak pernah mengubah baris yang sudah ada, jadi
     * pengaturan administrator nanti tidak tertimpa.
     *
     * @return array{rows:int}
     */
    public static function syncSeed(bool $apply): array
    {
        $ada = DB::table('role_data_scopes')->get(['role_id', 'module', 'dimension'])
            ->mapWithKeys(fn ($r) => [$r->role_id . '|' . $r->module . '|' . $r->dimension => true]);

        $baris = [];
        $now   = now();

        foreach (DB::table('roles')->get(['id', 'name']) as $role) {
            foreach (self::modules() as $mod => $def) {
                foreach ($def['dimensions'] as $dimKey => $dim) {
                    if ($ada->has($role->id . '|' . $mod . '|' . $dimKey)) {
                        continue;
                    }
                    $baris[] = [
                        'role_id' => $role->id, 'module' => $mod, 'dimension' => $dimKey,
                        'scope' => self::seedValue($role->name, $dim), 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
        }

        if ($apply && $baris) {
            DB::transaction(function () use ($baris) {
                foreach (array_chunk($baris, 500) as $potongan) {
                    DB::table('role_data_scopes')->insertOrIgnore($potongan);
                }
            });
        }

        return ['rows' => count($baris)];
    }

    /** Ringkasan untuk tab "Batas Data" di modal Role (baca-saja). */
    public static function statusForRole(int $roleId): array
    {
        if (! Schema::hasTable('role_data_scopes')) {
            return ['ready' => false, 'modules' => [], 'fixed' => array_values(config('data_scopes.fixed', []))];
        }

        $rows = DB::table('role_data_scopes')->where('role_id', $roleId)->get(['module', 'dimension', 'scope'])
            ->mapWithKeys(fn ($r) => [$r->module . '.' . $r->dimension => $r->scope]);

        $modules = [];
        foreach (self::modules() as $mod => $def) {
            $dims = [];
            foreach ($def['dimensions'] as $dimKey => $dim) {
                // Sama dengan valueFor(): nilai tersimpan yang tidak dikenal jatuh ke default.
                $nilai  = $rows[$mod . '.' . $dimKey] ?? null;
                $nilai  = $nilai !== null && array_key_exists($nilai, $dim['options']) ? $nilai : $dim['default'];
                $dims[] = [
                    'label'   => $dim['label'],
                    'value'   => $nilai,
                    'text'    => $dim['options'][$nilai] ?? $nilai,
                    'is_open' => $nilai === 'all',
                    'stored'  => $rows->has($mod . '.' . $dimKey),
                ];
            }
            $modules[] = ['key' => $mod, 'label' => $def['label'], 'dimensions' => $dims];
        }

        return ['ready' => true, 'modules' => $modules, 'fixed' => array_values(config('data_scopes.fixed', []))];
    }
}

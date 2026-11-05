<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CostCenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Siapa boleh melihat Budget Plan yang mana.
 *
 * Aturannya sebelumnya tinggal sebagai method publik di BudgetPlanController,
 * dan GlobalSearchController meminjamnya dengan
 * `app(BudgetPlanController::class)->scopeVisibleTo($query)` — me-resolve
 * sebuah controller dari container untuk dipakai sebagai layanan.
 *
 * Niat di balik itu benar dan tetap dipertahankan: aturan visibilitas TIDAK
 * boleh ditulis dua kali, karena dua salinan pasti menyimpang dan yang satu
 * akan menampilkan baris yang seharusnya tidak terlihat. Yang salah hanya
 * caranya. Controller membawa keadaan yang terikat satu permintaan HTTP;
 * memanggilnya dari tempat lain — apalagi kelak dari command atau job —
 * berperilaku tidak menentu.
 *
 * Trait adalah pola yang SUDAH dipakai di proyek ini untuk masalah yang sama
 * persis: Concerns\BuildsCrWorklist menyediakan scopeByRole() untuk Cost
 * Request, dan GlobalSearchController memakainya dengan benar lewat `use`.
 * Berkas ini menyamakan Budget Plan dengan pola itu.
 *
 * Isinya dipindahkan apa adanya — tidak satu aturan pun diubah.
 */
/*
| NAMA BER-AWALAN "bp"/"BP_" — DISENGAJA, DAN PENTING.
|
| Concerns\BuildsCrWorklist (aturan visibilitas COST REQUEST) sudah
| mendefinisikan overheadPrivileged(), canSeeOverhead(),
| accessibleOverheadCostCenterIds(), canSeePayroll(), plus konstanta
| OVERHEAD_PRIVILEGED_ROLES dan PAYROLL_ROLES. GlobalSearchController memakai
| KEDUA trait, jadi nama yang sama akan bentrok - dan PHP menolaknya.
|
| Yang lebih penting: isinya TIDAK SAMA.
|
|   BuildsCrWorklist  : ['superadmin','approver','finance_manager','guest']
|   di sini (Budget Plan): ['superadmin','approver','finance_manager']
|
| Bedanya peran 'guest'. Perbedaan itu dibiarkan APA ADANYA - menyatukannya
| berarti mengubah siapa yang melihat data apa, dan itu keputusan kebijakan,
| bukan pembersihan kode. Memberi nama berbeda membuat perbedaannya terlihat
| alih-alih tersembunyi di balik dua konstanta bernama sama.
*/
trait ScopesBudgetPlanVisibility
{
    /** Role yang melihat SEMUA budget overhead Budget Plan. */
    protected const BP_OVERHEAD_PRIVILEGED_ROLES = ['superadmin', 'approver', 'finance_manager'];

    /** Role yang boleh melihat budget PAYROLL (rahasia). */
    protected const BP_PAYROLL_ROLES = ['superadmin', 'approver', 'hr', 'finance_manager'];

    /** Role yang melihat SEMUA budget proyek (tanpa filter access group). */
    protected const BP_PROJECT_PRIVILEGED_ROLES = [
        'superadmin', 'finance', 'finance_manager', 'approver', 'admin',
        'cost_control', 'treasury', 'collection', 'accounting',
    ];

    protected function bpOverheadPrivileged(): bool
    {
        return in_array(strtolower(Auth::user()->role->name ?? ''), self::BP_OVERHEAD_PRIVILEGED_ROLES);
    }

    protected function bpAccessibleOverheadCostCenterIds(): Collection
    {
        // Access Group individu ∪ Access Group team ACTIVE (User::accessibleCostCenterIds()).
        return Auth::user()->accessibleCostCenterIds();
    }

    protected function bpCanSeeOverhead(): bool
    {
        return $this->bpOverheadPrivileged()
            || $this->bpAccessibleOverheadCostCenterIds()->isNotEmpty();
    }

    protected function bpCanSeePayroll(): bool
    {
        return in_array(strtolower(Auth::user()->role->name ?? ''), self::BP_PAYROLL_ROLES);
    }

    /**
     * Batasi query Budget Plan ke baris yang boleh dilihat user saat ini.
     */
    public function scopeVisibleTo($query): void
    {
        $user = Auth::user();
        $role = strtolower($user->role->name ?? '');

        // Sama dengan daftar PO: Access Group individu ∪ team ACTIVE.
        $projectIds = $user->accessibleProjectIds();

        $overheadPrivileged = $this->bpOverheadPrivileged();
        $overheadCcIds      = $this->bpAccessibleOverheadCostCenterIds();
        $canPayroll         = $this->bpCanSeePayroll();

        $query->where(function ($q) use ($role, $projectIds, $overheadPrivileged, $overheadCcIds, $canPayroll) {

            // 1) Budget proyek
            $q->where(function ($qq) use ($role, $projectIds) {
                $qq->whereNotNull('project_id');

                if (!in_array($role, self::BP_PROJECT_PRIVILEGED_ROLES)) {
                    $qq->whereIn('project_id', $projectIds->isEmpty() ? [0] : $projectIds);
                }
            });

            // 2) Budget overhead (dan tipe non-payroll lain — Tax/Asset/Liability/
            // Capital/Adjustment — yang diperlakukan sama seperti Overhead)
            if ($overheadPrivileged) {
                $q->orWhereHas('costCenter', fn ($c) => $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES));
            } elseif ($overheadCcIds->isNotEmpty()) {
                $q->orWhereHas('costCenter', fn ($c) =>
                    $c->whereIn('type', CostCenter::OVERHEAD_LIKE_TYPES)->whereIn('id', $overheadCcIds)
                );
            }

            // 3) Budget payroll
            if ($canPayroll) {
                $q->orWhereHas('costCenter', fn ($c) => $c->where('type', 'PAYROLL'));
            }
        });
    }
}

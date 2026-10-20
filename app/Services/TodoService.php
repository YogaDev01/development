<?php

namespace App\Services;

use App\Http\Controllers\TodoListController;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * SATU-SATUNYA sumber definisi antrean Todo List: siapa (role) menangani
 * antrean apa, dan di status mana dokumennya mengantre.
 *
 * Dipakai oleh:
 *  - TodoListController::buildWorklistData() — isi halaman Todo List &
 *    blok "Perlu Tindakan" di Dashboard (gerbang role & status dibaca dari sini);
 *  - hitungan ringan untuk badge sidebar (todo-list.has-pending) dan
 *    pengingat terjadwal (todo:remind) lewat pendingCounts()/hasPending().
 *
 * Dengan begitu badge, pengingat, dan halaman tidak mungkin berbeda
 * definisi. (Isi lama build() — daftar item bergaya "APPROVED = verifikasi
 * Finance, REALIZED = Finance" — sudah usang dan dihapus.)
 */
class TodoService
{
    /**
     * Role yang TIDAK dibatasi Access Group (keputusan pemilik K3). Role lain
     * — termasuk head, manager, supervisor, dan guest — dibatasi Access Group
     * pada antrean yang dokumennya terikat proyek.
     */
    public const AKSES_PENUH_ROLES = [
        'superadmin', 'approver', 'accounting', 'treasury',
        'finance', 'finance_manager', 'collection', 'cost_control',
    ];

    /**
     * Role yang oleh scopeByRole() (Cost Request) diperlakukan istimewa di kode
     * padahal BUKAN bagian dari AKSES_PENUH_ROLES. Pada Todo List keduanya tetap
     * dibatasi Access Group untuk dokumen proyek.
     */
    public const ISTIMEWA_DI_KODE_BUKAN_K3 = ['admin', 'guest'];

    /**
     * key antrean => role penangan, status antrean (bila antrean berbasis status),
     * dan apakah ikut hitungan "total" (sama dengan $totalPending di halaman).
     */
    public const QUEUES = [
        // Cost Request — review berjenjang
        'head'                    => ['roles' => ['superadmin', 'head'], 'status' => ['SUBMITTED']],
        'cost_control_checked'    => ['roles' => ['superadmin', 'finance', 'finance_manager', 'cost_control'], 'status' => ['CHECKED']],
        'ceo'                     => ['roles' => ['superadmin', 'approver', 'guest'], 'status' => ['VERIFIED']],
        // Cost Request — pengaju
        'managerRejected'         => ['roles' => ['superadmin', 'manager', 'supervisor'], 'status' => ['REJECTED']],
        'managerPaid'             => ['roles' => ['superadmin', 'manager', 'supervisor'], 'status' => ['PAID', 'SETTLE_REJECTED']],
        // Cost Request — settlement
        'headSettlementCheck'     => ['roles' => ['superadmin', 'head'], 'status' => ['REALIZED']],
        'financeSettlementVerify' => ['roles' => ['superadmin', 'finance', 'finance_manager', 'cost_control'], 'status' => ['SETTLE_CHECKED']],
        'ceoSettlementClose'      => ['roles' => ['superadmin', 'finance_manager'], 'status' => ['SETTLE_VERIFIED']],
        'approverSecondApprove'   => ['roles' => ['superadmin', 'approver'], 'status' => ['SETTLE_APPROVED']],
        // Treasury
        'secondPaymentPending'    => ['roles' => ['superadmin', 'treasury', 'finance', 'finance_manager']],
        'treasuryBatchCreate'     => ['roles' => ['superadmin', 'treasury', 'finance', 'finance_manager'], 'status' => ['APPROVED']],
        'treasuryBatchSecond'     => ['roles' => ['superadmin', 'treasury', 'finance', 'finance_manager'], 'total' => false],
        'treasuryProcess'         => ['roles' => ['superadmin', 'approver', 'guest'], 'status' => ['PENDING_APPROVER']],
        'treasuryReadyToPay'      => ['roles' => ['superadmin', 'treasury', 'finance', 'finance_manager'], 'status' => ['DONE']],
        // Budget Plan
        'budgetingHeadCheck'      => ['roles' => ['superadmin', 'head'], 'status' => ['SUBMITTED']],
        'budgetingVerify'         => ['roles' => ['superadmin', 'finance', 'finance_manager'], 'status' => ['CHECKED']],
        'budgetingCeoApprove'     => ['roles' => ['superadmin', 'approver', 'guest'], 'status' => ['VERIFIED']],
        'budgetingClosingDue'     => ['roles' => ['superadmin', 'manager'], 'status' => ['CLOSING']],
        'budgetingRejected'       => ['roles' => ['superadmin', 'manager'], 'status' => ['REJECTED']],
        // Purchase Order, Invoice, Collection
        'poSubmitted'             => ['roles' => ['superadmin', 'finance', 'finance_manager'], 'status' => ['SUBMITTED']],
        'invoiceVerify'           => ['roles' => ['superadmin', 'finance_manager', 'accounting'], 'status' => ['SUBMITTED']],
        'invoiceApprove'          => ['roles' => ['superadmin', 'finance_manager'], 'status' => ['VERIFIED']],
        'collectionWaitingInvoice' => ['roles' => ['superadmin', 'collection']],
    ];

    /** Role penangan sebuah antrean. */
    public static function roles(string $key): array
    {
        return self::QUEUES[$key]['roles'];
    }

    /** Status dokumen yang mengantre di antrean ini. */
    public static function statuses(string $key): array
    {
        return self::QUEUES[$key]['status'] ?? [];
    }

    /** Apakah user (role efektif, ikut "View As") menangani antrean ini? */
    public static function allows(User $user, string $key): bool
    {
        return $user->hasRole(self::roles($key));
    }

    /** Role user (efektif) dibatasi Access Group? Lawan dari AKSES_PENUH_ROLES. */
    public static function terbatasAccessGroup(User $user): bool
    {
        $nama = strtolower($user->effectiveRole()?->name ?? '');

        return ! in_array($nama, self::AKSES_PENUH_ROLES, true);
    }

    /**
     * Jumlah dokumen per antrean untuk user — hanya hitungan, tanpa CoA,
     * summary, atau respon time. Gerbang role dan scope visibilitas sama
     * dengan halaman (dibangun oleh TodoListController::queueQuery()).
     *
     * @return array<string,int> key antrean => jumlah (hanya antrean milik user)
     */
    public static function pendingCounts(User $user): array
    {
        return app(TodoListController::class)->pendingCounts($user);
    }

    /** Jumlah tugas yang sama dengan $totalPending di halaman (tanpa batch second). */
    public static function pendingTotal(User $user, ?array $counts = null): int
    {
        $total = 0;
        foreach ($counts ?? self::pendingCounts($user) as $key => $n) {
            if (self::QUEUES[$key]['total'] ?? true) {
                $total += $n;
            }
        }

        return $total;
    }

    /** Lama cache hasPending() per user (detik) — badge sidebar dipoll berkala. */
    public const HAS_PENDING_TTL = 60;

    /**
     * Ada tugas menunggu? Definisi sama dengan $hasAnyTask di halaman, tetapi
     * berhenti di antrean pertama yang berisi (exists) dan di-cache singkat.
     * Kunci cache memuat id user DAN id role efektif, jadi "View As" superadmin
     * tidak memakai hasil milik role aslinya. Halaman Todo List dan todo:remind
     * tetap memakai pendingCounts() yang penuh.
     */
    public static function hasPending(User $user): bool
    {
        $kunci = 'todo-list.has-pending.u' . $user->id . '.r' . ($user->effectiveRole()?->id ?? 0);

        return Cache::remember(
            $kunci,
            self::HAS_PENDING_TTL,
            fn () => app(TodoListController::class)->anyPending($user)
        );
    }
}

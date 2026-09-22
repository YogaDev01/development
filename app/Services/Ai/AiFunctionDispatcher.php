<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Finance\AiDataService;
use App\Services\TavilyService;

/**
 * Satu-satunya jalur AI untuk "menyentuh" data. Gemini hanya mengirim
 * nama function + parameter (JSON terstruktur) — TIDAK PERNAH SQL —
 * dan dispatcher inilah yang memutuskan boleh/tidaknya function itu
 * dijalankan (berdasarkan role user, BUKAN permintaan AI) sebelum
 * meneruskannya ke AiDataService.
 */
class AiFunctionDispatcher
{
    /**
     * Function yang boleh dipanggil per role (matriks akses, dikonfirmasi
     * user 2026-08-12). Superadmin/Approver/Finance: akses penuh. Head:
     * Budget (termasuk nilai PO)/Realisasi/Rincian-Project-saja, TANPA
     * Invoice/Payment/Ringkasan (Ringkasan membundel data Payment). Manager:
     * sama seperti Head tapi TANPA nilai PO (lihat PO_VALUE_HIDDEN_ROLES).
     */
    private const FUNCTION_ROLES = [
        'get_budget'            => ['superadmin', 'approver', 'finance', 'head', 'manager'],
        'get_realization'       => ['superadmin', 'approver', 'finance', 'head', 'manager'],
        'get_project_cost'      => ['superadmin', 'approver', 'finance', 'head', 'manager'],
        'search_expense_items'  => ['superadmin', 'approver', 'finance', 'head', 'manager'],
        'get_invoice'           => ['superadmin', 'approver', 'finance'],
        'get_payment'           => ['superadmin', 'approver', 'finance'],
        'get_project_summary'   => ['superadmin', 'approver', 'finance'],
        'list_accessible_projects' => ['superadmin', 'approver', 'finance', 'head', 'manager'],
        // Informasi PO (status/No. PO/BAST/progress) HANYA untuk role yang
        // eksplisit disebut user 2026-08-12 — Manager TIDAK termasuk (beda
        // dengan get_budget yang boleh Manager akses tanpa nilai PO-nya).
        'get_purchase_order'    => ['superadmin', 'approver', 'finance', 'head'],
        // Data agregat level perusahaan (treasury), sama seperti gerbang akses
        // halaman Finance Summary Executive — bukan data per-Project, jadi
        // TIDAK ikut pola Access Group seperti function lain di atas.
        'get_cashflow'          => ['superadmin', 'approver', 'finance'],
        'get_invoice_detail'    => ['superadmin', 'approver', 'finance'],
        // Bukan data internal/sensitif — tidak perlu ikut aturan Access
        // Group, terbuka untuk semua role yang sudah punya akses TesysAI.
        'search_web'            => ['superadmin', 'approver', 'finance', 'head', 'manager'],
    ];

    /**
     * Role yang TIDAK boleh melihat nilai PO (po_value) & angka turunannya
     * (outstanding, gross profit/margin) — cuma "Budget" (anggaran internal)
     * yang boleh, bukan nilai kontrak PO ke customer.
     */
    private const PO_VALUE_HIDDEN_ROLES = ['manager'];

    /** Role yang HANYA boleh lihat data berbasis Project (PO) — Cost Center (overhead/payroll) selalu diblokir apa pun isi Access Group-nya. */
    private const PROJECT_ONLY_ROLES = ['head', 'manager'];

    /**
     * Role yang TIDAK dibatasi Access Group (lihat semua Project/Cost Center).
     * PENTING: disamakan persis dengan BudgetRequest::isAccessibleBy() —
     * cuma superadmin & approver yang bypass di situ, role lain (termasuk
     * finance) TETAP dibatasi Access Group. Jangan diubah tanpa mengubah
     * BudgetRequest::isAccessibleBy() juga, supaya dua-duanya tetap konsisten.
     */
    private const UNSCOPED_ROLES = ['superadmin', 'approver', 'cost_control', 'treasury', 'collection', 'accounting'];

    /** Field yang di-strip dari hasil function kalau role termasuk PO_VALUE_HIDDEN_ROLES. */
    private const PO_VALUE_FIELDS = ['po_value', 'outstanding', 'gross_profit', 'gross_margin'];

    public function __construct(private AiDataService $data, private TavilyService $webSearch)
    {
    }

    /**
     * Dipakai controller untuk memberi tahu Gemini di system prompt (BUKAN
     * lewat function call) apakah user ini punya akses ke fitur ini SAMA
     * SEKALI — supaya AI tidak perlu buang satu giliran mencoba function
     * yang pasti ditolak hanya untuk pertanyaan meta seperti "data apa yang
     * bisa saya akses?".
     */
    public function canAccessData(User $user): bool
    {
        $role = strtolower($user->role->name ?? '');
        foreach (self::FUNCTION_ROLES as $roles) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }
        return false;
    }

    /** Daftar function yang boleh dipakai role ini — dipakai controller untuk system prompt. */
    public function allowedFunctionNames(User $user): array
    {
        $role = strtolower($user->role->name ?? '');
        return array_keys(array_filter(
            self::FUNCTION_ROLES,
            fn ($roles) => in_array($role, $roles, true)
        ));
    }

    /**
     * @param array $state compact conversation state (current_project, dst) — dipakai
     *                      sebagai fallback kalau Gemini tidak menyertakan parameter
     *                      eksplisit untuk pertanyaan lanjutan ("sudah terpakai berapa?").
     */
    public function dispatch(string $name, array $args, User $user, array $state = []): array
    {
        if (!in_array($name, FunctionRegistry::names(), true)) {
            return ['found' => false, 'message' => "Function \"{$name}\" tidak dikenali sistem."];
        }

        $role = strtolower($user->role->name ?? '');

        if (!in_array($role, self::FUNCTION_ROLES[$name] ?? [], true)) {
            return [
                'found'   => false,
                'message' => 'Mohon maaf, role Anda (' . ($user->role->name ?? '-') . ') tidak memiliki akses ke informasi ini.',
            ];
        }

        $scopedProjectIds = in_array($role, self::UNSCOPED_ROLES, true)
            ? null
            : $user->accessibleProjectIds()->all();

        $project = $args['project'] ?? ($state['current_project'] ?? null);
        $period  = $args['period']  ?? ($state['current_period']  ?? null);

        $result = match ($name) {
            'get_budget'          => $this->data->getBudget($project, $period, $scopedProjectIds),
            'get_realization'     => $this->data->getRealization($project, $period, $scopedProjectIds),
            'get_invoice'         => $this->data->getInvoice($project, $args['customer'] ?? ($state['current_customer'] ?? null), $args['status'] ?? null, $period, $scopedProjectIds),
            'get_payment'         => $this->data->getPayment($project, $args['invoice_number'] ?? null, $period, $scopedProjectIds),
            'get_project_summary' => $this->data->getProjectSummary($project, $scopedProjectIds),
            'get_project_cost'    => $this->data->getProjectCost($project, $args['category'] ?? null, $period, $scopedProjectIds),
            // Sengaja TIDAK pakai fallback $state['current_project'] — inti function ini
            // justru mencari LINTAS project (bahkan lintas Cost Center, kalau role-nya
            // diizinkan) berdasarkan kata kunci, bukan project tertentu. $user diteruskan
            // supaya akses per-baris dicek lewat BudgetRequest::isAccessibleBy().
            'search_expense_items' => $this->data->searchExpenseItems(
                $args['keyword'] ?? '',
                $args['project'] ?? null,
                $scopedProjectIds,
                $user,
                in_array($role, self::PROJECT_ONLY_ROLES, true)
            ),
            'list_accessible_projects' => $this->data->listAccessibleProjects($scopedProjectIds),
            'get_purchase_order' => $this->data->getPurchaseOrder($project, $scopedProjectIds, in_array($role, self::PO_VALUE_HIDDEN_ROLES, true)),
            'get_cashflow'       => $this->data->getCashflow($args['period_start'] ?? null, $args['period_end'] ?? null),
            'get_invoice_detail' => $this->data->getInvoiceDetail($args['invoice_number'] ?? '', $scopedProjectIds),
            'search_web'         => $this->webSearch->search($args['query'] ?? ''),
        };

        if (in_array($role, self::PO_VALUE_HIDDEN_ROLES, true)) {
            foreach (self::PO_VALUE_FIELDS as $field) {
                unset($result[$field]);
            }
        }

        return $result;
    }
}

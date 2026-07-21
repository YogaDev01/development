<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ClusterController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\MasterPicController;
use App\Http\Controllers\SourceFundController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\CostCenterController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\ClusterGroupController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AccessGroupController;
use App\Http\Controllers\BudgetPlanController;
use App\Http\Controllers\BudgetRequestController;
use App\Http\Controllers\BudgetRequestApprovalController;
use App\Http\Controllers\ProjectMonitoringController;
use App\Http\Controllers\InvoiceCashReceiptController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DailyTransactionController;
use App\Http\Controllers\FinanceSummaryController;

/*
|--------------------------------------------------------------------------
| AUTHENTICATION ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/', function () {
        $today = now();
        $currentDay = $today->day;
        
        if ($currentDay > 29) {
            $fromDate = $today->copy()->day(30);
            $toDate = $today->copy()->addMonth()->day(29);
        } else {
            $fromDate = $today->copy()->subMonth()->day(30);
            $toDate = $today->copy()->day(29);
        }

        $statistics = [
            'pending' => \App\Models\BudgetRequest::where('status', 'PENDING_APPROVER')
                ->orWhere('status', 'PENDING_FINANCE')
                ->whereBetween('created_at', [$fromDate, $toDate])->count(),
            'approved' => \App\Models\BudgetRequest::where('status', 'APPROVED')
                ->whereBetween('created_at', [$fromDate, $toDate])->count(),
            'rejected' => \App\Models\BudgetRequest::where('status', 'REJECTED')
                ->whereBetween('created_at', [$fromDate, $toDate])->count(),
            'draft' => \App\Models\BudgetRequest::where('status', 'DRAFT')
                ->whereBetween('created_at', [$fromDate, $toDate])->count(),
        ];

        return view('dashboard.index', compact('statistics'));
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Project
    |--------------------------------------------------------------------------
    */
   // AJAX
    Route::get('project/{id}/detail-json', [ProjectController::class, 'showJson'])->name('project.show-json');
    
    // Options JSON untuk dropdown
    Route::get('cluster/options-json', fn() => response()->json(\App\Models\Cluster::orderBy('name')->get(['id','name'])))->name('cluster.options-json');
    Route::get('stage/options-json',   fn() => response()->json(\App\Models\Stage::orderBy('name')->get(['id','name'])))->name('stage.options-json');
    
    // Workflow actions
    Route::post('project/{id}/submit',                [ProjectController::class, 'submit'])->name('project.submit');
    Route::post('project/{id}/finance-approve',       [ProjectController::class, 'financeApprove'])->name('project.finance-approve');
    Route::post('project/{id}/activate-from-waiting', [ProjectController::class, 'activateFromWaiting'])->name('project.activate-from-waiting');

    // Aktivasi WAITING_PO → ACTIVE via form edit lengkap (Finance)
    Route::get('project/{id}/activate',               [ProjectController::class, 'activateForm'])->name('project.activate-form');
    Route::put('project/{id}/activate',               [ProjectController::class, 'activate'])->name('project.activate');
    Route::post('project/{id}/finance-revise',        [ProjectController::class, 'financeRevise'])->name('project.finance-revise');
    Route::post('project/{id}/submit-bast',           [ProjectController::class, 'submitBast'])->name('project.submit-bast');
    Route::post('project/{id}/submit-closing',        [ProjectController::class, 'submitClosing'])->name('project.submit-closing');
    Route::post('project/{id}/verify-closing',        [ProjectController::class, 'verifyClosing'])->name('project.verify-closing');
    Route::post('project/{id}/reject-closing',        [ProjectController::class, 'rejectClosing'])->name('project.reject-closing');
    
    // CRUD
    Route::middleware('role:superadmin,head')->group(function () {
        Route::get('project/create',    [ProjectController::class, 'create'])->name('project.create');
        Route::post('project',          [ProjectController::class, 'store'])->name('project.store');
        Route::get('project/{id}/edit', [ProjectController::class, 'edit'])->name('project.edit');
        Route::put('project/{id}',      [ProjectController::class, 'update'])->name('project.update');
        Route::delete('project/{id}',   [ProjectController::class, 'destroy'])->name('project.destroy');

        // Import Excel — Project & Milestone (wajib sebelum route wildcard project/{id})
        Route::get('project/import-template', [ProjectController::class, 'downloadDetailTemplate'])->name('project.import-template');
        Route::post('project/import-preview', [ProjectController::class, 'importDetailPreview'])->name('project.import-preview');
    });
    
    // Index & show — akses berbasis menu (List PO = /project)
    Route::middleware('menu:list-po')->group(function () {
        Route::get('project',      [ProjectController::class, 'index'])->name('project.index');
        Route::get('project/{id}', [ProjectController::class, 'show'])->name('project.show');
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Customer
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin,admin')->group(function () {
        Route::post('customer', [CustomerController::class, 'store'])->name('customer.store');
        Route::get('customer/create', [CustomerController::class, 'create'])->name('customer.create');
        Route::get('customer/suggest-initial', [CustomerController::class, 'suggestInitial'])->name('customer.suggest-initial');
        Route::get('customer/{id}/edit', [CustomerController::class, 'edit'])->name('customer.edit');
        Route::put('customer/{id}', [CustomerController::class, 'update'])->name('customer.update');
        Route::delete('customer/{id}', [CustomerController::class, 'destroy'])->name('customer.destroy');
    });

    // Index, show & detail-json — akses berbasis menu
    Route::middleware('menu:customer')->group(function () {
        Route::get('customer',                    [CustomerController::class, 'index'])->name('customer.index');
        Route::get('customer/{id}/detail-json',   [CustomerController::class, 'showJson'])->name('customer.show-json');
        Route::get('customer/{id}',               [CustomerController::class, 'show'])->name('customer.show');
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Cluster
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin,admin')->group(function () {
        Route::post('cluster', [ClusterController::class, 'store'])->name('cluster.store');
        Route::get('cluster/create', [ClusterController::class, 'create'])->name('cluster.create');

        // AJAX routes — WAJIB sebelum cluster/{id} agar tidak tertangkap sebagai {id}
        Route::get('cluster/next-code',      [ClusterController::class, 'getNextCode'])->name('cluster.next-code');
        Route::get('cluster/{id}/edit-data', [ClusterController::class, 'getEditData'])->name('cluster.edit-data');

        Route::get('cluster/{id}/edit', [ClusterController::class, 'edit'])->name('cluster.edit');
        Route::put('cluster/{id}',      [ClusterController::class, 'update'])->name('cluster.update');
        Route::delete('cluster/{id}',   [ClusterController::class, 'destroy'])->name('cluster.destroy');
    });

    Route::middleware('menu:project')->group(function () {
        Route::get('cluster',       [ClusterController::class, 'index'])->name('cluster.index');
        Route::get('cluster/{id}',  [ClusterController::class, 'show'])->name('cluster.show');
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Bank
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin,admin')->group(function () {
        Route::post('bank', [BankController::class, 'store'])->name('bank.store');
        Route::get('bank/create', [BankController::class, 'create'])->name('bank.create');
        Route::get('bank/{id}/edit', [BankController::class, 'edit'])->name('bank.edit');
        Route::put('bank/{id}', [BankController::class, 'update'])->name('bank.update');
        Route::delete('bank/{id}', [BankController::class, 'destroy'])->name('bank.destroy');
    });

    Route::middleware('role:superadmin,admin,guest')->group(function () {
        Route::get('bank', [BankController::class, 'index'])->name('bank.index');
        Route::get('bank/{id}', [BankController::class, 'show'])->name('bank.show');
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Cluster Groups
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin,admin')->group(function () {
    Route::post('groups', [ClusterGroupController::class, 'store'])->name('groups.store');
    Route::get('groups/create', [ClusterGroupController::class, 'create'])->name('groups.create');
 
    // AJAX — harus sebelum {id} agar tidak tertangkap sebagai show
    Route::get('groups/{id}/edit-data', [ClusterGroupController::class, 'getEditData'])->name('groups.edit-data');
 
    Route::get('groups/{id}/edit', [ClusterGroupController::class, 'edit'])->name('groups.edit');
    Route::put('groups/{id}', [ClusterGroupController::class, 'update'])->name('groups.update');
    Route::delete('groups/{id}', [ClusterGroupController::class, 'destroy'])->name('groups.destroy');
    });
    
    Route::middleware('role:superadmin,admin,guest')->group(function () {
        Route::get('groups', [ClusterGroupController::class, 'index'])->name('groups.index');
        Route::get('groups/{id}', [ClusterGroupController::class, 'show'])->name('groups.show');
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Beneficiaries
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin,admin')->group(function () {
        Route::post('beneficiaries',               [BeneficiaryController::class, 'store'])->name('beneficiaries.store');
        Route::get('beneficiaries/create',         [BeneficiaryController::class, 'create'])->name('beneficiaries.create');
    
        // AJAX — wajib sebelum {id}
        Route::get('beneficiaries/{id}/edit-data', [BeneficiaryController::class, 'getEditData'])->name('beneficiaries.edit-data');
    
        Route::get('beneficiaries/{id}/edit',      [BeneficiaryController::class, 'edit'])->name('beneficiaries.edit');
        Route::put('beneficiaries/{id}',           [BeneficiaryController::class, 'update'])->name('beneficiaries.update');
        Route::delete('beneficiaries/{id}',        [BeneficiaryController::class, 'destroy'])->name('beneficiaries.destroy');
    });
    
    Route::middleware('menu:beneficiaries')->group(function () {
        Route::get('beneficiaries',       [BeneficiaryController::class, 'index'])->name('beneficiaries.index');
        Route::get('beneficiaries/{id}',  [BeneficiaryController::class, 'show'])->name('beneficiaries.show');
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - PIC
    |--------------------------------------------------------------------------
    */
    Route::get('master-pic/options-json', [MasterPicController::class, 'optionsJson'])->name('master-pic.options-json');

    Route::middleware('role:superadmin,admin')->group(function () {
        Route::post('master-pic',               [MasterPicController::class, 'store'])->name('master-pic.store');

        // AJAX — wajib sebelum {id}
        Route::get('master-pic/{id}/edit-data', [MasterPicController::class, 'getEditData'])->name('master-pic.edit-data');

        Route::put('master-pic/{id}',           [MasterPicController::class, 'update'])->name('master-pic.update');
        Route::delete('master-pic/{id}',        [MasterPicController::class, 'destroy'])->name('master-pic.destroy');
    });

    Route::middleware('menu:master-pic')->group(function () {
        Route::get('master-pic', [MasterPicController::class, 'index'])->name('master-pic.index');
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - SOURCE FUND
    |--------------------------------------------------------------------------
    */
    Route::get('source-fund/options-json', [SourceFundController::class, 'optionsJson'])->name('source-fund.options-json');

    Route::middleware('role:superadmin,finance')->group(function () {
        Route::post('source-fund',               [SourceFundController::class, 'store'])->name('source-fund.store');

        // AJAX — wajib sebelum {id}
        Route::get('source-fund/{id}/edit-data', [SourceFundController::class, 'getEditData'])->name('source-fund.edit-data');

        Route::put('source-fund/{id}',           [SourceFundController::class, 'update'])->name('source-fund.update');
        Route::delete('source-fund/{id}',        [SourceFundController::class, 'destroy'])->name('source-fund.destroy');
    });

    Route::middleware('menu:source-fund')->group(function () {
        Route::get('source-fund', [SourceFundController::class, 'index'])->name('source-fund.index');
    });

    /*
    |--------------------------------------------------------------------------
    | USER & ACCESS GROUP - Superadmin Only
    |--------------------------------------------------------------------------
    */

    // WRITE — Superadmin only (definisikan LEBIH DULU)
    Route::middleware('role:superadmin')->group(function () {
        Route::resource('user', UserController::class)->except(['index', 'show']);
        Route::resource('accessgroup', AccessGroupController::class)->except(['index', 'show']);
    });

    // READ — Superadmin + Guest
    // Route AJAX detail-json WAJIB sebelum {user} param
    Route::middleware('menu:user-access')->group(function () {
        Route::get('user',                  [UserController::class, 'index'])->name('user.index');
        Route::get('user/{id}/detail-json', [UserController::class, 'showJson'])->name('user.show-json');
        Route::get('user/{id}',             [UserController::class, 'show'])->name('user.show');

        Route::get('accessgroup',                    [AccessGroupController::class, 'index'])->name('accessgroup.index');
        Route::get('accessgroup/{id}/detail-json',   [AccessGroupController::class, 'showJson'])->name('accessgroup.show-json');
        Route::get('accessgroup/{id}',               [AccessGroupController::class, 'show'])->name('accessgroup.show');
    });

    /*
    |--------------------------------------------------------------------------
    | BUDGET PLAN
    |--------------------------------------------------------------------------
    */
    
    Route::get('budget-plan/{id}/detail-json',    [BudgetPlanController::class, 'showJson'])->name('budget-plan.show-json');
    Route::get('budget-plan/accounts-json',       [BudgetPlanController::class, 'getAccounts'])->name('budget-plan.accounts-json');
    
    // Workflow
    Route::post('budget-plan/{id}/submit',         [BudgetPlanController::class, 'submit'])->name('budget-plan.submit');
    Route::post('budget-plan/{id}/finance-verify', [BudgetPlanController::class, 'financeVerify'])->name('budget-plan.finance-verify');
    Route::post('budget-plan/{id}/finance-revise', [BudgetPlanController::class, 'financeRevise'])->name('budget-plan.finance-revise');
    Route::post('budget-plan/{id}/finance-reject', [BudgetPlanController::class, 'financeReject'])->name('budget-plan.finance-reject');
    Route::post('budget-plan/{id}/submit-closing', [BudgetPlanController::class, 'submitClosing'])->name('budget-plan.submit-closing');
    Route::post('budget-plan/{id}/verify-closing', [BudgetPlanController::class, 'verifyClosing'])->name('budget-plan.verify-closing');
    Route::post('budget-plan/{id}/reject-closing', [BudgetPlanController::class, 'rejectClosing'])->name('budget-plan.reject-closing');
    
    // CRUD
    Route::middleware('role:superadmin,manager')->group(function () {
        Route::get('budget-plan/create',    [BudgetPlanController::class, 'create'])->name('budget-plan.create');
        Route::post('budget-plan',          [BudgetPlanController::class, 'store'])->name('budget-plan.store');
        Route::get('budget-plan/{id}/edit', [BudgetPlanController::class, 'edit'])->name('budget-plan.edit');
        Route::put('budget-plan/{id}',      [BudgetPlanController::class, 'update'])->name('budget-plan.update');
        Route::delete('budget-plan/{id}',   [BudgetPlanController::class, 'destroy'])->name('budget-plan.destroy');

        // Import Excel — Detail Budget
        Route::get('budget-plan/import-template',  [BudgetPlanController::class, 'downloadTemplate'])->name('budget-plan.import-template');
        Route::post('budget-plan/import-preview',  [BudgetPlanController::class, 'importPreview'])->name('budget-plan.import-preview');
    });
    
    Route::middleware('menu:finance-budgeting,project-budgeting')->group(function () {
        Route::get('budget-plan',      [BudgetPlanController::class, 'index'])->name('budget-plan.index');
        Route::get('budget-plan/{id}', [BudgetPlanController::class, 'show'])->name('budget-plan.show');
    });

    /*
    |--------------------------------------------------------------------------
    | COST REQUEST (budget_request)
    |--------------------------------------------------------------------------
    */
    
    Route::get('budget-request/{id}/detail-json', [BudgetRequestController::class, 'showJson'])->name('budget-request.show-json');
    Route::post('budget-request/check-nob',       [BudgetRequestController::class, 'checkNob'])->name('budget-request.check-nob');
    
    // Workflow
    Route::post('budget-request/{id}/submit',              [BudgetRequestController::class, 'submit'])->name('budget-request.submit');
    Route::post('budget-request/{id}/head-approve',        [BudgetRequestController::class, 'headApprove'])->name('budget-request.head-approve');
    Route::post('budget-request/{id}/head-revise',         [BudgetRequestController::class, 'headRevise'])->name('budget-request.head-revise');
    Route::post('budget-request/{id}/head-reject',         [BudgetRequestController::class, 'headReject'])->name('budget-request.head-reject');
    Route::post('budget-request/{id}/finance-verify',      [BudgetRequestController::class, 'financeVerify'])->name('budget-request.finance-verify');
    Route::post('budget-request/{id}/finance-revise',      [BudgetRequestController::class, 'financeRevise'])->name('budget-request.finance-revise');
    Route::post('budget-request/{id}/finance-reject',      [BudgetRequestController::class, 'financeReject'])->name('budget-request.finance-reject');
    Route::post('budget-request/{id}/submit-realization',  [BudgetRequestController::class, 'submitRealization'])->name('budget-request.submit-realization');
    Route::post('budget-request/{id}/verify-realization',  [BudgetRequestController::class, 'verifyRealization'])->name('budget-request.verify-realization');
    Route::post('budget-request/{id}/reject-realization',  [BudgetRequestController::class, 'rejectRealization'])->name('budget-request.reject-realization');
    
    // CRUD
    Route::middleware('role:superadmin,manager')->group(function () {
        Route::get('budget-request/create',    [BudgetRequestController::class, 'create'])->name('budget-request.create');
        Route::post('budget-request',          [BudgetRequestController::class, 'store'])->name('budget-request.store');
        Route::get('budget-request/{id}/edit', [BudgetRequestController::class, 'edit'])->name('budget-request.edit');
        Route::put('budget-request/{id}',      [BudgetRequestController::class, 'update'])->name('budget-request.update');
        Route::delete('budget-request/{id}',   [BudgetRequestController::class, 'destroy'])->name('budget-request.destroy');
    });
    
    Route::middleware('menu:finance-summary')->group(function () {
        Route::get('finance-summary', [FinanceSummaryController::class, 'index'])->name('finance-summary.index');
    });

    Route::middleware('menu:finance-cost-request,project-cost-request')->group(function () {
        Route::get('budget-request',          [BudgetRequestController::class, 'index'])->name('budget-request.index');
        Route::get('budget-request/export',   [BudgetRequestController::class, 'export'])->name('budget-request.export');
        Route::get('budget-request/{id}',     [BudgetRequestController::class, 'show'])->name('budget-request.show');
    });

        /*
    |--------------------------------------------------------------------------
    | SERVICE MANAGEMENT - PROJECT MONITORING
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu:project-monitoring')->group(function () {

        Route::get(
            'project-monitoring',
            [ProjectMonitoringController::class, 'index']
        )->name('project-monitoring.index');

        Route::get(
            'project-monitoring/{id}',
            [ProjectMonitoringController::class, 'show']
        )->name('project-monitoring.show');

    });

        /*
    |--------------------------------------------------------------------------
    | FINANCE MANAGEMENT - INVOICE CASH RECEIPT
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:superadmin,admin,finance')->group(function () {
        Route::get('invoice-cash-receipt/create',    [InvoiceCashReceiptController::class, 'create'])->name('invoice-cash-receipt.create');
        Route::post('invoice-cash-receipt',           [InvoiceCashReceiptController::class, 'store'])->name('invoice-cash-receipt.store');
        Route::get('invoice-cash-receipt/{id}/edit',  [InvoiceCashReceiptController::class, 'edit'])->name('invoice-cash-receipt.edit');
        Route::put('invoice-cash-receipt/{id}',       [InvoiceCashReceiptController::class, 'update'])->name('invoice-cash-receipt.update');
        Route::delete('invoice-cash-receipt/{id}',    [InvoiceCashReceiptController::class, 'destroy'])->name('invoice-cash-receipt.destroy');

        Route::post('invoice-cash-receipt/{id}/submit', [InvoiceCashReceiptController::class, 'submit'])->name('invoice-cash-receipt.submit');
        Route::post('invoice-cash-receipt/{id}/sent',   [InvoiceCashReceiptController::class, 'sent'])->name('invoice-cash-receipt.sent');
        Route::post('invoice-cash-receipt/{id}/cash-receipt', [InvoiceCashReceiptController::class, 'storeCashReceipt'])->name('invoice-cash-receipt.cash-receipt.store');
        Route::delete('invoice-cash-receipt/{id}/cash-receipt/{crId}', [InvoiceCashReceiptController::class, 'destroyCashReceipt'])->name('invoice-cash-receipt.cash-receipt.destroy');
    });

    Route::middleware('role:superadmin,finance_manager')->group(function () {
        Route::post('invoice-cash-receipt/{id}/verify', [InvoiceCashReceiptController::class, 'verify'])->name('invoice-cash-receipt.verify');
    });

    Route::middleware('role:superadmin,approver')->group(function () {
        Route::post('invoice-cash-receipt/{id}/approve', [InvoiceCashReceiptController::class, 'approve'])->name('invoice-cash-receipt.approve');
    });

    Route::middleware('role:superadmin,finance_manager,approver')->group(function () {
        Route::post('invoice-cash-receipt/{id}/revise', [InvoiceCashReceiptController::class, 'revise'])->name('invoice-cash-receipt.revise');
        Route::post('invoice-cash-receipt/{id}/cancel', [InvoiceCashReceiptController::class, 'cancel'])->name('invoice-cash-receipt.cancel');
    });

    Route::middleware('menu:invoice-cash-receipt')->group(function () {

        Route::get(
            'invoice-cash-receipt',
            [InvoiceCashReceiptController::class, 'index']
        )->name('invoice-cash-receipt.index');


        Route::get(
            'invoice-cash-receipt/{id}',
            [InvoiceCashReceiptController::class, 'show']
        )->name('invoice-cash-receipt.show');

    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Stage
    |--------------------------------------------------------------------------
    */


    Route::middleware('role:superadmin,admin')->group(function () {
        Route::post('stage',               [StageController::class, 'store'])->name('stage.store');
        Route::get('stage/create',         [StageController::class, 'create'])->name('stage.create');
    
        // AJAX — wajib sebelum {id}
        Route::get('stage/next-code',      [StageController::class, 'getNextCode'])->name('stage.next-code');
        Route::get('stage/{id}/edit-data', [StageController::class, 'getEditData'])->name('stage.edit-data');
    
        Route::get('stage/{id}/edit',      [StageController::class, 'edit'])->name('stage.edit');
        Route::put('stage/{id}',           [StageController::class, 'update'])->name('stage.update');
        Route::delete('stage/{id}',        [StageController::class, 'destroy'])->name('stage.destroy');
    });
    
    Route::middleware('menu:milestone')->group(function () {
        Route::get('stage',      [StageController::class, 'index'])->name('stage.index');
        Route::get('stage/{id}', [StageController::class, 'show'])->name('stage.show');
    });

    /*
    |--------------------------------------------------------------------------
    | ROLE MANAGEMENT — Superadmin Only
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin')->group(function () {
        Route::get('role',              [RoleController::class, 'index'])->name('role.index');
        Route::get('role/{id}/edit-data',[RoleController::class, 'getEditData'])->name('role.edit-data');
        Route::get('role/{id}/edit',    [RoleController::class, 'edit'])->name('role.edit');
        Route::put('role/{id}',         [RoleController::class, 'update'])->name('role.update');
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Account
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin,admin')->group(function () {
        Route::post('account',               [AccountController::class, 'store'])->name('account.store');
        Route::get('account/{id}/edit-data', [AccountController::class, 'getEditData'])->name('account.edit-data');
        Route::put('account/{id}',           [AccountController::class, 'update'])->name('account.update');
        Route::delete('account/{id}',        [AccountController::class, 'destroy'])->name('account.destroy');
    });
    
    Route::middleware('menu:account')->group(function () {
        Route::get('account', [AccountController::class, 'index'])->name('account.index');
    });

    /*
    |--------------------------------------------------------------------------
    | COST CENTER (Overhead & Payroll)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin,admin,finance')->group(function () {
        Route::post('cost-center',            [CostCenterController::class, 'store'])->name('cost-center.store');
        Route::put('cost-center/{id}',        [CostCenterController::class, 'update'])->name('cost-center.update');
        Route::delete('cost-center/{id}',     [CostCenterController::class, 'destroy'])->name('cost-center.destroy');
    });

    Route::middleware('menu:cost-center')->group(function () {
        Route::get('cost-center',                    [CostCenterController::class, 'index'])->name('cost-center.index');
        Route::get('cost-center/{id}/detail-json',   [CostCenterController::class, 'showJson'])->name('cost-center.show-json');
    });

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE (Master Karyawan — untuk Payroll)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:superadmin,admin,hr')->group(function () {
        Route::get('employee/create',       [EmployeeController::class, 'create'])->name('employee.create');
        Route::post('employee',             [EmployeeController::class, 'store'])->name('employee.store');
        Route::get('employee/{id}/edit',    [EmployeeController::class, 'edit'])->name('employee.edit');
        Route::put('employee/{id}',         [EmployeeController::class, 'update'])->name('employee.update');
        Route::delete('employee/{id}',      [EmployeeController::class, 'destroy'])->name('employee.destroy');
    });

    Route::middleware('menu:employee')->group(function () {
        Route::get('employee',                     [EmployeeController::class, 'index'])->name('employee.index');
        Route::get('employee/{id}/detail-json',    [EmployeeController::class, 'showJson'])->name('employee.show-json');
    });

    /*
    |--------------------------------------------------------------------------
    | PAYROLL RUN
    |--------------------------------------------------------------------------
    | Akses dijaga di dalam controller (guardAccess) untuk role payroll:
    | hr, finance_manager, approver, superadmin. Menu tetap menu:payroll-run.
    */
    Route::middleware('menu:payroll-run')->group(function () {
        Route::get('payroll-run',                [PayrollRunController::class, 'index'])->name('payroll-run.index');
        Route::get('payroll-run/create',         [PayrollRunController::class, 'create'])->name('payroll-run.create');
        Route::post('payroll-run',               [PayrollRunController::class, 'store'])->name('payroll-run.store');
        Route::get('payroll-run/{id}',           [PayrollRunController::class, 'show'])->name('payroll-run.show');
        Route::get('payroll-run/{id}/edit',      [PayrollRunController::class, 'edit'])->name('payroll-run.edit');
        Route::put('payroll-run/{id}',           [PayrollRunController::class, 'update'])->name('payroll-run.update');
        Route::delete('payroll-run/{id}',        [PayrollRunController::class, 'destroy'])->name('payroll-run.destroy');

        // Workflow
        Route::post('payroll-run/{id}/submit',   [PayrollRunController::class, 'submit'])->name('payroll-run.submit');
        Route::post('payroll-run/{id}/verify',   [PayrollRunController::class, 'verify'])->name('payroll-run.verify');
        Route::post('payroll-run/{id}/approve',  [PayrollRunController::class, 'approve'])->name('payroll-run.approve');
        Route::post('payroll-run/{id}/reject',   [PayrollRunController::class, 'reject'])->name('payroll-run.reject');
        Route::post('payroll-run/{id}/reopen',   [PayrollRunController::class, 'reopen'])->name('payroll-run.reopen');

        // Pembayaran (opsi B — export langsung, tanpa Daily Transaction)
        Route::get('payroll-run/{id}/export',    [PayrollRunController::class, 'export'])->name('payroll-run.export');
        Route::post('payroll-run/{id}/paid',     [PayrollRunController::class, 'markPaid'])->name('payroll-run.paid');
        Route::post('payroll-run/{id}/close',    [PayrollRunController::class, 'close'])->name('payroll-run.close');
    });

    
    /*
    |--------------------------------------------------------------------------
    | DAILY TRANSACTION
    |--------------------------------------------------------------------------
    */
    
    Route::get('daily-transaction/{id}/detail-json',                  [DailyTransactionController::class, 'showJson'])->name('daily-transaction.show-json');
    Route::post('daily-transaction/{id}/approve',                     [DailyTransactionController::class, 'approve'])->name('daily-transaction.approve');
    Route::post('daily-transaction/{id}/reject-batch',                [DailyTransactionController::class, 'rejectBatch'])->name('daily-transaction.reject-batch');
    Route::post('daily-transaction/{id}/add-item',                    [DailyTransactionController::class, 'addItem'])->name('daily-transaction.add-item');
    Route::post('daily-transaction/{id}/mark-paid',                   [DailyTransactionController::class, 'markPaid'])->name('daily-transaction.mark-paid');
    Route::post('daily-transaction/{batchId}/reject-item/{itemId}',   [DailyTransactionController::class, 'rejectItem'])->name('daily-transaction.reject-item');
    
    Route::middleware('role:superadmin,finance')->group(function () {
        Route::get('daily-transaction/create', [DailyTransactionController::class, 'create'])->name('daily-transaction.create');
        Route::post('daily-transaction', [DailyTransactionController::class, 'store'])->name('daily-transaction.store');
    });
    
    Route::middleware('menu:finance-daily-transaction')->group(function () {
        Route::get('daily-transaction',             [DailyTransactionController::class, 'index'])->name('daily-transaction.index');
        Route::get('daily-transaction/{id}/export', [DailyTransactionController::class, 'export'])->name('daily-transaction.export');
        Route::get('daily-transaction/{id}',        [DailyTransactionController::class, 'show'])->name('daily-transaction.show');
    });

        /*
    |--------------------------------------------------------------------------
    | API ROUTES
    |--------------------------------------------------------------------------
    */
    Route::get('/api/clusters/{budgetPlanId}', [BudgetRequestController::class, 'getClusters']);
    Route::get('/api/budget-plan/{budgetPlanId}/details', [BudgetPlanController::class, 'getDetails']);

});
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SecureFileController;
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
use App\Http\Controllers\TeamController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\BudgetPlanController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\BudgetRequestController;
use App\Http\Controllers\ProjectMonitoringController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\InvoiceCashReceiptController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\CostRegisterController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\TodoListController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DailyTransactionController;
use App\Http\Controllers\FinanceSummaryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| AUTHENTICATION ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');

    // throttle:5,1 = maksimal 5 percobaan per menit per IP+username. Tanpa ini
    // tidak ada apa pun yang memperlambat percobaan password beruntun; batas
    // per menit cukup longgar untuk salah ketik, cukup ketat untuk membuat
    // serangan daftar-password tidak praktis. Kuncinya diatur di
    // AuthController::login() lewat RateLimiter supaya ikut menghitung username,
    // bukan IP saja (kantor yang berbagi satu IP publik tidak saling mengunci).
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

    // LUPA SANDI — reset password tamu lewat kode email. Di-throttle ketat
    // per IP; endpoint "send" & "reset" punya rate-limit tambahan di controller.
    Route::post('/forgot/verify', [AuthController::class, 'forgotVerify'])->middleware('throttle:15,1')->name('forgot.verify');
    Route::post('/forgot/send',   [AuthController::class, 'forgotSendCode'])->middleware('throttle:10,1')->name('forgot.send');
    Route::post('/forgot/reset',  [AuthController::class, 'forgotReset'])->middleware('throttle:15,1')->name('forgot.reset');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| BERKAS LAMPIRAN
|--------------------------------------------------------------------------
| Semua lampiran (invoice, BAST, bukti transfer, bukti settlement, dokumen
| pendukung CR) tersimpan di disk privat dan hanya keluar lewat route ini,
| yang memeriksa sesi + hak akses per modul. Bentuk URL-nya sengaja sama
| dengan pola lama /storage/{path} supaya tautan yang sudah tersebar di Blade
| dan JavaScript tetap berfungsi tanpa diubah.
|
| Diletakkan SEBELUM grup 'auth' yang besar karena punya middleware sendiri.
*/
Route::middleware('auth')
    ->get('/storage/{path}', [SecureFileController::class, 'show'])
    ->where('path', '.*')
    ->name('secure-file');

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'fetch'])->name('fetch');
        Route::post('/{id}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    });

    /*
    | Command palette (Ctrl+K). Tanpa gerbang menu di sini: tiap modul
    | digerbangi sendiri di dalam controller memakai kunci menu yang sama
    | dengan route index-nya, lalu di-scope memakai aturan modul itu sendiri.
    |
    | throttle:60,1 — endpoint ini yang paling mahal per permintaan di seluruh
    | aplikasi (pencarian lintas lima tabel terbesar) sekaligus yang paling
    | mudah dipicu: satu ketikan. Peramban sudah men-debounce 500 ms dan
    | membatalkan permintaan basi, tapi debounce di sisi klien bukan batas —
    | ia bisa dilewati hanya dengan memanggil URL-nya langsung. 60 per menit
    | jauh di atas pemakaian wajar, dan cukup rendah untuk mencegah satu
    | pengguna menghabiskan koneksi basis data untuk semua orang.
    */
    Route::middleware('throttle:60,1')
        ->get('global-search', [\App\Http\Controllers\GlobalSearchController::class, 'search'])
        ->name('global-search');

    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::post('/avatar', [ProfileController::class, 'uploadAvatar'])->name('avatar');
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar'])->name('avatar.delete');
        Route::post('/cover', [ProfileController::class, 'uploadCover'])->name('cover');
        Route::delete('/cover', [ProfileController::class, 'deleteCover'])->name('cover.delete');
        Route::post('/notifications', [ProfileController::class, 'updateNotifications'])->name('notifications');
        Route::post('/password/token', [ProfileController::class, 'requestPasswordToken'])->name('password.token');
        Route::post('/password', [ProfileController::class, 'changePassword'])->name('password');
        Route::get('/todo-summary', [ProfileController::class, 'todoSummary'])->name('todo-summary');
        Route::get('/todo-matrix', [ProfileController::class, 'todoMatrix'])->name('todo-matrix');
    });

    // VIEW AS — superadmin mencoba UI/menu sebagai role lain (preview).
    Route::post('view-as', [ProfileController::class, 'viewAs'])->name('view-as');
    Route::post('view-as/reset', [ProfileController::class, 'resetViewAs'])->name('view-as.reset');

    // Panduan Penggunaan — tersedia untuk semua user login (tanpa gerbang menu).
    Route::prefix('panduan')->name('panduan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PanduanController::class, 'index'])->name('index');
        Route::get('/{slug}', [\App\Http\Controllers\PanduanController::class, 'show'])->name('show');
    });

    // Halaman utama aplikasi = Profil Saya (menggantikan Dashboard).
    // Sekaligus menandai aplikasi aktif = App Default pilihan user, jadi
    // sidebar/menu yang muncul mengikuti preferensi (lihat layouts/app.blade.php
    // $currentApp & User::defaultApp()).
    Route::get('/', function (\Illuminate\Http\Request $request) {
        $user = \Illuminate\Support\Facades\Auth::user();
        session(['tesys.app' => $user->defaultApp()]);

        /* Aplikasi yang punya 'landing' (Operations → /trouble-ticket)
           membawa penggunanya langsung ke modul itu, juga saat ia membuka
           alamat utama — bukan hanya sesudah login (keputusan 20 Okt 2026).
           Tanpa ini teknisi mendarat di Profil Saya. */
        $landing = config('apps.items.' . $user->defaultApp() . '.landing');
        if ($landing) {
            return redirect($landing);
        }

        return app(ProfileController::class)->show($request);
    });

    // Muka aplikasi selain Finance (HR, Logistic, Operation, Project).
    // Daftarnya di config/apps.php; semuanya masih "Coming Soon".
    Route::get('app/{app}', [\App\Http\Controllers\AppShellController::class, 'show'])->name('app.home');

    Route::post('changelog/dismiss', [\App\Http\Controllers\ChangelogController::class, 'dismiss'])->name('changelog.dismiss');

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Project
    |--------------------------------------------------------------------------
    */
   // AJAX
    Route::get('project/{id}/detail-json', [ProjectController::class, 'showJson'])->name('project.show-json');
    Route::post('project/{id}/log-note', [ProjectController::class, 'storeLogNote'])->middleware('throttle:30,1')->name('project.log-note');

    // Export daftar PO ke Excel — hanya superadmin & approver.
    // POST juga didukung — export mengirim daftar ID baris hasil filter aktif
    // di tabel (bisa ribuan, tidak muat di query string GET).
    Route::match(['get', 'post'], 'project/export-excel', [ProjectController::class, 'exportExcel'])
        ->middleware('role:superadmin,approver')
        ->name('project.export-excel');
    
    // Options JSON untuk dropdown
    /*
    | Pengisi dropdown. Sebelumnya hanya bermiddleware 'auth', sehingga setiap
    | pengguna yang login - termasuk peran yang tidak punya menunya sama sekali -
    | bisa menarik DAFTAR LENGKAP cluster & stage hanya dengan membuka URL-nya.
    | Bukan data transaksi, tapi tidak ada alasan membukanya, dan gerbangnya
    | disamakan dengan halaman yang memang memakainya.
    */
    /*
    | 'list-po' ikut dibolehkan: form Tambah/Edit PO mengisi dropdown Project &
    | Milestone dari sini, sedangkan Head tidak punya menu master Project Master
    | maupun Milestone - tanpa ini kedua dropdown itu selalu "Tidak ada data".
    */
    Route::middleware('menu:project,list-po')
        ->get('cluster/options-json', fn() => response()->json(\App\Models\Cluster::orderBy('name')->get(['id','name'])))
        ->name('cluster.options-json');
    Route::middleware('menu:milestone,list-po')
        ->get('stage/options-json', fn() => response()->json(\App\Models\Stage::orderBy('name')->get(['id','name'])))
        ->name('stage.options-json');
    
    // Workflow actions — otorisasi tahap (status) tetap dijaga di controller,
    // gerbang role di sini mencegah endpoint dipanggil langsung oleh role lain.
    Route::middleware('role:superadmin,head')->group(function () {
        Route::post('project/{id}/submit',         [ProjectController::class, 'submit'])->name('project.submit');
        Route::post('project/{id}/submit-closing', [ProjectController::class, 'submitClosing'])->name('project.submit-closing');
    });

    // Bulk Verify/Approve/Minta Revisi: tiap PO diproses method satuan di bawah. Role per tahap
    // (SUBMITTED: finance group, VERIFIED: approver) dicek ulang di ProjectController::bulkProses().
    Route::middleware('role:superadmin,finance,finance_manager,approver')->group(function () {
        Route::post('project/bulk-approve', [ProjectController::class, 'bulkApprove'])->name('project.bulk-approve');
        Route::post('project/bulk-revise',  [ProjectController::class, 'bulkRevise'])->name('project.bulk-revise');
    });

    // Approval PO dua langkah: Finance VERIFY, lalu Approver APPROVE.
    Route::middleware('role:superadmin,finance,finance_manager')->group(function () {
        Route::post('project/{id}/verify',          [ProjectController::class, 'verify'])->name('project.verify');
        Route::post('project/{id}/verify-closing',  [ProjectController::class, 'verifyClosing'])->name('project.verify-closing');
        // Tutup langsung dari PAID, tanpa menunggu MoM Closing dari Head.
        Route::post('project/{id}/finance-close',    [ProjectController::class, 'financeClose'])->name('project.finance-close');
        Route::post('project/{id}/reject-closing',  [ProjectController::class, 'rejectClosing'])->name('project.reject-closing');
    });

    // APPROVE (VERIFIED → Early Start / Waiting PO / Active) — Approver.
    Route::middleware('role:superadmin,approver')->group(function () {
        Route::post('project/{id}/approve', [ProjectController::class, 'approve'])->name('project.approve');
    });

    // REVISE (SUBMITTED/VERIFIED → DRAFT) — Finance mengembalikan dari Submitted,
    // Approver dari Verified. Gerbang status dicek di controller.
    Route::middleware('role:superadmin,finance,finance_manager,approver')->group(function () {
        Route::post('project/{id}/finance-revise',  [ProjectController::class, 'financeRevise'])->name('project.finance-revise');
    });

    // Aktivasi WAITING_PO → ACTIVE via form edit lengkap (Head/Finance)
    Route::middleware('role:superadmin,finance,finance_manager,head')->group(function () {
        Route::post('project/{id}/activate-from-waiting', [ProjectController::class, 'activateFromWaiting'])->name('project.activate-from-waiting');
        Route::get('project/{id}/activate',               [ProjectController::class, 'activateForm'])->name('project.activate-form');
        Route::put('project/{id}/activate',               [ProjectController::class, 'activate'])->name('project.activate');

        // No. PO (detail baris per Purchase Order dalam 1 item scope of work)
        Route::post('project/po-number/{poNumberId}/status',        [ProjectController::class, 'updatePoNumberStatus'])->name('project.po-number.status');
        Route::post('project/po-number/{poNumberId}/complete-bast', [ProjectController::class, 'completePoNumberBast'])->name('project.po-number.complete-bast');
        Route::delete('project/po-number/{poNumberId}',             [ProjectController::class, 'destroyPoNumber'])->name('project.po-number.destroy');
    });

    // Hapus BAST (soft delete) — superadmin saja; syarat "belum ditagih" dicek di controller.
    Route::middleware('role:superadmin')->group(function () {
        Route::delete('project/po-bast/{bastId}', [ProjectController::class, 'destroyBast'])->name('project.po-bast.destroy');
    });

    // Submit BAST — halaman terpisah, bisa banyak No. PO sekaligus per project
    Route::middleware('role:superadmin,head')->group(function () {
        Route::get('project/{id}/submit-bast',  [ProjectController::class, 'showBastPage'])->name('project.submit-bast');
        Route::post('project/{id}/submit-bast', [ProjectController::class, 'storeBastBatch'])->name('project.submit-bast.store');
    });

    // CRUD
    Route::middleware('role:superadmin,head')->group(function () {
        Route::get('project/create',    [ProjectController::class, 'create'])->name('project.create');
        Route::post('project',          [ProjectController::class, 'store'])->name('project.store');
        Route::delete('project/{id}',   [ProjectController::class, 'destroy'])->name('project.destroy');

        // Import Excel — Project & Milestone (wajib sebelum route wildcard project/{id})
        Route::get('project/import-template', [ProjectController::class, 'downloadDetailTemplate'])->name('project.import-template');
        Route::post('project/import-preview', [ProjectController::class, 'importDetailPreview'])->name('project.import-preview');

        // Import Excel — Detail Budget di form PO (source terkunci PROJECT; wajib sebelum route wildcard project/{id})
        Route::get('project/budget-import-template', [ProjectController::class, 'downloadBudgetTemplate'])->name('project.budget-import-template');
        Route::post('project/budget-import-preview', [ProjectController::class, 'importBudgetPreview'])->name('project.budget-import-preview');
    });

    // Edit — superadmin/head bebas, finance juga boleh (gerbang detail per-status ada di controller).
    // Approver & Finance Manager boleh mengedit PO pada status apa pun
    // (Project::FULL_EDIT_ROLES); pembatasan per-status untuk peran lain tetap
    // dijaga di ProjectController::edit()/update().
    Route::middleware('role:superadmin,approver,finance,finance_manager,head')->group(function () {
        Route::get('project/{id}/edit', [ProjectController::class, 'edit'])->name('project.edit');
        Route::put('project/{id}',      [ProjectController::class, 'update'])->name('project.update');

        // Edit terbatas — PO status WAITING_PO, cuma bisa tambah No. PO
        Route::get('project/{id}/edit-po-numbers', [ProjectController::class, 'editPoNumbers'])->name('project.edit-po-numbers');
        Route::put('project/{id}/edit-po-numbers', [ProjectController::class, 'updatePoNumbers'])->name('project.update-po-numbers');

        // Import Excel — No. PO
        Route::get('project/po-number/import-template', [ProjectController::class, 'downloadPoNumberTemplate'])->name('project.po-number.import-template');
        Route::post('project/po-number/import-preview', [ProjectController::class, 'importPoNumberPreview'])->name('project.po-number.import-preview');
    });
    
    // Index & show — akses berbasis menu (List PO = /project)
    Route::middleware('menu:list-po')->group(function () {
        Route::get('project',      [ProjectController::class, 'index'])->name('project.index');
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Customer
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu:customer')->group(function () {
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
    Route::middleware('menu:project')->group(function () {
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
    | DUA TINGKAT KEWENANGAN, sengaja dipisah:
    |
    |   menu:beneficiaries   -> boleh MELIHAT daftar & MENAMBAH penerima dana
    |   + role:superadmin    -> boleh MENGUBAH & MENGHAPUS
    |
    | Gerbang 'menu' sendirian tidak cukup untuk aturan ini: ia bersifat
    | semua-atau-tidak-sama-sekali. Manager perlu bisa mendaftarkan penerima
    | dana baru saat membuat Cost Request, tapi tidak boleh menyentuh baris
    | lama — Beneficiary dipakai Cost Request yang sudah disetujui, jadi
    | menyunting nomor rekening sebuah baris diam-diam mengubah arti dokumen
    | yang sudah berjalan.
    |
    | Daftar role di lapis kedua harus SAMA PERSIS dengan
    | BeneficiaryController::MANAGE_ROLES — konstanta itu yang menentukan
    | tombol Edit & Hapus dirender atau tidak. Kalau keduanya berbeda,
    | gejalanya membingungkan: tombolnya terlihat lalu menabrak 403, atau
    | sebaliknya hilang padahal role-nya berhak.
    |
    | Route 'beneficiaries.create', 'beneficiaries.edit' dan
    | 'beneficiaries.show' DIHAPUS di sini: ketiganya sudah mati sejak lama —
    | dua yang pertama me-return view('beneficiaries.create'/'edit') yang
    | tidak pernah ada (formulirnya modal di dalam index), dan yang terakhir
    | menunjuk BeneficiaryController@show yang juga tidak ada. Membiarkannya
    | berarti Manager yang kini boleh masuk bisa menabrak error 500 lewat URL.
    */
    Route::middleware('menu:beneficiaries')->group(function () {
        Route::get('beneficiaries',  [BeneficiaryController::class, 'index'])->name('beneficiaries.index');
        Route::post('beneficiaries', [BeneficiaryController::class, 'store'])->name('beneficiaries.store');

        // Pemuatan lazy untuk halaman index: baris tab External/Database dan
        // ringkasan per PIC diambil saat dibutuhkan, bukan di tiap muat halaman.
        Route::get('beneficiaries/rows/{part}', [BeneficiaryController::class, 'rows'])->name('beneficiaries.rows');
        Route::get('beneficiaries/pic-groups',  [BeneficiaryController::class, 'picGroups'])->name('beneficiaries.pic-groups');

        Route::middleware('role:superadmin')->group(function () {
            // Daftar pemilih "Tambahkan Beneficiary" di modal Detail PIC.
            Route::get('beneficiaries/picker', [BeneficiaryController::class, 'picker'])->name('beneficiaries.picker');

            // AJAX pengisi modal Edit — ikut dikunci. Tanpa ini, data lengkap
            // (nomor rekening, kontak) masih bisa ditarik lewat URL oleh role
            // yang tombol Edit-nya sudah disembunyikan.
            Route::get('beneficiaries/{id}/edit-data', [BeneficiaryController::class, 'getEditData'])->name('beneficiaries.edit-data');

            // Menghapus satu PIC beserta datanya di Master PIC. Beneficiary-nya
            // tidak ikut terhapus, hanya kehilangan PIC (pic_id nullOnDelete).
            Route::delete('beneficiaries/pic/{picId}', [BeneficiaryController::class, 'destroyPic'])->name('beneficiaries.pic-destroy');

            // Memindahkan satu beneficiary ke PIC lain tanpa membuka modal Edit.
            Route::put('beneficiaries/{id}/move-pic', [BeneficiaryController::class, 'movePic'])->name('beneficiaries.move-pic');

            // Kebalikan dari move-pic: dari sisi PIC, kaitkan beberapa
            // beneficiary yang SUDAH ADA sekaligus — tombol "Tambahkan
            // Beneficiary" di dalam modal Detail PIC. Tidak membuat baris baru.
            Route::post('beneficiaries/pic/{picId}/attach', [BeneficiaryController::class, 'attachToPic'])->name('beneficiaries.pic-attach');

            Route::put('beneficiaries/{id}',    [BeneficiaryController::class, 'update'])->name('beneficiaries.update');
            Route::delete('beneficiaries/{id}', [BeneficiaryController::class, 'destroy'])->name('beneficiaries.destroy');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - PIC
    |--------------------------------------------------------------------------
    */
    // Lihat catatan gerbang options-json di atas.
    Route::middleware('menu:master-pic')
        ->get('master-pic/options-json', [MasterPicController::class, 'optionsJson'])
        ->name('master-pic.options-json');

    Route::middleware('menu:master-pic')->group(function () {
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
    | MASTER DATA - SITE & MATERIAL
    |--------------------------------------------------------------------------
    | CRUD bersama (Concerns\ManagesMasterRecords). Kunci menu = prefix URL =
    | prefix nama rute; harus sama dengan menu_items.key & config/navigation.php.
    */
    $masterModules = [
        'category-item'   => \App\Http\Controllers\CategoryItemController::class,
        'master-material' => \App\Http\Controllers\MasterMaterialController::class,
        'accessories'     => \App\Http\Controllers\AccessoryController::class,
        'site'            => \App\Http\Controllers\SiteController::class,
        'network'         => \App\Http\Controllers\NetworkController::class,
        'homebase'        => \App\Http\Controllers\HomebaseController::class,
        'region'          => \App\Http\Controllers\RegionController::class,
    ];

    foreach ($masterModules as $key => $controller) {
        Route::middleware('menu:' . $key)->group(function () use ($key, $controller) {
            Route::get($key, [$controller, 'index'])->name($key . '.index');
            Route::post($key, [$controller, 'store'])->name($key . '.store');
            Route::get($key . '/{id}/edit-data', [$controller, 'editData'])->whereNumber('id')->name($key . '.edit-data');
            Route::put($key . '/{id}', [$controller, 'update'])->whereNumber('id')->name($key . '.update');
            Route::delete($key . '/{id}', [$controller, 'destroy'])->whereNumber('id')->name($key . '.destroy');

            if (in_array(\App\Http\Controllers\Concerns\ImportsMasterRecords::class, class_uses($controller))) {
                Route::get($key . '/import-template', [$controller, 'importTemplate'])->name($key . '.import-template');
                Route::post($key . '/import', [$controller, 'import'])->name($key . '.import');
            }
        });
    }

    Route::middleware('menu:site')->group(function () {
        Route::get('site/create', [\App\Http\Controllers\SiteController::class, 'create'])->name('site.create');
        Route::get('site/{id}/edit', [\App\Http\Controllers\SiteController::class, 'edit'])->whereNumber('id')->name('site.edit');
        Route::get('site/{id}/drawer', [\App\Http\Controllers\SiteController::class, 'drawer'])->whereNumber('id')->name('site.drawer');
    });

    // Relasi Site <-> Material / Accessories (site_material, site_accessories).
    Route::middleware('menu:site')->prefix('site/{id}')->whereNumber('id')->group(function () {
        Route::get('/', [\App\Http\Controllers\SiteController::class, 'show'])->name('site.show');
        Route::post('material', [\App\Http\Controllers\SiteController::class, 'addMaterial'])->name('site.material.store');
        Route::delete('material/{linkId}', [\App\Http\Controllers\SiteController::class, 'removeMaterial'])->whereNumber('linkId')->name('site.material.destroy');
        Route::post('accessories', [\App\Http\Controllers\SiteController::class, 'addAccessory'])->name('site.accessories.store');
        Route::put('accessories/{linkId}', [\App\Http\Controllers\SiteController::class, 'updateAccessory'])->whereNumber('linkId')->name('site.accessories.update');
        Route::delete('accessories/{linkId}', [\App\Http\Controllers\SiteController::class, 'removeAccessory'])->whereNumber('linkId')->name('site.accessories.destroy');
        Route::post('pic', [\App\Http\Controllers\SiteController::class, 'addPic'])->name('site.pic.store');
        Route::delete('pic/{linkId}', [\App\Http\Controllers\SiteController::class, 'removePic'])->whereNumber('linkId')->name('site.pic.destroy');
        // Dokumen site: berkas disimpan di disk `secure`, diunduh lewat SecureFileController.
        Route::post('document', [\App\Http\Controllers\SiteController::class, 'addDocument'])->name('site.document.store');
        Route::delete('document/{docId}', [\App\Http\Controllers\SiteController::class, 'removeDocument'])->whereNumber('docId')->name('site.document.destroy');
        // Preventive Maintenance: catatan SN perangkat sebelum/sesudah kunjungan.
        Route::post('maintenance', [\App\Http\Controllers\SiteController::class, 'storeMaintenance'])->name('site.maintenance.store');
    });

    // Relasi Homebase <-> PIC (homebase_detail).
    Route::middleware('menu:homebase')->prefix('homebase/{id}')->whereNumber('id')->group(function () {
        Route::get('/', [\App\Http\Controllers\HomebaseController::class, 'show'])->name('homebase.show');
        Route::post('pic', [\App\Http\Controllers\HomebaseController::class, 'addPic'])->name('homebase.pic.store');
        Route::delete('pic/{detailId}', [\App\Http\Controllers\HomebaseController::class, 'removePic'])->whereNumber('detailId')->name('homebase.pic.destroy');
    });

    // Wilayah: satu rute per jenjang (jenjang dibaca controller dari segmen URL).
    Route::middleware('menu:wilayah')->group(function () {
        Route::redirect('wilayah', '/wilayah/pulau')->name('wilayah.index');

        foreach (array_keys(\App\Http\Controllers\WilayahController::LEVELS) as $level) {
            $ctrl = \App\Http\Controllers\WilayahController::class;
            Route::get('wilayah/' . $level, [$ctrl, 'index'])->name('wilayah.' . $level . '.index');
            Route::post('wilayah/' . $level, [$ctrl, 'store'])->name('wilayah.' . $level . '.store');
            Route::get('wilayah/' . $level . '/{id}/edit-data', [$ctrl, 'editData'])->whereNumber('id')->name('wilayah.' . $level . '.edit-data');
            Route::put('wilayah/' . $level . '/{id}', [$ctrl, 'update'])->whereNumber('id')->name('wilayah.' . $level . '.update');
            Route::delete('wilayah/' . $level . '/{id}', [$ctrl, 'destroy'])->whereNumber('id')->name('wilayah.' . $level . '.destroy');
        }
    });

    // Dropdown berjenjang dipakai form Site juga.
    Route::middleware('menu:wilayah,site')->group(function () {
        foreach (array_keys(\App\Http\Controllers\WilayahController::LEVELS) as $level) {
            Route::get('wilayah/' . $level . '/options', [\App\Http\Controllers\WilayahController::class, 'options'])->name('wilayah.' . $level . '.options');
        }
    });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - SOURCE FUND
    |--------------------------------------------------------------------------
    */
    // Lihat catatan gerbang options-json di atas.
    Route::middleware('menu:source-fund')
        ->get('source-fund/options-json', [SourceFundController::class, 'optionsJson'])
        ->name('source-fund.options-json');

    Route::middleware('role:superadmin,finance,finance_manager')->group(function () {
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
        Route::post('team',        [TeamController::class, 'store'])->name('team.store');
        // Team tidak dihapus permanen (riwayat anggota ikut hilang) — hanya
        // diaktifkan/nonaktifkan. Keputusan 2026-11-06.
        Route::patch('team/{id}/status', [TeamController::class, 'updateStatus'])->name('team.status');
        Route::post('department',        [DepartmentController::class, 'store'])->name('department.store');
        Route::put('department/{id}',    [DepartmentController::class, 'update'])->name('department.update');
        Route::delete('department/{id}', [DepartmentController::class, 'destroy'])->name('department.destroy');
    });

    // Team CREATE tetap superadmin only. EDIT boleh diakses Team Leader
    // (per-team, dicek di controller) selain superadmin — lihat
    // TeamController::authorizeEdit().
    Route::middleware('role:superadmin')->group(function () {
        Route::get('team/create', [TeamController::class, 'create'])->name('team.create');
    });
    Route::middleware('menu:team')->group(function () {
        Route::get('team/{id}/edit', [TeamController::class, 'edit'])->name('team.edit');
        Route::put('team/{id}',      [TeamController::class, 'update'])->name('team.update');
    });

    // READ — Superadmin + Guest
    // Route AJAX detail-json WAJIB sebelum {user} param
    Route::middleware('menu:user-access')->group(function () {
        Route::get('user',                  [UserController::class, 'index'])->name('user.index');
        Route::get('user/{id}/detail-json', [UserController::class, 'showJson'])->name('user.show-json');
        Route::get('user/{id}/activity-log', [UserController::class, 'activityLog'])->name('user.activity-log');
        Route::get('user/{id}',             [UserController::class, 'show'])->name('user.show');

        Route::get('accessgroup',                    [AccessGroupController::class, 'index'])->name('accessgroup.index');
        Route::get('accessgroup/{id}/detail-json',   [AccessGroupController::class, 'showJson'])->name('accessgroup.show-json');
        Route::get('accessgroup/{id}',               [AccessGroupController::class, 'show'])->name('accessgroup.show');
    });

    Route::middleware('menu:team')->group(function () {
        Route::get('team',      [TeamController::class, 'index'])->name('team.index');
        // Struktur organisasi (tampilan saja) — WAJIB sebelum team/{id}.
        Route::get('team/structure', [TeamController::class, 'structure'])->name('team.structure');
        Route::get('team/{id}', [TeamController::class, 'show'])->name('team.show');
    });

    // Daftar Department hanya untuk superadmin — sejajar dengan aksi tulisnya
    // (store/update/destroy) yang memang sudah superadmin-only. Sebelumnya READ
    // dijaga menu:team, jadi siapa pun yang bisa membuka Team tinggal mengetik
    // /department untuk melihat & masuk ke formnya.
    Route::middleware('role:superadmin')->group(function () {
        Route::get('department', [DepartmentController::class, 'index'])->name('department.index');
    });

    /*
    |--------------------------------------------------------------------------
    | FINANCE FORECAST
    |--------------------------------------------------------------------------
    */

    Route::middleware('menu:forecast')->group(function () {
        Route::get('forecast',           [ForecastController::class, 'index'])->name('forecast.index');
        Route::get('forecast/create',    [ForecastController::class, 'create'])->name('forecast.create');
        Route::post('forecast',          [ForecastController::class, 'store'])->name('forecast.store');
        // AJAX pengisi modal detail — wajib sebelum {id}.
        Route::get('forecast/{id}/detail-json', [ForecastController::class, 'detailJson'])->name('forecast.detail-json');
        Route::get('forecast/{id}',      [ForecastController::class, 'show'])->name('forecast.show');
        Route::get('forecast/{id}/edit', [ForecastController::class, 'edit'])->name('forecast.edit');
        Route::put('forecast/{id}',      [ForecastController::class, 'update'])->name('forecast.update');
        Route::delete('forecast/{id}',   [ForecastController::class, 'destroy'])->name('forecast.destroy');
        Route::post('forecast/{id}/activate',      [ForecastController::class, 'activate'])->name('forecast.activate');
        // Realisasi: entri berulang per baris rincian, masing-masing wajib
        // berketerangan (lihat ForecastController::storeActual()).
        Route::post('forecast/{id}/detail/{detail}/actual', [ForecastController::class, 'storeActual'])->name('forecast.actual.store');
        Route::delete('forecast/{id}/actual/{entry}', [ForecastController::class, 'destroyActual'])->name('forecast.actual.destroy');
        Route::post('forecast/{id}/close',         [ForecastController::class, 'close'])->name('forecast.close');
    });

    /*
    |--------------------------------------------------------------------------
    | BUDGET PLAN
    |--------------------------------------------------------------------------
    */

    // Detail JSON & export report — scoping per-record (termasuk kerahasiaan
    // PAYROLL) dijaga di BudgetPlanController::canViewBudgetPlan().
    Route::get('budget-plan/{id}/detail-json',    [BudgetPlanController::class, 'showJson'])->name('budget-plan.show-json');
    Route::post('budget-plan/{id}/log-note',      [BudgetPlanController::class, 'storeLogNote'])->middleware('throttle:30,1')->name('budget-plan.log-note');
    Route::get('budget-plan/{id}/export-report',  [BudgetPlanController::class, 'exportReport'])->name('budget-plan.export-report');
    Route::get('budget-plan/{id}/export-cost',    [BudgetPlanController::class, 'exportCost'])->name('budget-plan.export-cost');
    Route::get('budget-plan/{id}/export-budget',  [BudgetPlanController::class, 'exportBudget'])->name('budget-plan.export-budget');

    Route::middleware('menu:finance-budgeting,project-budgeting')
        ->get('budget-plan/accounts-json', [BudgetPlanController::class, 'getAccounts'])->name('budget-plan.accounts-json');

    // Workflow — 4 tahap sama seperti Cost Request: Manager submit -> Head
    // check -> Finance verify -> CEO approve (ACTIVE). Ditolak di tahap mana
    // pun -> REJECTED, Manager bisa merevisi balik ke Draft.
    Route::middleware('role:superadmin,manager')->group(function () {
        Route::post('budget-plan/{id}/submit',         [BudgetPlanController::class, 'submit'])->name('budget-plan.submit');
        Route::post('budget-plan/{id}/submit-closing', [BudgetPlanController::class, 'submitClosing'])->name('budget-plan.submit-closing');
        Route::post('budget-plan/{id}/manager-revise', [BudgetPlanController::class, 'managerRevise'])->name('budget-plan.manager-revise');
        // Tambah baris COA (nilai 0) ke Budget Plan ACTIVE — tidak menambah nilai budget.
        Route::post('budget-plan/{id}/add-coa',        [BudgetPlanController::class, 'addCoa'])->name('budget-plan.add-coa');
    });

    // Bulk approve/reject: tiap Budget Plan diproses method satuan di atas (role dicek ulang di sana).
    Route::middleware('role:superadmin,head,finance,finance_manager,approver')->group(function () {
        Route::post('budget-plan/bulk-approve', [BudgetPlanController::class, 'bulkApprove'])->name('budget-plan.bulk-approve');
        Route::post('budget-plan/bulk-reject',  [BudgetPlanController::class, 'bulkReject'])->name('budget-plan.bulk-reject');
    });

    Route::middleware('role:superadmin,head')->group(function () {
        Route::post('budget-plan/{id}/head-approve', [BudgetPlanController::class, 'headApprove'])->name('budget-plan.head-approve');
        Route::post('budget-plan/{id}/head-reject',  [BudgetPlanController::class, 'headReject'])->name('budget-plan.head-reject');
    });

    Route::middleware('role:superadmin,finance,finance_manager')->group(function () {
        Route::post('budget-plan/{id}/finance-verify', [BudgetPlanController::class, 'financeVerify'])->name('budget-plan.finance-verify');
        Route::post('budget-plan/{id}/finance-reject', [BudgetPlanController::class, 'financeReject'])->name('budget-plan.finance-reject');
        Route::post('budget-plan/{id}/verify-closing', [BudgetPlanController::class, 'verifyClosing'])->name('budget-plan.verify-closing');
        Route::post('budget-plan/{id}/reject-closing', [BudgetPlanController::class, 'rejectClosing'])->name('budget-plan.reject-closing');
        // Close langsung dari ACTIVE — jalur Finance menutup budget yang sudah
        // selesai tanpa menunggu pengajuan closing dari manager.
        Route::post('budget-plan/{id}/finance-close',  [BudgetPlanController::class, 'financeClose'])->name('budget-plan.finance-close');
    });

    Route::middleware('role:superadmin,approver')->group(function () {
        Route::post('budget-plan/{id}/ceo-approve', [BudgetPlanController::class, 'ceoApprove'])->name('budget-plan.ceo-approve');
        Route::post('budget-plan/{id}/ceo-reject',  [BudgetPlanController::class, 'ceoReject'])->name('budget-plan.ceo-reject');
    });
    
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
        Route::get('budget-plan',        [BudgetPlanController::class, 'index'])->name('budget-plan.index');
        Route::match(['get', 'post'], 'budget-plan/export', [BudgetPlanController::class, 'exportList'])->name('budget-plan.export');
    });

    /*
    |--------------------------------------------------------------------------
    | COST REQUEST (budget_request)
    |--------------------------------------------------------------------------
    */
    
    Route::get('budget-request/{id}/detail-json', [BudgetRequestController::class, 'showJson'])->name('budget-request.show-json');
    Route::post('budget-request/{id}/log-note',   [BudgetRequestController::class, 'storeLogNote'])->middleware('throttle:30,1')->name('budget-request.log-note');
    Route::post('budget-request/check-nob',       [BudgetRequestController::class, 'checkNob'])->name('budget-request.check-nob');
    // Info pagu Cash Advance per PIC (dipakai form create/edit saat beneficiary dipilih)
    Route::get('budget-request/ca-quota',        [BudgetRequestController::class, 'caQuota'])->name('budget-request.ca-quota');
    
    /*
    |--------------------------------------------------------------------------
    | WORKFLOW COST REQUEST
    |--------------------------------------------------------------------------
    | Gerbang role: di bawah adalah LAPIS KEDUA, bukan satu-satunya.
    |
    | Otorisasi sebenarnya tetap di controller: setiap method memeriksa
    | hasRole([...]) lalu abort(403), dan findVisibleCr() memastikan dokumennya
    | memang boleh diakses user itu. Itu sudah benar dan tidak diubah.
    |
    | Yang kurang sebelumnya: 23 route ini sama sekali tidak bergerbang peran di
    | level route, padahal modul sejenis (Budget Plan, di atas) memasangnya di
    | KEDUA tempat. Bedanya terasa saat ada aksi workflow BARU ditambahkan:
    | tanpa gerbang route, method yang lupa memeriksa peran tidak memunculkan
    | gejala apa pun sampai seseorang menemukannya.
    |
    | Daftar peran di setiap grup dicocokkan PERSIS dengan cek di controller
    | (BudgetRequestController + BudgetRequest::STAGE_ROLES). Kalau keduanya
    | berbeda, gejalanya membingungkan: tombol terlihat lalu menabrak 403.
    */

    // Pengaju: submit & batal (manager), revisi & input realisasi (manager +
    // supervisor, sesuai cek di controller).
    Route::middleware('role:superadmin,manager')->group(function () {
        Route::post('budget-request/{id}/submit',         [BudgetRequestController::class, 'submit'])->name('budget-request.submit');
        Route::post('budget-request/{id}/manager-cancel', [BudgetRequestController::class, 'managerCancel'])->name('budget-request.manager-cancel');
    });

    /* Submit settlement juga boleh TEKNISI (20 Okt 2026) — tapi hanya untuk CR
       yang lahir dari plan visit tiket yang ditugaskan kepadanya. Penyempitan
       itu ada di BudgetRequestController::submitRealization(); gerbang route
       ini sekadar menyaring peran yang sama sekali tidak berkepentingan.
       `staff` ikut karena PIC teknisi tidak harus ber-role teknisi.
       Form realisasi penuh (realize-form) TETAP tanpa teknisi: ia menyettle
       lewat modal di halaman tiketnya sendiri. */
    Route::middleware('role:superadmin,manager,supervisor,teknisi,staff')->group(function () {
        Route::post('budget-request/{id}/submit-realization', [BudgetRequestController::class, 'submitRealization'])->name('budget-request.submit-realization');
        // Draft settlement: jalur yang dipakai modal di halaman tiket.
        Route::post('budget-request/{id}/save-realization-draft', [BudgetRequestController::class, 'saveRealizationDraft'])->name('budget-request.save-realization-draft');
    });

    Route::middleware('role:superadmin,manager,supervisor')->group(function () {
        Route::post('budget-request/{id}/manager-revise',     [BudgetRequestController::class, 'managerRevise'])->name('budget-request.manager-revise');
        Route::get('budget-request/{id}/realize-form',        [BudgetRequestController::class, 'realizeForm'])->name('budget-request.realize-form');
        // GET ke URL POST (mis. tombol Back / refresh peramban setelah submit):
        // jangan tampilkan 405, kembalikan saja ke form realisasi.
        Route::get('budget-request/{id}/submit-realization',  [BudgetRequestController::class, 'realizeForm']);
    });

    // Tahap 1 - Head: check pengajuan & check settlement.
    Route::middleware('role:superadmin,head')->group(function () {
        Route::post('budget-request/{id}/head-approve',           [BudgetRequestController::class, 'headApprove'])->name('budget-request.head-approve');
        Route::post('budget-request/{id}/head-reject',            [BudgetRequestController::class, 'headReject'])->name('budget-request.head-reject');
        Route::post('budget-request/{id}/head-check-settlement',  [BudgetRequestController::class, 'headCheckSettlement'])->name('budget-request.head-check-settlement');
        Route::post('budget-request/{id}/head-reject-settlement', [BudgetRequestController::class, 'headRejectSettlement'])->name('budget-request.head-reject-settlement');
    });

    // Tahap 2 - Finance: verify pengajuan & verify settlement.
    // cost_control ikut karena peran itu mengambil alih langkah verifikasi
    // Finance pada sebagian alur (BudgetRequest::STAGE_ROLES['CHECKED']).
    Route::middleware('role:superadmin,finance,finance_manager,cost_control')->group(function () {
        Route::post('budget-request/{id}/finance-verify',            [BudgetRequestController::class, 'financeVerify'])->name('budget-request.finance-verify');
        Route::post('budget-request/{id}/finance-reject',            [BudgetRequestController::class, 'financeReject'])->name('budget-request.finance-reject');
        Route::post('budget-request/{id}/finance-verify-settlement', [BudgetRequestController::class, 'financeVerifySettlement'])->name('budget-request.finance-verify-settlement');
        Route::post('budget-request/{id}/finance-reject-settlement', [BudgetRequestController::class, 'financeRejectSettlement'])->name('budget-request.finance-reject-settlement');
    });

    // Tahap 3 - CEO/Approver: persetujuan akhir pengajuan & 2nd Approve.
    Route::middleware('role:superadmin,approver')->group(function () {
        Route::post('budget-request/{id}/ceo-approve',    [BudgetRequestController::class, 'ceoApprove'])->name('budget-request.ceo-approve');
        Route::post('budget-request/{id}/ceo-reject',     [BudgetRequestController::class, 'ceoReject'])->name('budget-request.ceo-reject');
        Route::post('budget-request/{id}/second-approve', [BudgetRequestController::class, 'secondApprove'])->name('budget-request.second-approve');
        Route::post('budget-request/{id}/second-reject',  [BudgetRequestController::class, 'secondReject'])->name('budget-request.second-reject');
    });

    // Penutupan settlement - authorizeSettlementClose(): Finance Manager.
    Route::middleware('role:superadmin,finance_manager')->group(function () {
        Route::post('budget-request/{id}/settle-approve',            [BudgetRequestController::class, 'settleApprove'])->name('budget-request.settle-approve');
        Route::post('budget-request/{id}/settle-reject',             [BudgetRequestController::class, 'settleReject'])->name('budget-request.settle-reject');
        Route::post('budget-request/{id}/ceo-close-settlement',      [BudgetRequestController::class, 'ceoCloseSettlement'])->name('budget-request.ceo-close-settlement');
        Route::post('budget-request/{id}/manager-reject-settlement', [BudgetRequestController::class, 'managerRejectSettlement'])->name('budget-request.manager-reject-settlement');
        // Bulk Close/Reject dari To-Do List (multi-select baris tahap Finance Manager Close)
        Route::post('budget-request/settlement-bulk-close',  [BudgetRequestController::class, 'settlementBulkClose'])->name('budget-request.settlement-bulk-close');
        Route::post('budget-request/settlement-bulk-reject', [BudgetRequestController::class, 'settlementBulkReject'])->name('budget-request.settlement-bulk-reject');
    });

    /*
    | Takeout/restore sub-item & bulk approve/reject dari To-Do List.
    |
    | Ketiganya berlaku pada BEBERAPA tahap sekaligus, dan peran yang berhak
    | ditentukan oleh status dokumen saat itu - BudgetRequest::STAGE_ROLES
    | (canModerateItems / applyReviewApprove). Gerbang di sini karena itu berupa
    | GABUNGAN seluruh peran tahap; penyaringan per-tahap yang sesungguhnya
    | tetap dilakukan controller, dan sengaja TIDAK digandakan di sini karena
    | menuliskannya ulang per-status hanya akan jadi salinan kedua yang bisa
    | menyimpang.
    */
    Route::middleware('role:superadmin,head,finance,finance_manager,cost_control,approver')->group(function () {
        Route::post('budget-request/review-bulk-approve', [BudgetRequestController::class, 'reviewBulkApprove'])->name('budget-request.review-bulk-approve');
        Route::post('budget-request/review-bulk-reject',  [BudgetRequestController::class, 'reviewBulkReject'])->name('budget-request.review-bulk-reject');
        // Bulk approve tahap settlement (Fase 4) dari daftar CR; peran per status dicek controller (SETTLE_BULK_ROLES).
        Route::post('budget-request/settlement-bulk-approve', [BudgetRequestController::class, 'settlementBulkApprove'])->name('budget-request.settlement-bulk-approve');
        Route::post('budget-request/{id}/detail/{detailId}/takeout', [BudgetRequestController::class, 'takeoutDetail'])->name('budget-request.detail.takeout');
        Route::post('budget-request/{id}/detail/{detailId}/restore', [BudgetRequestController::class, 'restoreDetail'])->name('budget-request.detail.restore');
    });
    
    // CRUD
    Route::middleware('role:superadmin,manager,supervisor')->group(function () {
        Route::get('budget-request/create',    [BudgetRequestController::class, 'create'])->name('budget-request.create');
        Route::post('budget-request',          [BudgetRequestController::class, 'store'])->name('budget-request.store');
        Route::get('budget-request/{id}/edit', [BudgetRequestController::class, 'edit'])->name('budget-request.edit');
        Route::put('budget-request/{id}',      [BudgetRequestController::class, 'update'])->name('budget-request.update');
        Route::delete('budget-request/{id}',   [BudgetRequestController::class, 'destroy'])->name('budget-request.destroy');

        // Template & import Excel — Rincian Pengajuan (create/edit)
        Route::get('budget-request/import-template',  [BudgetRequestController::class, 'downloadDetailTemplate'])->name('budget-request.import-template');
        Route::post('budget-request/import-preview',  [BudgetRequestController::class, 'importDetailPreview'])->name('budget-request.import-preview');

        // Template & import Excel — Rincian Settlement (realize.blade)
        Route::get('budget-request/{id}/settlement-template',       [BudgetRequestController::class, 'downloadSettlementTemplate'])->name('budget-request.settlement-template');
        Route::post('budget-request/{id}/settlement-import-preview', [BudgetRequestController::class, 'importSettlementPreview'])->name('budget-request.settlement-import-preview');
    });
    
    Route::middleware('menu:finance-summary')->group(function () {
        Route::get('finance-summary', [FinanceSummaryController::class, 'index'])->name('finance-summary.index');

        // Halaman gabungan (Summary Executive + Summary Budget dalam 1 halaman
        // bertab) — route lama dipertahankan supaya link/bookmark existing tetap
        // jalan, tapi keduanya mengarah ke action yang sama. Tab Executive tetap
        // digerbang role di dalam controller (executiveData() hanya dihitung &
        // dikirim ke view kalau role berhak), URL ini cuma menentukan tab awal.
        Route::middleware('role:superadmin,finance,finance_manager,approver')->group(function () {
            Route::get('finance-summary-executive', [FinanceSummaryController::class, 'index'])->name('finance-summary-executive.index');
        });
    });

    Route::middleware('menu:reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    });

    Route::middleware('menu:finance-cost-request,project-cost-request')->group(function () {
        Route::get('budget-request',          [BudgetRequestController::class, 'index'])->name('budget-request.index');
        // Isi tabel per tab (Cash Advance / Cost Payment / Database) — dimuat AJAX saat tab dibuka.
        Route::get('budget-request/tab/{tab}', [BudgetRequestController::class, 'tab'])->name('budget-request.tab');
        // POST juga didukung — export mengirim daftar ID hasil filter aktif di
        // tabel (bisa banyak baris, tidak muat di query string GET).
        Route::match(['get', 'post'], 'budget-request/export', [BudgetRequestController::class, 'export'])->name('budget-request.export');
    });

        /*
    |--------------------------------------------------------------------------
    | SERVICE MANAGEMENT - PROJECT MONITORING
    |--------------------------------------------------------------------------
    */
    /*
    | AKSES SENGAJA TERBUKA UNTUK SEMUA PERAN - dan itu bukan kelalaian.
    |
    | Modul ini tidak digerbangi 'menu:project-monitoring'. Alasannya historis
    | dan masih berlaku: beberapa peran yang memang perlu membaca progres PO
    | (mis. finance) tidak punya menu itu di master Role, dan menggerbanginya
    | membuat mereka melihat halaman kosong atau 403.
    |
    | Yang menjaga data BUKAN gerbang route, melainkan controller-nya, dan itu
    | sudah menyeluruh:
    |   - findAccessibleProject() membatasi PO ke Access Group pengguna,
    |     kecuali untuk MENTION_BYPASS_ROLES yang memang melihat semua;
    |   - canManageProject() menjaga setiap aksi tulis;
    |   - canSetMilestone() memisahkan supervisor (hanya boleh menulis log);
    |   - milestone & budget plan dicari lewat relasi PO-nya, jadi id dari PO
    |     lain tidak bisa disusupkan.
    |
    | `Route::group([], ...)` yang membungkusnya sebelumnya sudah dihapus:
    | grup tanpa atribut tidak melakukan apa pun selain menyamarkan ketiadaan
    | gerbang sebagai "ada grupnya, berarti ada aturannya". Route-nya tetap di
    | dalam grup 'auth' yang membungkus seluruh blok ini.
    |
    | CATATAN KEBIJAKAN: kalau suatu saat Project Monitoring perlu benar-benar
    | bisa dicabut lewat master Role, gerbang 'menu:project-monitoring' harus
    | dipasang BERSAMAAN dengan pemberian menu itu ke peran-peran yang sekarang
    | mengandalkan keterbukaan ini - kalau tidak, mereka langsung terkunci.
    */

    Route::get(
        'project-monitoring',
        [ProjectMonitoringController::class, 'index']
    )->name('project-monitoring.index');

    Route::get(
        'project-monitoring/{id}',
        [ProjectMonitoringController::class, 'show']
    )->name('project-monitoring.show');

    Route::post(
        'project-monitoring/{id}/message',
        [ProjectMonitoringController::class, 'storeMessage']
    )->name('project-monitoring.message.store');

    Route::get(
        'project-monitoring/{id}/messages',
        [ProjectMonitoringController::class, 'fetchMessages']
    )->name('project-monitoring.message.fetch');

    Route::post(
        'project-monitoring/detail/{detailId}/set-status',
        [ProjectMonitoringController::class, 'setMilestoneStatus']
    )->name('project-monitoring.detail.set-status');

    Route::post(
        'project-monitoring/detail/{detailId}/set-finish',
        [ProjectMonitoringController::class, 'setMilestoneFinish']
    )->name('project-monitoring.detail.set-finish');

    Route::post(
        'project-monitoring/{id}/weights',
        [ProjectMonitoringController::class, 'updateWeights']
    )->name('project-monitoring.weights.update');

        /*
    |--------------------------------------------------------------------------
    | FINANCE MANAGEMENT - INVOICE CASH RECEIPT
    |--------------------------------------------------------------------------
    */

    // Collection ikut bisa membuat & mengelola Invoice (tombol "Buat Invoice")
    // — role ini ditambahkan langsung ke grup yang sudah ada supaya alur create
    // -> edit -> submit tetap utuh (bukan cuma tombolnya nongol tapi macet di
    // DRAFT karena tidak bisa submit).
    Route::middleware('role:superadmin,admin,finance,finance_manager,collection')->group(function () {
        Route::get('invoice-cash-receipt/create',    [InvoiceCashReceiptController::class, 'create'])->name('invoice-cash-receipt.create');
        Route::post('invoice-cash-receipt',           [InvoiceCashReceiptController::class, 'store'])->name('invoice-cash-receipt.store');
        Route::get('invoice-cash-receipt/{id}/edit',  [InvoiceCashReceiptController::class, 'edit'])->name('invoice-cash-receipt.edit');
        Route::put('invoice-cash-receipt/{id}',       [InvoiceCashReceiptController::class, 'update'])->name('invoice-cash-receipt.update');
        Route::delete('invoice-cash-receipt/{id}',    [InvoiceCashReceiptController::class, 'destroy'])->name('invoice-cash-receipt.destroy');

        // Import Excel — Rincian Invoice
        Route::get('invoice-cash-receipt/import-template', [InvoiceCashReceiptController::class, 'downloadTemplate'])->name('invoice-cash-receipt.import-template');
        Route::post('invoice-cash-receipt/import-preview', [InvoiceCashReceiptController::class, 'importPreview'])->name('invoice-cash-receipt.import-preview');

        Route::post('invoice-cash-receipt/{id}/submit', [InvoiceCashReceiptController::class, 'submit'])->name('invoice-cash-receipt.submit');
        Route::delete('invoice-cash-receipt/{id}/cash-receipt/{crId}', [InvoiceCashReceiptController::class, 'destroyCashReceipt'])->name('invoice-cash-receipt.cash-receipt.destroy');
        Route::post('invoice-cash-receipt/{id}/cash-receipt/{crId}/settle', [InvoiceCashReceiptController::class, 'settleCashReceipt'])->name('invoice-cash-receipt.cash-receipt.settle');
    });

    // Collection ikut bisa menambahkan Cash Receipt (input pembayaran yang
    // diterima) — sengaja dipisah dari grup role di atas supaya role ini
    // tidak ikut kebagian akses edit/delete/import invoice yang lain.
    Route::middleware('role:superadmin,admin,finance,finance_manager,collection')->group(function () {
        Route::post('invoice-cash-receipt/{id}/cash-receipt', [InvoiceCashReceiptController::class, 'storeCashReceipt'])->name('invoice-cash-receipt.cash-receipt.store');
    });

    // Alur: collection submit -> accounting verifikasi -> finance_manager approve.
    Route::middleware('role:superadmin,finance_manager,accounting')->group(function () {
        Route::post('invoice-cash-receipt/{id}/verify', [InvoiceCashReceiptController::class, 'verify'])->name('invoice-cash-receipt.verify');
    });

    // Approval invoice milik Finance Manager, sesuai alur di komentar atas.
    // Approver sengaja TIDAK ikut: tahap ini bukan bagian dari tugasnya.
    Route::middleware('role:superadmin,finance_manager')->group(function () {
        Route::post('invoice-cash-receipt/{id}/approve', [InvoiceCashReceiptController::class, 'approve'])->name('invoice-cash-receipt.approve');
    });

    Route::middleware('role:superadmin,finance_manager,approver,accounting')->group(function () {
        Route::post('invoice-cash-receipt/{id}/revise', [InvoiceCashReceiptController::class, 'revise'])->name('invoice-cash-receipt.revise');
    });

    Route::middleware('role:superadmin,finance_manager,approver')->group(function () {
        Route::post('invoice-cash-receipt/{id}/cancel', [InvoiceCashReceiptController::class, 'cancel'])->name('invoice-cash-receipt.cancel');
    });

    Route::middleware('role:superadmin,admin,finance,finance_manager,collection')->group(function () {
        Route::post('invoice-cash-receipt/{id}/mark-received', [InvoiceCashReceiptController::class, 'markReceived'])->name('invoice-cash-receipt.mark-received');
    });

    Route::middleware('menu:invoice-cash-receipt')->group(function () {

        Route::post(
            'invoice-cash-receipt/{id}/comment',
            [InvoiceCashReceiptController::class, 'storeComment']
        )->name('invoice-cash-receipt.comment');

        // Panel komentar di daftar: info singkat (tanpa nominal) + log chat.
        Route::get(
            'invoice-cash-receipt/{id}/drawer-json',
            [InvoiceCashReceiptController::class, 'drawerJson']
        )->name('invoice-cash-receipt.drawer-json');

        Route::get(
            'invoice-cash-receipt',
            [InvoiceCashReceiptController::class, 'index']
        )->name('invoice-cash-receipt.index');

        Route::match(['get', 'post'],
            'invoice-cash-receipt/export',
            [InvoiceCashReceiptController::class, 'exportList']
        )->name('invoice-cash-receipt.export');

        /*
        | Isi modal Surat Jalan di tab Log detail invoice. Sengaja berada di
        | gerbang menu invoice (bukan 'menu:delivery-note'): role Collection
        | harus bisa melihat Surat Jalan yang menyangkut invoice-nya tanpa
        | diberi akses modul Surat Jalan. Hanya baca, dan Surat Jalan yang
        | dibuka wajib yang terpaut pada invoice itu (dicek di controller).
        */
        Route::get(
            'invoice-cash-receipt/{id}/delivery-note/{dnId}',
            [InvoiceCashReceiptController::class, 'deliveryNoteModal']
        )->name('invoice-cash-receipt.delivery-note');

        Route::get(
            'invoice-cash-receipt/{id}',
            [InvoiceCashReceiptController::class, 'show']
        )->name('invoice-cash-receipt.show');

        Route::get(
            'invoice-cash-receipt/{id}/print',
            [InvoiceCashReceiptController::class, 'printView']
        )->name('invoice-cash-receipt.print');

    });

    /*
    |--------------------------------------------------------------------------
    | FINANCE MANAGEMENT - SURAT JALAN INVOICE (DELIVERY NOTE)
    |--------------------------------------------------------------------------
    */

    // Gerbangnya kunci menu 'delivery-note' (bukan daftar role), supaya
    // pencabutan lewat Role master benar-benar menutup halamannya — bukan cuma
    // menyembunyikan menunya sementara URL-nya tetap bisa dibuka.
    Route::middleware('menu:delivery-note')->group(function () {
        Route::get('delivery-note', [DeliveryNoteController::class, 'index'])->name('delivery-note.index');
        // POST juga didukung — export mengirim id baris hasil filter aktif (tesys-export-filtered.js).
        Route::match(['get', 'post'], 'delivery-note/export', [DeliveryNoteController::class, 'exportList'])->name('delivery-note.export');
        Route::get('delivery-note/create', [DeliveryNoteController::class, 'create'])->name('delivery-note.create');
        Route::post('delivery-note', [DeliveryNoteController::class, 'store'])->name('delivery-note.store');
        Route::get('delivery-note/{id}', [DeliveryNoteController::class, 'show'])->name('delivery-note.show');
        Route::get('delivery-note/{id}/print', [DeliveryNoteController::class, 'printView'])->name('delivery-note.print');
        Route::post('delivery-note/{id}/receive', [DeliveryNoteController::class, 'receive'])->name('delivery-note.receive');
    });

    /*
    |--------------------------------------------------------------------------
    | CASH RECEIPT — HEADER + DETAIL (M-03)
    |--------------------------------------------------------------------------
    | Untuk satu penerimaan yang menutup beberapa invoice sekaligus. Pembayaran
    | satu invoice tunggal tetap lewat halaman invoice; keduanya menulis ke
    | tabel cash_receipts yang sama.
    */

    Route::middleware('role:superadmin,finance,finance_manager,treasury,approver,collection')->group(function () {
        Route::get('cash-receipt', [\App\Http\Controllers\CashReceiptController::class, 'index'])->name('cash-receipt.index');
        Route::get('cash-receipt/create', [\App\Http\Controllers\CashReceiptController::class, 'create'])->name('cash-receipt.create');
        // Harus di ATAS 'cash-receipt/{id}', kalau tidak 'open-invoices' ditangkap
        // sebagai id. Dipakai halaman Catat Penerimaan untuk mengambil daftar
        // invoice saat customer dipilih — tanpa memuat ulang halaman.
        Route::get('cash-receipt/open-invoices', [\App\Http\Controllers\CashReceiptController::class, 'openInvoices'])->name('cash-receipt.open-invoices');
        Route::post('cash-receipt', [\App\Http\Controllers\CashReceiptController::class, 'store'])->name('cash-receipt.store');
        Route::get('cash-receipt/{id}', [\App\Http\Controllers\CashReceiptController::class, 'show'])->name('cash-receipt.show');
        Route::post('cash-receipt/{id}/allocate', [\App\Http\Controllers\CashReceiptController::class, 'allocate'])->name('cash-receipt.allocate');
        Route::delete('cash-receipt/{id}/allocation/{detailId}', [\App\Http\Controllers\CashReceiptController::class, 'removeAllocation'])->name('cash-receipt.remove-allocation');
        Route::post('cash-receipt/{id}/cancel', [\App\Http\Controllers\CashReceiptController::class, 'cancel'])->name('cash-receipt.cancel');
        Route::delete('cash-receipt/{id}', [\App\Http\Controllers\CashReceiptController::class, 'destroy'])->name('cash-receipt.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | CASH MANAGEMENT / CASH FLOW (M-01)
    |--------------------------------------------------------------------------
    | Pemantauan pergerakan kas perusahaan. Isinya disusun dari modul yang
    | sudah ada (Cash Receipt, Treasury) plus entri manual untuk pergerakan
    | kas yang tidak punya dokumen di modul lain — lihat CashFlowService.
    |
    | Akses disamakan dengan Treasury: finance & treasury yang memegang kas.
    */

    Route::middleware('role:superadmin,finance,finance_manager,treasury,approver')->group(function () {
        Route::get('cash-management', [\App\Http\Controllers\CashManagementController::class, 'index'])->name('cash-management.index');
        Route::get('cash-management/create', [\App\Http\Controllers\CashManagementController::class, 'create'])->name('cash-management.create');
        Route::post('cash-management', [\App\Http\Controllers\CashManagementController::class, 'store'])->name('cash-management.store');
        Route::get('cash-management/{id}', [\App\Http\Controllers\CashManagementController::class, 'show'])->name('cash-management.show');
        Route::get('cash-management/{id}/edit', [\App\Http\Controllers\CashManagementController::class, 'edit'])->name('cash-management.edit');
        Route::put('cash-management/{id}', [\App\Http\Controllers\CashManagementController::class, 'update'])->name('cash-management.update');
        Route::post('cash-management/{id}/post', [\App\Http\Controllers\CashManagementController::class, 'post'])->name('cash-management.post');
        Route::post('cash-management/{id}/cancel', [\App\Http\Controllers\CashManagementController::class, 'cancel'])->name('cash-management.cancel');
        Route::delete('cash-management/{id}', [\App\Http\Controllers\CashManagementController::class, 'destroy'])->name('cash-management.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | FINANCE MANAGEMENT - COST REGISTER (Fixed Cost & Overhead manual)
    |--------------------------------------------------------------------------
    | Khusus Finance & Superadmin — nominal divalidasi lewat meeting +
    | approval CEO di luar sistem, sistem ini cuma tempat mencatatnya.
    */

    // Alur = Cost Payment (Non-CA): DRAFT -> VERIFIED ("Submitted") -> APPROVED -> QUEUED -> CLOSED.
    // Melihat daftar/detail/export + menulis catatan log: finance group + approver.
    // Role per aksi (submit/cancel: finance group; approve/revise: approver) dicek lagi di controller.
    Route::middleware('role:superadmin,finance,finance_manager,approver')->group(function () {
        Route::get('cost-register', [CostRegisterController::class, 'index'])->name('cost-register.index');
        Route::match(['get', 'post'], 'cost-register/export', [CostRegisterController::class, 'export'])->name('cost-register.export');
        Route::get('cost-register/{id}/detail-json', [CostRegisterController::class, 'showJson'])->name('cost-register.show-json');
        Route::post('cost-register/{id}/log-note', [CostRegisterController::class, 'storeLogNote'])->middleware('throttle:30,1')->name('cost-register.log-note');
        Route::post('cost-register/{id}/approve', [CostRegisterController::class, 'approve'])->name('cost-register.approve');
        Route::post('cost-register/{id}/revise', [CostRegisterController::class, 'revise'])->name('cost-register.revise');
    });

    Route::middleware('role:superadmin,finance,finance_manager')->group(function () {
        Route::get('cost-register/create', [CostRegisterController::class, 'create'])->name('cost-register.create');
        Route::post('cost-register', [CostRegisterController::class, 'store'])->name('cost-register.store');
        Route::delete('cost-register/{id}', [CostRegisterController::class, 'destroy'])->name('cost-register.destroy');
        Route::post('cost-register/{id}/submit', [CostRegisterController::class, 'submit'])->name('cost-register.submit');
        Route::post('cost-register/{id}/cancel', [CostRegisterController::class, 'cancel'])->name('cost-register.cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | GENERAL FEEDBACK — kanal kritik/saran/concern/ide karyawan ke CEO
    |--------------------------------------------------------------------------
    | Terbuka untuk SELURUH karyawan yang login (submit + lihat riwayat
    | milik sendiri). Inbox (lihat SEMUA submission) & aksi respond/close
    | dibatasi lewat menu access 'feedback-inbox' (superadmin selalu lolos),
    | diberikan lewat Role > Menu Access ke role CEO.
    */

    Route::get('feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::get('feedback/create', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    Route::get('feedback/{id}', [FeedbackController::class, 'show'])->name('feedback.show');

    Route::middleware('menu:feedback-inbox')->group(function () {
        Route::get('feedback-inbox', [FeedbackController::class, 'inbox'])->name('feedback.inbox');
        Route::post('feedback/{id}/respond', [FeedbackController::class, 'respond'])->name('feedback.respond');
        Route::post('feedback/{id}/close', [FeedbackController::class, 'close'])->name('feedback.close');
    });

    /*
    |--------------------------------------------------------------------------
    | TODO LIST REQUEST — worklist Cost Request nyata per role (menggantikan
    | section "Review Cost Request" di budget-request/index).
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu:todo-list')->get('todo-list', [TodoListController::class, 'index'])->name('todo-list.index');
    Route::middleware('menu:todo-list')->match(['get', 'post'], 'todo-list/export', [TodoListController::class, 'exportList'])->name('todo-list.export');
    Route::middleware('menu:todo-list')->post('todo-list/bulk/{section}/{mode}', [TodoListController::class, 'bulk'])
        ->whereIn('mode', ['approve', 'reject'])->name('todo-list.bulk');
    // Dipakai badge titik kuning di sidebar (polling ringan, lihat tesys-shell.js).
    Route::middleware('menu:todo-list')->get('todo-list/has-pending', [TodoListController::class, 'hasPending'])->name('todo-list.has-pending');

    /*
    |--------------------------------------------------------------------------
    | ASSIGNMENT — penugasan bebas antar-user
    |
    | Tanpa gerbang menu/role: keputusan bisnis "semua user -> siapa saja".
    | Otorisasi per-aksi (pemberi vs penerima) dicek di dalam controller.
    |--------------------------------------------------------------------------
    */
    Route::get('assignment',                     [AssignmentController::class, 'index'])->name('assignment.index');
    Route::post('assignment',                    [AssignmentController::class, 'store'])->name('assignment.store');
    Route::get('assignment/{assignment}/logs',   [AssignmentController::class, 'logs'])->name('assignment.logs');
    Route::put('assignment/{assignment}',        [AssignmentController::class, 'update'])->name('assignment.update');
    Route::post('assignment/{assignment}/start', [AssignmentController::class, 'startProgress'])->name('assignment.start');
    Route::post('assignment/{assignment}/comment',[AssignmentController::class, 'comment'])->name('assignment.comment');
    Route::post('assignment/{assignment}/done',  [AssignmentController::class, 'markDone'])->name('assignment.done');
    Route::post('assignment/{assignment}/cancel',[AssignmentController::class, 'cancel'])->name('assignment.cancel');
    Route::post('assignment/{assignment}/reopen',[AssignmentController::class, 'reopen'])->name('assignment.reopen');

    /*
    |--------------------------------------------------------------------------
    | TROUBLE TICKET (TESYS Operation)
    |
    | Gerbang menu:trouble-ticket. Keterlihatan per tiket (rantai site → PIC →
    | team) dan hak per aksi (teknisi / leader team) diperiksa
    | App\Services\TroubleTicketWorkflow — bukan oleh route. Pengaturan
    | kategori & SLA: role sama persis dengan AccessRoles::TROUBLE_TICKET_SETTINGS.
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu:trouble-ticket')->prefix('trouble-ticket')->name('trouble-ticket.')
        ->controller(\App\Http\Controllers\TroubleTicketController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            // Buat tiket massal dari Excel; hak buat diperiksa controller.
            Route::get('import/template', 'importTemplate')->name('import.template');
            Route::post('import/preview', 'importPreview')->middleware('throttle:30,1')->name('import.preview');
            Route::post('import/commit', 'importCommit')->middleware('throttle:10,1')->name('import.commit');
            Route::get('site/{site}/technicians', 'technicians')->whereNumber('site')->name('technicians');
            Route::get('attachment/{attachment}', 'download')->whereNumber('attachment')->name('attachment');
            // Modal info dari daftar — tidak mencatat respons teknisi.
            Route::get('{ticket}/info', 'info')->whereNumber('ticket')->name('info');
            // GET = semua tiket terlihat; POST ids[] = baris yang lolos filter tabel.
            Route::match(['get', 'post'], 'export', 'exportList')->name('export');

            Route::middleware('role:superadmin')->group(function () {
                Route::get('settings', 'settings')->name('settings');
                // Hanya tiga limit (response/travel/resolution) yang diatur di layar.
                Route::post('settings/sla', 'updateSlaLimit')->name('settings.sla.limit');
                // Master RCA / Sub-RCA / Severity — tambah & aktif-nonaktif saja.
                Route::post('settings/rca', 'storeRca')->name('settings.rca.store');
                Route::post('settings/rca/{rca}', 'updateRca')->whereNumber('rca')->name('settings.rca.update');
                Route::post('settings/rca/{rca}/toggle', 'toggleRca')->whereNumber('rca')->name('settings.rca.toggle');
                Route::post('settings/sub-rca', 'storeSubRca')->name('settings.sub-rca.store');
                Route::post('settings/sub-rca/{subRca}', 'updateSubRca')->whereNumber('subRca')->name('settings.sub-rca.update');
                Route::post('settings/sub-rca/{subRca}/toggle', 'toggleSubRca')->whereNumber('subRca')->name('settings.sub-rca.toggle');
                Route::post('settings/severity', 'storeSeverity')->name('settings.severity.store');
                Route::post('settings/severity/{severity}', 'updateSeverity')->whereNumber('severity')->name('settings.severity.update');
                Route::post('settings/severity/{severity}/toggle', 'toggleSeverity')->whereNumber('severity')->name('settings.severity.toggle');
            });

            Route::prefix('{ticket}')->whereNumber('ticket')->group(function () {
                Route::get('/', 'show')->name('show');
                Route::get('messages', 'messages')->name('messages');
                Route::post('messages', 'postMessage')->name('messages.store');
                Route::post('plan', 'createPlan')->name('plan.create');
                // Approval plan & biaya oleh supervisor -> Cost Request draft
                // (20 Okt 2026). Tiket tetap di tahap Plan.
                Route::post('plan/approve', 'approvePlanBudget')->name('plan.approve');
                Route::post('plan/reject', 'rejectPlanBudget')->name('plan.reject');
                // Pengajuan biaya (pra-Cost Request), terlepas dari Plan Visit. Hak tiap aksi
                // diperiksa TroubleTicketWorkflow (teknisi / supervisor team yang sama).
                Route::post('requests', 'saveRequest')->name('requests.save');
                Route::get('requests/attachment/{attachment}', 'downloadRequestAttachment')->whereNumber('attachment')->name('requests.attachment');
                Route::post('requests/{requestId}/submit', 'submitRequest')->whereNumber('requestId')->name('requests.submit');
                Route::post('requests/{requestId}/edit', 'editRequest')->whereNumber('requestId')->name('requests.edit');
                Route::post('requests/{requestId}/approve', 'approveRequest')->whereNumber('requestId')->name('requests.approve');
                Route::post('requests/{requestId}/reject', 'rejectRequest')->whereNumber('requestId')->name('requests.reject');
                Route::post('requests/{requestId}/cancel', 'cancelRequest')->whereNumber('requestId')->name('requests.cancel');
                Route::post('requests/{requestId}/resubmit', 'resubmitRequest')->whereNumber('requestId')->name('requests.resubmit');
                // Settlement dari tiket: teknisi kirim, atasan teruskan ke Finance atau kembalikan.
                Route::post('settlement/{crId}/submit', 'settlementSubmit')->whereNumber('crId')->name('settlement.submit');
                Route::post('settlement/{crId}/review', 'settlementReview')->whereNumber('crId')->name('settlement.review');
                Route::get('receipt/{receipt}', 'receipt')->whereNumber('receipt')->name('receipt');
                // Plan → Trip: digeser teknisi lewat "Swipe for travel", satu
                // aksi dengan mulai perjalanan (20 Okt 2026).
                Route::post('travel', 'startTravel')->name('travel.start');
                Route::post('travel/cancel', 'cancelTravel')->name('travel.cancel');
                Route::post('arrive', 'arrive')->name('arrive');
                Route::post('start', 'startProgress')->name('start');
                Route::post('close', 'close')->name('close');
                Route::post('close/approve', 'approveClose')->name('close.approve');
                Route::post('close/reject', 'rejectClose')->name('close.reject');
                Route::post('reassign', 'reassign')->name('reassign');
            });
        });

    /*
    |--------------------------------------------------------------------------
    | MASTER DATA - Stage
    |--------------------------------------------------------------------------
    */


    Route::middleware('menu:milestone')->group(function () {
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
    Route::middleware('menu:account')->group(function () {
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
    Route::middleware('role:superadmin,admin,finance,finance_manager')->group(function () {
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
    
    // Approve / reject batch — tahap CEO/Approver. Cek status batch tetap di controller.
    Route::middleware('role:superadmin,approver')->group(function () {
        Route::post('daily-transaction/bulk-approve', [DailyTransactionController::class, 'bulkApprove'])->name('daily-transaction.bulk-approve');
        Route::post('daily-transaction/bulk-reject',  [DailyTransactionController::class, 'bulkReject'])->name('daily-transaction.bulk-reject');
        Route::post('daily-transaction/{id}/approve',                   [DailyTransactionController::class, 'approve'])->name('daily-transaction.approve');
        Route::post('daily-transaction/{id}/reject-batch',              [DailyTransactionController::class, 'rejectBatch'])->name('daily-transaction.reject-batch');
        Route::post('daily-transaction/{batchId}/reject-item/{itemId}', [DailyTransactionController::class, 'rejectItem'])->name('daily-transaction.reject-item');
        Route::post('daily-transaction/{batchId}/reject-cr-item/{itemId}', [DailyTransactionController::class, 'rejectCrItem'])->name('daily-transaction.reject-cr-item');
    });

    // Kelola isi batch & tandai paid — Treasury/Finance.
    Route::middleware('role:superadmin,finance,finance_manager,treasury')->group(function () {
        Route::get('daily-transaction/create', [DailyTransactionController::class, 'create'])->name('daily-transaction.create');
        Route::post('daily-transaction', [DailyTransactionController::class, 'store'])->name('daily-transaction.store');
        Route::post('daily-transaction/{id}/add-item',  [DailyTransactionController::class, 'addItem'])->name('daily-transaction.add-item');
        Route::post('daily-transaction/{id}/mark-paid', [DailyTransactionController::class, 'markPaid'])->name('daily-transaction.mark-paid');
        // Draft batch (29 Okt 2026): ajukan ke CEO, hapus draft, keluarkan item dari draft.
        Route::post('daily-transaction/{id}/submit',   [DailyTransactionController::class, 'submit'])->name('daily-transaction.submit');
        Route::delete('daily-transaction/{id}',        [DailyTransactionController::class, 'destroy'])->name('daily-transaction.destroy');
        Route::post('daily-transaction/{batchId}/draft-remove-item/{itemId}', [DailyTransactionController::class, 'removeDraftItem'])->name('daily-transaction.draft-remove-item');
    });
    
    // Tutup Periode (month-end close) — M-04, sudah tersambung ke data nyata.
    Route::middleware('menu:closing')->group(function () {
        Route::get('closing', [\App\Http\Controllers\ClosingController::class, 'index'])->name('closing.index');
        Route::post('closing/build', [\App\Http\Controllers\ClosingController::class, 'build'])->name('closing.build');
        Route::post('closing/{id}/close', [\App\Http\Controllers\ClosingController::class, 'close'])->name('closing.close');
        Route::post('closing/{id}/reopen', [\App\Http\Controllers\ClosingController::class, 'reopen'])->name('closing.reopen');
    });

    Route::middleware('menu:finance-daily-transaction')->group(function () {
        Route::get('daily-transaction',                    [DailyTransactionController::class, 'index'])->name('daily-transaction.index');
        Route::match(['get', 'post'], 'daily-transaction/export', [DailyTransactionController::class, 'exportList'])->name('daily-transaction.export-list');
        Route::get('daily-transaction/{id}/detail-json',    [DailyTransactionController::class, 'showJson'])->name('daily-transaction.show-json');
        Route::get('daily-transaction/{id}/items-html',     [DailyTransactionController::class, 'itemsHtml'])->name('daily-transaction.items-html');
        Route::post('daily-transaction/{id}/log-note',      [DailyTransactionController::class, 'storeLogNote'])->middleware('throttle:30,1')->name('daily-transaction.log-note');
        Route::get('daily-transaction/{id}/export',         [DailyTransactionController::class, 'export'])->name('daily-transaction.export');
        Route::get('daily-transaction/{id}/export-data',    [DailyTransactionController::class, 'exportData'])->name('daily-transaction.export-data');
        Route::get('daily-transaction/{id}',                [DailyTransactionController::class, 'show'])->name('daily-transaction.show');
    });

        /*
    |--------------------------------------------------------------------------
    | API ROUTES
    |--------------------------------------------------------------------------
    */
    Route::get('/api/clusters/{budgetPlanId}', [BudgetRequestController::class, 'getClusters']);
    Route::get('/api/budget-plan/{budgetPlanId}/details', [BudgetPlanController::class, 'getDetails']);

});

/*
|--------------------------------------------------------------------------
| PUBLIC API — dilindungi token statis (bukan session login), untuk
| konsumsi eksternal seperti Excel Power Query. Lihat EXPORT_API_TOKEN di .env.
|
| Token dikirim lewat header: "Authorization: Bearer <token>" atau
| "X-Export-Token: <token>". Query string ?token= masih diterima untuk konsumen
| lama, tapi dicatat sebagai usang - token di URL ikut masuk access log server
| dan riwayat peramban.
|
| throttle:30,1 membatasi 30 permintaan per menit per IP: cukup untuk refresh
| Power Query, terlalu lambat untuk menyedot seluruh tabel lewat tebakan token.
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/api/public/budget-request', [BudgetRequestController::class, 'apiExportData']);
    Route::get('/api/public/budget-request/detail', [BudgetRequestController::class, 'apiExportDetailData']);

    /*
    | KETIGA endpoint publik ada di dalam grup ini.
    |
    | Sebelumnya '/realisasi' berada di luar grup dan karenanya tidak punya
    | batas laju sama sekali - satu-satunya endpoint bertoken di aplikasi yang
    | bisa dipanggil tanpa henti. Itu membatalkan seluruh alasan throttle di
    | komentar atas: token statis yang dibandingkan dengan hash_equals tetap
    | bisa ditebak beruntun kalau tidak ada yang memperlambat percobaannya,
    | dan token yang tertebak membuka SELURUH Cost Request tanpa batasan
    | access group.
    */
    Route::get('/api/public/budget-request/realisasi', [BudgetRequestController::class, 'apiExportRealizationData']);
});
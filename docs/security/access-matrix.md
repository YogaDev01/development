# Matriks Akses TESYS — Baseline Fase 0

- Diambil: 2026-11-07, branch `update/v3.3.2` (commit `7708553` + perubahan Fase 0).
- Bagian dari: `docs/plans/2026-11-06-security-access-control.md` (Fase 0, D1 disetujui).
- Sifat: **inventaris read-only**. Dokumen ini tidak mengubah perilaku apa pun.
- Sumber: `artisan route:list --json` dibaca dari **source** `routes/web.php` (541 route, termasuk
  3 route konsol Keamanan Fase 1) + pembacaan controller. Klaim perilaku
  disertai `file:line`; yang belum dibaca ditandai **Needs confirmation**.

## 1. Alat baseline

| Berkas | Fungsi |
|---|---|
| `tests/Fixtures/security/route-gates.json` | Snapshot gerbang middleware (auth/guest/role:/menu:/throttle:) untuk **setiap** route, kunci `"METHOD /uri"` |
| `scripts/security/route-gates-snapshot.php` | Membangun ulang fixture dari `route:list --json` (tanpa DB) |
| `tests/Feature/Security/RouteGateSnapshotTest.php` | Gagal bila gerbang route berubah dari snapshot, atau ada route non-publik tanpa `auth`. Tanpa DB |
| `tests/Feature/Security/AccessBaselineTest.php` | Karakterisasi perilaku akses saat ini (sesi nonaktif, role/menu, IDOR, temuan). Pakai `RefreshDatabase` → hanya dijalankan lewat CLAUDE.md §4.3 |

Memperbarui snapshot (hanya untuk perubahan gerbang yang disengaja):

```bash
php artisan route:list --json > route-list.json
php scripts/security/route-gates-snapshot.php route-list.json
```

Ganti `php` dengan path PHP terverifikasi (CLAUDE.md §4.3 langkah 2). Hapus `route-list.json` setelahnya.

**Route cache harus tidak aktif.** Bila `bootstrap/cache/routes-v7.php` ada, `route:list` dan uji
membaca route dari cache, bukan dari `routes/web.php`. Pada 2026-11-07 working copy lokal memiliki
route cache basi (6 Nov 04:28: 531 route, tanpa 7 route v3.3.1 dan role `staff` di dua route settlement),
sehingga snapshot pertama Fase 0 sempat dibangun dari cache dan sudah dibangun ulang dari source.
Tanpa menghapus cache, `route:list` bisa diarahkan ke source dengan
`APP_ROUTES_CACHE=<path-file-yang-tidak-ada> php artisan route:list --json`.
`RouteGateSnapshotTest` gagal dengan pesan jelas bila route cache aktif.

## 2. Statistik gerbang route

| Kategori | Jumlah |
|---|---|
| Total route | 541 (538 sebelum Fase 1 + 3 route `security.*`) |
| Memakai `role:` | 222 |
| Memakai `menu:` | 272 (sebagian juga `role:`) |
| Hanya `auth` (tanpa role/menu) | 57 |
| `guest` (login, lupa sandi) | 5 |
| Publik tanpa login | 4 — `/up`, `/api/public/budget-request{,/detail,/realisasi}` (token + `throttle:30,1`) |

Middleware global grup web: `SecurityHeaders`, `EnsureUserActive` (`bootstrap/app.php`).

## 3. Gerbang per kelompok route

Kolom "Gerbang" = gabungan semua `role:`/`menu:` yang dipakai route di prefix itu; detail per
route ada di fixture.

| Prefix | Route | Gerbang |
|---|---|---|
| `/` | 1 | `(tanpa role/menu)` |
| `/accessgroup` | 8 | `role:superadmin`<br>`menu:user-access` |
| `/accessories` | 5 | `menu:accessories` |
| `/account` | 5 | `menu:account` |
| `/api/budget-plan` | 1 | `(tanpa role/menu)` |
| `/api/clusters` | 1 | `(tanpa role/menu)` |
| `/api/public` | 3 | `(tanpa role/menu)` |
| `/app` | 1 | `(tanpa role/menu)` |
| `/assignment` | 9 | `(tanpa role/menu)` |
| `/bank` | 7 | `role:superadmin,admin`<br>`role:superadmin,admin,guest` |
| `/beneficiaries` | 11 | `menu:beneficiaries`<br>`role:superadmin` |
| `/budget-plan` | 30 | `role:superadmin,manager`<br>`menu:finance-budgeting,project-budgeting`<br>`(tanpa role/menu)`<br>`role:superadmin,head,finance,finance_manager,approver`<br>`role:superadmin,approver`<br>`role:superadmin,finance,finance_manager`<br>`role:superadmin,head` |
| `/budget-request` | 46 | `role:superadmin,manager,supervisor`<br>`menu:finance-cost-request,project-cost-request`<br>`(tanpa role/menu)`<br>`role:superadmin,head,finance,finance_manager,cost_control,approver`<br>`role:superadmin,finance_manager`<br>`role:superadmin,approver`<br>`role:superadmin,finance,finance_manager,cost_control`<br>`role:superadmin,head`<br>`role:superadmin,manager`<br>`role:superadmin,manager,supervisor,teknisi,staff` |
| `/cash-management` | 9 | `role:superadmin,finance,finance_manager,treasury,approver` |
| `/cash-receipt` | 9 | `role:superadmin,finance,finance_manager,treasury,approver,collection` |
| `/category-item` | 5 | `menu:category-item` |
| `/changelog` | 1 | `(tanpa role/menu)` |
| `/closing` | 4 | `menu:closing` |
| `/cluster` | 10 | `menu:project`<br>`menu:project,list-po` |
| `/cost-center` | 5 | `role:superadmin,admin,finance,finance_manager`<br>`menu:cost-center` |
| `/cost-register` | 11 | `role:superadmin,finance,finance_manager`<br>`role:superadmin,finance,finance_manager,approver` |
| `/customer` | 9 | `menu:customer` |
| `/daily-transaction` | 21 | `role:superadmin,finance,finance_manager,treasury`<br>`menu:finance-daily-transaction`<br>`role:superadmin,approver` |
| `/delivery-note` | 7 | `menu:delivery-note` |
| `/department` | 4 | `role:superadmin` |
| `/employee` | 7 | `role:superadmin,admin,hr`<br>`menu:employee` |
| `/feedback` | 6 | `(tanpa role/menu)`<br>`menu:feedback-inbox` |
| `/feedback-inbox` | 1 | `menu:feedback-inbox` |
| `/finance-summary` | 1 | `menu:finance-summary` |
| `/finance-summary-executive` | 1 | `menu:finance-summary`<br>`role:superadmin,finance,finance_manager,approver` |
| `/forecast` | 12 | `menu:forecast` |
| `/forgot` | 3 | `(tanpa role/menu)` |
| `/global-search` | 1 | `(tanpa role/menu)` |
| `/groups` | 8 | `role:superadmin,admin`<br>`role:superadmin,admin,guest` |
| `/homebase` | 8 | `menu:homebase` |
| `/invoice-cash-receipt` | 23 | `role:superadmin,admin,finance,finance_manager,collection`<br>`menu:invoice-cash-receipt`<br>`role:superadmin,finance_manager`<br>`role:superadmin,finance_manager,approver`<br>`role:superadmin,finance_manager,approver,accounting`<br>`role:superadmin,finance_manager,accounting` |
| `/login` | 2 | `(tanpa role/menu)` |
| `/logout` | 1 | `(tanpa role/menu)` |
| `/master-material` | 7 | `menu:master-material` |
| `/master-pic` | 6 | `menu:master-pic` |
| `/network` | 5 | `menu:network` |
| `/notifications` | 3 | `(tanpa role/menu)` |
| `/panduan` | 2 | `(tanpa role/menu)` |
| `/payroll-run` | 15 | `menu:payroll-run` |
| `/profile` | 11 | `(tanpa role/menu)` |
| `/project` | 36 | `role:superadmin`<br>`role:superadmin,finance,finance_manager,head`<br>`role:superadmin,head`<br>`menu:list-po`<br>`role:superadmin,approver,finance,finance_manager,head`<br>`(tanpa role/menu)`<br>`role:superadmin,approver`<br>`role:superadmin,finance,finance_manager,approver`<br>`role:superadmin,finance,finance_manager` |
| `/project-monitoring` | 7 | `(tanpa role/menu)` |
| `/region` | 5 | `menu:region` |
| `/reports` | 1 | `menu:reports` |
| `/role` | 4 | `role:superadmin` |
| `/security` | 3 | `role:superadmin` |
| `/site` | 21 | `menu:site` |
| `/source-fund` | 6 | `role:superadmin,finance,finance_manager`<br>`menu:source-fund` |
| `/stage` | 10 | `menu:milestone`<br>`menu:milestone,list-po` |
| `/storage` | 1 | `(tanpa role/menu)` |
| `/team` | 8 | `menu:team`<br>`role:superadmin` |
| `/todo-list` | 4 | `menu:todo-list` |
| `/trouble-ticket` | 46 | `menu:trouble-ticket`<br>`role:superadmin` |
| `/up` | 1 | `(tanpa role/menu)` |
| `/user` | 9 | `role:superadmin`<br>`menu:user-access` |
| `/view-as` | 2 | `(tanpa role/menu)` |
| `/wilayah` | 31 | `menu:wilayah`<br>`menu:wilayah,site` |

`(tanpa role/menu)` = route itu hanya digerbang `auth`, `guest` (`/login`, `/forgot`), atau
publik (`/up`, `/api/public`) — lihat §2 dan fixture.

## 4. Route `auth` saja — pemeriksaan di controller

Route ini tidak punya `role:`/`menu:`; keamanannya bergantung sepenuhnya pada controller.

| Route | Pemeriksaan di controller | Status |
|---|---|---|
| `GET /budget-request/{id}/detail-json` | `canViewCr()` ∪ `wasActedOnBy()` baca-saja | OK |
| `POST /budget-request/{id}/log-note` | `findVisibleCr()` | OK |
| `GET /budget-plan/{id}/detail-json`, `export-report`, `export-cost`, `export-budget` | `canViewBudgetPlan()` (`BudgetPlanController.php:128`) | OK |
| `POST /budget-plan/{id}/log-note` | `findVisibleBudgetPlan()` | OK |
| `GET /project/{id}/detail-json` | `canViewProjectJson()` → `User::canSeeProject()` ∪ pernah menindak | OK |
| `POST /project/{id}/log-note` | `canViewProjectJson()` + `canPostLogNote()` | OK |
| `GET /project-monitoring`, `/{id}`, `/{id}/messages` | `accessibleProjectIds()` / `findAccessibleProject()` (404 di luar akses) | OK |
| `POST /project-monitoring/{id}/message`, `/weights`, `detail/{id}/set-status`, `set-finish` | `canManageProject()` (+ `canSetMilestone()`) | OK |
| `/assignment/*` | `canView()` / `canManage()` / `canComplete()` per aksi | OK (index: Needs confirmation) |
| `GET /feedback/{id}` | pemilik ∪ `isAuthorizedViewer()` | OK |
| `GET /storage/{path}` | `SecureFileController` → `FileAccess::allows()` default tolak (`FileAccess.php:132`) | OK |
| `GET /global-search` | per modul: `bolehBukaMenu()` + scope (CR `scopeByRole`, BP `scopeVisibleTo`, Invoice `scopeInvoiceVisibleTo`) | **F-1** untuk PO |
| `GET /api/budget-plan/{id}/details` | **tidak ada** (`BudgetPlanController.php:2107`) | **F-3** |
| `POST /budget-request/check-nob` | **tidak ada** (`BudgetRequestController.php:3596`) | **F-4** |
| `GET /budget-request/ca-quota` | **tidak ada**; kuota CA per `beneficiary_id` (`BudgetRequestController.php:233`) | **F-5** |
| `GET /api/clusters/{budgetPlanId}` | method `getClusters` **tidak ada** di `BudgetRequestController` | **F-2** |
| `/profile/*`, `/notifications/*`, `/view-as*`, `/changelog/dismiss`, `/panduan*`, `/app/{app}` | data milik user sendiri / statis; View As dicek `isRealSuperadmin()` | OK |

## 5. Data scope per modul (perilaku saat ini)

| Modul | Scope baris di daftar | Fungsi | Kolom kepemilikan |
|---|---|---|---|
| Cost Request | Role istimewa: semua; lainnya Access Group (proyek/cost center) ∪ milik sendiri | `BuildsCrWorklist::scopeByRole()` (`:29`), `findVisibleCr()` (`BudgetRequestController.php:138`) | `applicant_id`, `beneficiary_id`, `deleted_by` |
| Budget Plan | `BP_PROJECT_PRIVILEGED_ROLES` semua BP proyek; lainnya Access Group; overhead & payroll terpisah | `ScopesBudgetPlanVisibility::scopeVisibleTo()` (`:89`) | `input_by`, `user_id` |
| Purchase Order / Project | `AccessRoles::PO_FULL_SCOPE` semua; lainnya Access Group | `User::accessibleProjectIds()`, `canSeeProject()` | `manager_id`, `input_by`, `user_id` |
| Project Monitoring | privileged semua; lainnya Access Group | `ProjectMonitoringController::accessibleProjectIds()` (`:92`) | — |
| Invoice & Cash Receipt | `ScopesInvoiceVisibility` | `scopeInvoiceVisibleTo()` | `created_by`, `submitted_by`, `user_id`, `deleted_by` |
| Trouble Ticket | site → PIC → team; `TROUBLE_TICKET_FULL_SCOPE` semua | `TroubleTicket::scopeVisibleTo()` | `created_by`, `requested_by`, `assigned_to`, `pic_id`, `user_id` |
| Site | teknisi: customer dari site tempat ia terdaftar | `SiteController::visibleCustomerIds()` | `pic_id` |
| Assignment | pembuat / assignee | `AssignmentController::canView()` | `assignee_id` (+ pembuat) |
| Finance Summary | proyek via Access Group untuk role terbatas | `FinanceSummaryController` (`accessibleProjectIds`) | — |
| Todo List | per antrean: `scopeByRole`, `scopeVisibleTo`, `accessibleProjectIds` | `TodoListController` | — |
| Treasury (Daily Transaction), Cash Receipt (header), Cash Management, Cost Register, Delivery Note, Forecast, Closing, Payroll, Report, Cost Center, Beneficiary | **Semua baris** bagi pemegang role/menu (tanpa scope baris) | — | `created_by` / `input_by` / `submitted_by` |

Catatan: tidak ada tabel transaksi yang menyimpan team/department pemilik → scope
Own Team/Department belum dapat diterapkan (keputusan D4 di blueprint).

## 6. Daftar role "lihat semua" yang berbeda antar modul

| Konstanta | Isi | Lokasi |
|---|---|---|
| `AccessRoles::PO_FULL_SCOPE` | superadmin, approver, finance_manager, cost_control, collection, treasury, accounting | `app/Support/AccessRoles.php` |
| `BP_PROJECT_PRIVILEGED_ROLES` | superadmin, **finance**, finance_manager, approver, **admin**, cost_control, treasury, collection, accounting | `Concerns/ScopesBudgetPlanVisibility.php:59` |
| `BudgetRequest::STAGE_ROLES` | SUBMITTED→head; CHECKED→finance, finance_manager, cost_control; VERIFIED→approver | `app/Models/BudgetRequest.php:1109` |

`finance` dan `admin` melihat semua Budget Plan proyek tetapi tidak semua PO. Sengaja atau
tidak: **Needs confirmation** (tidak diubah).

## 7. Temuan Fase 0

**Status 2026-11-07 (keputusan pemilik D19):**
- F-1 **diperbaiki**: Global Search PO memakai `PO_FULL_SCOPE` / `accessibleProjectIds()`.
- F-2 **diperbaiki**: route `/api/clusters/{budgetPlanId}` dihapus (fixture kini 540 route).
- F-3 **diperbaiki**: `getDetails` memakai `canViewBudgetPlan()` → 403 di luar akses.
- F-4 **diperbaiki**: `checkNob` memakai `scopeVisibleTo()`; Budget Plan tak terlihat dijawab
  seperti tidak ada (`{"is_nob":false,"remaining":0}`).
- F-5: pemilik memilih **aturan bisnis baru**, bukan menutup endpoint — CA hanya untuk beneficiary
  INTERNAL ber-PIC dengan Token > 0 (`docs/workflows/cash-advance.md`). Endpoint `ca-quota`
  tetap terbuka bagi user login (kebocoran pemakaian kuota per beneficiary masih ada, LOW–MEDIUM).

Tabel di bawah adalah temuan asli saat audit.

| # | Temuan | Dampak | Level | Bukti |
|---|---|---|---|---|
| F-1 | Global Search Purchase Order tidak menyaring Access Group; komentar kode masih menyebut "ProjectController::index() tidak menyaring", padahal sejak 26 Sep 2026 daftar PO sudah tersaring | Pemegang menu `list-po` melihat kode, nama, dan status PO di luar Access Group-nya (tanpa nilai uang) | MEDIUM | `GlobalSearchController.php:188-202` |
| F-2 | Route `/api/clusters/{budgetPlanId}` menunjuk method yang tidak ada | Error 500 bila dipanggil; tidak ada pemanggil di `resources/` & `public/js` | LOW | `routes/web.php:1453` |
| F-3 | `/api/budget-plan/{id}/details` tanpa cek visibilitas | User login mana pun membaca sub-item Budget Plan ACTIVE (nilai, terpakai, sisa) lewat id | MEDIUM | `BudgetPlanController.php:2107-2140`; uji `test_temuan_rincian_budget_plan_tidak_tersaring` |
| F-4 | `/budget-request/check-nob` tanpa cek visibilitas | Mengungkap `budget_amount` & sisa Budget Plan mana pun | MEDIUM | `BudgetRequestController.php:3596-3613`; uji `test_temuan_check_nob_membuka_nilai_budget_plan_lain` |
| F-5 | `/budget-request/ca-quota` tanpa cek visibilitas beneficiary | Mengungkap pemakaian kuota Cash Advance (jumlah & nominal) per beneficiary | LOW–MEDIUM | `BudgetRequestController.php:233` |

Usulan penanganan (menunggu keputusan pemilik): F-3/F-4 memakai `canViewBudgetPlan()` yang
sudah ada; F-1 memakai `accessibleProjectIds()` seperti halaman PO; F-2 hapus route mati;
F-5 batasi ke beneficiary yang boleh dipakai user. Semua mengubah respons bagi sebagian user
→ masuk daftar keputusan blueprint.

## 8. Yang belum dicakup Fase 0 (Needs confirmation)

- Kondisi tombol di Blade per role (109 `hasRole()` di views) belum dipetakan per halaman — dikerjakan
  saat cutover per modul (uji render G3 di blueprint).
- Endpoint export per modul sudah terdaftar di fixture. Scope export per dokumen Budget Plan
  (`export-budget/cost/report`) terverifikasi memakai `canViewBudgetPlan()`. Kesamaan scope export daftar
  dengan scope daftar untuk `budget-request/export`, `budget-plan/export`, `project/export-excel`,
  `invoice-cash-receipt/export`, `delivery-note/export`, `cost-register/export`, `todo-list/export`:
  **belum diverifikasi** (Fase 4).
- Pemegang role di produksi tidak diketahui (tidak membaca DB produksi).

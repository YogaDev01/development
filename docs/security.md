# Security & authorization

Verified 2026-10-02 from `bootstrap/app.php`, `routes/web.php`,
`app/Http/Middleware`, `app/Support/AccessRoles.php`, `app/Services/FileAccess.php`.

## Authentication

- Session login via `AuthController` (`/login`, `throttle:20,1`; test
  `LoginThrottleTest`). No registration route.
- `EnsureUserActive` ends sessions of deactivated users.
- "View As": superadmin can preview another role (`profile/view-as`);
  `CheckRole` redirects back instead of 403 while previewing.
- **Jejak & sesi (2026-11-07, Fase 1 Security & Access Control):** login gagal (`login_failed`) dan
  pemblokiran (`lockout`) kini juga masuk `user_activity_logs` (sebelumnya hanya `laravel.log`);
  `users.last_login_at/ip` diisi saat login. Alur, pesan, dan batas login **tidak berubah**.
- **Konsol Keamanan** `/security` (`role:superadmin`): Audit Trail seluruh user, Riwayat Login, Sesi
  Aktif (tabel `sessions`, hanya bila `SESSION_DRIVER=database`). Aksi: cabut satu sesi (dikenali lewat
  sha256 id sesi) dan putus semua sesi user lain; keduanya merotasi `remember_token` agar "Ingat saya"
  tidak langsung memulihkan sesi. Sesi sendiri tidak bisa dicabut dari konsol. Tercatat sebagai
  `revoke_session`.
- **Jejak export/unduh & akses ditolak (2026-11-07, D18/D11):** middleware global
  `RecordSecurityEvents` (grup web, setelah `EnsureUserActive`) hanya mengamati respons:
  route bernama `*.export*` / `*.print` yang sukses → `export`/`print`; respons 403 untuk user
  login → `access_denied` (module `security`, maks. 1 baris per user+URL per menit). Unduhan
  lampiran dicatat di `SecureFileController` (module `file`, `download`, maks. 1 per user+berkas
  per 10 menit; `avatars/`, `covers/` dilewati). Tidak ada respons yang diubah.
- **Tambah role (2026-11-07, D3):** `POST /role` (`role:superadmin`). Role baru tanpa menu
  (default deny); nama yang dipakai kode (`RoleController::RESERVED_NAMES` ∪ semua nama di
  gerbang `role:`) ditolak agar tidak mewarisi kewenangan hardcoded; nama tidak bisa diubah.
- **Mesin permission — mode shadow (2026-11-07, Fase 2):** registry `config/permissions.php` (1 entri per route
  bergerbang `role:`), hak di `role_permissions`. `ShadowPermission` hanya membandingkan dengan gerbang lama dan
  mencatat selisih ke `permission_shadow_logs` bila `SECURITY_PERMISSION_MODE=shadow`; gerbang `role:` lama tetap
  yang memutuskan. Route baru bergerbang `role:` wajib didaftarkan lewat generator (dijaga `PermissionRegistryTest`).
- **Tab Kewenangan Aksi (2026-11-07, Fase 3):** modal Role & Menu Access menampilkan 47 kelompok aksi
  (`config/permission_groups.php`) dengan status per role dari `role_permissions`. Baca-saja; belum berlaku
  sampai cutover per modul.
  Lihat `docs/security/access-matrix.md` dan
  `docs/plans/2026-11-06-security-access-control.md`.

## Roles (as used in route gates)

`superadmin`, `approver` (CEO), `head`, `manager`, `supervisor`, `finance`,
`finance_manager`, `cost_control`, `treasury`, `accounting`, `collection`,
`admin`, `hr`, `guest`. Who holds each role in production: Needs confirmation.

## Authorization layers

1. `role:a,b` → `CheckRole` (`User::hasRole`).
2. `menu:key` → `CheckMenu` (`role_menu_items`; superadmin always passes).
3. In-controller `hasRole()` checks (duplicate route gates, e.g. `headApprove`).
4. Record visibility: Access Groups (`findVisibleCr`, `scopeByRole`,
   `ScopesBudgetPlanVisibility`, `ScopesInvoiceVisibility`, `AccessRoles::PO_FULL_SCOPE`).
   Sumber tunggal sejak 2026-11-06: `User::accessibleProjectIds()` /
   `accessibleCostCenterIds()` = Access Group individu (`users.access_group_id`)
   ∪ Access Group yang dipasang ke Team ACTIVE tempat user menjadi anggota ACTIVE
   (`access_group_details.team_id`). Kebalikannya: `User::scopeWithProjectAccess()`.
   Leader/induk team tidak memberi akses. Akses PO team hanya diubah superadmin
   (form Team, bagian Akses PO, atau halaman Access Group — tabel yang sama).
   `PO_FULL_SCOPE`/`PO_VALUE`/`PO_MONEY_COLUMNS` mencakup cost_control, collection,
   treasury, accounting (tetap digerbang menu); `finance` umum tidak.
   Jalur **baca-saja** (2026-11-06, K7): pelaku yang pernah menindak dokumen
   (`BudgetRequest::wasActedOnBy()` dari `request_status_logs.acted_by`,
   `Project::wasActedOnBy()` dari `project_logs.user_id`) tetap boleh membuka
   detail JSON & lampirannya (`FileAccess`) walau aksesnya tidak aktif lagi.
   Respons memuat `read_only: true`, aksi workflow kosong, catatan log ditolak.
   Aksi tetap lewat `findVisibleCr()` / `canViewProjectJson()` — tidak melebar.
   Daftar (index) tidak menampilkan dokumen itu.
   Lampiran PO/BAST (`FileAccess::allowsProject`, sejak 2026-11-06) = `User::canSeeProject()`
   ∪ pernah menindak ∪ (bukan manager/head dan punya menu `invoice-cash-receipt`, sama dengan
   keterlihatan invoice). Lampiran Budget Plan (`allowsBudgetPlan`) = `scopeVisibleTo()` Budget
   Plan (termasuk PAYROLL hanya role payroll). Sebelumnya semua role selain manager/head lolos.
   Halaman Budgeting & PO tetap digerbang menu; menurut pemilik, role supervisor ke bawah memang
   tidak diberi menu tersebut. Keputusan pemilik 2026-11-06: pembatasan supervisor ke bawah
   **cukup lewat menu** — tidak ada blokir data berbasis role; anggota team ber-Access Group
   tetap bisa membuka detail/lampiran PO lewat tautan langsung.
5. Files: `SecureFileController` → `FileAccess` (reuses the same visibility
   scopes; default = must be logged in).

## Who can do what (route gates)

Roles below are the **route-level** gate. Many CR actions repeat the check at
**controller level** (`hasRole()` / `authorizeSettlementClose()`), and record
visibility (`findVisibleCr`) applies on top. Bulk actions decide per row by
status + role inside the controller. `hasRole()` itself has no superadmin
bypass — superadmin passes because it is listed in each route gate; only
`CheckMenu` bypasses for superadmin.

| Action | Roles |
|--------|-------|
| CR create/edit/delete, revise, submit settlement | superadmin, manager, supervisor |
| CR submit, cancel | superadmin, manager |
| CR head approve/reject, settlement head check/reject | superadmin, head |
| CR finance verify/reject (request + settlement) | superadmin, finance, finance_manager, cost_control |
| CR CEO approve/reject, second approve/reject | superadmin, approver |
| CR settle approve/reject, `ceo-close-settlement`, settlement bulk close/reject | superadmin, finance_manager |
| CR `manager-reject-settlement` (SETTLE_VERIFIED → PAID) | Route: `role:superadmin,finance_manager`. Controller: `authorizeSettlementClose()` → same two roles. Despite the name, `manager` cannot call it. |
| CR bulk approve/reject, item takeout/restore | superadmin, head, finance, finance_manager, cost_control, approver (stage checked in controller) |
|   - bulk dari index CR (tab CA/CP) | Memakai route yang sama. Kolom checkbox hanya untuk peran efektif di `STAGE_ROLES` + superadmin; server memeriksa ulang status, peran, visibilitas (`visibleCrs`) per CR, plus `expected[id]` opsional dan batas 50 id. Id tak ditemukan dan id tanpa akses diberi pesan yang sama (tidak membocorkan keberadaan). Needs confirmation: belum ada throttle khusus pada endpoint ini. |
| Treasury batch create / add item / **mark paid** | superadmin, finance, finance_manager, treasury |
| Treasury batch approve / reject batch / reject item | superadmin, approver |
| Budget Plan create/submit/closing request | superadmin, manager |
| Budget Plan head / finance / CEO steps | head / finance+finance_manager / approver (+superadmin) |
| Invoice create/submit/mark received, cash receipt on invoice | superadmin, admin, finance, finance_manager, collection |
| Invoice verify | superadmin, finance_manager, accounting |
| Invoice approve | superadmin, finance_manager |
| Invoice revise / cancel | +approver (+accounting for revise) |
| Cash Receipt (header) store/allocate/cancel/delete | superadmin, finance, finance_manager, treasury, approver, collection |
| Cash Management post/cancel/delete | superadmin, finance, finance_manager, treasury, approver |
| Closing close | `menu:closing` only |
| Beneficiary edit/delete | superadmin only (`AccessRoles::BENEFICIARY_MANAGE`) |
| Site tambah/ubah/hapus/import, PIC, material, accessories, dokumen, maintenance | semua role KECUALI `AccessRoles::SITE_READ_ONLY` (teknisi) |

## Files & uploads

- Disk `secure` → `storage/app/private`, `throw => true`. `storage:link` must
  not be run (`filesystems.links` intentionally empty).
- Uploads validated per action (`mimes`, `max:10240` typical).

## Public API

`/api/public/budget-request`, `/detail`, `/realisasi`: static token
(`EXPORT_API_TOKEN`, Bearer / `X-Export-Token` / deprecated `?token=`),
`throttle:30,1`. Returns **all** CRs without Access Group scoping.

## Suspicious / inconsistent findings (report only — not changed)

1. **No segregation-of-duties check found**: no guard preventing a user from
   approving a CR they applied for (e.g. a superadmin or someone holding
   multiple duties). Needs confirmation whether intended.
2. **superadmin passes every gate**, including payment and approval steps.
3. **`ceo-close-settlement` is gated to finance_manager**, not approver; the
   name suggests CEO. UI text ("Menunggu penutupan Finance Manager") matches
   the route, so the route name is the legacy part.
4. **Invoice approve** — *text fixed on `develop` (2026-10-02)*: UI said
   VERIFIED = "Menunggu persetujuan CEO" while route
   `invoice-cash-receipt/{id}/approve` allows only superadmin +
   finance_manager. `WorkflowStatus`, the panduan page, and the submit
   confirmation now name Accounting / Finance Manager (verify) and Finance
   Manager (approve). Open question: the Todo List verify queue still shows
   only for finance_manager although `accounting` may verify.
5. **Closing** actions only require `menu:closing`; no role gate. Closing a
   period does not lock other modules (no lock check outside Forecast found).
6. **Concurrency (audit 2026-10-02):** Treasury batch `store`/`addItem`,
   `financeVerifySettlement`, and Cash Receipt `allocate` checked their guards
   without a row lock (HIGH: could double-pay / over-allocate; production data
   check found no occurrence). `markPaid` without lock only duplicates logs and
   attachments (MEDIUM, no duplicate money rows). Invoice status transitions
   are unlocked but `storeCashReceipt`/`destroyCashReceipt` are locked (LOW).
   Fix status: H1, H2, H3, M1 (`markPaid`) and M3 (`managerRevise`, bulk
   review, `takeoutDetail`/`restoreDetail` now use locked transitions) are
   fixed on `develop` (pushed as `origin/develop-v2`), not yet released.
   - **Invoice / Surat Jalan (LOW, fixed on `develop`):** invoice
     submit/verify/approve/revise use `transitionStatus()`; `cancel` and
     `settleCashReceipt` (PPh) lock the invoice; `storeCashReceipt` re-checks
     the invoice status inside its lock; `DeliveryNoteController::store` locks
     the invoices and re-checks APPROVED + "not already on a Surat Jalan".
   - **Second Payment re-batch (fixed on `develop`):** `rejectBatch` /
     `rejectItem` now return the parent CR from QUEUED to SECOND_APPROVED, so
     a Second Payment from a rejected batch can be batched again.
   - **Bulk review Access Group gap (found 2026-10-02, fixed with M3):**
     `reviewBulkApprove`/`reviewBulkReject` loaded CRs by id without
     `canViewCr()`, so a Head could approve/reject CRs outside their Access
     Group via a crafted POST. Now filtered through `visibleCrs()`, like the
     single actions and bulk settlement.
   - **M2 reclassified (2026-10-02): FALSE POSITIVE / LOW.** The Cash Advance
     per-PIC limit cannot be bypassed by concurrent submits. The nominal limit
     counts every CR not CLOSED/CANCELLED, including DRAFT, and `submit`
     re-checks it against committed rows, so drafts created concurrently over
     the limit are stopped at submit. The count limit only counts PAID →
     SETTLE_APPROVED CRs, which submit does not change. No code change.
7. Cash Receipt delete is allowed for `approver`, `collection`, `treasury` —
   a reversal of received money. Logged per `docs/DEPLOYMENT.md`, but scope
   Needs confirmation.
8. Public export API bypasses Access Group scoping by design.

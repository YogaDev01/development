# Security & authorization

Verified 2026-10-02 from `bootstrap/app.php`, `routes/web.php`,
`app/Http/Middleware`, `app/Support/AccessRoles.php`, `app/Services/FileAccess.php`.

## Authentication

- Session login via `AuthController` (`/login`, `throttle:20,1`; test
  `LoginThrottleTest`). No registration route.
- `EnsureUserActive` ends sessions of deactivated users.
- "View As": superadmin can preview another role (`profile/view-as`);
  `CheckRole` redirects back instead of 403 while previewing.

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

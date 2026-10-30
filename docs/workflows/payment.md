# Workflow — Payment (Cost Payment & Treasury batch)

## Cost Payment (every CR type that is not Cash Advance)

Same request leg as Cash Advance (DRAFT → SUBMITTED → CHECKED → VERIFIED →
APPROVED → QUEUED), but submit requires an item attachment on every
non-TAKEOUT detail, and on batch mark-paid the CR goes **QUEUED → CLOSED**
(no settlement).

## Treasury batch (`daily_transactions`)

Types: `CASH_OUT` (CR / Cost Register / Second Payment items), `TOP_UP`
(transfer between company accounts, no items).

| From → To | Action | Roles |
|-----------|--------|-------|
| — → DRAFT | `store` (since 29 Oct 2026). Items are reserved (`DailyTransaction::ACTIVE_STATUSES` includes DRAFT) but CR / Cost Register / Second Payment parent **do not change status** | superadmin, finance, finance_manager, treasury |
| DRAFT (edit) | `add-item` (no status change), `draft-remove-item` (item row deleted), `DELETE daily-transaction/{id}` (draft deleted; sources untouched) | superadmin, finance, finance_manager, treasury |
| DRAFT → PENDING_APPROVER | `submit`: locks rows, re-checks CR APPROVED / register SUBMITTED / SP PENDING + parent SECOND_APPROVED, then CR → QUEUED (`batched`), register → QUEUED, SP parent → QUEUED; notifies approvers. CEO cannot approve a DRAFT. Payroll Run batches still start at PENDING_APPROVER | superadmin, finance, finance_manager, treasury |
| PENDING_APPROVER (edit) | `add-item` (CRs move APPROVED → QUEUED) | superadmin, finance, finance_manager, treasury |
| PENDING_APPROVER → DONE | `approve` | superadmin, approver |
| PENDING_APPROVER → DONE / REJECTED (massal) | `bulk-approve` / `bulk-reject` (notes wajib) dari daftar Treasury: per batch menjalankan `approve` / `rejectBatch`; maks 50; `expected[id]` melewati batch yang statusnya berubah; Draft dilewati | superadmin, approver |
| PENDING_APPROVER → REJECTED | `reject-batch` (CRs back to APPROVED) | superadmin, approver |
| Per item, batch PENDING_APPROVER | `reject-item` = **Takeout**: CR QUEUED → APPROVED (log `batch_takeout`; Second Payment parent → SECOND_APPROVED; Cost Register → SUBMITTED) | superadmin, approver |
| Per item, batch PENDING_APPROVER | `reject-cr-item` = **Reject**: CR QUEUED → REJECTED (`batch_item_rejected`); Second Payment: pending SP deleted, parent CR → SETTLE_REJECTED (`second_payment_rejected`); Cost Register not allowed | superadmin, approver |
| DONE → PAID | `mark-paid` (≥1 attachment, optional charge) | superadmin, finance, finance_manager, treasury |

Effects of mark-paid: see `docs/business-rules.md#treasury-batch`.

Risk: `markPaid` checks `isDone()` before the transaction without a row lock.

## Cost Register

Statuses DRAFT, SUBMITTED, QUEUED, PAID, CANCELLED; paid through a Treasury
batch (QUEUED → PAID on mark-paid). Create/delete: superadmin, finance,
finance_manager. Full flow: Needs confirmation.

## Payroll Run

Statuses DRAFT, SUBMITTED, VERIFIED, APPROVED, PAID, CLOSED, REJECTED
(`PayrollRunController`, `menu:payroll-run`). Role gates per step: Needs confirmation.

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
| — → PENDING_APPROVER | `store`, `add-item` (CRs move APPROVED → QUEUED) | superadmin, finance, finance_manager, treasury |
| PENDING_APPROVER → DONE | `approve` | superadmin, approver |
| PENDING_APPROVER → REJECTED | `reject-batch` (CRs back to APPROVED) / `reject-item` | superadmin, approver |
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

# Workflow — Settlement (Cash Advance only)

Source: `HandlesCrSettlement`, `BudgetRequest::getAllowedNextStatuses()`,
`BudgetRequestSecondPayment`, `DailyTransactionController::markPaid()`.

| From → To | Action | Roles |
|-----------|--------|-------|
| PAID → REALIZED | `submit-realization` (realization rows; may be 0 without attachment since v2.4.2) | manager, supervisor, superadmin |
| REALIZED → SETTLE_CHECKED / PAID | `head-check-settlement` / `head-reject-settlement` | head, superadmin |
| SETTLE_CHECKED → SETTLE_VERIFIED / PAID | `finance-verify-settlement` / `finance-reject-settlement` | finance, finance_manager, cost_control, superadmin |
| SETTLE_VERIFIED → CLOSED (no expense claim) | `ceo-close-settlement` | **finance_manager**, superadmin |
| SETTLE_VERIFIED → SETTLE_APPROVED (expense claim) | `settle-approve` | finance_manager, superadmin |
| SETTLE_APPROVED → SECOND_APPROVED / PAID | `second-approve` / `second-reject` | approver, superadmin |
| SECOND_APPROVED → QUEUED | Treasury batches the Second Payment | treasury group |
| QUEUED → SECOND_APPROVED | Batch rejected (`reject-batch`) or SP item taken out (`reject-item`); SP stays PENDING and can be batched again (fixed on `develop` 2026-10-02 — before, the CR stayed QUEUED and the SP could never be re-batched) | approver, superadmin |
| QUEUED → CLOSED | batch `mark-paid` closes CR automatically | treasury group |
| REALIZED / SETTLE_CHECKED / SETTLE_VERIFIED / SETTLE_APPROVED → PAID | reject actions (head, finance, `settle-reject`, `manager-reject-settlement`, `second-reject`) | per action, see `docs/security.md` |

Expense claim = realization − request amount > 0. Refund = request −
realization, only counted once settled.

## Second Payment lifecycle (expense claim only)

The Second Payment **record** and its **approval/payment** are separate steps:

1. **Creation — at SETTLE_VERIFIED.** `financeVerifySettlement` sets the CR to
   SETTLE_VERIFIED and, inside the same DB transaction, creates a
   `budget_request_second_payments` row with `status = PENDING` and
   `amount = settlement_overage` (`total_realization − amount`) when that
   value is > 0.
2. **Approval of the claim** — `settle-approve` (finance_manager):
   SETTLE_VERIFIED → SETTLE_APPROVED. Then `second-approve` (approver):
   SETTLE_APPROVED → SECOND_APPROVED. The Second Payment row stays PENDING.
3. **Batching** — Treasury adds it to a batch; the parent CR moves
   SECOND_APPROVED → QUEUED (`DailyTransactionController`, store/add-item).
4. **Payment** — batch `mark-paid`: Second Payment PENDING → PAID (`paid_by`,
   `paid_at`), and the parent CR QUEUED → CLOSED.

Settlement reject (`settle-reject`, `second-reject`, `manager-reject-settlement`)
returns the CR to PAID and is refused if a Second Payment is already PAID or is
on an INCLUDED batch item (`applySettlementRejectClose`). What happens to the
PENDING row on reject: see that method (Needs confirmation — not traced in
detail).

Discrepancies: comments in `DailyTransactionController` describe an older
design (parent CR "stays SETTLE_VERIFIED"). Behavior = table above.

Needs confirmation: how refunds (money returned by PIC) are physically recorded
— no refund receipt flow found.

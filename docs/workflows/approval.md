# Workflow — Approval chains (all modules)

| Module | Chain | Step owners |
|--------|-------|-------------|
| Cost Request | DRAFT → SUBMITTED → CHECKED → VERIFIED → APPROVED | manager → head → finance/finance_manager/cost_control → approver |
| CR Settlement | REALIZED → SETTLE_CHECKED → SETTLE_VERIFIED → (CLOSED \| SETTLE_APPROVED → SECOND_APPROVED) | head → finance group → finance_manager → approver |
| Budget Plan | DRAFT → SUBMITTED → CHECKED → VERIFIED → ACTIVE → CLOSING → CLOSED | manager → head → finance/finance_manager → approver; closing: manager `submit-closing` (ACTIVE → CLOSING; also accepts CLOSING not yet submitted — `isClosingSubmitted()`, meaning Needs confirmation), finance group `verify-closing` (CLOSING → CLOSED) |
| Budget Plan (other closing paths) | `finance-close`: ACTIVE → CLOSED directly · `reject-closing`: CLOSING → ACTIVE | finance, finance_manager, superadmin (`BudgetPlanController`) |
| Treasury batch | PENDING_APPROVER → DONE → PAID | approver → treasury group |
| Invoice | DRAFT → SUBMITTED → VERIFIED → APPROVED → SENT → RECEIVED → PARTIALLY_PAID → PAID | creator (admin/finance/collection) → finance_manager/accounting → finance_manager → Delivery Note → collection |
| Purchase Order | see `purchase.md` | |
| Payroll Run | DRAFT → SUBMITTED → VERIFIED → APPROVED → PAID → CLOSED | Needs confirmation |
| Forecast | DRAFT → ACTIVE → CLOSED (auto-close daily) | `menu:forecast` |
| Cash Management | DRAFT → POSTED / CANCELLED | treasury group + approver |
| Cash Receipt header | DRAFT → ALLOCATED / CANCELLED | finance/treasury/collection/approver |
| Closing | DRAFT → CLOSED → REOPENED | `menu:closing` |

Rejections: CR → REJECTED then applicant Revise (→ DRAFT) or Cancel. Budget
Plan → REJECTED then manager revise. Settlement rejects return to PAID.

Todo List (`TodoListController`, `BuildsCrWorklist`) aggregates the pending
items per role and supports bulk actions (`todo-list/bulk/{section}/{mode}`).

Not found: self-approval prevention, approval amount thresholds, delegation.
**Needs confirmation** whether these are required.

# Workflow — Purchase Order (Project)

In TESYS "PO" = **customer Purchase Order** (revenue contract) stored in
`projects` + `project_po_numbers`. No procurement / vendor-purchase module was
found — **Needs confirmation** if one is expected.

Statuses (`Project::STATUSES`): DRAFT, SUBMITTED, WAITING_PO, ACTIVE,
PROGRESS_DONE, PAID, CLOSING, CLOSED.

| Stage | Meaning (from `WorkflowStatus` 'project' context) | Who |
|-------|----------------------------------------|-----|
| SUBMITTED | awaiting Finance approval | finance, finance_manager, superadmin |
| WAITING_PO → ACTIVE | **manual** activation: `activate-from-waiting` / `activate` | superadmin, finance, finance_manager, head (route gate) |
| ACTIVE | running; awaiting BAST on all PO numbers | — |
| ACTIVE → PROGRESS_DONE → PAID | described as automatic from PO-number status in `WorkflowStatus` comments (`Project::syncStatusFromPoNumbers()`); exact triggers not traced — Needs confirmation | PO-number actions (`complete-bast`, `po-number/status`): superadmin, finance, finance_manager, head |
| PAID | awaiting MoM Closing by Head | head, superadmin |
| CLOSING | awaiting Finance closing verification (`finance-close`) | finance, finance_manager, superadmin |

PO number statuses: RELEASED, PARTIAL, BAST_SUBMITTED, INVOICED, PAID.

Money visibility: `AccessRoles::PO_VALUE`, `PO_MONEY_COLUMNS`, `PO_FULL_SCOPE`.
Payment metric trap: use `paymentTotal()` / `paidDppByProject()`, not
`paymentProgressStatus()`.

# Workflow — Reimbursement

**Needs confirmation.** No module, table, route, or RequestType constant named
reimbursement / petty cash was found in the code.

What exists that may cover it:

- **Cost Payment** (any non-Cash-Advance RequestType) — pays an approved
  request directly; QUEUED → CLOSED when the Treasury batch is paid. See
  `payment.md`.
- **Expense Claim** inside Cash Advance settlement — when realization exceeds
  the advance, the difference is paid via Second Payment. See `settlement.md`.

RequestType rows are data (table `request_type`), so a "Reimbursement" type may
exist in production. Confirm with the business before documenting a flow.

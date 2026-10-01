# Module — Cash Management & Cash Receipt

## Cash Management (`CashManagementController`, `CashFlowService`)
- Tables `cash_flows`, `cash_flow_details`. Statuses DRAFT → POSTED / CANCELLED.
- Roles: superadmin, finance, finance_manager, treasury, approver
  (store, update, post, cancel, destroy).
- Purpose/accounting treatment: Needs confirmation.

## Cash Receipt
Two structures exist:

1. **Invoice cash receipts** (`cash_receipts`, soft-deleted) recorded on an
   invoice (`InvoiceCashReceiptController::storeCashReceipt`, `settleCashReceipt`)
   — only for invoices SENT / RECEIVED / PARTIALLY_PAID; moves the invoice
   toward PARTIALLY_PAID / PAID. Withholding tax supported.
2. **Cash Receipt headers** (`cash_receipt_headers`, `CashReceiptController`)
   — money received then **allocated** to invoices. Statuses DRAFT →
   ALLOCATED / CANCELLED. Roles: superadmin, finance, finance_manager,
   treasury, approver, collection.

Deleting a cash receipt reverses a payment; it is logged ("Cash Receipt
dihapus", see `docs/DEPLOYMENT.md`).

Needs confirmation: relationship and precedence between the two structures
(both can mark invoice payments).

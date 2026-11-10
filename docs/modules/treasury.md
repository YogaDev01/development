# Module — Treasury

- **Daily Transaction** (`DailyTransactionController`, menu
  `finance-daily-transaction`): payment batches. Types CASH_OUT / TOP_UP.
  Items (`daily_transaction_items`) reference a CR, an Expense (`cost_registers`) row, or a
  Second Payment; item status INCLUDED / REJECTED. Flow in
  `docs/workflows/payment.md`.
- **Expense** (dulu Cost Register, `CostRegisterController`, URL `/expense`): non-CR costs paid through a
  batch.
- **Source Funds** (`source_funds`): company bank accounts; `is_main_account`;
  TOP_UP moves money between them (`to_source_fund_id`).
- `captured_amount` on the batch is recalculated on mark-paid
  (`recalculateCapturedAmount()`).
- Attachments (proof of transfer) stored on disk `secure`.

Who: create/add/mark-paid = superadmin, finance, finance_manager, treasury;
approve/reject = superadmin, approver.

Needs confirmation: bank reconciliation process (none found in code).

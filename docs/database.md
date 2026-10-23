# Database architecture

Source: `database/migrations` (190 files) and `app/Models`. Verified 2026-10-02.
Read migrations, not memory, before changing anything.

## Major tables by domain

| Domain | Tables |
|--------|--------|
| Identity & access | `users` (incl. `notification_prefs` JSON — per-user notification opt-out, safe-by-default; `cover` — profile cover photo, like `avatar`; `default_app` — user's default app/sidebar at login, see `config/apps.php`), `roles`, `menu_items`, `role_menu_items`, `access_groups`, `access_group_details` (project / cost center / team scope) |
| Org | `departments`, `teams`, `team_members`, `employees`, `master_pics` (linked to users/employees; CA limit `token`, `nominal`) |
| Master | `customers`, `customer_types`, `customer_details`, `banks`, `beneficiaries`, `clusters`, `groups`, `accounts`, `cost_centers`, `source_funds`, `stages`, `request_type` |
| Budget | `budget_plans`, `budget_plan_details`, `budget_plan_logs`, `budget_plan_attachments` |
| Cost Request | `budget_request`, `request_detail`, `request_attachments`, `request_detail_attachments`, `request_status_logs`, `budget_request_realizations`, `budget_request_realization_attachments`, `realization_detail_attachments`, `budget_request_second_payments` |
| Treasury | `daily_transactions`, `daily_transaction_items`, `daily_transaction_attachments`, `cost_registers`, `cost_register_attachments` |
| Purchase Order (customer PO) | `projects`, `project_details`, `project_pics`, `project_attachments`, `project_po_numbers`, `project_logs`, `project_monitoring_logs`(+attachments) |
| Billing & collection | `invoices`, `invoice_details`, `invoice_attachments`, `invoice_comments`, `cash_receipts`, `cash_receipt_headers`, `delivery_notes`(+items/logs/attachments) |
| Cash management | `cash_flows`, `cash_flow_details` |
| Forecast | `forecasts`, `forecast_details`, `forecast_actual_entries`, `forecast_logs` |
| Closing | `closings`, `closing_details` |
| Payroll | `payroll_runs`, `payroll_run_lines` |
| System | `notifications`, `jobs`, `failed_jobs`, `cache`, `sessions`, `document_sequences`, `feedback_submissions`, `ai_*` (legacy TesysAI) |

Note: table names are inconsistent (`budget_request`, `request_detail`,
`request_type` are singular). Never "fix" table names.

## Money columns

- Rule since `2026_09_18_000005_unify_money_columns_to_integer`: money is
  integer rupiah, `BIGINT UNSIGNED`. `2026_09_20_000013_…` and
  `2026_09_26_000001_widen_remaining_money_columns_to_bigint` widened leftover
  INT columns.
- Exceptions: forecast amounts are **signed** (`2026_09_22_000010`);
  percentages (`tax_rate`, `billing_percentage`) and `qty` remain decimal.
- `master_pics.nominal` was created as decimal (`2026_09_25_000010`) — Needs
  confirmation whether that is intended.

## Status columns

Every workflow table has a string/enum `status`. Allowed values = model
`STATUSES` constant (see `docs/business-rules.md`). Several migrations remap
enums (`remap_budget_request_status_add_checked_cancelled`,
`rename_approved_to_done_on_daily_transactions`,
`rename_budget_plan_pending_extend_to_closing`, …) — historical data was
converted in place.

## Audit fields

- `budget_plans.via_po_approval` (boolean, default 0; migrasi
  `2026_10_22_000100`): 1 = Budget Plan dibuat dari form PO dan statusnya
  digerakkan approval PO. Aditif, tanpa backfill; migrasi belum dijalankan
  (menunggu manusia pada target terverifikasi). Urutan deploy: migrasi dulu,
  baru upload kode (kode memakai kolom ini tanpa guard; lihat `DEPLOYMENT.md` §2).
- `budget_plans.plan_finish` (date, nullable; migrasi `2026_09_28_000001`) tidak lagi diisi
  maupun ditampilkan sejak Plan Finish & auto-closing dihapus (22 Okt 2026). Kolom
  sengaja dibiarkan, tanpa migrasi/backfill; nilai lama tetap tersimpan dan tidak ditimpa
  saat BP diedit.
- Hapus BP bundled lewat Edit PO adalah hard delete: `budget_plan_details` dan
  `budget_plan_logs` ikut terhapus (FK `cascadeOnDelete`), `budget_plan_attachments` juga
  cascade (berkas `secure` dihapus setelah commit), `project_monitoring_logs.budget_plan_id`
  dan `payroll_runs.budget_plan_id` menjadi NULL (`nullOnDelete`), sedangkan `budget_request`
  dan `trouble_ticket_requests` RESTRICT (penghapusan ditolak). `trouble_tickets.budget_plan_id`
  tanpa FK, jadi dicek eksplisit di kode.
- History tables: `request_status_logs`, `budget_plan_logs`, `project_logs`,
  `delivery_note_logs`, `forecast_logs`, `project_monitoring_logs`.
- Per-document actors/timestamps: e.g. `submitted_at`, `approved_at`,
  `paid_at`, `paid_by`, `closed_at`, `uploaded_by`.
- Soft deletes with `deleted_by` + `delete_reason`: `budget_request`,
  `invoices`, `cash_receipts` (`2026_09_18_000002`).
- Other models using `SoftDeletes`: `CashFlow` (`cash_flows`),
  `CashReceiptHeader` (`cash_receipt_headers`), `Closing`.
- No soft deletes: `budget_request_second_payments` — rows are hard-deleted on
  settlement reject (`HandlesCrSettlement::applySettlementRejectClose`), so a
  CR has at most one Second Payment row at a time by design.

## Foreign keys & deletes

- 55 migrations declare `cascadeOnDelete` / `onDelete('cascade')` (mainly
  child → parent: details, attachments, logs).
- `2026_09_26_000002_restrict_deletes_on_financial_foreign_keys` changed
  `budget_request.{applicant_id, beneficiary_id, type_id, cluster_id,
  budget_plan_id}` and `daily_transactions.created_by` to **RESTRICT**.
- Remaining cascade risk (Needs confirmation / review): child tables of
  financial docs still cascade from their parent (e.g. `request_detail`,
  realizations, `invoice_details`, `daily_transaction_items`). With soft
  deletes this is harmless, but a hard delete or `forceDelete()` of a parent
  removes history.
- `destroy` actions are status-guarded (verified): CR, Budget Plan, Project,
  Cash Management → DRAFT only; Cost Register → non-processed only; PO number →
  RELEASED only; Cash Receipt header → only without allocations. Treasury
  batches have no delete route.
- `daily_transaction_items.budget_request_id` is intentionally NOT unique: a CR
  legitimately appears in several batches after an item/batch rejection
  (confirmed in production data, 19 cases, 2026-10-02).

## Indexes & uniqueness

- `2026_09_03_000003_add_missing_status_indexes`, `2026_09_18_000004_add_reporting_indexes`.
- `clusters.code` unique (`2026_05_23_183411`). Document numbers protected by
  `document_sequences` counters.

## Migration hygiene findings

- Duplicate timestamp prefixes: `2026_09_20_000013_*` (2 files) and
  `2026_09_26_000001_*` (2 files). Both run (different names) but ordering
  between them is alphabetical. Do not add more.
- Many migrations mix schema + data (backfills, menu grants, role inserts,
  status remaps). Treat them as historical; never edit.
- `2026_10_01_000020_drop_bank_account_from_master_pics` (despite its name)
  drops `master_pics.account_number` and `bank_id`, added by
  `2026_09_22_000020`. Intentional (CHANGELOG v2.5.0: bank data lives on
  Beneficiary only); no code references remain. Data entered 22 Sep–1 Oct was
  not copied anywhere.

## Makna `withholding_tax` (tanpa skema baru)

- `invoices.withholding_tax`: PPh yang mengurangi `captured_total`; awalnya PPh di muka, kini disinkronkan dari PPh aktual pada `cash_receipts` (lihat business-rules.md).
- `cash_receipts.withholding_tax`: PPh yang diinput pada penerimaan; bukan uang masuk. Bila sama dengan PPh invoice (>0) tidak dihitung dua kali pada "diterima" (`Invoice::hitungDiterima`).

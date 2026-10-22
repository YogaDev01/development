---
name: tesys-database-safety
description: Use when writing or reviewing TESYS migrations, schema changes, queries, relationships, transactions or data backfills. Protects financial tables and history on a live production ERP.
---

# TESYS database and data integrity

Production is live finance data. The agent never runs DB write operations: migrations are written, not executed against any shared DB (CLAUDE.md §4, §14).

## Rules
- Additive by default: new table / nullable column / index. Guard with `Schema::hasTable/hasColumn` like existing migrations. Never edit a migration that has run; use a new file with a unique timestamp (check `database/migrations` for duplicates).
- Data-changing migrations (UPDATE/backfill/type/enum/drop) are HIGH; anything that can lose data is CRITICAL → stop and get approval. Log what changed and provide a working `down()` where feasible.
- Money columns: `BIGINT UNSIGNED` integer rupiah (cast `integer`); never `decimal`/`float` for money. Percent/qty may be decimal.
- `budget_request`, `invoices`, `cash_receipts` use SoftDeletes with `deleted_by`/`delete_reason`; never hard-delete. FKs to financial records are `RESTRICT`; do not add `cascadeOnDelete`.
- Status columns must match the model `STATUSES` and the existing definition.
- Multi-row writes that must be consistent go in `DB::transaction`; use row locks only following existing `TransitionsStatus` usage.
- Queries: select needed columns, eager-load relations used in loops, index columns used in filters. Reference `docs/database.md`.

## Verification
- Read the migration twice for `up()`/`down()` symmetry and guards; `php -l`.
- Do not run `migrate`, `migrate:fresh/reset/rollback`, `db:wipe`, seeders, or write-tinker. State explicitly that the migration was not executed and needs a human to run it on a verified target.
- Update `docs/database.md` when the schema changes.

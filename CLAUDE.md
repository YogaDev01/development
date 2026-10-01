# CLAUDE.md — TESYS development instructions

TESYS is a **live production ERP** that runs real company finance (cost requests,
payments, settlements, invoices, cash receipts, period closing). Every change is
made against that fact. When in doubt: **stop, explain, ask.**

Detailed references live in `docs/` (start at `docs/architecture.md`). This file is
the rulebook; `docs/` is the map.

---

## 1. Project overview

- Internal ERP, UI and code comments mostly in **Bahasa Indonesia**. Keep new
  user-facing text and comments in the same language as the surrounding code.
- Main domains: Cost Request (Cash Advance / Cost Payment) with Settlement,
  Treasury batches (Daily Transaction), Budget Plan, Purchase Order (Project),
  Invoice & Cash Receipt (collection), Cash Management, Cost Register, Forecast,
  Closing (period close), Payroll, master data, Todo List (cross-role approval
  worklist). See `docs/business-rules.md` and `docs/workflows/`.
- Release history: `CHANGELOG.md` + `config/changelog.php` (drives the in-app
  "What's New"). In-app user guide: `config/panduan.php` + `resources/views/panduan/`.

## 2. Technology stack

| Layer      | Actual                                                              |
|------------|---------------------------------------------------------------------|
| PHP        | ^8.3 (local: Laragon `php-8.3.30`, not on PATH in Git Bash)          |
| Framework  | Laravel 13 (`laravel/framework` v13.32.0 in composer.lock)          |
| DB         | MySQL 8 (sessions, cache, queue all on the `database` driver)       |
| Excel      | phpoffice/phpspreadsheet                                            |
| Frontend   | Blade + Bootstrap 4.6 / AdminLTE-style shell + jQuery + DataTables; custom `public/css/tesys-ui-*.css` and `public/js/tesys-*.js`. **No Vite / no build step** (removed intentionally — see `package.json` note). |
| Tests      | PHPUnit 12, feature tests against MySQL DB `tesys_test` (`phpunit.xml`) |
| Hosting    | Shared hosting (Hostinger) per project notes; ops guide `docs/DEPLOYMENT.md` |

## 3. Architecture principles (follow what exists)

TESYS is **not** textbook Laravel. Respect the real pattern:

- **Fat controllers + rich models.** Business logic lives mainly in controllers
  (e.g. `BudgetRequestController` ~3.2k lines) and model helpers
  (`BudgetRequest::getAllowedNextStatuses()`, `refundAmount()`, scopes).
- **Controller concerns** in `app/Http/Controllers/Concerns/` split large
  controllers (`HandlesCrSettlement`, `BuildsCrWorklist`, `TransitionsStatus`,
  `Scopes*Visibility`, `CapsLargeLists`).
- **Support classes** in `app/Support/` are single sources of truth for
  cross-cutting concerns: `AccessRoles` (who may do X), `WorkflowStatus`
  (presentation only — not transition rules), `BudgetConsumption`,
  `DocumentSequence`, `Navigation`.
- **Services** (`app/Services/`) are few: `NotifyService`, `RoleDirectory`,
  `FileAccess`, `ClosingService`, `CashFlowService`, `InvoiceCalculationService`,
  `TodoService`.
- **No Policies, no FormRequests, no Events/Listeners/Jobs classes.**
  Authorization = route middleware (`role:`, `menu:`) + in-controller checks +
  visibility scopes. Validation = inline `$request->validate()`.
- New code goes where similar code already lives. Do not introduce a new layer
  (Repositories, Actions, Policies, DTOs) unless explicitly approved.

## 4. Production safety rules

- The working copy at `C:\laragon\www\tesys` is **local** (`APP_ENV=local`,
  DB host defaults to 127.0.0.1). It is not production. Still: never point it
  at production credentials.
- NEVER: deploy, run `migrate:fresh` / `migrate:reset` / `db:wipe` /
  `migrate:rollback` on any shared DB, run `storage:link` (attachments are
  deliberately private), edit production `.env`, or run tinker writes against
  real data.
- Tests use `RefreshDatabase` on `tesys_test`. Confirm `phpunit.xml` still
  points to `tesys_test` before running tests.
- **Before EVERY test run, `bootstrap/cache/config.php` must NOT exist.** A
  cached config makes Laravel ignore `phpunit.xml`; `RefreshDatabase` then
  wipes whatever DB the cache points to (locally the dev DB `laravel`, because
  `.env` leaves `DB_DATABASE` commented). This happened on 2026-10-02 and the
  dev DB had to be restored from `C:\laragon\backup\mysql`. Check with
  `ls bootstrap/cache/config.php` and
  `APP_ENV=testing DB_DATABASE=tesys_test php artisan config:show database.connections.mysql.database`
  (must print `tesys_test`). Never run `config:cache`/`optimize` locally
  without clearing it before tests.
- Never commit `.env`, credentials, tokens (`EXPORT_API_TOKEN`, `FONNTE_TOKEN`).

## 5. Database rules

- Money = **integer rupiah** (`BIGINT UNSIGNED`, cast `integer`). Never add
  `decimal`/`float` money columns. Signed money only where already signed
  (forecast amounts). Percentages/qty may be decimal.
- Financial tables `budget_request`, `invoices`, `cash_receipts` use
  **SoftDeletes** with `deleted_by` / `delete_reason`. Never hard-delete them.
- FKs from `budget_request` / `daily_transactions` to masters are `RESTRICT`
  (migration `2026_09_26_000002`). Do not reintroduce `cascadeOnDelete` on any
  FK pointing at financial records.
- Status columns: values must match the model `STATUSES` constant and the
  existing enum/varchar definition. Adding a status = HIGH risk.
- Document numbers come from `App\Support\DocumentSequence` (never `max()+1`).
- Status locking — CURRENT state (not uniform):
  - `Concerns\TransitionsStatus::transitionStatus()` (row lock + expected-status
    check in one transaction) is used by some Cost Request actions, e.g.
    `headApprove`/`headReject`, `managerCancel`, `headCheckSettlement`,
    `financeRejectSettlement`, `settleApprove`, `secondApprove`.
  - Other paths that change CR status do **not** use it (status checked, then
    updated without a row lock): `submit`, `managerRevise`, bulk review
    (`applyReviewApprove`/`applyReviewReject`), `takeoutDetail` (auto-REJECTED),
    `financeVerifySettlement`, and Treasury `DailyTransactionController`
    store/add-item, `markPaid`, `rejectBatch` (which change CR status; batch
    `approve` only writes CR logs, it does not change CR status).
  - Other modules (Budget Plan, Invoice, Project, …) do not use it at all.
  - Most CR transitions write a `RequestStatusLog` via `addLog()`; do not assume
    every path does without checking.
  - Changing locking behavior on an existing path is a HIGH-risk change.

## 6. Financial data rules

- Do not change any calculation (budget usage, NOB, refund, expense claim,
  effective amount, PPN/DPP, withholding tax, CA quota) without explicit
  approval. Known single sources of truth:
  - `BudgetRequest::refundAmount()`, `expenseClaimAmount()`,
    `realizedCostValue()`, `scopeDisbursed()`
  - NOT on `main` yet: `effectiveAmount()`, `effectiveAmountSql()`,
    `effectiveItems()`, `RequestDetail::effectiveTotal()` live only on branch
    `feature/effective-amount` (unreleased, HIGH-risk calculation change).
    Do not depend on them from other branches.
  - `App\Support\BudgetConsumption`
  - `InvoiceCalculationService`
  - `Project::paymentTotal()` / `paidDppByProject()` (rupiah). Note:
    `Project::paymentProgressStatus()` is a binary flag, **not** money.
- Reuse these; never re-implement a calculation inline.
- Any change to what moves money (batch paid, second payment, settlement close,
  cash receipt allocation) is **CRITICAL**.

## 7. Business logic rules

- **Never assume a business rule.** Only rely on what code, DB, tests or
  existing docs confirm. Unverified = "UNKNOWN / NEEDS CONFIRMATION".
- If a new requirement conflicts with current behavior: **STOP** and report
  current behavior, requested behavior, impact, affected files, data/migration
  implications. Wait for approval.
- Cash Advance vs other CR types is decided by `RequestType` **name**
  (`BudgetRequest::CASH_ADVANCE_TYPE_NAMES`). Renaming a request type changes
  workflow behavior.

## 8. Authorization rules

- Layers: `auth` → `role:a,b` (`CheckRole`) and/or `menu:key` (`CheckMenu`,
  table `role_menu_items`) → in-controller `hasRole()` → record visibility
  (`findVisibleCr`, `scopeByRole`, Access Groups) → `FileAccess` for files.
- Route `role:` lists, `AccessRoles` constants, and Blade button conditions must
  agree. If you touch one, check the other two (a mismatch = visible button that
  403s, or hidden button for an entitled role).
- `superadmin` bypasses `menu:` checks. "View As" (`isViewingAs()`) lets
  superadmin preview other roles.
- Any authorization change is HIGH risk; authentication changes are CRITICAL.

## 9. UI/UX rules

Direction: corporate, dense-but-readable, dark sidebar/topbar ("chrome"),
light content, no decoration for its own sake.

- Use existing tokens in `public/css/tesys-ui-tokens.css` and components in
  `tesys-ui-components.css`; no new colors/hex values inline.
- Reuse Blade components: `x-status` (status chip via `WorkflowStatus`),
  `x-workflow-stepper`, `x-workflow-flow`, `x-drawer`, `x-empty`, `x-skeleton`,
  `x-stat-cards`.
- Reuse JS helpers: `tesys-column-filter.js`, `tesys-row-actions.js`,
  `tesys-toolbar-actions.js`, `tesys-export-filtered.js`, `tesys-searchable-select.js`.
- Index pages follow the Budget Plan pattern (modal sizing, tabs, filters,
  sort). See `docs/architecture.md#ui`.
- Avoid gradients, oversized cards, animations, extra whitespace.
- Add/modify sidebar entries only via `config/navigation.php`.

## 10. Git workflow

Target flow: `feature/*` → `develop` → review/UAT → `main` → production.

- Never develop directly on `main`. Create `feature/<short-name>` from the
  current integration branch.
- Do not switch branches, commit, push, merge or rebase unless the user asks.
- Commit messages follow the existing style: `feat: …`, `fix: …`,
  `chore(scope): …`, Indonesian descriptions, release tags like `v2.5.3`.
- Current state and gaps: `docs/git-workflow.md`.

## 11. Testing requirements

- Feature tests in `tests/Feature`, helpers in `tests/Support/MakesTesysData.php`.
- Any change to workflow, access, money, numbering or file access needs a
  feature test (follow `CostRequestWorkflowTest`, `CostRequestAccessTest`,
  `BudgetConsumptionTest`, `SecureFileAccessTest`).
- Run: `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan test --filter=<Name>`
  (requires MySQL `tesys_test`).
- Report test results honestly, including failures and skipped runs.

## 12. Code quality requirements

- Match surrounding style, naming, comment density and language.
- `laravel/pint` is available; only format files you changed.
- No dead code, no speculative abstractions, no unrelated reformatting.
- Avoid N+1: eager-load relations used in loops (existing code caches per
  instance — keep that).

## 13. Refactoring rules — Progressive Improvement

1. Preserve existing behavior.
2. Make the smallest required change.
3. Improve only code directly related to the change.
4. Add tests.
5. Document the change.
6. Leave unrelated legacy code untouched.

Do not turn a feature request into a refactor. Unrelated findings are reported
as **"Potential future improvement"**, not implemented.

## 14. Migration rules

- Additive only by default (new table / nullable column / index).
- Never edit or delete an existing migration that has run. New change = new
  migration file with a unique timestamp (two duplicate timestamps already
  exist — don't add more).
- Data-changing migrations (UPDATE/backfill/enum change/type change/drop) are
  HIGH; anything that can lose data is CRITICAL. They must log what changed
  and have a working `down()` where feasible.
- Check `Schema::hasTable/hasColumn` guards like existing migrations do.
- Never run migrations against production; deployment is a human step.

## 15. Security requirements

- Attachments go to disk `secure` (`storage/app/private`) and are served only
  via `SecureFileController` + `App\Services\FileAccess`. Never use the
  `public` disk for business files; never `storage:link`.
- Validate uploads (`mimes`, `max`) like existing code.
- Public export API (`/api/public/budget-request*`) is token-based, throttled.
  Do not add unauthenticated endpoints.
- Never log request bodies (they contain amounts and passwords).
- Keep `SecurityHeaders` and `EnsureUserActive` middleware intact.

## 16. Performance considerations

- Sessions/cache/queue are on MySQL; shared hosting connection limits caused
  production 500s before. Avoid extra queries per request and long locks.
- Large lists are capped (`Concerns\CapsLargeLists`, 1,000 rows). CR index
  tabs are lazy-loaded. Prefer server-side filtering and indexed columns.
- Notifications are queued (`ShouldQueue`); a worker must run (see
  `docs/DEPLOYMENT.md`).

## 17. Documentation requirements

- Update `docs/` when behavior, workflow, roles or schema change.
- User-visible changes: add entry to `CHANGELOG.md` and `config/changelog.php`
  when preparing a release (only if the user asks for a release).
- Mark anything unverified as "Needs confirmation."

## 18. Definition of Done

A change is not done until each applicable item is checked:

- [ ] Business requirement understood
- [ ] Existing implementation inspected
- [ ] Impact analysis completed
- [ ] Database changes reviewed
- [ ] Validation implemented
- [ ] Authorization implemented (route + controller + UI agree)
- [ ] Business logic implemented
- [ ] UI implemented
- [ ] Error states handled
- [ ] Empty states handled
- [ ] Existing workflow preserved
- [ ] Tests added/updated
- [ ] Relevant tests passed
- [ ] Security implications reviewed
- [ ] Performance implications reviewed
- [ ] Regression risks reviewed
- [ ] Git diff reviewed
- [ ] Documentation updated
- [ ] UAT completed where applicable

## 19. Standard workflow for every request

1. **Understand** the request; restate it if ambiguous.
2. **Explore** the existing implementation (routes → controller → model → view → tests).
3. **Identify dependencies** (other modules, reports, Todo List, exports, notifications).
4. **Plan** the implementation.
5. **Identify risks** and classify (section 20).
6. **Wait for approval** when HIGH or CRITICAL.
7. **Implement the smallest safe change.**
8. **Run tests.**
9. **Self-review** the diff.
10. **Report changed files** (always list every new/changed file — the user uploads manually).
11. **Report risks.**
12. **Update documentation.**
13. **Prepare for human review.**

Do not expand scope automatically.

## 20. Risk classification & handling uncertainty

| Level    | Examples | Rule |
|----------|----------|------|
| LOW      | Text changes, isolated UI/visual changes | Proceed |
| MEDIUM   | New CRUD, filters, reports, non-critical additive DB columns | Proceed with plan + tests |
| HIGH     | Financial calculation, approval workflow, authorization, DB relationship changes, migrations affecting existing data | **STOP before implementing**, explain impact, get explicit approval |
| CRITICAL | Production data modification, payment logic, authentication, destructive migration, deletion of financial history, deployment changes | **STOP**, explain impact, get explicit approval |

Handling uncertainty:
- If a rule can't be confirmed from code/DB/tests/docs → write
  "UNKNOWN / NEEDS CONFIRMATION" and ask.
- If code comments contradict code, **the code is the current behavior**;
  report the contradiction, don't "fix" it silently.
- Never fabricate documentation, statuses, roles or flows.

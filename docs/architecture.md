# Architecture overview

Verified from the codebase on 2026-10-02 (commit `70dfa9e` + uncommitted work).

## Stack

Laravel 13 (PHP 8.3), MySQL 8, Blade + Bootstrap 4.6 + jQuery/DataTables, no
asset build step (assets served directly from `public/css`, `public/js`,
`public/plugins`, `public/dist`). Session, cache and queue drivers = `database`.

## Request path

```
routes/web.php (single file, ~1.2k lines)
  └─ middleware: auth → role:<list> (CheckRole) / menu:<key> (CheckMenu)
       └─ Controller (fat; business rules + validation inline)
            ├─ Concerns/*        (split of large controllers)
            ├─ Models            (status constants, transitions, money helpers, scopes)
            ├─ app/Support/*     (single sources of truth: roles, status display, numbering…)
            ├─ app/Services/*    (notifications, file access, closing, cash flow, invoice calc)
            └─ Blade views       (resources/views/<module>/…)
```

Global web middleware: `SecurityHeaders`, `EnsureUserActive` (`bootstrap/app.php`).

## Directory map

| Path | Contents |
|------|----------|
| `app/Http/Controllers` | 42 controllers, one per module. Largest: `BudgetRequestController` (3.2k), `FinanceSummaryController`, `ProjectController` (2k), `BudgetPlanController`, `DailyTransactionController`, `TodoListController`, `InvoiceCashReceiptController` |
| `app/Http/Controllers/Concerns` | `TransitionsStatus` (locked status change), `HandlesCrSettlement`, `BuildsCrWorklist`, `ScopesBudgetPlanVisibility`, `ScopesInvoiceVisibility`, `CapsLargeLists` |
| `app/Http/Middleware` | `CheckRole`, `CheckMenu`, `EnsureUserActive`, `SecurityHeaders` |
| `app/Models` | 74 Eloquent models (see `docs/database.md`) |
| `app/Services` | `NotifyService`, `RoleDirectory`, `FileAccess`, `ClosingService`, `CashFlowService`, `InvoiceCalculationService`, `TodoService` |
| `app/Support` | `AccessRoles`, `WorkflowStatus`, `BudgetConsumption`, `DocumentSequence`, `InvoiceTimeline`, `Navigation`, `AssetVersion`, `Terbilang` |
| `app/Notifications` | one queued notification + a WhatsApp (Fonnte) channel |
| `app/Console/Commands` | `forecast:auto-close`, `budget-plan:auto-closing`, `queue:health`, `CheckUserRoles`, `MoveLegacyAttachments`, `ResyncProjectPoValue` |
| `app/Policies`, `app/Http/Requests`, `app/Jobs`, `app/Events`, `app/Listeners` | **Do not exist** |
| `config/navigation.php` | sidebar + role menu-access definition (via `App\Support\Navigation`) |
| `config/changelog.php`, `config/panduan.php` | in-app What's New + user guide |
| `database/migrations` | 190 migrations (May → Oct 2026) |
| `database/seeders` | `RoleSeeder`, `MenuItemSeeder`, many `Dummy*Seeder` (test/demo data) |
| `database/factories` | only `UserFactory` |
| `tests/Feature` | 21 test files (incl. `ExampleTest`); `tests/Support/MakesTesysData.php` builds fixtures |
| `resources/views/components` | `status`, `workflow-stepper`, `workflow-flow`, `drawer`, `empty`, `skeleton`, `stat-cards` |
| `resources/views/layouts` | `app.blade.php` (+ `partials/`), `error.blade.php` |
| `storage/app/private` | disk `secure`: all business attachments |

## Architectural patterns actually used

- Status workflows are **string status columns** + model `STATUSES` constant +
  controller action per transition + `*_logs` table for history.
- Concurrency: only Cost Request transitions use `TransitionsStatus` (row lock).
  Other modules check status then update without a lock.
- Authorization: route middleware + controller `hasRole()` + query visibility
  scopes (Access Groups). No Laravel Policies/Gates.
- Notifications: `NotifyService::send()` → queued in-app + WhatsApp.
- Numbering: `DocumentSequence` counters (`document_sequences` table).
- Presentation of statuses: `WorkflowStatus` (labels/tones/flows only).

## UI

- Shell: `layouts/app.blade.php`; dark "chrome" sidebar + topbar, light
  content (`tesys-ui-tokens.css` `--chrome`, `--sidebar-*`; dark-mode tokens
  also defined). App-shell launcher at `/app/{app}` groups menus per "app".
- CSS load order (as referenced in `resources/views/layouts`): `tesys-ui` →
  `compat` → `base` → `tokens` → `components` → `compat` → `pages` → `shell` →
  `motion` → `luxe` → `mobile`. (`tesys-ui.css` is referenced more than once
  across layout files; `tesys-ui-chrome.css` is not referenced directly —
  whether it is `@import`ed: Needs confirmation.)
- JS helpers: column filters, row action menus, toolbar actions, filtered
  export, searchable select, drawer, timeline, workflow stepper, command
  palette (`tesys-command.js`), session keep-alive.
- Tables: DataTables with per-column filters; lists capped at 1,000 rows;
  CR index uses lazy-loaded tabs.
- Detail views: modals/drawers fed by `*/detail-json` endpoints.
- Empty/loading: `x-empty`, `x-skeleton`. Status: `x-status`.
- Reference pattern for index pages: Budget Plan (modal sizing, sidebar, tabs,
  NOB colors, sort, filters).

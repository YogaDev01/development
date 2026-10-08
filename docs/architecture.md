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
| `app/Console/Commands` | `forecast:auto-close`, `budget-plan:auto-closing`, `todo:remind`, `queue:health`, `CheckUserRoles`, `MoveLegacyAttachments`, `ResyncProjectPoValue` |
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
- Accent color is customizable per-device via Profil > Settings. Stored in
  `localStorage['tesys-accent']` and injected as a `<style>` at the end of
  `<head>` (`window.TesysAccent.apply`), overriding the `--accent*` tokens **and
  the dark chrome** (`--chrome*`, `--topbar*`, `--chrome-active`) with
  `color-mix` derivatives for both light and dark — so the sidebar/topbar follow
  the chosen accent too. Default = Burgundy `#92232b`; when unset, no rule is
  injected. Same per-device pattern as the dark/light toggle
  (`localStorage['tesys-theme']`).

### Profil Saya (`ProfileController` + `resources/views/profile/`)

Tabs: **Informasi** (read-only account facts, activity/data summary stat cards,
app changelog from `config/changelog.php`), **Access**, **Project Log**,
**Request Log**, **Activity Log**, **Team** (Organization Chart per level from
`Team::orgChart()` for the teams the user is an ACTIVE member of), and
**Settings** (profile name/phone, **change-password modal verified by an email
code**, session info, appearance = theme + accent, notification preferences, and
View As for real superadmin). Partials: `_info`, `_team`, `_settings`,
`_activity_log`.

Avatar + **cover photo**: both stored on disk `secure` (`avatars/`, `covers/`),
served via `/storage/{path}` (`SecureFileController`, `FileAccess` allows both),
`User::avatar_url` / `cover_url`. Each has a photo menu (Lihat / Upload / Hapus)
and a shared lightbox. Routes: `profile.avatar[.delete]`, `profile.cover[.delete]`.

The app's landing page is **Profil Saya**, not the dashboard: route `/` (inside
auth) sets `session('tesys.app')` to the user's App Default and renders
`ProfileController@show`. Each user picks their **App Default** (which app's
sidebar/menu is active at login) in Settings → saved to `users.default_app`
(nullable; falls back to `config('apps.default')` via `User::defaultApp()`).
`AuthController::login()` also sets the active app from `defaultApp()`.

Change password flow (no "current password"): `profile.password.token` emails a
6-digit code (hashed in cache 15 min, rate-limited 5/10 min) → `profile.password`
verifies the code (max 5 tries) then updates. Requires SMTP configured in `.env`
(`MAIL_MAILER=smtp`). `ProfileController::update()` now only handles name/phone.

**Lupa Sandi (guest)** on the login page mirrors this for logged-out users
(`AuthController::forgotVerify/forgotSendCode/forgotReset`, routes `forgot.*` in
the `guest` group, throttled): enter username+email → if they match an ACTIVE
user the "Kirim Kode" button enables → 6-digit code emailed (cache key
`auth:forgot-token:{id}`, hashed, 15 min) → code + new password resets it. The
username+email match is a deliberate enumeration oracle (per request); throttle
+ per-IP rate limits bound abuse.

Todo List is expensive, so the Informasi tab shows it **lazy-loaded**: a brief
per-user summary via `profile.todo-summary` (JSON, reuses
`TodoListController::actionQueues()`) grouped into **3 tables** — Cost Request,
Budgeting, Purchase Order (columns Tahap Action / Qty, subtotal footer; mapping
in `ProfileController::TODO_MODULE_MAP`) — and a **Summary Task** button (shown
to every logged-in user) that fetches the aggregate matrix via
`profile.todo-matrix` → `TodoListController::summaryPartial()` (`_summary_task_list`)
into a modal on first open. The matrix is auth-only (counts only, no money);
the Todo List *page* stays gated by `menu:todo-list`. Neither runs during the
initial profile render.

Notification preferences are stored in `users.notification_prefs` (nullable
JSON) and read by `User::wantsNotification($category, $channel)` which is
**safe-by-default** (null or missing keys ⇒ on). `AppNotification::via()` drops
the `database` and/or Fonnte channel per that preference. A global WhatsApp
off-switch (`wa_enabled`) silences all Fonnte while leaving the in-app bell
intact.

The only user-configurable notification (Profil → Settings → Preferensi
Notifikasi) is the **To-Do List reminder** (`notification_prefs.todo_reminder`,
category `todo`, defaults in `User::TODO_REMINDER_DEFAULTS` — in-app ON, WhatsApp
opt-in, fixed hours 08/13/16). The scheduled command `todo:remind` runs hourly
(`routes/console.php`); for each ACTIVE user whose schedule matches the current
hour (`User::todoReminderDueNow()` — mode `fixed` = listed hours, mode `interval`
= every N hours) and whose `TodoService::build()` is non-empty, it sends one
reminder. Requires the host `schedule:run` cron (same as `forecast:auto-close`).
The legacy `workflow`/`milestone`/`mention` categories (`User::NOTIFICATION_CATEGORIES`)
still exist for `wantsNotification()` back-compat but are no longer surfaced in
the UI; `general` is never muted.

### Assignment (`AssignmentController` + `resources/views/assignment/`)

Free-form task assignment between users — the first **persistent** data source on
the Todo List page (every other section is a live query over document status).
Business rule: **any logged-in user may assign to any other active user** (no
`menu:`/`role:` gate on the routes; per-action authorization is in-controller).

- Tables (additive migrations `2026_10_08_000001..3`): `assignments`
  (`assigner_id`, `assignee_id`, `due_date`, `priority` LOW/MEDIUM/HIGH, `status`
  OPEN/DONE/CANCELLED), `assignment_attachments` (disk `secure`, served via
  `SecureFileController`; `FileAccess::allowsAssignment()` = assigner/assignee/
  superadmin only), `assignment_logs` (immutable timeline, `AssignmentLog::record()`,
  pattern mirrors `RequestStatusLog`). **No SoftDeletes** — not a financial record.
- Actions: assigner (or superadmin) may **update/cancel/reopen**; assignee,
  assigner or superadmin may **mark done**. Each action writes an `assignment_logs`
  row and notifies the counterpart via `NotifyService` (category `assignment`).
- Surfaces: dedicated page `/assignment` and a "Tugas / Assignment" section on
  `/todo-list` — both render the shared `assignment/_board` partial. The Todo List
  section is injected outside the `$hasAnySection` branch and the worklist logic in
  `TodoListController::buildWorklistData()` is untouched; `index()` loads the two
  lists separately via `AssignmentController::listsFor()`. Users without
  `menu:todo-list` still reach tasks through `/assignment` (sidebar entry has no gate).

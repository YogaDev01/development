---
name: tesys-laravel-development
description: Use when changing TESYS routes, controllers, models, middleware, services, validation, notifications or API endpoints, and for focused performance or code-quality work on that code. Laravel 13 / PHP 8.3, fat-controller architecture.
---

# TESYS backend (Laravel 13, PHP 8.3)

Follow the real pattern, not textbook Laravel: fat controllers + rich models; no Policies, FormRequests, Events/Jobs. Validation is inline `$request->validate()`. Do not add a new layer.

## Before editing
1. Trace routes (`routes/web.php`) → middleware → controller → model → view → existing tests.
2. Find the single source of truth for what you touch (CLAUDE.md §6: `BudgetRequest::refundAmount()`, `BudgetConsumption`, `InvoiceCalculationService`, `Project::paymentTotal()`, `DocumentSequence`, `AccessRoles`, `WorkflowStatus`). Reuse; never re-implement inline.

## Rules
- Money = integer rupiah. Status values must match the model `STATUSES` constant. Adding a status or changing a transition = HIGH risk (stop and ask).
- Status change: check whether the path uses `Concerns\TransitionsStatus`; do not change locking behavior silently (HIGH). Write `RequestStatusLog` via `addLog()` where neighbors do.
- Numbering via `DocumentSequence`, not `max()+1`.
- Authorization: update route `role:`/`menu:`, in-controller checks and Blade together.
- Notifications via `NotifyService` (queued).
- Match language/comment density of the surrounding code. `pint` only on files you changed, and not on very large controllers.

## Performance / quality (only when asked or directly related)
- Measure first: identify the slow query/view (eager loads, repeated queries in loops, uncapped lists — `CapsLargeLists`, 1,000 rows). Shared hosting has MySQL connection limits; avoid extra queries per request.
- Prefer targeted fixes over refactors; unrelated findings go to "Potential future improvement".

## Verification
`php -l` on changed files (PHP path per CLAUDE.md §4.3 step 2). Tests: add/adjust feature tests for workflow/access/money/numbering/file access, but run them only under CLAUDE.md §4.3 and on user request.

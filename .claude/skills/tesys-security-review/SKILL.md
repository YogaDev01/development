---
name: tesys-security-review
description: Use when a TESYS change touches authentication, authorization (role/menu/visibility), input validation, file uploads/downloads, tokens or sensitive data, or when reviewing such changes.
---

# TESYS security

Authentication changes are CRITICAL; authorization changes are HIGH (CLAUDE.md §8, §20) → get approval before implementing.

## Authorization checklist (layers, in order)
`auth` → route `role:`/`menu:` (`CheckRole`, `CheckMenu`, `role_menu_items`) → in-controller `hasRole()` → record visibility (`findVisibleCr`, `scopeByRole`, Access Groups) → `FileAccess` for files.
- Route `role:` lists, `App\Support\AccessRoles` and Blade button conditions must agree. Touch one → check the other two.
- `superadmin` bypasses `menu:`; "View As" (`isViewingAs()`) previews roles — make sure it never grants write power.
- Check IDOR: every `{id}` endpoint must go through a visibility scope, not a bare `find()`.

## Input and files
- Inline `$request->validate()` with `mimes`/`max` for uploads; business files go to disk `secure` (`storage/app/private`), served only via `SecureFileController` + `FileAccess`. Never the `public` disk, never `storage:link`.
- Public export API (`/api/public/budget-request*`) is token-based and throttled; add no unauthenticated endpoints.
- Output escaping: use `{{ }}` in Blade; justify any `{!! !!}`.
- Never log request bodies, passwords or tokens (`EXPORT_API_TOKEN`, `FONNTE_TOKEN`); never read or print `.env`.
- Keep `SecurityHeaders` and `EnsureUserActive` middleware intact.

## Verification
Show the route → middleware → controller → view agreement with file:line for each changed endpoint. Add/adjust access tests (allowed and denied roles). Reference `docs/security.md`. State what was not verified at runtime.

---
name: tesys-frontend-ui
description: Use when editing TESYS Blade views, CSS, JS, tables, modals, forms, dashboards or mobile/responsive layout. Covers the existing design tokens, Blade components, JS helpers and the Budget Plan index pattern. Not for backend logic.
---

# TESYS frontend, UI/UX and responsive

Stack: Blade + Bootstrap 4.6 + jQuery + DataTables. **No Vite/build step**; assets are served from `public/css`, `public/js`. UI text and comments in Bahasa Indonesia.

## Before editing
1. Read the nearest similar view (index pages: Budget Plan is the reference pattern — modal sizing, tabs, filters, sort, NOB colors).
2. Read `docs/architecture.md` section "UI" and `docs/ui/datatable-inventory.md` if touching tables.

## Rules
- Colors/spacing only from `public/css/tesys-ui-tokens.css` tokens; no new inline hex values. Dark mode and per-device accent exist — use tokens so both keep working.
- Reuse components in `resources/views/components` (`x-status`, `x-workflow-stepper`, `x-workflow-flow`, `x-drawer`, `x-empty`, `x-skeleton`, `x-stat-cards`) and helpers in `public/js` (`tesys-column-filter`, `tesys-row-actions`, `tesys-toolbar-actions`, `tesys-export-filtered`, `tesys-searchable-select`, `tesys-table`).
- Tables: wrap in `.table-responsive` or follow the `tesys-table.js` card mode (≤640px); use `data-ts-card="off"` only where already justified. Money cells right-aligned with `.ts-num`/`.text-right`.
- Mobile breakpoints: 900 / 768 / 640 / 480; new rules use 640 and `max-width`. No fixed inline widths that exceed a 320px viewport.
- Dropdowns inside tables/overflow containers need `data-display="static"` or `data-boundary="viewport"`.
- Icon-only buttons need `title` (gets `aria-label` via `tesys-icon-buttons.js`); labels linked to controls.
- Sidebar entries only via `config/navigation.php`. Button visibility must agree with route `role:` and `AccessRoles` (see `tesys-security-review`).
- No gradients, oversized cards, extra animation.
- Add empty and loading states (`x-empty`, `x-skeleton`).

## Verification
- Syntax: re-read Blade for balanced directives; JS syntax check with `node --check` if node exists.
- Visual/responsive checks need a running app and browser; if not done, report "NOT VERIFIED in browser". Do not start servers via Bash (use the preview tooling if the user wants it).
- After PHP/Blade changes, remind the user to run `php artisan optimize:clear` on the server.

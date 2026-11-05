# Architecture overview

Verified from the codebase on 2026-10-02 (commit `70dfa9e` + uncommitted work).

## Stack

Laravel 13 (PHP 8.3), MySQL 8, Blade + Bootstrap 4.6 + jQuery/DataTables, no
Vite/bundler/Node build (assets served directly from `public/css`, `public/js`,
`public/plugins`, `public/dist`). The only generated asset is the Tailwind stylesheet
`public/css/tesys-tw.css`, built on a developer machine with a checksum-verified
standalone binary (`scripts/build-tailwind.ps1`) and committed; production builds
nothing. Session, cache and queue drivers = `database`.

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
| `app/Services` | `NotifyService`, `RoleDirectory`, `FileAccess`, `ClosingService`, `CashFlowService`, `InvoiceCalculationService`, `TodoService`, `TroubleTicketWorkflow` |
| `app/Support` | `AccessRoles`, `WorkflowStatus`, `BudgetConsumption`, `DocumentSequence`, `InvoiceTimeline`, `Navigation`, `AssetVersion`, `Terbilang` |
| `app/Notifications` | one queued notification + a WhatsApp (Fonnte) channel |
| `app/Console/Commands` | `forecast:auto-close`, `todo:remind`, `queue:health`, `CheckUserRoles`, `MoveLegacyAttachments`, `ResyncProjectPoValue` |
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
- Refine layer: `public/css/tesys-ui-refine.css` is loaded **last** (after
  `datatable`) and owns the typography scale (`--fs-*`, `--fw-*`, `--lh-*`),
  control heights (`--ctl-h` 34px / `--ctl-h-sm` 30px), card/cell padding, and
  the shared KPI shape (`.ts-kpi`, `.col-kpi-card`, `.todo-kpi-card`). It adds
  no colors. Earlier layers still define the same properties several times
  (many with `!important`), so refine repeats `!important` only where the
  earlier rule has it. Sizes at ≤640px are intentionally left to
  `tesys-ui-mobile.css`. Roll back by removing its `<link>` in
  `layouts/app.blade.php`.
  - Section 14 of the refine layer ("Cost Request") is scoped by `.cr-head` /
    `.cr-pane` / `.cr-card` / `.cr-toolbar` (classes added in
    `budget-request/index|_toolbar|_table|_rows|_rows_db.blade.php`), so PO and
    Budget Plan, which share `x-workflow-stepper` and `.custom-toolbar`, are
    unchanged. It restyles the category tabs, the workflow strip (one panel of
    count-over-label cells, no circles/connectors; red = rejected, amber =
    pending, green = done, rest neutral), the single card (toolbar + table),
    row typography (`.cr-desc-main`, `.cr-meta`), NOB markers (left edge bar,
    not whole-row color) and row actions (soft-burgundy primary, ghost
    neutral/more, outlined danger). Presentation only — no behavior change.
- Tailwind (F2 foundation + F3 pilot; **loaded only by the Feedback views** `feedback/index|create|show|inbox` and the Cost Request index `budget-request/index` (M1, see `docs/ui/tailwind-budget-request.md`) via `@push('styles')` + `@aset`, never by the layout or other pages): production input
  `resources/tailwind/tesys-tw.css` builds to `public/css/tesys-tw.css` (standalone
  binary, prefix `tw:`, no preflight, tokens mapped via `var(--…)`); the test input
  `tesys-tw.uji.css` (safelist) builds only to gitignored `tools/tailwind/uji/` and is
  never deployed. Components: `resources/views/components/tw/`. Rules, build, evidence:
  `docs/ui/tailwind.md`, `docs/ui/tailwind-components.md`. Every change to a `tw:` class
  requires `build-tailwind.ps1 -Cek` (exit 0) and committing the rebuilt file. CSS is
  cached 1 year (`public/.htaccess`), so bump the asset version when it starts being
  loaded. `tools/`, `scripts/`, `resources/tailwind/` and `docs/ui/tailwind-evidence/`
  are not uploaded to the server.
- Font: Inter variable, self-hosted in `public/fonts/inter/` (SIL OFL 1.1,
  `LICENSE.txt` beside it; latin subset ≈ 48 KB), declared in the refine layer
  and preloaded in the layout. Plus Jakarta Sans and the Google Fonts `<link>`
  were removed from the app layout (`--font-display` now resolves to Inter);
  `auth/login.blade.php` keeps its own font link. Money/code cells use
  `font-variant-numeric: tabular-nums`.
- JS helpers: column filters, row action menus, toolbar actions, filtered
  export, searchable select, drawer, timeline, workflow stepper, command
  palette (`tesys-command.js`), session keep-alive.
- Tables: DataTables with per-column filters; lists capped at 1,000 rows;
  CR index uses lazy-loaded tabs.
- Listing DataTable & mode card mobile: `public/js/tesys-table.js` +
  `public/css/tesys-ui-datatable.css` (dimuat setelah `components` dan `mobile`;
  file terpisah agar mudah ditinjau/ditarik, boleh dilipat atas keputusan pemilik).
  - Struktur listing (desktop & mobile) = margin halaman → tabel: `.card` di
    `.main > .content` yang berisi `.dataTables_wrapper` dilepas bingkainya
    (border/latar/padding card-body). Pengecualian: kartu di modal/drawer,
    `.ts-keep-card`, dan kartu berisi `.col-pic-table` (pivot Finance Summary).
  - Mode card ≤640px otomatis untuk tabel layak (thead satu baris tanpa
    colspan/rowspan > 1, tanpa baris grup); semua listing ikut, termasuk CR
    (`#tblCA/#tblCP/#tblDB`), Invoice, Project. Dikecualikan: pivot/grup (Finance
    Summary) dan `data-ts-card="off"` (Trouble Ticket, punya card sendiri).
    Label mengikuti kolom terlihat; pemicu `init/draw/column-visibility/
    responsive-display.dt` dan `shown.bs.tab`; kolom sticky dinetralkan; status di
    baris judul; tfoot jadi kartu ringkasan di atas. `<table>` tidak pernah
    `display:none` (pencarian topbar `tesys-shell.js`); teks sel tidak diubah
    (`tesys-money-sort.js`).
  - Spesifisitas: aturan card diawali `body.tesys-ui table.ts-card-table:not(#ts-card)`
    (`:not(#id)` = spesifisitas ID tanpa menambah `!important` berserakan).
  - Atribut opsional: `data-ts-card="off"`, `data-short`, `data-card="title|hide|show"`.
  - Batasan: sel `tbody` ber-`colspan` di tengah baris tidak didukung.
  - Bottom tab (Fase 3) ditahan; lihat `docs/ui/datatable-inventory.md`.
- Detail views: modals/drawers fed by `*/detail-json` endpoints.
- Accessibility: fokus keyboard tab & dropdown-item diatur di
  `tesys-ui-motion.css`; menu sidebar (`.sidebar .nav-item`, `.nav-group-toggle`)
  memakai cincin warna `--chrome-text` (latar gelap); `.custom-select` dan
  centang kustom memakai `box-shadow` `--accent-ring`. Fokus `.page-link`
  DataTables dimiliki `tesys-ui-base.css` (box-shadow `--accent-ring`, tanpa
  outline). Menu aktif diberi `aria-current="page"` (`nav.blade.php`).
  Kontrol ikon-saja (sel tabel, topbar, toolbar, modal) mendapat `aria-label`
  dari `title`/`data-original-title` lewat `tesys-icon-buttons.js`; `<label>`
  tanpa `for` dihubungkan ke satu-satunya kontrol di `.form-group` oleh
  `tesys-ui.js` (juga label hasil `.clone()` yang `for`-nya menunjuk kontrol
  di luar `.form-group` yang sama; `id` hanya diganti bila terduplikasi).
  Angka rata kanan memakai `tabular-nums` (`tesys-ui-pages.css`): sel/th
  `.text-right`, `.text-end`, `.num`, `.ts-num` (td saja), dan style inline
  `text-align:right` / `text-align: right`. Batas: spasi ganda di style inline
  dan kolom uang yang rata kanan lewat kelas halaman sendiri tidak tertangkap
  (pasang `.ts-num`).
  Modal panjang menggulir di badannya pada layar pendek/sempit
  (`tesys-ui-compat.css`); rantai flex hanya mencakup satu pembungkus
  (`<form>` atau `<div>`) langsung di bawah `.modal-content`, pembungkus yang
  lebih dalam belum diaudit per modal.
- Mobile (`tesys-ui-mobile.css`, dimuat terakhir): breakpoint 900 (sidebar
  jadi laci) / 768 (modal detail ditumpuk) / **640 (handphone)** / 480. Aturan
  baru memakai 640px dan dibatasi `max-width`; target sentuh 36px hanya di
  `@media (pointer: coarse)` agar desktop tidak berubah. Bagian bawah
  DataTables (info/length/pagination) ditengahkan di ≤640px untuk tiga bentuk
  `dom` yang dipakai (`.row` + kolom `d-flex`, dan `d-flex justify-content-between`
  tanpa `.row`); utilitas Bootstrap 4 itu `!important`, jadi override-nya juga.
  Di tabel Cost Request hanya kolom Action yang beku di ≤640px (Code ikut
  tergulir) — keputusan ini Needs confirmation dari pengguna. Verifikasi
  browser per viewport: belum dilakukan.
  - Inventaris audit mobile (ORC-20261017-002; semua berstatus COMPLETED di
    kode, **NOT VERIFIED** di browser):

    | Berkas:baris | Viewport | Severity | Sumber | Temuan → perbaikan |
    |---|---|---|---|---|
    | `tesys-ui-shell.css:643-655` | ≤900 | MAJOR | shared | laci `height:100vh` terpotong bilah alamat → `100dvh` (mobile §17) |
    | `tesys-ui-shell.css:134,1198` | ≤380 | MINOR | shared | `.crumb` tanpa ellipsis, topbar sesak di 320px → ellipsis, gap/padding dirapatkan (§17) |
    | `layouts/app.blade.php:424-443` | ≤900 | — | shared | struktur topbar (toggle, judul, aksi) sudah wrap-safe; hanya CSS yang disesuaikan |
    | `cash-receipt/create:79`, `customer/show:123`, `delivery-note/show:115` | 320 | MINOR | halaman | input `width:220-260px` inline → `max-width:100%` global (§18) |
    | `budget-request/_toolbar:31,50`, `todo-list/_summary_task_list:51,66` | 320 | MAJOR | halaman | `min-width` inline 180-420px melebihi layar → `max-width: calc(100vw - 16px)` (§18) |
    | view non-index (payroll-run, assignment, todo-list, trouble-ticket/settings, daily-transaction/show, groups/bank/budget-plan show) | semua | — | halaman | tabel sudah dalam `.table-responsive`; **tidak ada temuan** |
    | `tesys-ui-motion.css:212` | — | MINOR | shared | `prefers-reduced-motion` sudah mencakup `animation`/`transition`/pseudo-element; ditambah `html{scroll-behavior:auto}` |
- **Bar navigasi bawah (layar sempit)** — `layouts/partials/bottom-nav.blade.php`
  + `tesys-ui-mobile.css` §19 (**NOT VERIFIED** di browser):
  - Tampil ≤767.98px (portrait, atau tinggi ≥481px); landscape pendek
    (`max-height:480px` + landscape) bar disembunyikan. Desktop (>900px) tak berubah.
  - Isi tetap 5 item, urutan: **Logo Terex · Home · Todo List · Tabs · Menu**
    (tidak lagi dari config; kunci config `bottom_nav`, kunci `short`, dan
    `Navigation::bottomKeys()` sudah dihapus — `@section('bottom_nav')` di
    Blade tetap dipakai, lihat di bawah).
    - Logo Terex: `dist/img/ts_icon.png` (sama dengan sidebar); dropup Bootstrap
      (`data-toggle="dropdown" data-display="static"`) berisi `$apps` dari layout
      (`config/apps.php`, tujuan `home` — sama dengan `#appSwitch` sidebar, yang
      tidak menyaring akses). Tertutup oleh klik di luar / Escape (Bootstrap).
    - Home: `$currentAppConfig['home']`, aktif bila URL = home (tanpa gerbang
      menu, seperti Dashboard di sidebar).
    - Todo List: hanya dirender bila `$can('todo-list')` (gerbang sidebar);
      titik `[data-todo-dot]` oleh `initTodoBadge()`.
    - Tabs: `initBottomNav()` (`tesys-shell.js`) membangun daftar saat klik dari
      strip `.nav-tabs/.nav-pills` tingkat halaman (kecuali di `.modal`,
      `.ts-drawer-root`, `.todo-drawer`, `.tab-pane`, `.card-header`, bar,
      sidebar) — hanya strip terlihat pertama dengan >=2 tab terlihat &
      tidak disabled. Memilih = klik elemen tab asli (lazy-load AJAX & handler
      halaman jalan; tab tautan = navigasi). Tab aktif = `.active`
      (highlight `--accent-tint` + teks `--accent-text`, tebal, `aria-current`;
      tanpa ikon centang — sama untuk dropup Logo/app aktif). Label lewat
      `textContent`; badge angka dipisah jadi hitungan (tetap ditampilkan).
      Tanpa tab: tombol redup (`aria-disabled`, tidak membuka apa pun);
      dievaluasi saat load dan saat klik.
    - Strip yang dipilih dropup diberi kelas `ts-tabs-hosted`; CSS
      menyembunyikannya (`display:none`, tetap di DOM) hanya saat bar tampil
      (`body:has(.ts-bnav)` di media bar), jadi tab tidak dobel. Hanya `ul`
      tab; filter/header satu wrapper (mis. `#crTabs`) tetap. `kumpulkan()`
      mencopot tanda sebelum mengukur keterlihatan (sinkron) agar strip yang
      disembunyikan sendiri tidak dianggap tak terlihat; strip tanpa jalan
      masuk lewat dropup (<2 tab) tidak ditandai. Bar mati (`bottom_nav=off`,
      768–900px, landscape pendek) → strip tampil seperti biasa.
    - Menu: `data-sidebar-toggle` (membuka laci; menutup dropup yang terbuka);
      `aria-expanded` disinkronkan dengan `body.sidebar-off`.
  - Warna bar disamakan dengan item sidebar (`--chrome`, `--chrome-text`, ikon
    `--chrome-dim`, hover putih beralfa, aktif `--chrome-active` + putih).
    Penyebab teks merah dulu: item bar berupa `<a>` dan `compat.css` mewarnai
    `body.tesys-ui a` dengan `--accent-text`; pemilih bar kini
    `body.tesys-ui .ts-bnav .ts-bnav-item` (kekhususan lebih tinggi).
  - Hamburger topbar (`#sidebarToggle`) disembunyikan (`display:none`, tetap di
    DOM) hanya saat bar tampil (`body:has(.ts-bnav)` di media bar); di
    768–900px dan landscape pendek tetap ada.
  - Laci sidebar punya tombol tutup (`.sidebar-close`, panah kiri, 44px) di
    kaki sidebar sisi kanan jam, `data-sidebar-toggle`; hanya ≤900px.
  - Garis tipis di bawah bar: html/body/`.shell`/`.main` semuanya `--bg` dan
    setinggi 100dvh (`tesys-ui-shell.css` juga memberi `html{background:
    var(--bg)}`). Dugaan dari CSS (belum direproduksi di browser): selisih
    sub-piksel antara 100dvh terbulatkan dan viewport nyata (zoom/emulasi,
    bilah alamat, area aman) menampakkan `--bg` di bawah bar gelap. Zoom 80%
    desktop (`--ts-zoom`) hanya ≥901px sehingga bukan penyebab di mobile.
    Perbaikan: selama bar ada (media bar), hanya `html` dan `.shell` dicat
    `--chrome`, sehingga celah di luar `.main` berwarna chrome. `.main` sengaja
    tetap `--bg` (bar adalah anak terakhir `.main`); jika `.main` dicat chrome,
    animasi `tesys-fade` pada `.content` (opacity 0 → 1) menampakkan latar gelap
    tiap halaman dimuat. `.content` dan topbar tidak diubah.
    Penyebab hanya dugaan dari CSS, belum direproduksi; perbaikan hanya menutup
    celah di luar `.main`. **NOT VERIFIED** di browser.
  - Halaman dapat mematikan bar dengan `@section('bottom_nav', 'off')`
    (dipakai `trouble-ticket/show`, yang punya bar aksi `.tt-mbar` sendiri).
  - Bar ada di alur `.main` (flex:none di bawah `.content`), bukan `fixed`;
    elemen fixed-bawah (`.ts-toast-stack`, `#prjCopyToast`, `.tt-fab`) digeser
    sebesar `--ts-bnav-h` lewat `body.tesys-ui:has(.ts-bnav)`.
  - Peta z-index: topbar 1020 < bar bawah 1025 < overlay laci 1040 < laci 1041
    < modal 1060 < toast 2000.
  - Titik Todo: `[data-todo-dot]` diubah oleh `initTodoBadge()` bersama
    `#navTodoBadge` sidebar (satu polling).
  - Tab halaman tidak dipindah ke bar kedua (dua bar bawah bertumpuk menyita
    tinggi layar): saat bar tampil strip yang dicantumkan dropup Tabs
    disembunyikan (lihat di atas); saat bar tidak tampil tab tetap di ATAS.
    Tab (`.nav-tabs` dan `.nav-pills`) ≤767.98px = satu baris gulir
    mendatar; `.nav-tabs .nav-link` tinggi ≥44px (hanya `padding-block`,
    padding horizontal tak berubah); tab aktif
    ditengahkan (`initPageTabs()`); aturan tambahan ini (tinggi 44px, nowrap)
    tidak menyentuh tab di `.modal`, `.ts-drawer-root`, `.todo-drawer`,
    `.tab-pane`, `.card-header` (aturan lama `.nav-tabs` ≤640px tetap
    berlaku ke semua tab). Kelas opt-in
    `.ts-tabs-sticky` membuat strip menempel di atas area gulir (dipasang di
    Finance Summary dan Cashflow; tab Cost Request tidak, karena berada dalam
    wrapper `d-flex` bersama filter). Saat bar tampil strip itu disembunyikan,
    jadi sticky hanya berlaku di landscape pendek (bar tidak tampil) — aturan
    dipertahankan untuk kasus itu.
  - Tinggi layar: ≤900px `body`, `.shell`, `.main` memakai `100dvh` (cadangan
    `100vh`) supaya bar tidak terdorong keluar layar oleh bilah alamat.
- Tom Select (`tesys-ui-compat.css`): border/ring ada di `.ts-control`, bukan
  wrapper (`.ts-wrapper.form-control` dikosongkan); `.is-invalid` diberi
  `border-color: var(--danger)` pada `.ts-control`. Tom Select hanya dipakai di
  toolbar accessgroup dan form customer (tidak di dalam modal), sehingga
  `dropdownParent` tidak diperlukan; bila nanti dipakai di modal, set
  `dropdownParent: 'body'`.
- Dropdown di tabel/kontainer ber-overflow memakai `data-display="static"` atau
  `data-boundary="viewport"` agar menu tidak terpotong. Inventaris 14
  `data-toggle="dropdown"` di `resources/views`: semua sudah memakai salah
  satunya (terakhir ditambah di `project-monitoring/show`). Dropdown baru wajib
  mengikuti.
- Pekerjaan lanjutan: aturan `.tsys-link` di `tesys-ui-base.css` dan
  `tesys-ui-chrome.css` tidak dipakai di view, belum dibersihkan.
  `.ts-kpi-label` (10.5px, `--text-dim`) belum diverifikasi di mode gelap.
- `tesys-ui-chrome.css`: tidak direferensikan di layout; belum dikonfirmasi
  pengguna apakah dipakai — tidak diubah/dihapus.
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
`AuthController::login()` also sets the active app from `defaultApp()` and
redirects to `User::defaultAppLanding()` — the app's `landing` (module home)
when it has one, else its `home`. Since 19 Oct 2026 an app whose `roles` list
matches the user **wins over** their saved `default_app`, so `teknisi` always
lands on Operations → Trouble Ticket (`/trouble-ticket`).

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

### Team & Struktur Organisasi (`TeamController` + `resources/views/team/`)

Blueprint: `docs/plans/2026-11-06-team-akses-organisasi.md` (K1–K10). Ringkas:
- **Akses data** = Access Group individu ∪ Access Group team ACTIVE (`User::accessibleProjectIds()` /
  `accessibleCostCenterIds()`); akses PO team diatur superadmin di form Team (bagian Akses PO,
  `_access_groups.blade.php`) atau halaman Access Group (tabel yang sama).
- **Daftar Team = 2 tab** (`team/index`): **Database** (`_database.blade.php`: pohon team kiri + panel detail kanan, filter server-side,
  tanpa pagination; `TeamController::buildTeamTree()`) dan
  **Organisasi** (`_organization.blade.php`, hash `#organisasi`): KPI (team aktif, orang unik,
  leader, multi-team), chart bar "Orang per Department" (satu seri, warna `--accent`, label
  langsung + `title` hover), lalu **bagan organisasi atas → bawah berbasis orang**
  (`TeamController::buildOrgChart()` → `leaderChart()` / `buildTeamViews()`) dengan dua mode:
  **Leader View** (partial rekursif `_org_card`, hanya orang yang memimpin team, tombol team
  `_org_team_links`) dan **Detail Member** (satu team terpilih, `_org_team` di `<template>`,
  disalin JS ke `#ocTeamView`; anggota per level, navigasi team atas/bawah). Kartu: foto bulat
  + label jabatan & nama. Penyambungan team (`orgAttachments()`): (1) leader team A yang juga anggota team B → team
  A di bawah kartunya di B; (2) bila tidak, Induk Team; (3) selain itu akar (leader beberapa
  team akar tampil sekali). Lingkaran diputus. Garis Leader View digambar JS sebagai SVG dari
  `data-up`; leader yang sama di beberapa tempat dihubungkan garis putus-putus + badge "Di N
  tempat". Bentang/ciut per kartu, zoom 40–140% (awal: pas lebar), pencarian (orang yang tidak
  tampil didaftar sebagai tombol ke team-nya), "Posisi Anda" (buka Detail Member), drawer profil (`x-drawer`, tanpa data kontak). Hanya tampilan, tidak memberi akses.
  Bagan menggulir mendatar di dalam bingkainya sendiri (bukan halaman). `GET team/structure` (lama) dialihkan ke `/team#organisasi`. Data dari
  `TeamController::organizationData()`. ≤640px: KPI 2 kolom, bar ditumpuk, kartu orang satu kolom.
- **Riwayat**: membership tidak pernah dihapus (keluar = INACTIVE); `team_logs` immutable; team
  hanya Aktif/Nonaktif. Log status dokumen membekukan `actor_org_context`; pelaku yang pernah
  menindak CR/PO tetap bisa membacanya (`read_only`).
- Verifikasi browser halaman Team/Struktur: **NOT VERIFIED** (migrasi belum dijalankan).

### Assignment (`AssignmentController` + `resources/views/assignment/`)

Free-form task assignment between users — the first **persistent** data source on
the Todo List page (every other section is a live query over document status).
Business rule: **any logged-in user may assign to any other active user** (no
`menu:`/`role:` gate on the routes; per-action authorization is in-controller).

- Tables: `assignments` (additive `2026_10_08_000001`, extended by additive
  `2026_10_09_000001` which adds `read_at`/`started_at` — `2026_10_08_000001`
  had already run before the status machine was redesigned, so per the
  migration rules it was never edited, only extended), `assignment_attachments`
  (disk `secure`, served via `SecureFileController`;
  `FileAccess::allowsAssignment()` = assigner/assignee/superadmin only),
  `assignment_logs` (immutable timeline **and comments** — `action='comment'`
  rows use `notes` as the comment body — `AssignmentLog::record()`, pattern
  mirrors `RequestStatusLog`). **No SoftDeletes** — not a financial record.
- Status machine (`App\Models\Assignment::STATUSES`/`OPEN_STATUSES`), one-way
  except `reopen`:
  `PENDING` (created) → `READ` (assignee opens the detail modal — the first
  `GET assignment/{id}/logs` by the assignee auto-transitions and logs
  `action=read`, see `AssignmentController::logs()`) → `ON_PROGRESS` (assignee
  only, via `POST assignment/{id}/start`) → `CLOSED` (assignee, assigner, or
  superadmin, via `POST assignment/{id}/done`). `CANCELLED` is reachable from
  any of `PENDING`/`READ`/`ON_PROGRESS` (assigner/superadmin only). Both
  `CLOSED` and `CANCELLED` are terminal; `POST assignment/{id}/reopen`
  (assigner/superadmin only) resets back to `PENDING` and clears
  `read_at`/`started_at`/`done_at`/`cancelled_at`.
- Comments (`POST assignment/{id}/comment`, assigner/assignee/superadmin only)
  are disabled once the status is terminal (`Assignment::$can_comment`
  accessor). Posted via `fetch()` from the detail modal (not a normal form
  submit) so the modal stays open; the controller returns JSON when the
  request is AJAX instead of `back()`'s redirect.
- Each action writes an `assignment_logs` row and notifies the counterpart via
  `NotifyService` (category `assignment`). Confirmation on done/cancel/reopen
  uses SweetAlert2 (already loaded globally), same pattern as Budget Plan's
  submit/delete confirms.
- Surfaces: dedicated page `/assignment` and a "Tugas / Assignment" section on
  `/todo-list` — both render the shared `assignment/_board` partial. The Todo List
  section is injected outside the `$hasAnySection` branch and the worklist logic in
  `TodoListController::buildWorklistData()` is untouched; `index()` loads the two
  lists separately via `AssignmentController::listsFor()`. Users without
  `menu:todo-list` still reach tasks through `/assignment` (sidebar entry has no gate).
- The assignee's picker (`<select name="assignee_id">`) is upgraded to
  `TesysSearchableSelect` (`public/js/tesys-searchable-select.js`, same
  component used by other searchable dropdowns). Its dropdown panel renders
  into `<body>`, outside the Bootstrap `.modal`, so both assignment modals set
  `data-focus="false"` to disable the modal's focus trap — without it the
  search input inside the panel cannot receive focus/clicks.

### Trouble Ticket (`TroubleTicketController` + `resources/views/trouble-ticket/`)

TESYS Operation → Workflow. Gate `menu:trouble-ticket`; all status/timestamp
changes and per-action authorization live in `App\Services\TroubleTicketWorkflow`
(row lock + expected-status check + timeline event in one transaction). Row
visibility = `TroubleTicket::scopeVisibleTo()` (site → site_pics → master_pics →
user → team), reused by `FileAccess::allowsTroubleTicket()` and the Site master
TT column/drawer tab. Full reference: `docs/modules/trouble-ticket.md`.

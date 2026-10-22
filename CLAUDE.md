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

### 4.1 Definisi lingkungan

Pisahkan tiga hal yang berbeda: **status lingkungan** (apa yang diketahui),
**larangan operasi** (apa yang tidak boleh dilakukan), dan **klasifikasi**
shared/produksi (hanya jika sudah terverifikasi). Jangan mengisi nilai
"BELUM TERVERIFIKASI" dengan tebakan.

| Lingkungan | Definisi | Status |
|---|---|---|
| Test | Database `tesys_test` di MySQL `127.0.0.1`, dipakai `RefreshDatabase`. Sumber: `phpunit.xml`. Dibuat ulang tiap suite dijalankan. | Dideklarasikan di `phpunit.xml` (dicek 2026-10-14); target efektif wajib diverifikasi melalui §4.3. |
| Lokal (dev) | Working copy `C:\laragon\www\tesys`, `APP_ENV=local`, host DB default `127.0.0.1`. Nama database dev: **[PERLU KONFIRMASI MANUSIA]** | Nama DB BELUM TERVERIFIKASI. Claude tidak membaca `.env`. |
| Shared / staging | Ada atau tidaknya DB bersama atau staging: **[PERLU KONFIRMASI MANUSIA]** | BELUM TERVERIFIKASI. `docs/DEPLOYMENT.md` tidak menyebut staging. |
| Produksi | Hostinger shared hosting, deploy manual. Lihat `docs/DEPLOYMENT.md`. | Terverifikasi dari `docs/DEPLOYMENT.md` dan CLAUDE.md §2 |

**Klasifikasi.** Sebuah database disebut "produksi" atau "shared" hanya jika sudah
terverifikasi dari dokumen atau konfirmasi manusia. Database yang targetnya belum
diketahui disebut **"target belum diketahui"**, bukan produksi.

**Larangan operasi.** Selama target database belum dikonfirmasi (selain `tesys_test`
yang lolos §4.3), Claude **berhenti sebelum operasi database yang berisiko**:
migrasi, seeding, `RefreshDatabase`/test, tinker yang menulis, perintah artisan yang
mengubah data, dan SQL yang mengubah data. Operasi baca-saja pada kode, file konfigurasi
non-rahasia, dan dokumen tetap boleh.

Jika lingkungan target tidak jelas, berhenti dan tanya. Claude tidak membaca atau
menampilkan isi `.env`, token, password, atau kredensial, dan tidak meminta pengguna
menempelkannya. Verifikasi memakai pemeriksaan per-kunci di §4.3.

### 4.2 Larangan

- NEVER: deploy, run `migrate:fresh` / `migrate:reset` / `db:wipe` /
  `migrate:rollback` on any shared DB, run `storage:link` (attachments are
  deliberately private), edit production `.env`, or run tinker writes against
  real data.
- Working copy lokal bukan produksi. Tetap jangan mengarahkannya ke kredensial produksi.
- **Skrip Composer** (`composer.json` → `scripts`):
  - `composer test` menjalankan `config:clear` lalu `artisan test`. Skrip ini hanya boleh
    dijalankan jika pengguna secara eksplisit meminta test **dan** seluruh langkah §4.3
    sudah terpenuhi. Jangan memakainya sebagai jalan pintas untuk melewati §4.3, termasuk
    persetujuan sebelum menghapus config cache (§4.3 langkah 1).
  - `composer setup` (menjalankan `migrate --force`) dan skrip Composer lain yang dapat
    menjalankan migrasi atau seeder, mengubah konfigurasi, atau memodifikasi database
    (termasuk skrip event yang menjalankan `migrate`) tidak boleh dijalankan tanpa
    permintaan eksplisit pengguna dan target database yang sudah terverifikasi (§4.1).
  - Jika target database atau dampak skrip belum jelas, berhenti dan minta konfirmasi.
    Jangan menjalankan skrip hanya untuk mengetahui dampaknya.
  - Membaca definisi skrip di `composer.json` secara read-only boleh, selama tidak membaca
    `.env` atau menampilkan rahasia.
- Never commit `.env`, credentials, tokens (`EXPORT_API_TOKEN`, `FONNTE_TOKEN`).

### 4.3 Prosedur wajib sebelum `artisan test`

Tests use `RefreshDatabase` on `tesys_test`. Config cache membuat Laravel mengabaikan
`phpunit.xml`, lalu `RefreshDatabase` menghapus database yang ditunjuk cache. Ini terjadi
pada 2026-10-02 (DB dev `laravel` dihapus dan dipulihkan dari `C:\laragon\backup\mysql`).

Jalankan berurutan. **Jika satu langkah gagal atau tidak bisa diverifikasi dengan aman,
BERHENTI dan minta konfirmasi manusia. Jangan menjalankan test.**
Test hanya dijalankan atas permintaan pengguna. Claude tidak membaca atau menampilkan isi
`.env`, password, token, atau kredensial, dan tidak menghapus config cache tanpa
persetujuan manusia.

1. **Cache konfigurasi harus tidak ada.** Jangan dihapus oleh Claude; jika ada, berhenti
   dan minta persetujuan.
   - PowerShell: `Test-Path bootstrap\cache\config.php` (harus `False`)
   - Git Bash: `ls bootstrap/cache/config.php` (harus "No such file")

2. **Tentukan executable PHP yang benar-benar tersedia** (pemeriksaan read-only; jangan
   mengasumsikan path tertentu, termasuk path Laragon pada contoh di dokumen lain).
   - PowerShell:
     ```powershell
     Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source
     where.exe php
     ```
   - Git Bash:
     ```bash
     command -v php
     ```
   - Jika `php` tidak ada di PATH, cari kandidat di lokasi instalasi PHP lokal pengguna
     (misalnya subfolder `bin\php` milik Laragon) dengan daftar direktori saja.
   - Verifikasi kandidat: `<path-php> -v` (versi harus memenuhi `^8.3`) dan
     `<path-php> -m` memuat `pdo_mysql`.
   - Jika kandidat tidak ada, ada lebih dari satu kandidat yang sama-sama masuk akal,
     atau versi tidak cocok, **berhenti dan minta manusia mengonfirmasi path PHP**.
   - **`<PATH-PHP-TERVERIFIKASI>` di langkah berikutnya dan di §11 adalah placeholder
     dokumentasi.** Placeholder ini **wajib diganti** dengan path executable PHP hasil
     langkah ini sebelum perintah dijalankan. **Jangan pernah mengeksekusi placeholder
     literal.** Contoh skrip di bawah menolak berjalan jika placeholder belum diganti.

3. **Baca `phpunit.xml`** dan pastikan: `DB_CONNECTION=mysql`, `DB_DATABASE=tesys_test`,
   `DB_URL` kosong (`value=""`), `DB_HOST` loopback (`127.0.0.1`/`localhost`),
   `DB_PORT=3306`. Jangan menampilkan `DB_USERNAME`/`DB_PASSWORD`.
   Pemeriksaan `DB_PORT` hanya memverifikasi nilai yang dideklarasikan di `phpunit.xml`,
   bukan membuktikan port atau instance MySQL efektif yang dipakai PHPUnit.

4. **Periksa koneksi tambahan.** Pencarian pola di `tests/` **bukan jaminan lengkap**:
   - PowerShell:
     `Get-ChildItem tests -Recurse -Filter *.php | Select-String -Pattern 'DB::connection|->connection\(|setDefaultConnection'`
   - Periksa juga kode aplikasi, helper, dan konfigurasi (tanpa membuka `.env`):
     `Get-ChildItem app,config,database,routes,tests -Recurse -Filter *.php | Select-String -Pattern 'DB::connection|Schema::connection|->connection\(|\$connection\s*=|setDefaultConnection'`
     serta daftar koneksi di `config/database.php` dan konfigurasi lain yang memakai
     `DB_*`/`*_URL`/host eksternal.
   - Jika ada koneksi tambahan, **periksa targetnya sebelum testing**. Jika target tidak
     bisa dipastikan aman, berhenti.

5. **Pemeriksaan konfigurasi simulasi — PROSEDUR MANUAL (default).**

   | Kunci | Nilai yang diharapkan | Cara verifikasi |
   |---|---|---|
   | `database.default` | `mysql` | `config:show`, output mentah (5c) |
   | `database.connections.mysql.database` | `tesys_test` | `config:show`, output mentah (5c) |
   | `database.connections.mysql.host` | `127.0.0.1` atau `localhost` | `config:show`, output mentah (5c) |
   | `database.connections.mysql.url` | kosong atau `null` | **Tidak boleh lewat `config:show`** (5d) |

   Jangan pernah menjalankan `config:show database`, `config:show database.connections.mysql`,
   atau `config:show database.connections.mysql.url`; ketiganya dapat mencetak kredensial.
   Tidak ada parser output otomatis: perbandingan dilakukan manual dan dikonfirmasi manusia.

   **5a. Cari potensi override dari environment proses (nama saja, tanpa nilai).**
   - PowerShell:
     `Get-ChildItem Env: | Where-Object { $_.Name -like 'DB_*' -or $_.Name -in 'APP_ENV','DATABASE_URL' } | Select-Object -ExpandProperty Name`
   - Git Bash: `compgen -e | grep -E '^(DB_.*|APP_ENV|DATABASE_URL)$'`
   - Jangan pernah menampilkan nilai variabel tersebut.
   - Jika ada nama yang muncul, berhenti dan tanya: variabel itu berpotensi mengalahkan
     `phpunit.xml` (menurut dokumentasi PHPUnit, `<env>` tidak menimpa variabel yang sudah
     ada kecuali `force="true"` [PERLU KONFIRMASI untuk PHPUnit 12]).
   - **Batas:** pemeriksaan ini hanya mendeteksi potensi override di shell saat ini. Hasil
     kosong **bukan bukti** konfigurasi efektif PHPUnit dan **tidak** berarti database aman.

   **5b. Pastikan executable PHP benar-benar tersedia sebelum Artisan.**
   `<PATH-PHP-TERVERIFIKASI>` adalah placeholder dokumentasi dan wajib diganti dengan
   **path lengkap** hasil langkah 2; jangan pernah mengeksekusi placeholder literal dan
   jangan mengasumsikan path instalasi tertentu. Contoh skrip di 5c menolak berjalan jika
   placeholder belum diganti, path bukan path lengkap ke file yang ada, nama file bukan
   `php`/`php.exe`, atau `<php> -v` gagal. Jika pemeriksaan ini gagal, hentikan prosedur.

   **Batas:** pemeriksaan ini hanya memastikan file bernama `php`/`php.exe` pada path
   lengkap tersebut ada dan keluar dengan kode 0 untuk `-v`. Ini tidak membuktikan bahwa
   file itu benar-benar PHP, versi `^8.3`, ekstensi `pdo_mysql`, atau bahwa path berasal
   dari langkah 2 — hal-hal tersebut tetap diverifikasi di langkah 2 — dan tidak
   membuktikan lingkungan PHP aman secara keseluruhan.

   **5c. Simulasi `config:show` untuk tiga kunci, output mentah, tanpa parsing.**
   - PowerShell. Seluruh blok dibungkus `& { … }` sehingga diparse dan dijalankan sebagai
     satu pernyataan, juga saat ditempel ke konsol interaktif; setiap `throw` menghentikan
     seluruh blok. Sebelum setiap panggilan executable, `$global:LASTEXITCODE` diisi
     sentinel `-1`, sehingga yang diperiksa selalu kode keluar baru (panggilan yang tidak
     menghasilkan kode keluar tetap dianggap gagal). Variabel environment dipulihkan di
     `finally`.
     ```powershell
     & {
         $php = '<PATH-PHP-TERVERIFIKASI>'
         if ($php -match '[<>]') { throw 'GAGAL: placeholder belum diganti.' }
         if (-not [System.IO.Path]::IsPathRooted($php) -or
             -not (Test-Path -LiteralPath $php -PathType Leaf) -or
             [System.IO.Path]::GetFileName($php) -notmatch '^php(\.exe)?$') {
             throw 'GAGAL: path PHP harus path lengkap ke executable php/php.exe yang ada.'
         }
         $global:LASTEXITCODE = -1
         & $php -v | Out-Null
         if ($global:LASTEXITCODE -ne 0) { throw "GAGAL: '$php -v' gagal (kode keluar $global:LASTEXITCODE)." }
         $names = 'APP_ENV','DB_CONNECTION','DB_DATABASE','DB_HOST'
         $saved = @{}
         foreach ($n in $names) { $saved[$n] = [Environment]::GetEnvironmentVariable($n, 'Process') }
         try {
             $env:APP_ENV = 'testing'; $env:DB_CONNECTION = 'mysql'
             $env:DB_DATABASE = 'tesys_test'; $env:DB_HOST = '127.0.0.1'
             foreach ($k in 'database.default','database.connections.mysql.database','database.connections.mysql.host') {
                 $global:LASTEXITCODE = -1
                 & $php artisan config:show $k
                 if ($global:LASTEXITCODE -ne 0) { throw "GAGAL: config:show $k (kode keluar $global:LASTEXITCODE)." }
             }
         }
         finally {
             foreach ($n in $names) { [Environment]::SetEnvironmentVariable($n, $saved[$n], 'Process') }
         }
     }
     ```
   - Git Bash. Seluruh pemeriksaan berada dalam subshell `( … )` yang diparse utuh
     sebelum dijalankan; setiap kegagalan memanggil `exit 1` dan menghentikan subshell
     dengan status gagal. Setiap panggilan executable diperiksa langsung lewat `||`.
     Variabel hanya berlaku untuk proses anak lewat `env`, jadi tidak perlu pemulihan.
     ```bash
     PHP_BIN='<PATH-PHP-TERVERIFIKASI>'
     (
       case "$PHP_BIN" in *'<'*|*'>'*) echo "GAGAL: placeholder belum diganti." >&2; exit 1;; esac
       case "$PHP_BIN" in /*|[A-Za-z]:[/\\]*) ;; *) echo "GAGAL: path PHP harus path lengkap." >&2; exit 1;; esac
       [ -f "$PHP_BIN" ] || { echo "GAGAL: file PHP tidak ditemukan." >&2; exit 1; }
       case "${PHP_BIN##*[/\\]}" in php|php.exe) ;; *) echo "GAGAL: file bukan php/php.exe." >&2; exit 1;; esac
       "$PHP_BIN" -v >/dev/null || { echo "GAGAL: '$PHP_BIN -v' gagal." >&2; exit 1; }
       for k in database.default database.connections.mysql.database database.connections.mysql.host; do
         env APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=tesys_test DB_HOST=127.0.0.1 \
           "$PHP_BIN" artisan config:show "$k" || { echo "GAGAL: config:show $k." >&2; exit 1; }
       done
     )
     ```
     Status keluar bukan 0 berarti pemeriksaan gagal: berhenti dan jangan menjalankan test.
   - Output mentah ditampilkan apa adanya kepada pengguna, dan **manusia mengonfirmasi**
     bahwa ketiga nilai sesuai tabel. Claude tidak menyimpulkan "sesuai" lewat parsing
     otomatis. Jika output kosong, berisi baris tambahan, berformat asing, atau ambigu
     sedikit pun, berhenti.

   **5d. `DB_URL` tidak diperiksa lewat `config:show`.** Mencetaknya dapat menampilkan
   kredensial. Yang diperiksa hanya: `phpunit.xml` memuat `DB_URL` dengan `value=""`
   (langkah 3), dan 5a tidak menemukan `DB_URL`/`DATABASE_URL` di environment proses.
   Ini **bukan bukti** bahwa `DB_URL` efektif di PHPUnit kosong; nyatakan keterbatasan ini
   saat melapor dan jangan menyebut `DB_URL` "aman".
   Dampak pada target koneksi: di Laravel, `url` digabung ke konfigurasi saat koneksi
   dibuat dan bagian-bagiannya menimpa `database`/`host`. Karena itu nilai
   `database`/`host` yang ditampilkan `config:show` di 5c **belum tentu target koneksi
   sebenarnya** bila `DB_URL` efektif terisi. Simulasi juga tidak mengatur `DB_URL`,
   sehingga tidak dapat menunjukkan apakah hal ini terjadi.

   **Sifat hasil.** Langkah 5 adalah **simulasi, bukan bukti langsung** konfigurasi
   efektif PHPUnit. Yang dibuktikan hanya ini: pada saat dijalankan, proses Artisan
   tersebut — dengan `APP_ENV`, `DB_CONNECTION`, `DB_DATABASE`, dan `DB_HOST` yang diatur
   oleh simulasi itu sendiri — menghasilkan nilai yang ditampilkan untuk tiga kunci di
   atas. Simulasi **tidak** membuktikan asal nilai tersebut (env, file env lain seperti
   `.env.testing`, atau nilai di file konfigurasi), **tidak** membuktikan target koneksi
   aktual (lihat 5d), dan **tidak** membuktikan bahwa PHPUnit akan memakai konfigurasi
   yang sama. Hasil yang sesuai **tidak mengizinkan test berjalan dengan sendirinya**:
   test tetap hanya atas permintaan eksplisit pengguna dan setelah seluruh langkah §4.3
   terpenuhi. Jika target database tidak dapat diverifikasi secara meyakinkan, berhenti
   dan jangan jalankan test.

   **Skrip parser otomatis: tidak dipakai.** Parser output `config:show` hanya boleh
   diusulkan kembali setelah format output divalidasi manusia di lingkungan TESYS
   [PERLU KONFIRMASI MANUSIA], dan tetap tidak boleh mencetak `...mysql.url`.

6. **Evaluasi, bukan pernyataan aman.** Hasil langkah 1–5 yang sesuai berarti syarat
   minimum terpenuhi, bukan bahwa test aman. Claude tidak boleh menyatakan test "aman"
   sebelum semua langkah terpenuhi, dan pemeriksaan di atas tetap memiliki batas:
   simulasi bukan bukti proses PHPUnit, dan pencarian koneksi tidak menjamin lengkap.
   Jika ada keraguan (hasil tidak sesuai, host bukan loopback, `DB_URL` tidak dapat
   dipastikan kosong sesuai langkah 5d, koneksi tambahan tidak jelas, path PHP tidak pasti), berhenti dan minta
   konfirmasi manusia. Test baru dijalankan jika pengguna memintanya.

7. Dilarang `config:cache` / `optimize` secara lokal tanpa membersihkannya lagi sebelum test.

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
- Run (hanya atas permintaan pengguna, dan hanya setelah seluruh prosedur §4.3 terpenuhi):
  `<PATH-PHP-TERVERIFIKASI> artisan test --filter=<Name>` (requires MySQL `tesys_test`).
  `<PATH-PHP-TERVERIFIKASI>` adalah placeholder dokumentasi: wajib diganti dengan executable
  PHP terverifikasi (§4.3 langkah 2) sebelum perintah dijalankan; jangan pernah
  mengeksekusi placeholder literal, dan jangan mengasumsikan path tertentu.
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
- [ ] Relevant tests passed (run only when explicitly requested by the user and after completing all safety checks in §4.3; if not run, report that they were not run).
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
8. **Run tests** — only when explicitly requested by the user and after completing all safety checks in §4.3. If tests are not run, report that they were not run.
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

---

## 21. AI roles and skills

Sections 1–20 are the rulebook for every role. This section only adds how roles and
skills are organized. Where this section and §1–20 differ, §1–20 win.

### 21.1 Definitions

- **Role** — responsibility, authority and working behavior (Planner, Executor, Reviewer,
  Evaluator, Refinement).
- **Skill** — specialized procedure/expertise for a type of work; supports a role, never
  changes its scope.
- **Tool** — ability to read, edit, run or test.
- **Task instruction** — the work requested by the IT team.

Priority when instructions conflict: safety/production rules (§4, §20) > explicit user
instruction > active role > skill. Report material conflicts instead of silently choosing.

### 21.2 Roles and how to invoke them

Canonical definitions: `.claude/roles/{planner,executor,reviewer,evaluator,refinement}.md`.
Custom subagents with the same names are in `.claude/agents/*.md`; each one only sets tool
permissions and tells the subagent to read its role file (single source, no duplicated text).
Markdown files do not activate a role by themselves. Ways to activate:

- Ask for it by name, e.g. "use the planner agent to …" or `@planner` (subagent via the Agent
  tool; the agent list is loaded at session start — restart the session or use `/agents` if a
  newly added file is not listed).
- Run the whole session as that role: `claude --agent <name>`.
- Or ask the main session to "read `.claude/roles/<role>.md` and act as that role".

If no role is named, the main session works as a general assistant under §1–20 and states
which role (if any) it is acting as. It must not claim that roles which did not run have run.
Do not run all five roles for every task; use the roles the task needs, in this usual order:
Planner → Executor → Reviewer → Evaluator. Refinement replaces Planner+Executor for small,
well-understood fixes (not for HIGH/CRITICAL changes without approval).

| Role | Edits code | Main output |
|---|---|---|
| Planner | no | plan, risks, acceptance criteria |
| Executor | yes (approved scope) | changes + report + "Pesan untuk Reviewer" |
| Reviewer | no | severity-ranked findings, verdict |
| Evaluator | no | per-criterion verdict with evidence |
| Refinement | yes (focused) | small change + concise report |

All roles: reply in Bahasa Indonesia; list new/changed files ("File baru" / "File diubah");
never claim tests passed unless actually run (and run only under §4.3).

### 21.3 Skill selection policy

Project skills live in `.claude/skills/<name>/SKILL.md`:

| Skill | Use for |
|---|---|
| `tesys-frontend-ui` | Blade, CSS/JS, tables, modals, forms, responsive/mobile, accessibility |
| `tesys-laravel-development` | routes, controllers, models, services, validation, targeted performance/quality |
| `tesys-database-safety` | migrations, schema, queries, transactions, financial data integrity |
| `tesys-testing-and-debugging` | root-cause analysis, writing tests, the §4.3 test gate |
| `tesys-security-review` | authn/authz, uploads, tokens, sensitive data |

Procedure: (1) understand the task; (2) identify the technical domain; (3) check skill
descriptions for applicability; (4) read the applicable `SKILL.md` before specialized work;
(5) use the minimum combination; (6) follow the skill's constraints; (7) stay within the active
role's scope; (8) verify with appropriate tools. Do not load every skill for every task; keep it
light for trivial tasks. If no skill fits, follow repository conventions — do not invent one.

Examples (not rigid): UI styling → frontend; business logic → laravel; migration → database
(+ laravel); unclear bug → testing-and-debugging + domain skill; mobile layout → frontend;
permission change → laravel + security; performance → testing-and-debugging + laravel/database;
independent review → Reviewer + security/domain skill; acceptance → Evaluator + testing skill.

### 21.4 Mode workflow

Sesi utama yang **mengoordinasikan**: memilih mode, memanggil subagent lewat Agent tool,
menyerahkan konteks dan bukti (diff, `git status`, output perintah) kepada agent read-only
(Planner, Reviewer, Evaluator tidak punya Bash), dan melaporkan dengan jujur agent mana yang
**benar-benar dipanggil**. Kelima role adalah subagent nyata di `.claude/agents/`; file di
`.claude/roles/` hanyalah definisi kanoniknya. Jangan mengklaim role sudah berjalan jika tidak
dipanggil.

**Mode A — Quick Refinement** (CSS, spasing, teks, responsif, bug sederhana; LOW):
1. Pahami permintaan dan baca file relevan.
2. Implementasikan perubahan dengan scope minimal (sesi utama atau Refinement).
3. Verifikasi ringan yang aman: `php -l`, pemeriksaan sintaks Blade/JS, baca ulang `git diff`.
   Tanpa test database untuk perubahan presentasi.
4. Laporkan file berubah, hasil verifikasi, risiko tersisa.
Tidak memanggil kelima agent otomatis. Jika ternyata HIGH/CRITICAL (§20), berhenti dan naikkan ke Mode B.

**Mode B — Full Workflow** (fitur baru, lintas modul, aturan bisnis keuangan, keamanan, database):
1. Planner: rencana, scope, risiko, acceptance criteria.
2. Executor: implementasi sesuai rencana yang disetujui.
3. Reviewer: periksa diff aktual (sesi utama menyuplai `git diff`).
4. Refinement: perbaiki temuan valid, bila ada.
5. Reviewer: periksa ulang bagian yang diperbaiki.
6. Evaluator: nilai acceptance criteria berdasarkan bukti.
Langkah boleh dilewati hanya dengan alasan jelas yang dinyatakan di laporan (mis. tidak ada temuan
→ langkah 4–5 dilewati).

### 21.5 Menjalankan test dengan aman (anti-menggantung)

Berlaku di atas §4.3 (gerbang database); tidak melonggarkannya. Test hanya atas permintaan
pengguna.
- **Prioritas verifikasi** tanpa database: `php -l`, pemeriksaan statis, `git diff`, test terisolasi
  tanpa DB. Jangan menjalankan seluruh suite untuk perubahan kecil; mulai dari `--filter` terpilih.
- Test ber-`RefreshDatabase` tidak dijalankan sebelum target efektif dipastikan aman (§4.3).
  `DB_DATABASE` di `phpunit.xml` bukan bukti tunggal. Jangan mengganti `RefreshDatabase` dengan
  mekanisme lain hanya agar test lolos.
- Jika test butuh database tetapi target belum aman, laporkan **BLOCKED / NOT RUN** (bukan gagal,
  bukan lulus).
- Dilarang otomatis: `migrate*`, `db:wipe`, `db:seed`, `config:cache`, `optimize`, `composer test`,
  `composer setup`.
- **Sebelum menjalankan**: tulis command lengkap, risiko, dan dependensi DB-nya.
- **Saat berjalan**: jalankan di background dengan output ke file log
  (scratchpad/`storage/logs` bila aman), mis. `... > <log> 2>&1`; pantau dengan membaca log.
  Jangan memakai pipeline `| tail -N` yang menyembunyikan progres sampai proses selesai.
- **Batas waktu**: tetapkan di awal (acuan: satu `--filter` ≤ 2 menit, grup kecil ≤ 5 menit); jangan
  mengasumsikan timeout tool/wrapper menghentikan child process (`php.exe` anak bisa tertinggal
  meski task berstatus killed).
- **Jika batas tercapai**: periksa status task, proses induk-anak, dan baris log terakhir. Jangan
  menjalankan ulang sebelum proses sebelumnya dipastikan berhenti. Hentikan hanya proses yang
  pasti milik test itu (PID dan command line cocok dengan command yang dijalankan); jangan
  menghentikan MySQL atau proses PHP lain (mis. server Laragon). Jangan mengklaim child berhenti
  hanya karena wrapper berstatus killed — verifikasi daftar proses.
- **Tanpa retry otomatis** untuk test yang timeout atau gagal saat migrasi; berhenti dan lapor.
- Laporan test memuat: command, durasi, status proses (selesai/ditinggalkan/dihentikan), output
  relevan, dan apakah test benar-benar selesai.

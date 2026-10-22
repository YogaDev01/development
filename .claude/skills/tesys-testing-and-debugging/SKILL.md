---
name: tesys-testing-and-debugging
description: Use to reproduce and diagnose a TESYS bug, write feature tests, or judge test evidence. Includes the mandatory safety gate before any artisan test run (tests use RefreshDatabase and once wiped a dev DB).
---

# TESYS testing and debugging

## Debugging method
1. Reproduce from code first: follow route → controller → model → view; read `storage/logs` only for the relevant error (never dump request bodies or secrets; do not read `.env`).
2. Form a hypothesis, find the root cause with evidence (file:line), then apply the smallest fix. Do not "fix" by silencing errors.
3. Known traps: stale compiled view/config cache after uploads (`optimize:clear`); production 500s from MySQL connection exhaustion; `Project::paymentProgressStatus()` is a binary flag, not money.

## Writing tests
- Feature tests in `tests/Feature`; fixtures from `tests/Support/MakesTesysData.php` (`userWithRole`, `giveMenuAccess`, `makeCostRequest`, `makeProject`, `makeInvoice`, …). Model new tests on `CostRequestWorkflowTest`, `CostRequestAccessTest`, `BudgetConsumptionTest`, `SecureFileAccessTest`.
- Required for changes to workflow, access, money, numbering, file access (CLAUDE.md §11). Assert allowed AND denied roles, and exact rupiah values.

## Running tests — GATE
`artisan test` only when the user explicitly asks AND every step of CLAUDE.md §4.3 is satisfied (no `bootstrap/cache/config.php`, verified PHP path, `phpunit.xml` check, extra-connection check, `config:show` simulation confirmed by the human). If any step fails or is unclear: stop and ask. Do not use `composer test`. Never delete the config cache yourself.
If not run, report "tests written, NOT run". Test yang butuh database tetapi targetnya belum aman = **BLOCKED / NOT RUN**, bukan gagal dan bukan lulus.

## Process hygiene (CLAUDE.md §21.5)
- Tulis command lengkap + risiko DB sebelum menjalankan; mulai dari `--filter` terpilih, bukan seluruh suite.
- Jalankan di background dengan output ke file log dan baca log selama berjalan; jangan `| tail -N` yang menutupi progres.
- Tetapkan batas waktu di awal; timeout wrapper tidak menjamin child `php.exe` berhenti. Saat batas tercapai: cek status task, proses induk-anak, log terakhir; jangan jalankan ulang sebelum proses lama dipastikan berhenti.
- Hentikan hanya proses yang pasti milik test itu; jangan menyentuh MySQL atau PHP lain. Tanpa retry otomatis. Laporkan durasi, status proses, dan apakah test benar-benar selesai.

## Verification
Report separately: static check (`php -l`), tests executed (with real output), and behavior not verified (browser, production data).

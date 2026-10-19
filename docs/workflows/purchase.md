# Workflow — Purchase Order (Project)

In TESYS "PO" = **customer Purchase Order** (revenue contract) stored in
`projects` + `project_po_numbers`. No procurement / vendor-purchase module was
found — **Needs confirmation** if one is expected.

Statuses (`Project::STATUSES`): DRAFT, SUBMITTED, WAITING_PO, ACTIVE,
PROGRESS_DONE, PAID, CLOSING, CLOSED.

| Stage | Meaning (from `WorkflowStatus` 'project' context) | Who |
|-------|----------------------------------------|-----|
| SUBMITTED | awaiting Finance approval | finance, finance_manager, superadmin |
| WAITING_PO → ACTIVE | **manual** activation: `activate-from-waiting` / `activate` | superadmin, finance, finance_manager, head (route gate) |
| ACTIVE | running; awaiting BAST on all PO numbers | — |
| ACTIVE → PROGRESS_DONE → PAID | described as automatic from PO-number status in `WorkflowStatus` comments (`Project::syncStatusFromPoNumbers()`); exact triggers not traced — Needs confirmation | PO-number actions (`complete-bast`, `po-number/status`): superadmin, finance, finance_manager, head |
| PAID | awaiting MoM Closing by Head | head, superadmin |
| CLOSING | awaiting Finance closing verification (`finance-close`) | finance, finance_manager, superadmin |

PO number statuses: RELEASED, PARTIAL, BAST_SUBMITTED, INVOICED, PAID.

**Struktur BAST (Okt 2026).** `project_po_numbers` = header, `project_po_basts` = detail:
satu No. PO bisa punya banyak BAST (`ProjectPoNumber::basts()`). Tiap submission BAST
(`ProjectController::applyBastSubmission`) menambah satu baris `project_po_basts` dan
menautkan lampirannya lewat `project_attachments.project_po_bast_id`. Kolom lama
`project_po_numbers.no_bast` / `tanggal_bast` / `nilai_bast` tetap diisi (no_bast & tanggal =
submission terakhir, `nilai_bast` = akumulasi semua BAST) karena invoice dan laporan membacanya.
Alur status No. PO tidak berubah. Backfill migrasi `2026_10_18_000100`: 1 baris per No. PO yang
`no_bast`-nya terisi (nilai = agregat lama; seluruh lampiran `po_number_bast` ditautkan ke baris itu —
rincian per-BAST sebelum migrasi tidak bisa dipulihkan).

Modal Detail PO > Summary > Value: BAST = SUM(`nilai_bast`) semua No. PO; Contract seharusnya =
BAST + Restitusi, selisihnya ditampilkan sebagai "Selisih" (hanya tampilan; `ProjectController::showJson`).

## Transisi ke penagihan per BAST (Fase 1 — skema + konversi dry-run)

Tujuan akhir: 1 invoice = 1 BAST (aturan status No. PO baru ada di Fase 2, belum dikerjakan).
Fase 1 hanya **additive** — tidak ada kolom/tabel/baris yang dihapus, nilai uang dan status invoice
tidak berubah, dan `invoices.project_po_number_id` serta `project_po_numbers.no_bast/tanggal_bast/nilai_bast`
tetap dipakai.

Skema (migrasi `2026_10_18_000200`): `invoices.project_po_bast_id` (nullable, FK restrict),
`project_po_basts` soft delete (`deleted_at`, `deleted_by`, `delete_reason`), tabel jejak
`invoice_bast_links_log`. Model: `Invoice::bast()`, `ProjectPoBast::invoices()/activeInvoices()/isBilled()`
(invoice CANCELLED tidak dihitung).

Command (default dry run; semua menulis CSV ke `storage/app/private/reports/`):

```
php artisan invoice:link-bast                          # dry run
php artisan invoice:link-bast --apply                  # tulis R1 saja
php artisan invoice:link-bast --apply --include-multi  # tulis R1 + R2
php artisan invoice:link-bast --rollback=<run_id>            # dry run: laporan jumlah yang akan dikosongkan
php artisan invoice:link-bast --rollback=<run_id> --apply    # kosongkan tautan satu run (dicatat rolled_back_at)
php artisan invoice:report-po-paid-drop                # baca-saja: No. PO PAID yang akan turun
```

| Aturan | Kondisi | Hasil |
|---|---|---|
| R1 | No. PO punya tepat 1 BAST | semua invoice ditautkan; flag `LEGACY_MULTI` (>1 invoice aktif), `VALUE_MISMATCH` (billed ≠ nilai BAST). Bila total `billed_amount` invoice aktif berbeda dari nilai BAST lebih dari Rp 1.000, **seluruh No. PO dilewati** (termasuk invoice CANCELLED) dengan flag `TAGIHAN_TIDAK_SAMA_BAST` dan dihitung terpisah di ringkasan; tulis sengaja dengan `--sertakan-tagihan-tidak-sama` |
| R2 | No. PO punya >1 BAST | cocok `billed_amount == nilai_bast` dulu, sisanya terdekat (`NEAREST`); invoice CANCELLED/soft-deleted ikut BAST invoice aktif berikutnya (flag `CANCELLED`/`DELETED`); hanya ditulis dengan `--include-multi` (tanpa itu diberi flag `SKIPPED_MULTI`) |
| R3 | No. PO tanpa BAST | dibiarkan NULL, flag `TANPA_BAST` |
| R4 | invoice tanpa No. PO | tidak disentuh, hanya dihitung |

Pengaman: kontrol invarian (count + SUM `billed_amount`, `captured_total`, `tax_amount`, `withholding_tax`
seluruh invoice termasuk soft-deleted) sebelum/sesudah — bila beda seluruh run dibatalkan. Penulisan per
No. PO dalam transaksi, tiap tautan dicatat di `invoice_bast_links_log` (log tidak dihapus saat rollback; kolom `rolled_back_at` mencatat waktu rollback — migrasi `2026_10_18_000300`). `down()` migrasi 000200/000300 menolak bila masih ada tautan, baris log, catatan rollback, atau BAST soft-deleted.
Command ini tidak memanggil `syncInvoiceStatus()`/`syncPaymentStatus()`. Urutan operasional: dry run ->
periksa CSV -> `--apply` -> (opsional) `--include-multi` -> `invoice:report-po-paid-drop`.

Tampilan: invoice show/print/index menampilkan No. BAST dari `bast()`; bila belum tertaut tampil
"No. PO: X · BAST: belum tertaut". Form create/edit invoice belum diubah.

## Penagihan per BAST (Fase 2 — aturan baru)

Berlaku setelah Fase 1 di-apply (skema + `invoice:link-bast`). **1 Invoice = 1 BAST; 1 BAST = 1 termin.**
Invoice lama (tanpa `project_po_bast_id`) tidak diubah dan tetap berjalan.

- **Bisa ditagih:** BAST yang `!isBilled()` (tanpa invoice aktif; CANCELLED tidak dihitung), tidak bergantung status No. PO.
- **Nilai:** `billed_amount` invoice (nilai sebelum PPN, hasil `InvoiceCalculationService`) harus sama dengan
  `nilai_bast` **persis** (`InvoiceCashReceiptController::BAST_TOLERANCE = 0`, keputusan bisnis P1); selain itu ditolak. Toleransi Rp 1.000 hanya dipakai converter data lama (`invoice:link-bast`, `invoice:split-synthetic-bast`) untuk klasifikasi.
  `nilai_bast` dianggap belum termasuk PPN. Form create/edit menampilkan nilai BAST dan cek kesesuaian secara live.
- **Anti-ganda:** baris BAST dikunci (`lockForUpdate`) di awal transaksi create/update; cek ulang saat Submit dan saat
  revisi CANCELLED -> DRAFT.
- **Status No. PO** (`ProjectPoNumber::syncInvoiceStatus()`, satu jalur untuk semua kejadian):
  tanpa BAST aktif -> RELEASED; ada BAST, belum "Tandai Lengkap" -> PARTIAL; ditandai lengkap -> BAST_SUBMITTED
  (boleh walau total BAST < nominal); semua BAST punya invoice aktif -> INVOICED; semua invoice aktif itu PAID -> PAID.
  "Lengkap" melekat pada status BAST_SUBMITTED/INVOICED/PAID. Satu termin PAID tidak membuat No. PO PAID.
  No. PO yang tidak pernah punya baris BAST memakai perhitungan lama (sisa nominal).
- **Batas total BAST:** submit BAST yang membuat total BAST aktif > nominal No. PO diblokir dengan alert.
- **BAST sintetis (backfill):** No. PO yang masih punya invoice lama *aktif* (bukan CANCELLED/soft-deleted) dengan
  `project_po_bast_id` NULL (`ProjectPoNumber::punyaInvoiceLamaBelumTertaut()`) memakai alur status lama
  (`usesBastBilling()` = false) dan BAST-nya tidak ditawarkan/ditolak untuk penagihan baru sampai invoice lamanya
  ditautkan (`invoice:link-bast`). Bila semua invoice lamanya CANCELLED/terhapus, No. PO itu boleh ditagih per BAST. Invoice lama yang CANCELLED tidak bisa direvisi ke DRAFT bila No. PO-nya sudah punya BAST yang ditagih invoice aktif lain.
- **Urutan kunci** (semua jalur: buat/ubah/hapus/submit/revisi/batal invoice, pembayaran & settle & hapus Cash Receipt,
  hapus BAST, submit BAST, serta Cash Receipt Header/Detail: store/alokasi/batal alokasi/batal bukti terima di `CashReceiptController`; trait `Concerns\LocksPoNumbers`): (1) baris No. PO (bila dua, berurutan id naik; submit BAST juga berurutan id),
  (2) baris BAST, (3) invoice per id — tidak pernah range lock. Kunci pertama harus pernyataan pertama dalam transaksi
  (No. PO dicari sebelum transaksi) supaya cek "BAST sudah ditagih" melihat commit transaksi lain.
- **`billing_percentage`** tetap boleh diisi pada invoice ber-BAST; yang dibandingkan dengan `nilai_bast` selalu
  `billed_amount` hasil hitung (detail x persentase), jadi persentase tidak bisa menghasilkan nilai yang berbeda dari nilai BAST.
- **Hapus BAST** (`DELETE project/po-bast/{id}`, superadmin saja): hanya bila belum ditagih, alasan wajib, soft delete
  (`deleted_by`, `delete_reason`); lampiran tetap tertaut ke BAST yang terhapus. Agregat `nilai_bast`/`no_bast`/`tanggal_bast`
  No. PO dihitung ulang dari BAST aktif, status mengikuti aturan di atas, tercatat di log PO.

## Fase 1b: pemecahan BAST sintetis

**Latar:** backfill migrasi `2026_10_18_000100` membuat satu BAST agregat per No. PO (BAST sintetis). Bila invoice lama
menagih sebagian saja (tertagih kurang), BAST itu dipecah supaya tiap invoice punya BAST sendiri dan sisanya bisa ditagih.

**Aturan mutlak:** tidak ada kolom/tabel/baris yang dihapus ("hapus" = soft delete + `delete_reason`); nilai & status invoice
serta `project_po_numbers.nilai_bast/no_bast/tanggal_bast` tidak ditulis (dijaga kontrol invarian per No. PO dan per run);
status tidak berubah oleh pemecahan; "Tandai Lengkap" tetap manual.

**Keputusan bisnis:** P1 form invoice menuntut nilai persis sama dengan BAST (toleransi 0); toleransi Rp 1.000 hanya converter.
P2 tertagih lebih tidak dipecah (`TAGIHAN_LEBIH`, tunggu BAST asli lalu run ulang). P3 invoice DRAFT dihitung aktif dan ikut dipecah
(status DRAFT sampai PAID, bukan CANCELLED/terhapus). P4 cetak invoice hanya memuat No. PO. P5 BAST sintetis = `origin=BACKFILL`
(`bast:mark-backfill --max-backfill-id`). P6 sinkron status No. PO adalah langkah terpisah (`--sync-po`).

**Skema (migrasi `2026_10_19_000100`):** `project_po_basts.origin` (BACKFILL | SPLIT_INVOICE | SPLIT_SISA | NULL),
`split_from_bast_id` (FK restrict), `split_run_id`; tabel jejak `bast_split_log`.

**Perintah (semua default dry run, CSV ke `storage/app/private/reports/`):**

```
php artisan bast:mark-backfill --max-backfill-id=<id>            # dry run
php artisan bast:mark-backfill --max-backfill-id=<id> --apply    # tulis kolom origin saja
php artisan invoice:split-synthetic-bast                          # dry run (CSV perkiraan)
php artisan invoice:split-synthetic-bast --apply --po=<id[,id]>   # percobaan 1-2 No. PO
php artisan invoice:split-synthetic-bast --apply                  # sisanya
php artisan invoice:split-synthetic-bast --rollback=<run_id>            # dry run
php artisan invoice:split-synthetic-bast --rollback=<run_id> --apply
php artisan invoice:split-synthetic-bast --sync-po=<run_id>             # dry run: status sebelum -> sesudah
php artisan invoice:split-synthetic-bast --sync-po=<run_id> --apply
```

Kandidat: No. PO dengan tepat 1 BAST aktif ber-`origin=BACKFILL`, ada invoice aktif belum tertaut, dan selisih Σ `billed_amount`
aktif terhadap `nilai_bast` > Rp 1.000. Tertagih kurang: satu pecahan per invoice aktif (urut `invoice_date`, id; `<no induk>/T<n>`,
nilai = `billed_amount`), satu sisa dibuat terakhir (id terbesar, nomor & tanggal induk, nilai = induk − Σ), invoice CANCELLED/terhapus
ditautkan ke induk, induk di-soft-delete (lampiran tetap di induk; tampilan memakai lampiran induk untuk pecahan). Dilaporkan tanpa tulis:
`TAGIHAN_LEBIH`, `SKIP_BUKAN_SINTETIS`, `SKIP_SUDAH_TERTAUT`, `BERUBAH_SEJAK_DRYRUN` (rencana dihitung ulang di dalam kunci).
Urutan kunci: No. PO (pernyataan pertama) -> BAST induk -> invoice id naik. Rollback mengembalikan tautan, soft-delete pecahan/sisa,
memulihkan induk, dan melewati No. PO yang pecahan/sisanya sudah dipakai invoice lain; log memakai `rolled_back_at`, tidak dihapus.
Jalankan di luar jam submit BAST/invoice: kontrol invarian run membandingkan total seluruh invoice & BAST.

**BAST dari invoice (No. PO tanpa BAST).** `php artisan bast:create-from-invoice` (default dry run; `--apply` wajib `--po=<id,...>`;
`--rollback=<run_id> [--apply]`) membuat satu BAST per invoice aktif untuk No. PO yang tidak punya baris BAST sama sekali: `no_bast` =
`TANPA-BAST/<kode invoice>`, `tanggal_bast` = `invoice_date`, `nilai_bast` = `billed_amount`, `origin` = `FROM_INVOICE`, invoice ditautkan,
agregat No. PO dihitung ulang (`recalculateBastAggregate()`). Nilai/status invoice dan status No. PO tidak berubah (dijaga kontrol invarian,
sinkron status tidak dipanggil); invoice CANCELLED/terhapus tidak ditautkan. Dilewati: `INVOICE_NILAI_NOL`, `MELEBIHI_NOMINAL`,
`BERUBAH_SEJAK_DRYRUN`. Jejak di `invoice_bast_links_log` (rule `FROM_INVOICE`, `flag` = agregat lama untuk rollback). Nomor BAST placeholder
tidak bisa diedit dari UI; koreksi ke nomor asli butuh command/SQL tersendiri (Needs confirmation).

**Urutan deploy (langkah manusia):** backup DB + baseline (COUNT/SUM invoice per status, Σ `nilai_bast` per No. PO, status No. PO & header)
-> upload + `migrate` + `config:clear` -> `bast:mark-backfill` dry run -> `--apply` -> `invoice:split-synthetic-bast` dry run -> review CSV oleh
pemilik -> `--apply --po=<1-2 id>` -> cek UI -> `--apply` sisanya -> bandingkan baseline -> `--sync-po` dry run -> review -> `--sync-po --apply`
+ `project:sync-status` -> `invoice:report-po-paid-drop` -> aktifkan Fase 2.

Money visibility: `AccessRoles::PO_VALUE`, `PO_MONEY_COLUMNS`, `PO_FULL_SCOPE`.
Payment metric trap: use `paymentTotal()` / `paidDppByProject()`, not
`paymentProgressStatus()`.

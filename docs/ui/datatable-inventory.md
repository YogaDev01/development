# Inventaris DataTable & Tab — UI/UX Overhaul (Fase 0)

Branch: `feature/ui-datatable-mobile` (dibuat dari `feature/invoice-per-bast`, working tree bersih saat dibuat).
Risiko: MEDIUM (UI global, tanpa logika bisnis). **Fase 0 = inventaris saja; tidak ada file kode/CSS/JS/Blade yang diubah.**
Status: **menunggu review pemilik sebelum Fase 1.**

Metode: `grep`/skrip baca-saja atas `resources/views` dan `public/js` (tanpa menjalankan aplikasi). Hal yang hanya bisa dipastikan di browser ditandai *belum diverifikasi* (§7).

## 0. Batasan seluruh proyek (berlaku untuk Fase 1+)

- Jangan ubah controller, query, route, permission, workflow, kalkulasi, maupun inisialisasi `.DataTable(...)` per halaman.
- Perubahan Blade per halaman hanya atribut opsional: `data-card`, `data-ts-card`, `data-ts-tabbar`, `data-short` bila heuristik tidak tepat.
- Tanpa dependency baru, tanpa build step (CLAUDE.md §2). Warna hanya dari token `tesys-ui-tokens.css` (§9).
- Tidak ada migrasi, tidak ada test dijalankan, tidak ada commit/push tanpa diminta.
- Saat Fase 0: belum ada `tesys-table.js` maupun `tesys-tabbar.js`. Sekarang `tesys-table.js` + `tesys-ui-datatable.css` ada (mode card); `tesys-tabbar.js` **ditahan** menunggu persetujuan pemilik. `public/css/tesys-ui-mobile.css` sudah ada (breakpoint 900px, aturan `.table-responsive`, `.col-sticky-left`, `aca-fixed`) → harus dibaca dulu di Fase 1 agar tidak bentrok.

### Titik sentuh global (syarat Fase 1)

- `public/js/tesys-shell.js` (± baris 390): pencarian topbar memakai `$.fn.dataTable.tables({ visible: true, api: true })` lalu `.search(q).draw()`. **Syarat:** mode card tidak boleh menyembunyikan elemen `<table>` (`display:none`/`hidden`) — DataTables menganggap tabel tak terlihat sehingga pencarian topbar berhenti bekerja. Mode card harus mengubah tampilan sel/baris saja (mis. `display:block`/`flex`), dan tabel tetap "visible". Cabang cadangan untuk tabel biasa memakai atribut `row.hidden`; selektor CSS card tidak boleh meng-override `display` baris pada tabel non-DataTable.
- `public/js/tesys-money-sort.js`: mendaftarkan tipe kolom `num-id` (`dt.ext.type.detect/order/search`) secara global. **Syarat:** mode card tidak mengubah isi teks sel yang dibaca pendeteksi tipe (format rupiah) dan tidak membuat ulang/menyusun ulang `<td>`; tambahan hanya atribut (`data-label`) dan kelas.

## Basis branch

- `feature/ui-datatable-mobile` dibuat dari `feature/invoice-per-bast`. Saat dokumen ini diperbarui, branch itu **10 commit** di depan `main` (bukan 9): `af4b976` (v3.0.4), `e4b9328` (v3.0.5), `a3ae5da`, `056b28f` (v3.0.6), `283f5e5`, `06def93`, `f543cc7`, `e586f4d`, `89d5ab0` (cash-receipt), dan `191ebb5` (fix popup datepicker landscape; commit ke-10 yang ditambahkan pemilik setelah Fase 0).
- Konsekuensi urutan rilis: karena branch UI memuat commit-commit tersebut, **invoice-per-bast harus dirilis lebih dulu**, lalu branch UI (atau UI di-rebase ke `main` sesudah invoice-per-bast masuk). Merilis UI lebih dulu otomatis ikut membawa kode invoice-per-bast.
- Status deploy kode v3.0.6 ke produksi: **NEEDS CONFIRMATION** (tidak dapat ditentukan dari repo).

## Catatan working tree

- Saat Fase 0 mulai, `public/css/tesys-ui-components.css` berstatus modified (perubahan `.tdr-pop` popup date-range). Itu **bukan bagian Fase 0** dan harus di-commit terpisah dari pekerjaan UI DataTable/tab. Pembaruan: perubahan itu kini sudah ada sebagai commit `191ebb5` di `feature/invoice-per-bast`, sehingga file tersebut tidak lagi kotor.

## 1. Ringkasan temuan

- `grep "\.DataTable("` menemukan **33 file**: 31 view halaman + 2 non-halaman (`components/workflow-stepper.blade.php` hanya memanggil `.DataTable()` untuk *mengambil instance* guna filter status; `public/js/tesys-column-filter.js` serupa). Jadi **31 view yang benar-benar menginisialisasi tabel**.
- Semua thead tabel DataTable utama **satu baris tanpa colspan/rowspan**, kecuali di `finance-summary` (tabel pivot/grup).
- `.col-sticky-left` dipakai di `finance-summary/index` (**18** kemunculan) dan didefinisikan/ditimpa di `public/css/tesys-ui-mobile.css` (**4**); `finance-summary/_collection-detail` = 0 kemunculan (koreksi dari angka 28 sebelumnya). Kolom beku lain memakai CSS `position:sticky` buatan sendiri (kiri/kanan) di `budget-request`, `invoice-cash-receipt`, `project`, `trouble-ticket` (`.tt-freeze`). **Tidak ada FixedColumns/scrollX DataTables** yang aktif (komentar di finance-summary menegaskan sengaja tidak dipakai).
- Satu halaman sudah punya mode card mobile sendiri: **`trouble-ticket/index`** (`#ttMobList .tt-mcard`) dan `project-monitoring/show` (`d-md-none`/mcard). Harus dikecualikan atau disatukan — jangan dobel.
- "Finance Summary 14 tabel": **tidak terverifikasi**. Yang ditemukan di Blade: 5 `<table>` di `index` + 1 di `_collection-detail`; hanya 2 yang berupa DataTable (`tblCollectionPerformance`, `tblPoSummary`, `dom:'t'`, tanpa paging). Kemungkinan angka 14 menghitung tabel yang dirender JS/ per-tab; perlu konfirmasi (§6).
- Jumlah file tab: grep `data-toggle="tab"` di `resources/views` = **20 file** (+ `profile/_user_log` memakai `data-toggle="pill"`); tambah 5 file yang punya `.nav-tabs/.nav-pills` tanpa `data-toggle` (tab berupa link/JS). Total 26 file bernuansa tab — bukan 24 persis; daftar lengkap di §3.

## 2. Inventaris view DataTable (31 view)

Kolom: **Tbl** = jumlah `<table>` di file (termasuk tabel statis non-DataTable di modal) / yang di-DataTable-kan. **Resp** = opsi `responsive` pada init (`inline` = Responsive ext. dengan child row; `true`; `off`). **Thead** = bentuk header tabel DataTable. **Beku** = kolom beku. **Aksi** = ada `.ts-actions`. **Tab hal.** = tab level halaman; **Tab modal** = tab di modal/drawer. **Card** = usulan layak-card.

| # | View | ID tabel DataTable | Tbl | Resp | Thead | Beku | Aksi | Tab hal. | Tab modal | Card | Catatan |
|---|------|--------------------|-----|------|-------|------|------|----------|-----------|------|---------|
| 1 | accessgroup/index | accessGroupTable | 2/1 | inline | 1 baris, 5 th | – | ya | – | ya (`detailTabs`) | **ya** | Tabel ke-2 statis di modal |
| 2 | account/index | tblAccount | 1/1 | true | 1 baris, 5 th | – | ya | – | – | **ya** | Master sederhana |
| 3 | bank/index | bankTable | 1/1 | true | 1 baris, 5 th | – | ya | – | – | **ya** | Master sederhana |
| 4 | beneficiaries/index | beneficiaryTable | 3/1 | inline | 1 baris, 10 th | – | ya (3) | ya (`ben-main-tabs`, link/JS) | – | **ya*** | Tab = pindah dataset/URL lewat JS; 10 kolom → perlu `data-short`? |
| 5 | budget-plan/index | tblBudgetPlan | 7/1 | inline | 1 baris, 9 th, **tfoot** | – | ya | – | ya (`bpDetailTabs`, 6 tab) | **ya*** | Referensi pola UI (memory). Ada tfoot total; 6 tabel statis di modal |
| 6 | budget-request/index | `#tblCA` `#tblCP` `#tblDB` (`_table`) | 3/3 | **tidak** (komentar: bentrok dgn toggle kolom) | 1 baris; kolom dinamis (kolom bisa disembunyikan user) | kiri+kanan sticky (CSS) | ya | **ya** (`crTabs`, 3 tab lazy AJAX) | ya (`crDetailTabs`) | **tidak (tahan)** | Kolom 0 disembunyikan, `columnDefs` visibilitas manual, tfoot, tabel dimuat ulang via AJAX (DOM diganti) → risiko tinggi; lihat §4 |
| 7 | cash-management/index | tblLedger, tblManual | 4/2 | true | 1 baris (8 & 9 th); tblLedger tfoot | – | tidak | ya (3 tab) | – | **ya*** (2 tabel) | 2 tabel di dua tab; tab ke-3 non-DataTable |
| 8 | cash-receipt/index | tblCashReceipt | 1/1 | inline | 1 baris, 8 th | – | tidak | – | – | **ya** | Terbaru diubah di commit 89d5ab0 (cek tak bentrok) |
| 9 | closing/index | tblClosingDetail | 3/1 | true | 1 baris, 6 th (**tanpa tfoot**) | – | tidak | – | – | **ya** | 2 tabel lain statis; tfoot (`closing/index.blade.php:160`) milik tabel ringkasan statis (bukan DataTable) |
| 10 | cluster/index | clusterTable | 1/1 | inline | 1 baris, 7 th | – | ya | – | – | **ya** | |
| 11 | cost-center/index | ccTable | 3/1 | inline | 1 baris, 7 th | – | ya | – | – | **ya** | 2 tabel statis (modal) |
| 12 | cost-register/index | tblCostRegister | 1/1 | inline | 1 baris, 11 th | – | ya (3; ada tombol di luar `.ts-actions`) | – | – | **ya*** | 11 kolom; komentar di sel aksi menyebut slot 36px |
| 13 | customer/index | customerTable | 1/1 | inline | 1 baris, 6 th | – | ya | – | – | **ya** | |
| 14 | daily-transaction/index | tblBatch | 1/1 | inline | 1 baris, 8 th, tfoot | – | ya | – | – | **ya*** | Ada tfoot total |
| 15 | delivery-note/index | tblDeliveryNote | 1/1 | inline | 1 baris, 7 th | – | ya | – | – | **ya** | |
| 16 | employee/index | empTable | 4/1 | inline | 1 baris, 7 th | – | ya | – | – | **ya** | 3 tabel statis di modal |
| 17 | feedback/inbox | tblFeedbackInbox | 1/1 | inline | 1 baris, 7 th | – | tidak | – | – | **ya** | |
| 18 | finance-summary/index | tblCollectionPerformance, tblPoSummary (DT); tblExecutive, tblAca, tblProgressSummary (bukan DT) | 5/2 (+1 partial) | off (`dom:'t'`, tanpa paging) | **bertingkat** 2–3 baris, colspan/rowspan banyak | **`.col-sticky-left`** + wrapper `col-pic-hscroll` | tidak | ya (`summaryTabs`, 3 tab) | – | **tidak** | Pivot/grup, tfoot dihitung ulang JS (`recalc*Footer`); lihat §4 |
| 19 | groups/index | groupTable | 1/1 | inline | 1 baris, 6 th | – | ya | – | – | **ya** | |
| 20 | invoice-cash-receipt/index | tblInvoice | 1/1 | **tidak** | 1 baris, **16 th**, tfoot | kiri+kanan sticky (CSS, sel `!important`) | ya | – | – | **tidak (tahan)** | Tabel lebar, kolom beku, tfoot; `tblInvoice` baru dimigrasi per-BAST |
| 21 | master-data/index | tblMaster | 1/1 | `true`/`false` (mode `compact` dari config) | 1 baris, 3 th | – | ya | ya (`master-tabs`, nav-pills = link URL) | – | **ya** | Banyak jenis master memakai satu view |
| 22 | master-pic/index | picTable | 1/1 | inline | 1 baris, 10 th | – | ya | ya (`pic-main-tabs`, link/JS) | – | **ya*** | 10 kolom |
| 23 | payroll-run/index | runTable | 1/1 | inline | 1 baris, 7 th | – | ya | – | – | **ya** | |
| 24 | project-monitoring/index | tblMonitoring | 4/1 | inline | 1 baris, 7 th | – | ya | – | ya (`poInfoTabs`) | **ya*** | `project-monitoring/show` sudah punya mcard sendiri |
| 25 | project/_index-assets (+ `project/index`) | projectTable | 6/1 | **tidak** | 1 baris, ±21 th (header di `project/index.blade.php:143`, tfoot) | kiri+kanan sticky (CSS) | ya (kolom `action-column`) | – | ya (`prjDetailTabs`, 10 tab) | **tidak (tahan)** | Tabel sangat lebar; modal bertab dengan banyak tabel dirender JS |
| 26 | project/show | budgetPlanTable, clusterTable | 3/2 | ya (konfigurasi objek) | 1 baris (5 & 6 th) | – | tidak | – | – | **ya*** | Sub-tabel di halaman detail |
| 27 | role/index | roleTable | 1/1 | inline | 1 baris, 7 th | – | ya | – | – | **ya** | |
| 28 | source-fund/index | sfTable | 1/1 | inline | 1 baris, 6 th | – | ya | – | – | **ya** | |
| 29 | stage/index | stageTable | 1/1 | inline | 1 baris, 6 th | – | ya | – | – | **ya** | |
| 30 | trouble-ticket/index | tblTt | 1/1 | **off** (`responsive:false`) | 1 baris | **kanan sticky `.tt-freeze`** | ya | – | – | **tidak (kecualikan)** | Sudah punya card mobile sendiri (`#ttMobList .tt-mcard`) + filter `$.fn.dataTable.ext.search` khusus |
| 31 | user/index | userTable | 2/1 | inline | 1 baris, 7 th | – | ya | – | ya (`detailTabs`) | **ya** | |

`*` = layak secara struktur tetapi perlu perhatian (kolom banyak / tfoot / tab). Non-halaman: `components/workflow-stepper` & `tesys-column-filter.js` hanya mengambil instance DataTable (tidak diinisialisasi di sana) — pastikan card mode tidak memutus `table.draw()`/`search` yang mereka panggil.

Catatan pengelompokan: dari 31 view, **18 "ya" bersih**, **8 "ya*"**, **5 "tidak/tahan"** (#6, #18, #20, #25, #30). Hitungan ini hanya usulan pemilik untuk tinjau.

## 3. Inventaris tab (26 file)

Rule calon bottom tab: hanya `.nav-tabs`/`.nav-pills` **pertama** di `.content` yang **bukan** di dalam `.modal`, `.ts-drawer-root`, `.todo-drawer`, atau `.tab-pane` lain.

| # | File | Nav (id/kelas) | Jumlah toggle/pane | Level | Calon bottom tab? | Catatan |
|---|------|----------------|--------------------|-------|-------------------|---------|
| 1 | accessgroup/index | `#detailTabs` | 2/2 | modal | tidak | |
| 2 | assignment/_board | `#assignmentTabs` | 2/2 | halaman (partial dipakai `assignment/index` & `todo-list/_assignments`) | ya di `assignment/index`; **tidak** bila ter-include di Todo List (nested di section) | Perlu `data-ts-tabbar="off"` di dalam todo-list |
| 3 | beneficiaries/index | `.ben-main-tabs` | link/JS | halaman | ya* | Tab tanpa `data-toggle`; klik ditangani JS (href tetap ada) — apakah tabbar harus ikut handler JS? |
| 4 | budget-plan/index | `#bpDetailTabs` | 6/6 | modal | tidak | |
| 5 | budget-request/_detail_modal | `#crDetailTabs` | 5/5 | modal | tidak | Partial; juga di-include di daily-transaction/show |
| 6 | budget-request/index | `#crTabs` (`.cr-main-tabs`) + `#crDetailTabs` | 3 lazy + 5 | halaman + modal | **ya** (`#crTabs` saja) | 3 tab AJAX lazy; label panjang? |
| 7 | cash-management/index | `.nav-tabs.mt-3` | 3/3 | halaman | ya | Mengandung 2 DataTable (tab tersembunyi saat init → hitung lebar) |
| 8 | cost-register/create | `#crModeTabs` (`card-header-tabs`) | 2/2 | halaman (di card header) | ragu | Mode input form, bukan navigasi data |
| 9 | daily-transaction/show | `#crDetailTabs` | 4/4 | modal | tidak | |
| 10 | finance-summary/index | `#summaryTabs` | 3/3 | halaman | **ya** | Konten pivot; tabbar boleh, card tidak |
| 11 | forecast/index | (tanpa id) di `.fc-modal-flex` | 3/3 | modal | tidak | |
| 12 | invoice-cash-receipt/show | `#invDetailTabs` (`card-header-tabs`) | 5/5 | halaman (di card header) | ragu | 5 tab; apakah di-fixed bottom? |
| 13 | master-data/index | `.master-tabs` (nav-pills) | link URL | halaman | ya* | Navigasi ke URL lain (bukan pane); banyak item |
| 14 | master-pic/index | `.pic-main-tabs` | link/JS | halaman | ya* | |
| 15 | profile/show | `#profileTabs` (`.prof-tabs`) | 6/6 | halaman | ya | 6 tab; + `_user_log` (pills) & `_settings` ter-include di pane |
| 16 | profile/_user_log | `#userLogTabs` (nav-pills, `data-toggle="pill"`) | 3/3 | **nested dalam `.tab-pane`** | **tidak** | |
| 17 | profile/_settings | (grep `nav-tabs/pills`, perlu cek manual) | – | nested dalam `.tab-pane` | tidak | Belum dibaca isinya |
| 18 | project-monitoring/index | `#poInfoTabs` | 3/3 | modal | tidak | |
| 19 | project/index | `#prjDetailTabs` | 10/10 | modal | tidak | |
| 20 | project/_index-assets | (hanya CSS/JS string) | – | – | tidak | Terdeteksi karena kelas `nav-tabs` di CSS/JS |
| 21 | site/_drawer | `.site-dw-tabs` | 7/6 | drawer | tidak | |
| 22 | team/show | `#teamTabs` (`card-header-tabs`) | 2/2 | halaman | ya | |
| 23 | todo-list/index | `#todoBpTabs` (modal), `#summaryTabs` (modal), tab di `#todoDrawer` | 9/9 | modal + drawer (3 nav) | **tidak** | Tak ada tab level halaman |
| 24 | trouble-ticket/settings | `.nav-tabs` | 4/4 | halaman | ya | |
| 25 | trouble-ticket/show | `.tt-tabs` (`nav-fill`) | 6/5 | halaman | ya | |
| 26 | user/index | `#detailTabs` | 2/2 | modal | tidak | |

Ringkas: **calon bottom tab ≈ 11–13 halaman** (#2 sebagian, 3, 6, 7, 10, 13, 14, 15, 22, 24, 25; ragu #8, #12). Semua lainnya (modal/drawer/nested ±13) tetap gulir mendatar sesuai keputusan pemilik. Klasifikasi level (halaman vs modal) dari konteks baris di sekitar `<ul class="nav ...">` — **perlu verifikasi visual** untuk #2, #8, #12, #17, #20.

## 4. Halaman yang diusulkan dikecualikan (card dan/atau bottom tab)

| Halaman | Dikecualikan dari | Alasan |
|---------|-------------------|--------|
| finance-summary (semua tabel) | card | Thead bertingkat/colspan/rowspan, pivot, kolom beku `.col-sticky-left`, tfoot dihitung ulang JS; banyak kolom lintas-bulan |
| budget-request (`tblCA/CP/DB`) | card (tahan; bottom tab **ya**) | Visibilitas kolom manual per user + tabel di-reload AJAX (DOM diganti) + sticky kiri/kanan + Responsive sengaja off; risiko regresi tinggi untuk modul keuangan utama |
| invoice-cash-receipt/index | card (tahan) | 16 kolom, sticky kiri/kanan, tfoot total; baru dimigrasi per-BAST |
| project/index (`projectTable`) | card (tahan) | ±21 kolom uang, sticky kiri/kanan, tfoot, modal 10 tab |
| trouble-ticket/index | card | Sudah punya mode card sendiri + filter DT khusus; hindari dobel |
| project-monitoring/show | card | Sudah punya `d-md-none`/mcard |
| Semua `<table>` statis di modal/drawer | card | Bukan DataTable; tanpa pemicu `.DataTable` → otomatis tak tersentuh |
| Tab di modal/drawer/nested (§3 "tidak") | bottom tab | Keputusan pemilik: tetap gulir mendatar |
| assignment/_board saat di dalam todo-list | bottom tab | Nested di section; hanya `assignment/index` yang jadi kandidat |

Tabel DataTable yang ber-tfoot (budget-plan, cash-management/tblLedger, daily-transaction, finance-summary, invoice, project; `closing/#tblClosingDetail` **tidak** punya tfoot): kebijakan card untuk tfoot belum ditetapkan — lihat pertanyaan #4.

## 5. Halaman yang mungkin butuh atribut manual

| Halaman | Atribut | Kapan |
|---------|---------|-------|
| trouble-ticket/index | `data-ts-card="off"` | Wajib (agar tak dobel dengan `.tt-mcard`) |
| project/index, invoice-cash-receipt/index, budget-request/_table, finance-summary | `data-ts-card="off"` | Bila heuristik tak otomatis mengecualikan (project/invoice satu thead tapi terlalu lebar/beku) |
| cost-register, beneficiaries, master-pic (10–11 kolom) | `data-short="..."` / `data-card` | Tentukan kolom judul & ringkasan card jika default (kolom ke-1/ke-2) tak tepat |
| assignment/_board di todo-list, cost-register/create, invoice-cash-receipt/show (tab di card header) | `data-ts-tabbar="off"` | Cegah bottom tab dari tab non-navigasi |
| cash-management | `data-ts-card` per tabel | Dua tabel di tab berbeda — pastikan keduanya diproses saat tab dibuka |
| master-data (mode compact: responsive off) | `data-card` / `data-ts-card="off"` | Tabel tiga kolom; card mungkin tak perlu |

## 6. Pertanyaan terbuka untuk pemilik

1. **Finance Summary "14 tabel"**: di Blade hanya 5 tabel (+1 partial), 2 DataTable. Dari mana angka 14 (tabel yang dirender JS)? Perlu daftar pasti bila mau dicakup.
   - **Rekomendasi Reviewer/Evaluator:** Ya, telusuri; sementara tabel pivot/grup tetap dikecualikan otomatis. (Jawaban pemilik: "ya".)
2. **Responsive extension vs card mode**: 20+ halaman memakai `responsive:{details:{type:'inline'}}` (child row). Di ≤640px card menggantikan itu — apakah Responsive boleh dibiarkan aktif (tak terlihat) atau perlu dinonaktifkan? Tanpa mengubah init per halaman, hanya bisa disembunyikan lewat CSS/JS global.
   - **Rekomendasi Reviewer/Evaluator:** Default: Responsive tetap aktif tetapi disembunyikan lewat CSS di mode card (card menampilkan semua kolom). Tidak diberi jawaban eksplisit pemilik.
3. **Tab pindah-URL (master-data, beneficiaries, master-pic)**: apakah dijadikan bottom tab juga? Item master-data bisa banyak (labels panjang), tab ini reload halaman.
   - **Rekomendasi Reviewer/Evaluator:** Default: ikut bottom tab hanya bila `.nav-tabs/.nav-pills` pertama di `.content`; tab pindah-URL diperlakukan sama. Belum dijawab pemilik.
4. **tfoot (baris total)** di tabel layak-card (budget-plan, daily-transaction, cash-management/tblLedger; bukan closing — `#tblClosingDetail` tidak punya tfoot): disembunyikan, dijadikan card ringkasan terpisah, atau tabel tersebut dikecualikan?
   - **Rekomendasi Reviewer/Evaluator:** Dijadikan summary card di posisi paling atas pada mode mobile (jawaban pemilik).
5. **Tahan 4 tabel besar** (budget-request, invoice, project, finance-summary) sebagai pengecualian permanen, atau rollout ke Fase terakhir?
   - **Rekomendasi Reviewer/Evaluator:** Tidak dikecualikan permanen; dikerjakan terakhir (jawaban pemilik, #3 dan #5 di pesan pemilik).
6. **Batas bottom tab**: maksimum item (profile punya 6, invoice show 5, project modal 10) — batasi 5 + "Lainnya", atau gulir mendatar di bar?
   - **Rekomendasi Reviewer/Evaluator:** Tanpa batas jumlah item, tanpa "Lainnya". Soal geser: pesan pemilik ambigu ("tidak gulir mendatar" vs "gulir ke samping") — menunggu klarifikasi.
7. **Tab di card header** (cost-register/create, invoice-cash-receipt/show, team/show): apakah ikut jadi bottom tab?
   - **Rekomendasi Reviewer/Evaluator:** Default: tab di card header tidak otomatis jadi bottom tab (`data-ts-tabbar` bila perlu). Belum dijawab pemilik.
8. **Tabel lazy/AJAX & tab tersembunyi** (budget-request, cash-management, finance-summary): pemicu pemasangan mode card sebaiknya MutationObserver + event `draw.dt`; setuju?
   - **Rekomendasi Reviewer/Evaluator:** Default: pasang lewat event `init.dt`/`draw.dt` (dan MutationObserver untuk tfoot). Belum dijawab pemilik.
9. **Rollout bertahap** — urutan diusulkan: master sederhana (account, bank, cluster, customer, groups, role, source-fund, stage, user, delivery-note, payroll-run, feedback) → sisanya. Setuju?
   - **Rekomendasi Reviewer/Evaluator:** Setuju: master sederhana dulu → sisanya → 4 tabel besar terakhir (jawaban pemilik).

> Rekomendasi di atas diringkas dari jawaban pemilik yang diteruskan sesi induk dan default dokumen ini. **Keputusan akhir tetap pada pemilik.** Penomoran jawaban pemilik tidak persis sama dengan nomor pertanyaan di sini; pemetaan di atas berdasarkan isi jawaban.

## 6b. Ditahan menunggu persetujuan pemilik

- **Bottom tab (Fase 3):** `tesys-tabbar.js`, CSS bagian 3, `<script>` di layout, dan pembungkus `data-ts-tabbar="off"` di `todo-list/index` dikeluarkan dari repo; salinan di scratchpad sesi (`tabbar-hold/`), bukan di repo.
  - Catatan: bar navigasi bawah **global** (`layouts/partials/bottom-nav.blade.php`, mobile.css §19; lihat `docs/architecture.md` bagian UI) adalah fitur berbeda — jalan pintas menu aplikasi, bukan tab halaman — dan **tidak menggantikan** Fase 3 yang ditahan ini. Tab halaman: saat bar tampil strip yang dicantumkan dropup Tabs disembunyikan, selain itu tetap di atas (gulir mendatar, opt-in `.ts-tabs-sticky`); `tesys-tabbar.js` tidak dihidupkan.
- **Desktop DAFTAR DATA:** usulan membuang border kiri/kanan semua DataTable di desktop dikeluarkan. Potongan CSS-nya (untuk disetujui nanti):

```css
@media (min-width: 641px) {
    .card-body > .table-responsive > .dataTables_wrapper > table.dataTable.table-bordered { border-left: 0; border-right: 0; }
    /* sel kolom pertama: border-left 0; sel kolom terakhir: border-right 0 (thead/tbody/tfoot) */
}
```

- Di desktop, mode card tidak mengubah apa pun selain `.ts-card-summary { display:none }`. Kartu ringkasan tfoot berada di posisi paling atas daftar (mobile).
- Batasan mode card: sel `tbody` ber-`colspan` di tengah baris tidak didukung (dilewati, tanpa label).

## 6c. Perbaikan lanjutan (keputusan pemilik)

- Daftar `TAHAN` dihapus: CR (`#tblCA/#tblCP/#tblDB`), `#tblInvoice`, `#projectTable` ikut mode card. Dikecualikan tetap: Finance Summary (thead bertingkat/pivot, `.col-pic-table`) dan Trouble Ticket (`data-ts-card="off"`).
- Struktur listing = margin halaman → tabel (tanpa bingkai kartu), desktop & mobile. Pengecualian: `.ts-keep-card` (belum dipakai di halaman mana pun), kartu modal/drawer, kartu berisi `.col-pic-table`.
- Akar masalah "card belum tampil" (diagnosa **statis**, bukan dari aplikasi sungguhan): CR/Invoice/Project = ditahan oleh `TAHAN` (terkonfirmasi dari kode dan tangkapan layar pemilik). Selain itu, aturan sticky halaman memakai ID + `!important` (`.inv-hscroll #tblInvoice td.action-column`, `.po-hscroll #projectTable ...`, `.cr-hscroll .cr-tbl ...`) yang akan mengalahkan CSS card berspesifisitas rendah → diperbaiki dengan `:not(#ts-card)`. Bank/Budget Plan: tidak ditemukan aturan yang mengalahkan card; penyebab pasti **belum bisa dipastikan** tanpa menjalankan aplikasi.
- Temuan harness: `box-sizing` baris (tr) harus `border-box` (width 100% + padding membuat card meluber), dan `right` chip status harus `!important` karena sel memakai `right:auto !important`.

## 7. Belum diverifikasi

- Tidak ada halaman yang dibuka di browser; level tab (halaman vs modal) disimpulkan dari konteks baris (skrip heuristik) — #2, #8, #12, #17, #20 perlu cek visual.
- Jumlah kolom `th` dari parsing teks; thead dinamis/JS-rendered (project/index modal, tabel budget-request di `_detail_modal`) tidak dihitung.
- Tidak membaca `tesys-ui-mobile.css`/`tesys-row-actions.js` secara penuh; interaksi `.ts-actions` dengan card belum dianalisis.
- Skrip pembaca inventaris (sementara) tidak disimpan di repo.

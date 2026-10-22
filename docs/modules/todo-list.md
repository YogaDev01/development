# Todo List

Antrean kerja lintas-role (approval Cost Request, Budget Plan, Treasury, Purchase
Order, Invoice, Collection) plus Assignment. Tampil di
`/todo-list` dan blok "Perlu Tindakan" di Dashboard; badge sidebar dan pengingat
terjadwal memakai definisi antrean yang sama.

## Sumber definisi antrean (satu)

`App\Services\TodoService::QUEUES` — per antrean: role penangan, status dokumen,
dan apakah ikut hitungan total. Dipakai oleh:

| Pemakai | Cara |
|---|---|
| Halaman Todo List / Dashboard | `TodoListController::buildWorklistData()` → gerbang `TodoService::allows()`, status `TodoService::statuses()`, query dasar `TodoListController::queueQuery()` |
| Badge sidebar (`todo-list.has-pending`) | `TodoService::hasPending()` → `TodoListController::pendingCounts()` (hanya `count()`, tanpa CoA/summary/respon time) |
| Pengingat `todo:remind` (`SendTodoReminders`) | `TodoService::pendingCounts()` / `pendingTotal()` |

`TodoService::build()` (daftar item gaya lama: APPROVED = verifikasi Finance,
REALIZED = Finance, dll.) dihapus; pemakainya hanya `has-pending` dan `todo:remind`.

`hasPending` = ada antrean berisi di luar `treasuryBatchSecond` — sama dengan
`$hasAnyTask` di halaman. Ada 25 antrean di `TodoService::QUEUES` (termasuk `poVerified` dan `ticketRequestReview`; tabel di bawah belum memuat `ticketRequestReview`).

## Antrean per role

| Antrean (key) | Role | Status / syarat |
|---|---|---|
| head | superadmin, head | CR SUBMITTED |
| cost_control_checked | superadmin, finance, finance_manager, cost_control | CR CHECKED |
| ceo | superadmin, approver, guest | CR VERIFIED |
| managerRejected | superadmin, manager, supervisor | CR REJECTED |
| managerPaid | superadmin, manager, supervisor | CR PAID, SETTLE_REJECTED |
| headSettlementCheck | superadmin, head | CR REALIZED |
| financeSettlementVerify | superadmin, finance, finance_manager, cost_control | CR SETTLE_CHECKED |
| ceoSettlementClose | superadmin, finance_manager | CR SETTLE_VERIFIED |
| approverSecondApprove | superadmin, approver | CR SETTLE_APPROVED |
| secondPaymentPending | superadmin, treasury, finance, finance_manager | CR dengan Second Payment PENDING |
| treasuryBatchCreate | superadmin, treasury, finance, finance_manager | CR APPROVED belum di batch aktif |
| treasuryBatchSecond | idem | Second Payment `availableForBatch()` (tidak masuk total) |
| treasuryProcess | superadmin, approver, guest | Batch CASH_OUT PENDING_APPROVER |
| treasuryReadyToPay | superadmin, treasury, finance, finance_manager | Batch CASH_OUT DONE |
| budgetingHeadCheck | superadmin, head | Budget Plan SUBMITTED |
| budgetingVerify | superadmin, finance, finance_manager | Budget Plan CHECKED |
| budgetingCeoApprove | superadmin, approver, guest | Budget Plan VERIFIED |
| budgetingClosingDue | superadmin, manager | CLOSING & `closing_submitted_at` null (tidak lagi dihasilkan otomatis sejak auto-closing Plan Finish dihapus; hanya menampilkan sisa data lama) |
| budgetingRejected | superadmin, manager | Budget Plan REJECTED |

Budget Plan bundled (`via_po_approval = 1`) dikecualikan dari `budgetingHeadCheck`,
`budgetingVerify`, `budgetingCeoApprove`, `budgetingRejected` dan angka
DRAFT/SUBMITTED/CHECKED/VERIFIED pada Summary Task (satu `where('via_po_approval', false)`
di `TodoListController::queueQuery()` / `buildSummaryTable()`): tugasnya sudah muncul
sebagai tugas PO, jadi tidak digandakan. `budgetingClosingDue` tetap memuatnya.
| poSubmitted | superadmin, finance, finance_manager | PO SUBMITTED |
| poVerified | superadmin, approver | PO VERIFIED (menunggu `ProjectController::approve()`; BP bundled ikut disetujui, tidak digandakan di `budgetingCeoApprove`). Section `#tl-po-verified`, key actionQueues `po-verified`, baris Summary Task "Approval Approver (PO VERIFIED)" |
| invoiceVerify | superadmin, finance_manager, accounting | Invoice SUBMITTED |
| invoiceApprove | superadmin, finance_manager | Invoice VERIFIED |
| collectionWaitingInvoice | superadmin, collection | lihat di bawah |

Accounting adalah pemilik tahap **Verifikasi Invoice** (selaras route
`invoice-cash-receipt.verify`: `superadmin, finance_manager, accounting`); tidak
ada lagi role "Coming Soon". Bulk `invoice-verify` mengikuti middleware `role:`
route tersebut.

### Collection — Waiting Invoice

No. PO yang punya **BAST aktif yang belum tertagih** (tidak punya invoice
non-CANCELLED). No. PO lama tanpa baris BAST tetap memakai aturan lama (status
PARTIAL / BAST_SUBMITTED). Aging dari `tanggal_bast` tertua yang belum tertagih
(No. PO lama: `bast_submitted_at`).

## Aturan scope (K3)

Role **tanpa** batas Access Group: `superadmin, approver, accounting, treasury,
finance, finance_manager, collection, cost_control`
(`TodoService::AKSES_PENUH_ROLES`). Role lain — termasuk head, manager,
supervisor, guest — dibatasi Access Group untuk dokumen proyek.

- Cost Request: `scopeByRole()` + (`TodoListController::scopeCrTodo()`) pembatasan
  proyek tambahan untuk `admin` dan `guest`, yang oleh `scopeByRole()` diperlakukan
  istimewa di kode padahal bukan K3.
- Budget Plan: `scopeVisibleTo()` (tidak diubah).
- Batch Treasury "Menunggu Proses": untuk role non-K3 (guest) batch hanya terlihat
  bila ada CR di dalamnya yang lolos `scopeCrTodo()`.

### Selisih daftar privileged di kode vs K3 (temuan, TIDAK diubah)

| Lokasi | Selisih terhadap K3 |
|---|---|
| `BuildsCrWorklist::scopeByRole()` (Cost Request) | kelebihan `admin`, `guest` |
| `ScopesBudgetPlanVisibility::BP_PROJECT_PRIVILEGED_ROLES` | kelebihan `admin` (guest sudah dibatasi) |
| `BuildsCrWorklist::OVERHEAD_PRIVILEGED_ROLES` / `PAYROLL_ROLES`, `BP_OVERHEAD_*` | daftar overhead/payroll terpisah (bukan Access Group proyek); tidak dibandingkan dengan K3 |
| `AccessRoles` | tidak memuat konstanta Todo List/K3 (tidak ada selisih yang bisa dibandingkan) |

Menyamakan helper global (`scopeByRole` dkk.) adalah keputusan terpisah.

## Ketergantungan teknis TodoService

`TodoService::pendingCounts()`/`hasPending()` memanggil
`TodoListController::pendingCounts()`/`anyPending()` lewat `app()`, karena
scope visibilitas (`scopeByRole`, `scopeVisibleTo`) hidup sebagai trait di
controller dan membaca `Auth::user()`. Untuk user yang bukan pengguna sesi
(mis. command `todo:remind`) controller memanggil `Auth::setUser($user)`
sementara lalu memulihkannya (`TodoListController::sebagai()`). Akibatnya
service ini tidak boleh dipanggil dari konteks yang membagi state Auth
secara bersamaan (queue worker multi-user dalam satu proses) tanpa membaca
catatan ini; memindahkan scope ke service adalah pekerjaan terpisah.

`hasPending()` berhenti di antrean pertama yang berisi (`exists()`), di-cache
60 detik per user + role efektif (`todo-list.has-pending.u{id}.r{roleId}`).
Definisi "ada tugas" identik dengan `$hasAnyTask` di halaman. Artinya badge
bisa tertinggal sampai 60 detik setelah dokumen diproses.

`php artisan todo:orphan-report` (baca-saja) melaporkan dokumen antrean yang
tak terlihat oleh handler mana pun karena scope Access Group.

## Collection — pengecualian invoice lama

No. PO dengan invoice AKTIF (bukan CANCELLED/soft-deleted) yang belum tertaut
BAST (`project_po_bast_id` NULL) tidak muncul di Collection — Waiting Invoice,
memakai batasan yang sama dengan `ProjectPoNumber::punyaInvoiceLamaBelumTertaut()`
(`ProjectPoNumber::batasInvoiceLamaBelumTertaut()`). Pengecualian ini hanya
berlaku pada cabang **BAST**; No. PO lama tanpa baris BAST (status PARTIAL /
BAST_SUBMITTED) tetap tampil walau invoice termin-nya belum tertaut.

## Keputusan pemilik

| Keputusan | Status |
|---|---|
| BAST sisa (origin `SPLIT_SISA`) tetap tampil di Collection. Kodenya tidak membaca `origin`: BAST yang belum punya invoice aktif tampil apa pun asalnya | **Disetujui pemilik** |
| Batch Treasury "Menunggu Proses" yang hanya berisi Cost Register (tanpa CR) tersembunyi dari guest | **Disetujui pemilik** |
| Role accounting memakai menu `todo-list` — dikonfigurasi di data Role (`role_menu_items`), bukan di kode | **Disetujui pemilik** |
| Aturan Assignment, jendela respon time 90 hari, batas bulk 50 | **Disetujui pemilik** (rincian di `docs/workflows/todo-list-assignment.md`) |
| Butir lain dokumen ini yang tidak tercantum di atas (cache `hasPending`, `todo:orphan-report`, format export) | Menunggu persetujuan pemilik |

## Perilaku lama yang diketahui (tidak diubah)

- Blade baris Collection memanggil `remainingValue()` per No. PO (satu query per
  baris) — perilaku lama. Export sudah memakai satu query agregat.
- `DailyTransaction::total_amount` (kolom nilai batch) menjalankan satu query per
  batch — accessor lama yang dipakai halaman dan export.

## Export

`/todo-list/export` mencakup semua antrean yang tampil: kolom Modul, Tahap,
Nomor, Referensi (PO/Budget Plan), Deskripsi, Pemohon/Pemilik, Nilai (rupiah
integer, kosong bila tidak relevan), Status, Aging. Sumber baris =
`buildWorklistData()` (koleksi yang sama dengan halaman). **Assignment tidak
pernah ikut export** (keputusan pemilik), dengan maupun tanpa filter. Label Tahap = judul antrean di halaman. Penyaringan dari
browser memakai kunci `modul:id` (`cr`, `bp`, `sp`, `dt`, `po`, `ponum`,
`inv`) lewat atribut `data-export-key`; kunci hanya mempersempit. Id polos
format lama (halaman belum dimuat ulang) ditolak dengan pesan "muat ulang
halaman", bukan menghasilkan file kosong. Bila filter di halaman aktif tetapi
tidak ada baris yang lolos, muncul pesan "Tidak ada baris untuk diekspor" dan
tidak ada unduhan; export penuh (GET) hanya terjadi tanpa filter.

## KPI "Tindakan Selesai Hari Ini"

Transisi yang dilakukan user hari ini: `request_status_logs.acted_by`,
`budget_plan_logs.user_id`, `project_logs.user_id`, plus kolom pelaku pada
`daily_transactions` (`approved_by/at`, `paid_by/at`) dan `invoices`
(`verified/approved/sent/received_by/at`). Daftar tidak punya tabel log sendiri.

## Respon Time & Summary Task

Hanya dimuat halaman Todo List (bukan Dashboard). Respon time dibatasi 90 hari
terakhir (`RESPONTIME_DAYS`) dan di-cache global 10 menit
(`todo-list.respontime.v1`) karena angkanya lintas-user.

## Bulk

Maksimum 50 id per request (`BULK_MAX_IDS`). Item dianggap gagal bila aksi
tujuan memasang session `error`/`warning`, mengembalikan JSON gagal, atau
respons non-redirect.

## Assignment

Selaras dengan `docs/workflows/todo-list-assignment.md` (semua disetujui pemilik):

- Tugas ke diri sendiri dilarang (`store()`).
- Pengalihan ke pemberi tugas dilarang (`update()`).
- Tenggat masa lalu dilarang: saat membuat `due_date` ≥ hari ini; saat edit,
  tanggal yang tidak berubah boleh tetap lampau, tanggal yang diganti tidak boleh.
- Ganti penerima → status kembali PENDING, `read_at`/`started_at` dikosongkan,
  log `reassign`, notifikasi ke penerima baru dan lama (K4).
- Transisi start/done/cancel/reopen/edit memakai update bersyarat pada status
  asal; bila 0 baris berubah → "Status sudah berubah, muat ulang."
- Todo List menampilkan tugas Closed/Cancelled 30 hari terakhir; `/assignment`
  menampilkan semua. Prefetch browser tidak menandai tugas "dibaca".
- Jendela respon time 90 hari, batas bulk 50, KPI "Tindakan Selesai Hari Ini",
  dan summary/respon time hanya di halaman Todo List juga disetujui.
- Assignment tidak ikut export Todo List.

## Project Monitoring dikeluarkan dari Todo List

Project Monitoring (reminder "Milestone Tertunda") dikeluarkan dari Todo List
atas permintaan pemilik (alasan: performa). Dampaknya: tidak ada antrean
`milestoneOverdue`, section "Milestone Tertunda", item Dashboard "Milestone Lewat
Tenggat", baris export, maupun hitungan badge sidebar dan pengingat
`todo:remind`. Modul Project Monitoring sendiri tidak berubah.

## Deploy manual

- Pastikan `role_menu_items` role **accounting** memuat menu `todo-list` (data
  DB lewat halaman Role, bukan kode). Tanpa itu antrean Verifikasi Invoice tidak
  terjangkau oleh accounting.

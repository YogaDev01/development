# Daftar istilah TESYS

Nama yang dilihat pengguna dan nama yang dipakai kode tidak selalu sama. Itu
sisa perjalanan: modul berganti nama di UI setelah tabel dan route-nya sudah
telanjur dipakai, dan menggantinya belakangan berarti migrasi tabel + perubahan
URL yang sudah tersimpan di bookmark orang. Daftar ini merapikan pemetaannya
supaya tidak perlu ditebak.

**Aturan untuk kode baru:** ikuti istilah UI. Jangan menambah nama ketiga.

| Istilah di UI        | Route            | Controller                       | Tabel               | Catatan |
|----------------------|------------------|----------------------------------|---------------------|---------|
| Purchase Order (PO)  | `/project`       | `ProjectController`              | `projects`          | "Project" di kode = PO di UI |
| No. PO               | —                | —                                | `project_po_numbers`| Satu PO bisa punya beberapa No. PO |
| Budget Plan          | `/budget-plan`   | `BudgetPlanController`           | `budget_plans`      | — |
| Cost Request (CR)    | `/budget-request`| `BudgetRequestController`        | `budget_request`    | Nama tabel tunggal, bukan jamak |
| Rincian Cost Request | —                | —                                | `request_detail`    | Tunggal juga |
| Daily Transaction    | `/daily-transaction` | `DailyTransactionController` | `daily_transactions`| Batch pembayaran CR |
| Invoice & Cash Receipt | `/invoice-cash-receipt` | `InvoiceCashReceiptController` | `invoices`, `cash_receipts` | — |
| Surat Jalan          | `/delivery-note` | `DeliveryNoteController`         | `delivery_notes`    | — |
| Project Monitoring   | `/project-monitoring` | `ProjectMonitoringController`| `project_details`   | Milestone per PO |
| Todo List Request    | `/todo-list`     | `TodoListController`             | —                   | Agregasi approval lintas modul |

## Istilah keuangan

| Istilah | Arti |
|---------|------|
| **NOB** (Not On Budget) | Pengajuan melampaui sisa anggaran. Disimpan sebagai kolom `is_nob` + `nob_amount`, bukan awalan teks pada deskripsi. |
| **Committed** | Anggaran sudah terikat (CR sejak Submitted sampai Closed). Dipakai pengecekan NOB. Lihat `App\Support\BudgetConsumption`. |
| **Disbursed** | Uang sudah keluar (Paid ke atas). Dipakai angka "Used" pada Budget Plan. |
| **Reported** | Terpakai menurut laporan (sejak Verified). Dipakai Finance Summary & Report. |
| **Captured Total** | Nilai invoice setelah pajak — yang ditagihkan ke customer. |
| **Deduction** | Pengurang nilai tagihan pada baris invoice. |
| **Settlement** | Pertanggungjawaban Cash Advance setelah uang dipakai. |
| **BAST** | Berita Acara Serah Terima — prasyarat penerbitan invoice. |
| **Additional** | Invoice melebihi sisa nilai kontrak PO (peringatan, bukan penghalang). |

## Peran

| Peran | Perannya dalam alur |
|-------|---------------------|
| `manager` | Mengajukan Cost Request. Dibatasi Access Group. |
| `head` | Pemeriksa tahap pertama (Submitted → Checked). Dibatasi Access Group. |
| `finance`, `finance_manager`, `cost_control` | Verifikasi (Checked → Verified). Melihat semua CR. |
| `approver` | Persetujuan akhir (Verified → Approved). Melihat semua CR. |
| `collection`, `treasury`, `accounting` | Peran Finance khusus; melihat semua CR lintas PO. |
| `superadmin` | Semua akses + fitur "View As" untuk meninjau UI sebagai peran lain. |
| `hr` | Satu-satunya peran non-Finance yang boleh melihat CR Payroll. |

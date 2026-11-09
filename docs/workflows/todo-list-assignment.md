# Todo List & Assignment — keputusan perilaku (Bagian A audit)

Status: **seluruh butir di bawah sudah disetujui pemilik** (ganti penerima
mereset status = keputusan K4).

## Assignment

| Keputusan | Perilaku | Lokasi | Status |
|---|---|---|---|
| Tugas ke diri sendiri dilarang | `store()` menolak `assignee_id` = pembuat | `AssignmentController::store` | Disetujui pemilik |
| Pengalihan ke pemberi tugas dilarang | `update()` menolak `assignee_id` = `assigner_id` tugas itu | `AssignmentController::update` | Disetujui pemilik |
| Tenggat masa lalu dilarang | `store()`: `due_date` ≥ hari ini. `update()`: tanggal yang tidak berubah boleh tetap lampau; tanggal yang diganti tidak boleh lampau (dibandingkan per tanggal) | `AssignmentController` | Disetujui pemilik |
| Ganti penerima mereset status | status → PENDING, `read_at`/`started_at` dikosongkan, log `reassign`, notifikasi ke penerima lama & baru | `AssignmentController::update` | Disetujui pemilik |
| Transisi bersyarat | start/done/cancel/reopen/edit memakai `UPDATE ... WHERE id AND status IN (status asal)`; 0 baris → "Status sudah berubah, muat ulang." | `AssignmentController::transisi` | Disetujui pemilik |
| Prefetch tidak menandai "dibaca" | `logs()` melewati transisi READ bila header `Sec-Purpose`/`Purpose` memuat `prefetch` | `AssignmentController::logs` | Disetujui pemilik |

## Todo List

| Keputusan | Perilaku | Lokasi | Status |
|---|---|---|---|
| Riwayat Assignment 30 hari | Di Todo List tugas CLOSED/CANCELLED hanya 30 hari terakhir (`RIWAYAT_HARI`), tugas terbuka selalu tampil; `/assignment` menampilkan semua | `AssignmentController::listsFor` | Disetujui pemilik |
| Jendela respon time 90 hari | Hanya transisi yang terjadi dalam 90 hari terakhir (`RESPONTIME_DAYS`) yang dihitung; selisih dihitung dari pendahulu dokumen walau pendahulunya di luar jendela. Cache global 10 menit (`todo-list.respontime.v1`) | `TodoListController::buildRespontimeTable` | Disetujui pemilik |
| Batas bulk 50 | `ids` wajib, maksimum 50 per request (`BULK_MAX_IDS`); JS menolak lebih dari 50 sebelum submit | `TodoListController::bulk` | Disetujui pemilik |
| KPI "Tindakan Selesai Hari Ini" | Lintas modul: `request_status_logs`, `budget_plan_logs`, `project_logs`, plus kolom pelaku `daily_transactions` dan `invoices` | `TodoListController::completedTodayCount` | Disetujui pemilik |
| Aksi inline semua section approval | PO Approval Finance (Verify/Revise), PO Approval Approver (Approve/Revise; Approve = keputusan APPROVE, status akhir ACTIVE/WAITING_PO dipilih sistem, Early Start tetap di halaman PO), PO Verifikasi Closing (Verify/Reject Closing), Budgeting Verifikasi Closing (Verify/Reject Closing), Treasury Draft (Ajukan), Invoice Verify/Approve (+ Revise). Semua lewat `todo-list.bulk` (`BULK_ROUTES`) yang memanggil aksi satuan modulnya; `BULK_EXPECTED_STATUS` menolak dokumen yang statusnya tidak sesuai tahap section. Mark Paid Treasury tetap link-out (wajib bukti transfer) | `TodoListController::bulk`, `todo-list/index.blade.php` | Disetujui pemilik 2026-11-09 |
| Summary & respon time hanya di halaman | Dashboard (`actionQueues`) tidak menghitungnya; masing-masing punya try sendiri di `index()` | `TodoListController` | Disetujui pemilik |

## Catatan: Project Monitoring dikeluarkan

Project Monitoring (reminder "Milestone Tertunda") dikeluarkan dari Todo List atas
permintaan pemilik (alasan: performa); modul Project Monitoring tidak berubah.
Assignment tidak ikut export Todo List.

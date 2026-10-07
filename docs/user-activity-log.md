# User Activity Log (Audit Trail)

Audit trail aktivitas bisnis user lintas module, ditampilkan di halaman Profil
(tab **Activity Log**). Immutable, tanpa UI CRUD. Actor selalu diambil dari
authenticated user/session — tidak pernah dari input request.

## Komponen

| Bagian | Lokasi |
|---|---|
| Tabel | `user_activity_logs` (migration `2026_10_07_000001`) |
| Model + helper | `app/Models/UserActivityLog.php` |
| Tampilan (partial) | `resources/views/profile/_activity_log.blade.php` |
| Tab Profil | `resources/views/profile/show.blade.php` (tab "Activity Log") |
| Halaman superadmin | `resources/views/user/activity-log.blade.php` + `UserController@activityLog` |

## Cara mencatat

```php
use App\Models\UserActivityLog;

UserActivityLog::record(
    module: 'treasury',          // key App\Models\UserActivityLog::MODULES
    action: 'pay',               // key App\Models\UserActivityLog::ACTIONS
    description: 'Menandai batch #BT-0012 telah dibayar (PAID)',
    subject: $batch,             // opsional; reference auto dari code/number
    notes: null,                 // opsional
);
```

- **Gagal-aman:** `record()` dibungkus try/catch. Jika pencatatan error,
  transaksi bisnis TIDAK ikut gagal (hanya `Log::warning`).
- `reference` diisi otomatis dari atribut `code`/`number`/… objek `$subject`,
  atau bisa dioverride lewat argumen keenam.
- Jangan pernah menulis password/token/credential ke `description`/`notes`.

## Modul yang sudah terintegrasi (lengkap)

Lewat **hook di `addLog()` model** (satu titik mencakup semua transisi):
- **Cost Request** — `BudgetRequest::addLog()` (submit/approve/reject/revise/
  cancel/pay/settle/close/takeout/restore, termasuk Second Payment).
- **Budget Plan** — `BudgetPlan::addLog()`.
- **Purchase Order** — `Project::addLog()` (submit/approve/reject/revise/
  activate/BAST/closing/closed).
- **Delivery Note** — `DeliveryNote::addLog()` (SENT/RECEIVED).

Lewat **`record()` inline di controller**:
- **Autentikasi** — login & logout (`AuthController`).
- **Profil** — update info & foto (`ProfileController`).
- **Treasury** — buat/setujui/tolak/bayar batch (`DailyTransactionController`).
- **Invoice** — create/update/delete/submit/verify/approve/revise/received/
  cancel (`InvoiceCashReceiptController`).
- **Cash Receipt** — catat/settle/hapus (`InvoiceCashReceiptController`) +
  create/allocate/cancel/delete (`CashReceiptController`).
- **Cash Management** — create/update/post/cancel/delete (`CashManagementController`).
- **Closing** — susun rekap/tutup/buka kembali (`ClosingController`).
- **Payroll** — create/update/submit/verify/approve/reject/revise/pay/close/
  delete (`PayrollRunController`).
- **Cost Register** — create/submit/cancel/delete (`CostRegisterController`).
- **Forecast** — create/update/delete/activate/realisasi/close (`ForecastController`).
- **User & Access** — user CRUD (`UserController`), role menu access
  (`RoleController`), Access Group CRUD (`AccessGroupController`).
- **Master Data** — Beneficiary, Customer, Account, Cost Center, Source Fund,
  Bank, Department, Karyawan, Master PIC, Cluster, Cluster Group, Stage, Team
  (create/update/delete masing-masing).

### Pola jika menambah modul baru

- Model yang punya `addLog()` terpusat → tambahkan satu panggilan `record()`
  di dalamnya (seperti CR/Budget Plan/Project/Delivery Note).
- Controller CRUD master biasa → tambahkan `record('master_data', 'create|update|delete', …)`
  di `store`/`update`/`destroy` (seperti `BeneficiaryController`).

## Otorisasi

- Setiap user melihat log-nya sendiri di halaman Profil.
- **Hanya superadmin** yang dapat melihat log user lain, via tombol
  "Activity Log" di modal detail User & Access → `/user/{id}/activity-log`
  (dijaga `isRealSuperadmin()` di controller).

## Performa

Index pada `user_id+created_at`, `module`, `action`, `subject_type+subject_id`,
`created_at`. Query selalu paginasi server-side (`feedFor()`, 25/halaman),
tidak pernah memuat seluruh log lalu memfilter di PHP.

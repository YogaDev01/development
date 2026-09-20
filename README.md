# TESYS — Terex System

ERP internal PT Terex. Satu aplikasi Laravel yang menangani rantai kerja
keuangan proyek dari awal sampai tutup buku:

```
Purchase Order  ->  Budget Plan  ->  Cost Request  ->  Pembayaran (Daily Transaction)
      |                                                       |
      +--> Invoice & Cash Receipt --> Surat Jalan --> Project Monitoring
```

Modul pendukung: Payroll Run, Finance Forecast, Cost Register, Todo List
(agregasi approval lintas modul), Finance Summary, Reports, Global Search, dan
master data (Customer, Beneficiary, Bank, Cluster, Cost Center, Source Fund,
Request Type, Stage, PIC, Team, User/Role/Access Group).

---

## Ringkasan teknis

| Lapis        | Isi                                                                 |
|--------------|---------------------------------------------------------------------|
| Runtime      | PHP 8.3, Laravel 13, MySQL 8                                        |
| Frontend     | Blade + jQuery + AdminLTE 3/Bootstrap 4 di atas lapis `tesys-ui`    |
| Tabel        | DataTables (sisi peramban), dibatasi 1.000 baris per halaman daftar |
| Otorisasi    | Middleware `role:` dan `menu:`, Access Group per proyek/cost center |
| Berkas       | Disk privat + route ber-otentikasi (`/storage/{path}`)              |
| Antrean      | Driver `database` — notifikasi dikirim lewat worker                 |
| Uji          | PHPUnit, basis data MySQL terpisah (`tesys_test`)                   |

---

## Menyiapkan lingkungan pengembangan

```bash
git clone <repo> tesys && cd tesys
composer install
cp .env.example .env
php artisan key:generate
```

Isi `.env` seperlunya (lihat tabel di bawah), buat basis datanya, lalu:

```bash
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=MenuItemSeeder
php artisan serve
```

**Jangan** menjalankan `php artisan storage:link`. Lampiran sengaja tidak
dilayani langsung oleh web server — lihat [Berkas & lampiran](#berkas--lampiran).

### Kunci `.env` yang perlu diperhatikan

| Kunci                  | Keterangan                                                                    |
|------------------------|-------------------------------------------------------------------------------|
| `APP_DEBUG`            | **Wajib `false` di produksi.** Jejak galat memuat isi query dan konfigurasi.   |
| `SESSION_SECURE_COOKIE`| **Wajib `true` di produksi** (HTTPS).                                          |
| `LOG_CHANNEL`          | `daily` — berkas log tunggal tanpa rotasi pernah tumbuh sampai 9 MB.           |
| `QUEUE_CONNECTION`     | `database`. Worker wajib jalan, jika tidak notifikasi tidak pernah terkirim.   |
| `EXPORT_API_TOKEN`     | Token API ekspor Cost Request. Kosongkan untuk mematikan endpoint-nya.         |
| `FONNTE_TOKEN`         | Notifikasi WhatsApp (opsional). Tanpa ini, notifikasi hanya masuk lonceng app. |

---

## Menjalankan uji

Uji memakai MySQL (bukan sqlite) karena sebagian migrasi memakai sintaks khusus
MySQL. Siapkan sekali:

```sql
CREATE DATABASE tesys_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan test
```

Basis data uji dibangun ulang setiap kali dijalankan. Yang ditanggung suite ini:

- akses berkas lampiran (`SecureFileAccessTest`)
- pembatasan percobaan login (`LoginThrottleTest`)
- pembatasan akses lintas Access Group (`CostRequestAccessTest`)
- alur approval + anti approval ganda (`CostRequestWorkflowTest`)
- perhitungan pemakaian anggaran / NOB (`BudgetConsumptionTest`)
- penomoran dokumen (`DocumentNumberingTest`)
- pembayaran & penghapusan Cash Receipt (`CashReceiptTest`)
- daftar penerima notifikasi per peran (`RoleDirectoryTest`)

Tambahkan uji untuk setiap perubahan yang menyentuh uang, status, atau hak
akses.

---

## Berkas & lampiran

Semua lampiran (invoice, BAST, bukti transfer, bukti settlement, dokumen
pendukung) disimpan di `storage/app/private` dan hanya bisa diunduh lewat route
`/storage/{path}` yang memeriksa sesi dan hak akses per modul
(`App\Services\FileAccess`).

Karena itu:

- `filesystems.links` sengaja kosong dan `storage:link` **tidak boleh**
  dijalankan — begitu `public/storage` ada, web server melayani berkasnya
  langsung tanpa pemeriksaan apa pun;
- `public/.htaccess` juga memaksa seluruh permintaan `/storage/...` masuk ke
  `index.php` sebagai lapis pengaman kedua;
- disk `local` memakai `'serve' => false`, karena bila `true` Laravel
  mendaftarkan route penyaji berkas tanpa otentikasi yang menang atas route
  aplikasi.

Menambah folder unggahan baru berarti menambah aturannya di `FileAccess`.
Folder tanpa aturan khusus jatuh ke aturan dasar: wajib login.

---

## Arsitektur yang perlu diketahui sebelum menyentuh kode

- **Status & alur** — kamus status ada di `App\Support\WorkflowStatus`;
  perpindahan status memakai `Concerns\TransitionsStatus` (transaksi + kunci
  baris). Jangan menulis `$model->update(['status' => ...])` langsung setelah
  memeriksa status: itu pola lama yang memungkinkan approval ganda.
- **Anggaran** — arti "terpakai" ada tiga dan berbeda: `BudgetConsumption::COMMITTED`
  (mengikat, dipakai pengecekan NOB), `DISBURSED` (sudah cair), `REPORTED`
  (laporan). Jangan menulis daftar status baru di tempat lain.
- **Nomor dokumen** — selalu lewat `App\Support\DocumentSequence`, tidak pernah
  `count() + 1`.
- **Keterlihatan data** — Cost Request memakai `Concerns\BuildsCrWorklist::scopeByRole()`,
  Invoice `scopeVisibleTo()`, Budget Plan `canViewBudgetPlan()`. Setiap aksi
  ber-`{id}` wajib memakai helper `findVisible*()` modulnya, bukan `findOrFail()`
  polos.
- **Notifikasi** — lewat `NotifyService`; daftar penerima per peran lewat
  `App\Services\RoleDirectory`.
- **Daftar besar** — `Concerns\CapsLargeLists` membatasi baris yang digambar;
  angka ringkasan tetap dihitung agregat dari seluruh data.

Dokumen operasional: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
Riwayat rilis: [CHANGELOG.md](CHANGELOG.md).

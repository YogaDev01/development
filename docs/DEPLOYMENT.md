# Deployment & operasional TESYS

Dokumen ini menjelaskan apa yang harus ada agar TESYS berjalan benar di server,
apa yang harus dijalankan saat merilis, dan apa yang harus dilakukan ketika
sesuatu rusak.

---

## 1. Yang wajib ada di server

| Komponen        | Kebutuhan                                                             |
|-----------------|-----------------------------------------------------------------------|
| PHP             | 8.3 dengan ekstensi `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`  |
| MySQL           | 8.x                                                                    |
| Web server      | Apache (memakai `public/.htaccess`) atau Nginx dengan aturan setara   |
| Cron            | satu entri untuk penjadwal Laravel                                     |
| Supervisor      | satu proses untuk `queue:work`                                        |

### Cron — penjadwal

```cron
* * * * * cd /path/ke/tesys && php artisan schedule:run >> /dev/null 2>&1
```

Tanpa ini, `forecast:auto-close` (menutup Finance Forecast yang lewat tanggal)
tidak pernah berjalan, dan forecast lama akan terus terhitung aktif.

### Supervisor — worker antrean

```ini
[program:tesys-queue]
command=php /path/ke/tesys/artisan queue:work --tries=3 --timeout=60 --sleep=3
directory=/path/ke/tesys
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/tesys-queue.log
```

**Ini bukan opsional.** Seluruh notifikasi (lonceng in-app dan WhatsApp) dikirim
lewat antrean. Tanpa worker, notifikasi menumpuk di tabel `jobs` dan approver
tidak pernah tahu ada yang menunggu persetujuannya — sementara aplikasinya
sendiri tampak baik-baik saja.

Periksa: `php artisan queue:monitor database:default --max=100`

### Nginx (bila tidak memakai Apache)

Aturan `public/.htaccess` yang memaksa `/storage/...` masuk ke aplikasi harus
ditiru:

```nginx
location ^~ /storage/ {
    try_files /nonexistent @laravel;
}
location @laravel {
    fastcgi_pass ...;   # sama dengan blok PHP utama
}
```

---

## 2. Rilis

```bash
php artisan down --render="errors::503"

git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

# muat ulang worker supaya memakai kode baru
php artisan queue:restart

php artisan up
```

Periksa setelah rilis:

- [ ] halaman login terbuka, bisa masuk
- [ ] satu halaman daftar (Cost Request) tampil dengan angka stepper benar
- [ ] satu lampiran bisa dibuka (memastikan disk privat + route berkas sehat)
- [ ] `php artisan queue:work --once` memproses satu job tanpa galat
- [ ] `storage/logs/laravel-*.log` tidak menampilkan galat baru

### Yang TIDAK boleh dijalankan

- `php artisan storage:link` — membuat lampiran dilayani web server tanpa
  pemeriksaan hak akses. `filesystems.links` sengaja kosong.
- `php artisan migrate:fresh` di server berisi data — menghapus semuanya.

---

## 3. Konfigurasi wajib di produksi

```dotenv
APP_ENV=production
APP_DEBUG=false          # jejak galat memuat query dan konfigurasi
LOG_CHANNEL=daily
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
```

`EXPORT_API_TOKEN` memberi akses baca ke SELURUH Cost Request tanpa sesi login.
Perlakukan seperti kata sandi: acak minimal 32 karakter, hanya diberikan ke
konsumen yang memang perlu, dan diganti bila ada yang pindah tugas. Token
dikirim lewat header:

```
Authorization: Bearer <token>
```

Jalur lama `?token=` masih diterima untuk konsumen yang belum dipindahkan, dan
setiap pemakaiannya dicatat sebagai peringatan di log — token di URL ikut
tersimpan di access log server dan riwayat peramban.

---

## 4. Cadangan

Minimal harian, disimpan di luar server aplikasi:

```bash
# basis data
mysqldump --single-transaction --routines --triggers \
  -u <user> -p <database> | gzip > /backup/tesys-db-$(date +%F).sql.gz

# lampiran (berkas keuangan - tidak bisa dibuat ulang)
rsync -a --delete /path/ke/tesys/storage/app/private/ /backup/tesys-files/
```

Simpan minimal 30 hari. **Pulihkan percobaan setidaknya sekali per kuartal** ke
lingkungan terpisah — cadangan yang tidak pernah diuji pulih bukan cadangan,
hanya berkas besar.

Pemulihan:

```bash
gunzip < /backup/tesys-db-YYYY-MM-DD.sql.gz | mysql -u <user> -p <database>
rsync -a /backup/tesys-files/ /path/ke/tesys/storage/app/private/
php artisan config:clear && php artisan cache:clear
```

---

## 5. Pemantauan

Yang perlu diperhatikan, berurutan menurut dampaknya:

| Tanda                                      | Artinya                                    |
|--------------------------------------------|--------------------------------------------|
| Tabel `jobs` menumpuk                       | Worker mati — notifikasi tidak terkirim    |
| Tabel `failed_jobs` bertambah               | Notifikasi gagal permanen (mis. Fonnte mati)|
| `Akses berkas ditolak` sering di log        | Salah hak akses, atau ada yang mencoba-coba|
| `Export API ditolak` berulang               | Token salah atau sedang ditebak            |
| `Login diblokir sementara`                  | Percobaan kata sandi beruntun              |
| `Cash Receipt dihapus`                      | Pembalikan pembayaran — selalu periksa     |

Belum ada integrasi pelaporan galat otomatis. Sampai ada, `storage/logs`
diperiksa manual — minimal saat ada keluhan pengguna.

---

## 6. Ketika terjadi masalah

**Halaman daftar sangat lambat / membeku.** Daftar dibatasi 1.000 baris
(`Concerns\CapsLargeLists`) dan akan menampilkan spanduk pemberitahuan.
Kalau tetap berat, persempit rentang tanggal. Solusi sebenarnya adalah paginasi
sisi server — pekerjaan yang belum dikerjakan.

**Lampiran tidak bisa dibuka (403).** Berarti aturan di `App\Services\FileAccess`
menolak. Periksa Access Group pengguna, atau apakah baris pemilik berkasnya
masih ada — berkas yatim memang ditolak untuk semua orang.

**Notifikasi tidak sampai.** Periksa worker (`supervisorctl status`), lalu
tabel `failed_jobs`, lalu `FONNTE_TOKEN`.

**Nomor dokumen melompat.** Itu normal sekarang: nomor diambil dari penghitung
(`document_sequences`) yang tidak pernah mundur, termasuk ketika dokumen
dihapus. Nomor yang melompat jauh lebih aman daripada nomor yang dipakai dua
kali.

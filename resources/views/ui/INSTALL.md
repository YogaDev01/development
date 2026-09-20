# Tesys UI — cara memasang (manual)

Ada **dua cara**. Cara 1 paling aman dan hanya butuh 4 baris.

---

## CARA 1 — Re-skin layout lama (rekomendasi)

Seluruh halaman (index/create/edit/show, ~116 blade) langsung berganti tampilan
**tanpa satu pun blade dimodifikasi**. Sidebar, navbar, card, tabel, badge, tombol,
form, modal, DataTables, SweetAlert ikut berubah. Semua JS lama tetap utuh.

### Langkah 1 — pastikan file ini ada di server

```
public/css/tesys-ui-tokens.css
public/css/tesys-ui-compat.css
public/css/tesys-ui-chrome.css
public/js/tesys-ui.js
```

### Langkah 2 — buka `resources/views/layouts/app.blade.php`

**(a)** Cari `</head>`. Tepat **sebelum** baris itu, tempelkan:

```blade
    {{-- ===== Tesys UI (re-skin) ===== --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-tokens.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-compat.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-chrome.css') }}?v=1">
```

> Harus **setelah** blok `<style>` bawaan layout, supaya CSS baru yang menang.

**(b)** Cari tag `<body ...>`. Tambahkan class `tesys-ui` pada class yang sudah ada.

```blade
{{-- sebelum --}}
<body class="hold-transition sidebar-mini layout-fixed">

{{-- sesudah --}}
<body class="hold-transition sidebar-mini layout-fixed tesys-ui">
```

**(c)** Cari `</body>`. Tepat **sebelum** baris itu, tempelkan:

```blade
<div class="toast-stack" id="toastStack"></div>
<script src="{{ asset('js/tesys-ui.js') }}?v=1"></script>
```

### Langkah 3 — bersihkan cache di server

```bash
php artisan view:clear
php artisan config:clear
php artisan route:clear
```

### Cara membatalkan

Hapus kembali 3 potongan di atas. Tidak ada file lain yang berubah.

---

## CARA 2 — Pindah ke layout baru sepenuhnya

Dipakai kalau Anda ingin sidebar & topbar versi baru (yang ada dropdown
**Profile Saya** + **Logout**), bukan sekadar mewarnai ulang sidebar lama.

Ganti satu baris di blade mana pun yang ingin dipindahkan:

```blade
@extends('layouts.app')        →     @extends('ui.layouts.app')
```

Opsional, agar judul tampil di topbar baru:

```blade
@section('title', 'Budgeting')
@section('breadcrumb', 'Finance / Budgeting')
```

Bisa dilakukan bertahap: satu modul dulu, cek, baru lanjut ke modul lain.
Halaman yang belum dipindah tetap memakai layout lama dan tetap berfungsi normal.

Contoh jadi bisa dilihat di `resources/views/ui/preview.blade.php`
(akses lewat route `/ui/preview`).

---

## Catatan penting saat upload ke server

1. **Folder `public/` wajib ikut ter-upload.** Layout `ui/layouts/app.blade.php`
   memanggil `filemtime()` pada file CSS/JS tersebut — bila filenya belum ada,
   halaman error 500 (bukan sekadar tampil polos).
2. **Route preview belum aktif.** `routes/web.php` sengaja TIDAK diubah.
   Bila ingin mencoba halaman `/ui/preview`, tambahkan sendiri satu baris ini
   di akhir `routes/web.php`:

   ```php
   require __DIR__.'/ui.php';
   ```

   Pastikan `routes/ui.php` ikut ter-upload bila baris tersebut dipakai —
   kalau filenya tertinggal, seluruh aplikasi akan error.
   Cara 1 (re-skin) **tidak** memerlukan route ini sama sekali.
3. Bila memakai cache production, jalankan ulang `php artisan route:cache`
   dan `php artisan view:cache` setelah upload.
4. Logo pada sidebar terang memakai filter `invert` (lihat `tesys-ui-chrome.css`
   bagian brand-image). Bila Anda punya file logo versi gelap, hapus aturan
   filter itu dan ganti `src` logonya.

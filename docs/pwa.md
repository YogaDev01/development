# PWA (Add to Home Screen)

Dibuat di branch `feature/web-app`. Tidak ada perubahan schema, route, atau auth.

## Komponen
| Berkas | Fungsi |
|---|---|
| `public/manifest.webmanifest` | Nama, ikon, `display: standalone`, `start_url`/`scope` relatif (`./`) |
| `public/dist/img/pwa/*.png` | Ikon 192/512, maskable 512, apple-touch 180 (diturunkan dari `ts_icon.png`) |
| `resources/views/layouts/partials/pwa-head.blade.php` | `<link rel="manifest">`, theme-color, meta iOS; di-include di layout `app`, `login`, `error` |
| `public/sw.js` | Service worker minimal (scope `/`) |
| `public/offline.html` | Halaman statis "Tidak ada koneksi" |
| `public/js/tesys-pwa.js` | Mendaftarkan service worker |
| `public/.htaccess` | MIME manifest; `sw.js`/manifest/offline `no-cache` |

## Kebijakan cache (keamanan)
- Service worker hanya meng-cache `offline.html` + satu ikon (cache `tesys-shell-<versi>`).
- Hanya request **navigasi GET** yang dicegat, dan hanya untuk fallback saat jaringan gagal. Halaman, POST, API, lampiran, dan aset tidak pernah disimpan.
- Header `no-store, private` dari `SecurityHeaders` tidak diubah. Logout tetap `invalidate()` di server; tidak ada data di Cache Storage yang perlu dibersihkan.
- Update: naikkan `CACHE_VERSION` di `sw.js` bila `PRECACHE` berubah. Worker baru aktif otomatis (`skipWaiting`) tanpa reload halaman.

## Deploy
Upload berkas di tabel di atas + 3 layout + `config/panduan.php` + `panduan/topics/instal-aplikasi.blade.php`. Tidak perlu migrasi. `php artisan view:clear` bila view cache aktif. Pastikan HTTPS aktif (syarat service worker) dan `.htaccess` ikut terunggah (LiteSpeed bisa menyimpan `sw.js` bila tidak).

## Rollback
Hapus `@include('layouts.partials.pwa-head')` dan `<script ... tesys-pwa.js>` dari 3 layout. Agar worker yang sudah terpasang lepas, ganti isi `public/sw.js` dengan `self.addEventListener('install',()=>self.skipWaiting());self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(k=>Promise.all(k.map(c=>caches.delete(c)))).then(()=>self.registration.unregister())));` lalu hapus setelah beberapa hari.

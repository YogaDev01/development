/* Service worker TESYS — sengaja minimal.
 *
 * TESYS adalah ERP keuangan: halaman dan data TIDAK BOLEH disimpan di perangkat.
 * Karena itu worker ini hanya:
 *  1. menyimpan halaman statis "tidak ada koneksi" + ikon (tanpa data pengguna);
 *  2. menampilkan halaman itu bila NAVIGASI gagal karena jaringan putus.
 * Semua request lain (POST, API, lampiran, aset, data) tidak disentuh sama
 * sekali — langsung ke jaringan seperti tanpa service worker.
 * Naikkan CACHE_VERSION saat berkas di PRECACHE berubah. */
const CACHE_VERSION = 'v1';
const CACHE_NAME = 'tesys-shell-' + CACHE_VERSION;
const OFFLINE_URL = 'offline.html';
const PRECACHE = [OFFLINE_URL, 'dist/img/pwa/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE.map((u) => new Request(u, { cache: 'reload' }))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k.startsWith('tesys-shell-') && k !== CACHE_NAME)
                    .map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    // Hanya navigasi GET. Tanpa respondWith, browser menangani request seperti biasa.
    if (req.mode !== 'navigate' || req.method !== 'GET') return;

    event.respondWith(
        fetch(req).catch(() =>
            caches.match(OFFLINE_URL).then((res) => res || Response.error())
        )
    );
});

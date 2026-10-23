/* Mendaftarkan service worker TESYS (lihat public/sw.js). Gagal = abaikan:
   aplikasi tetap berjalan normal tanpa service worker. */
(function () {
    'use strict';
    if (!('serviceWorker' in navigator)) return;
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
    });
})();

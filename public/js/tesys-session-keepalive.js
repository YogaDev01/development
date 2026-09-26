/* =====================================================================
   tesys-session-keepalive.js

   Menjaga sesi tetap hidup selama sebuah FORM sedang dibuka, dan
   memperingatkan sebelum sesinya benar-benar habis.

   MASALAH YANG DITANGANI

   SESSION_LIFETIME 120 menit. Form Cost Request (1.386 baris markup) dan
   Budget Plan diisi bertahap: puluhan baris rincian, unggah lampiran,
   menunggu konfirmasi dari orang lain. Mengetik di dalam form TIDAK
   memperpanjang sesi - hanya permintaan ke server yang memperpanjangnya.
   Jadi form yang dibuka dua jam lalu akan gagal saat Simpan ditekan,
   dengan 419, dan SELURUH isinya hilang.

   Itu jenis kegagalan yang paling merusak kepercayaan pada aplikasi:
   pekerjaan setengah jam lenyap, tidak ada galat di log, dan tidak ada
   cara pengguna mengetahui sebabnya.

   CARA KERJANYA

   1. Selama ada aktivitas pengguna di halaman berform, kirim satu
      permintaan ringan ke server setiap SELANG_PING. Itu memperbarui
      stempel waktu sesi, sehingga sesi tidak pernah habis selama orangnya
      memang masih bekerja.

   2. Kalau tidak ada aktivitas sama sekali selama BATAS_MENGANGGUR, ping
      dihentikan - sesi memang seharusnya berakhir kalau komputernya
      ditinggal. Aktivitas berikutnya menyalakannya lagi.

   3. Kalau ping mengembalikan 401/419, sesi sudah terlanjur berakhir:
      pengguna diberi tahu SEBELUM ia menekan Simpan dan kehilangan
      isinya, bukan sesudahnya.

   Hanya aktif di halaman yang punya <form method="post">, jadi halaman
   daftar dan laporan tidak menambah lalu lintas apa pun.
   ===================================================================== */
(function () {
    'use strict';

    /* Ping tiap 5 menit. Jauh lebih rapat dari perlu (sesi 120 menit), tapi
       murah - satu permintaan kecil - dan membuat perpanjangannya tidak
       bergantung pada ketepatan waktu. */
    var SELANG_PING = 5 * 60 * 1000;

    /* Berhenti setelah 30 menit tanpa sentuhan: menjaga sesi hidup untuk
       halaman yang benar-benar ditinggalkan justru melemahkan gunanya batas
       sesi itu sendiri. */
    var BATAS_MENGANGGUR = 30 * 60 * 1000;

    var timer = null;
    var aktivitasTerakhir = Date.now();

    function adaForm() {
        var forms = document.querySelectorAll('form[method="post"], form[method="POST"]');

        for (var i = 0; i < forms.length; i++) {
            // Form logout/pencarian kecil tidak perlu penjagaan sesi; yang
            // dijaga adalah form yang memang lama diisi. Ambang 3 kolom isian
            // memisahkan keduanya tanpa perlu menandai form satu per satu.
            if (forms[i].querySelectorAll('input, select, textarea').length >= 3) {
                return true;
            }
        }

        return false;
    }

    function catatAktivitas() {
        aktivitasTerakhir = Date.now();
    }

    function beriTahuSesiHabis() {
        hentikan();

        var pesan = 'Sesi Anda sudah berakhir. Simpan isi formulir ini ke tempat lain '
                  + '(salin teksnya) sebelum memuat ulang halaman, lalu masuk kembali.';

        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                icon: 'warning',
                title: 'Sesi berakhir',
                text: pesan,
                confirmButtonText: 'Mengerti',
                allowOutsideClick: false
            });
        } else {
            window.alert(pesan);
        }
    }

    function ping() {
        if (Date.now() - aktivitasTerakhir > BATAS_MENGANGGUR) {
            hentikan();
            return;
        }

        /* Memanggil halaman notifikasi: endpoint ringan yang sudah ada,
           ber-otentikasi, dan tidak mengubah apa pun. Tidak perlu route baru. */
        fetch(window.TESYS_KEEPALIVE_URL || '/notifications', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            cache: 'no-store'
        })
            .then(function (r) {
                if (r.status === 401 || r.status === 419) {
                    beriTahuSesiHabis();
                }
            })
            .catch(function () {
                /* Jaringan putus sesaat bukan alasan mengganggu pengguna;
                   ping berikutnya akan mencoba lagi. */
            });
    }

    function mulai() {
        if (timer) return;
        timer = window.setInterval(ping, SELANG_PING);
    }

    function hentikan() {
        if (!timer) return;
        window.clearInterval(timer);
        timer = null;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!adaForm()) return;

        ['keydown', 'mousedown', 'touchstart'].forEach(function (ev) {
            document.addEventListener(ev, catatAktivitas, { passive: true });
        });

        mulai();
    });
})();

/* =====================================================================
   Tesys — jam WIB di kaki sidebar
   ---------------------------------------------------------------------
   Menampilkan waktu berjalan dalam zona Asia/Jakarta (WIB, GMT+7).

   Dua keputusan yang perlu dicatat:

   1. ZONA DITENTUKAN, BUKAN DIWARISI.
      Waktunya diformat lewat Intl.DateTimeFormat dengan timeZone
      'Asia/Jakarta' yang ditulis tegas. Jadi kalau laptop penggunanya
      diset ke WITA, WIT, atau zona luar negeri, yang tampil tetap WIB —
      dan itu memang yang dimaksud: satu acuan waktu untuk seluruh
      perusahaan, bukan waktu lokal masing-masing orang.

   2. JAMNYA DISELARASKAN KE SERVER.
      Jam perangkat bisa meleset berjam-jam; di aplikasi keuangan, waktu
      yang tampil di layar tidak boleh berbeda dari waktu yang dipakai
      server saat mencatat transaksi. Layout menuliskan waktu server ke
      atribut data-epoch; berkas ini menghitung selisihnya sekali saat
      dimuat, lalu memakainya terus. Kalau atributnya tidak ada, ia jatuh
      ke jam perangkat tanpa mengeluh.

   Markup yang diharapkan (lihat layouts/app.blade.php):
     <div class="sidebar-clock" data-epoch="1758…">
        <span class="sc-time">--:--</span><span class="sc-wib">WIB</span>
        <span class="sc-date">Sabtu, 19 Sep 2026</span>
        <span class="sc-zone">Asia/Jakarta</span>
     </div>
   ===================================================================== */
(function () {
    'use strict';

    var simpul = document.querySelector('.sidebar-clock');
    if (!simpul) return;

    var elWaktu = simpul.querySelector('.sc-time');
    if (!elWaktu) return;

    /* Tanggal boleh tidak ada (mis. sidebar mode rail menyembunyikannya
       lewat CSS, tapi simpulnya tetap ada; kalau markup lama dipakai,
       simpulnya memang tidak ada sama sekali). Karena itu keberadaannya
       diperiksa, bukan diasumsikan. */
    var elTanggal = simpul.querySelector('.sc-date');

    /* Selisih jam perangkat terhadap jam server, dihitung sekali. */
    var epochServer = parseInt(simpul.getAttribute('data-epoch'), 10);
    var selisih = isFinite(epochServer) ? epochServer - Date.now() : 0;

    /* Intl dengan zona tertulis. hourCycle 'h23' dipakai supaya tengah
       malam tampil 00:00, bukan 24:00 — beberapa mesin render memakai
       h24 sebagai bawaan untuk locale id-ID. */
    var format;
    try {
        format = new Intl.DateTimeFormat('id-ID', {
            timeZone: 'Asia/Jakarta',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23'
        });
    } catch (e) {
        format = null;
    }

    /* Tanggal memakai formatter terpisah: "Jumat, 18 Sep 2026". Nama hari
       dan bulan diambil dari locale id-ID, jadi tidak ada tabel nama bulan
       yang perlu dirawat sendiri. Intl menulisnya "Jumat, 18 Sep 2026"
       untuk sebagian mesin dan "Jumat, 18 Sep 2026" dengan spasi tak
       terputus untuk sebagian lain — keduanya diseragamkan di gambar(). */
    var formatTanggal;
    try {
        formatTanggal = new Intl.DateTimeFormat('id-ID', {
            timeZone: 'Asia/Jakarta',
            weekday: 'long',
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    } catch (e) {
        formatTanggal = null;
    }

    /* Cadangan kalau Intl tidak mendukung zona waktu (peramban lama di
       jaringan kantor): hitung manual dari UTC + 7 jam. WIB tidak pernah
       memakai daylight saving, jadi penambahan tetap ini selalu benar. */
    var HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function manualTanggal(ms) {
        var d = new Date(ms + 7 * 3600 * 1000);
        return HARI[d.getUTCDay()] + ', ' + d.getUTCDate() + ' '
             + BULAN[d.getUTCMonth()] + ' ' + d.getUTCFullYear();
    }

    function manual(ms) {
        var d = new Date(ms + 7 * 3600 * 1000);
        var j = String(d.getUTCHours()).padStart(2, '0');
        var m = String(d.getUTCMinutes()).padStart(2, '0');
        return j + ':' + m;
    }

    var terakhir = null;
    var terakhirTanggal = null;

    function gambar() {
        var ms = Date.now() + selisih;
        var teks = format ? format.format(new Date(ms)) : manual(ms);

        /* Beberapa locale menyisipkan pemisah tak terlihat; disamakan
           supaya perbandingan "berubah atau tidak" tetap sahih. */
        teks = teks.replace(/ | /g, ' ').trim();

        /* id-ID memformat jam dengan TITIK (13.53), sedangkan sisi server
           menuliskannya dengan TITIK DUA lewat format('H:i'). Tanpa
           penyeragaman ini angka yang tercetak saat halaman dimuat berubah
           bentuk satu detik kemudian — terbaca seperti komponen yang salah
           pasang. Titik dua juga yang diminta spesifikasi (13:30 WIB) dan
           lebih lazim dibaca sebagai jam pada layar sistem perusahaan. */
        teks = teks.replace('.', ':');

        if (teks !== terakhir) {
            elWaktu.textContent = teks;
            terakhir = teks;
        }

        /* Tanggal dihitung di tiap tik yang sama, bukan lewat timer kedua:
           satu sumber waktu berarti jam dan tanggal tidak mungkin berbeda
           hari saat lewat tengah malam. Simpulnya tetap hanya disentuh
           kalau teksnya berubah — sekali sehari. */
        if (elTanggal) {
            var tgl = formatTanggal
                ? formatTanggal.format(new Date(ms))
                : manualTanggal(ms);

            tgl = tgl.replace(/\u00a0|\u202f/g, ' ').trim();

            if (tgl !== terakhirTanggal) {
                elTanggal.textContent = tgl;
                terakhirTanggal = tgl;
            }
        }
    }

    gambar();

    /* Ditembak tiap detik, tapi simpul DOM hanya disentuh saat menitnya
       benar-benar berganti (lihat penjaga di atas) — jadi tidak ada
       pekerjaan tata letak yang sia-sia 59 kali per menit. */
    setInterval(gambar, 1000);

    /* Saat tab kembali aktif setelah lama ditinggalkan, peramban menunda
       timer; gambar ulang segera supaya tidak sempat terlihat basi. */
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) gambar();
    });
})();

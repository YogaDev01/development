/* =====================================================================
   TESYS — Command Center (Ctrl+K)
   ---------------------------------------------------------------------
   Satu tempat untuk berpindah halaman, mencari dokumen lintas modul, dan
   memulai pekerjaan baru.

   Saat kolom masih kosong palet TIDAK menampilkan daftar kosong, melainkan:

     Terakhir dibuka — riwayat halaman user ini (disimpan di browser-nya
                       sendiri, tidak pernah dikirim ke server).
     Buka halaman    — tujuan yang paling sering dipakai.
     Aksi            — window.TESYS_ACTIONS, sudah disaring di server sesuai
                       role & menu, jadi tidak pernah menawarkan jalan buntu.

   Setelah user mengetik, tiga sumber hasil:

     1. Halaman — dibaca dari tautan menu yang SUDAH dirender di sidebar.
        Karena sidebar hanya memuat menu yang boleh dibuka user ini,
        daftarnya otomatis mengikuti hak akses; tidak ada daftar menu
        kedua yang harus dijaga agar tetap selaras.

     2. Dokumen — dari GET /global-search. Gerbang akses dan scoping
        barisnya dikerjakan di server oleh masing-masing modul.

     3. Aksi — daftar yang sama seperti di atas, disaring dengan kata kunci.

   Palet tidak pernah mengubah data: setiap baris hanya membuka halaman.
   ===================================================================== */
(function () {
    'use strict';

    /*
     * TUNDA_KETIK: jeda setelah ketikan BERHENTI sebelum bertanya ke server.
     *
     * Dinaikkan dari 220 ke 500 ms. Pencarian lintas modul di server memakai
     * LIKE pada kolom kode & deskripsi lima tabel terbesar; setiap permintaan
     * karena itu mahal, dan pada 220 ms mengetik dengan kecepatan normal masih
     * mengirim beberapa permintaan per kata. Setengah detik sudah terasa
     * seketika bagi yang mengetik, tapi memotong jumlah permintaan secara
     * drastis. AbortController di bawah tetap membatalkan permintaan yang
     * sudah tidak relevan.
     *
     * MIN_HURUF: dinaikkan dari 2 ke 3. Dua huruf cocok dengan hampir semua
     * baris, jadi hasilnya tidak berguna sementara biaya pencariannya penuh.
     * Di bawah batas ini palet tetap menjawab - halaman & aksi disaring
     * seketika di peramban, tanpa menyentuh server sama sekali.
     */
    var TUNDA_KETIK = 500;
    var MIN_HURUF   = 3;

    var akar, kolom, isi, pemuat;
    var timer = null;
    var pembatal = null;      // AbortController permintaan yang sedang berjalan
    var terpilih = -1;
    var hasil = [];           // daftar rata semua baris, untuk navigasi keyboard
    var pemicuTerakhir = null;

    function esc(v) {
        if (v === null || v === undefined) return '';
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* ---------------- Riwayat halaman (tersimpan di browser) ---------------- */

    var KUNCI_RIWAYAT = 'tesys.cmd.recent';
    var MAKS_RIWAYAT  = 8;

    function bacaRiwayat() {
        try {
            var mentah = window.localStorage.getItem(KUNCI_RIWAYAT);
            var data = mentah ? JSON.parse(mentah) : [];
            return Array.isArray(data) ? data : [];
        } catch (e) {
            return [];   // mode privat / storage penuh — riwayat memang opsional
        }
    }

    function catatKunjungan() {
        var judul = document.getElementById('tsTitle');
        var teks = judul ? judul.textContent.trim() : '';
        if (!teks) teks = (document.title || '').split('|')[0].trim();
        if (!teks) return;

        var url = window.location.pathname + window.location.search;
        var ikon = document.querySelector('.sidebar .nav-item.active i');

        var daftar = bacaRiwayat().filter(function (r) { return r.url !== url; });
        daftar.unshift({
            title: teks,
            url: url,
            icon: ikon ? ikon.className.replace(/\bic\b/, '').trim() : 'fas fa-history',
            subtitle: ''
        });

        try {
            window.localStorage.setItem(KUNCI_RIWAYAT, JSON.stringify(daftar.slice(0, MAKS_RIWAYAT)));
        } catch (e) { /* abaikan */ }
    }

    /* Riwayat yang ditampilkan: bukan halaman yang sedang dibuka, dan hanya
       yang masih boleh dibuka user ini (dicocokkan ke menu sidebar). */
    function riwayatTampil() {
        var sekarang = window.location.pathname + window.location.search;
        var boleh = {};
        daftarHalaman().forEach(function (h) { boleh[h.url.split('?')[0]] = true; });

        return bacaRiwayat().filter(function (r) {
            return r.url !== sekarang;
        }).filter(function (r) {
            var dasar = r.url.split('?')[0];
            if (boleh[dasar]) return true;
            // Halaman detail (mis. /budget-request/12) tidak ada di sidebar;
            // izinkan bila menu induknya ada.
            var potong = dasar.split('/').filter(Boolean);
            return potong.length ? !!boleh['/' + potong[0]] : false;
        }).slice(0, 4);
    }

    /* ---------------- Tujuan cepat & aksi ---------------- */

    // Tujuan yang paling sering dipakai. Yang tidak ada di sidebar (karena
    // hak akses) otomatis terlewat.
    var TUJUAN = ['Dashboard', 'Cost Request', 'Summary', 'Treasury', 'Budgeting', 'Collection'];

    function tujuanCepat() {
        var halaman = daftarHalaman();
        var pilihan = [];
        TUJUAN.forEach(function (nama) {
            var cocok = halaman.filter(function (h) { return h.title === nama; })[0];
            if (cocok) pilihan.push(cocok);
        });
        // Role tertentu mungkin tidak punya satu pun dari daftar di atas —
        // tampilkan menu pertamanya supaya palet tetap berguna.
        if (pilihan.length < 3) {
            halaman.slice(0, 4).forEach(function (h) {
                if (pilihan.indexOf(h) === -1 && pilihan.length < 4) pilihan.push(h);
            });
        }
        return pilihan.slice(0, 4);
    }

    function daftarAksi() {
        var aksi = window.TESYS_ACTIONS;
        if (!Array.isArray(aksi)) return [];
        return aksi.map(function (a) {
            return { title: a.title, url: a.url, icon: a.icon || 'fas fa-plus', subtitle: '', aksi: true };
        });
    }

    /* ---------------- Daftar halaman dari sidebar ---------------- */

    function daftarHalaman() {
        var tautan = document.querySelectorAll('.sidebar a.nav-item[href]');
        return Array.prototype.map.call(tautan, function (a) {
            var ikon = a.querySelector('i');
            return {
                title: a.textContent.trim(),
                subtitle: '',
                status: null,
                url: a.getAttribute('href'),
                icon: ikon ? ikon.className.replace(/\bic\b/, '').trim() : 'fas fa-arrow-right'
            };
        }).filter(function (h) { return h.title && h.url && h.url !== '#'; });
    }

    function saringHalaman(q) {
        var kunci = q.toLowerCase();
        return daftarHalaman().filter(function (h) {
            return h.title.toLowerCase().indexOf(kunci) !== -1;
        }).slice(0, 6);
    }

    /* ---------------- Render ---------------- */

    function barisHtml(item, indeks) {
        var status = item.status
            ? '<span class="ts-status ts-status-' + esc(item.tone || 'neutral') + ' ts-status-sm">' + esc(item.status) + '</span>'
            : '';
        var sub = item.subtitle ? '<span class="ts-cmd-sub">' + esc(item.subtitle) + '</span>' : '';

        var kelas = 'ts-cmd-item' + (item.aksi ? ' is-aksi' : '');

        return '<a class="' + kelas + '" role="option" id="ts-cmd-' + indeks + '" data-indeks="' + indeks + '" href="' + esc(item.url) + '">'
             +   '<i class="ts-cmd-ic ' + esc(item.icon || 'fas fa-file') + '" aria-hidden="true"></i>'
             +   '<span class="ts-cmd-teks"><span class="ts-cmd-judul">' + esc(item.title) + '</span>' + sub + '</span>'
             +   status
             + '</a>';
    }

    function render(grup) {
        hasil = [];
        var html = '';

        grup.forEach(function (g) {
            if (!g.items || !g.items.length) return;
            html += '<div class="ts-cmd-grup"><p class="ts-cmd-label">' + esc(g.label) + '</p>';
            g.items.forEach(function (item) {
                if (!item.icon) item.icon = g.icon;
                html += barisHtml(item, hasil.length);
                hasil.push(item);
            });
            html += '</div>';
        });

        if (!html) {
            html = '<div class="ts-empty ts-empty-compact">'
                 +   '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                 +   '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                 +   '<p class="ts-empty-msg">Coba kode dokumen, misalnya CR-0926, atau nama halaman.</p></div>';
        }

        isi.innerHTML = html;
        terpilih = hasil.length ? 0 : -1;
        tandai();
    }

    function tandai() {
        var baris = isi.querySelectorAll('.ts-cmd-item');
        Array.prototype.forEach.call(baris, function (el, i) {
            el.classList.toggle('is-aktif', i === terpilih);
            if (i === terpilih) {
                el.setAttribute('aria-selected', 'true');
                el.scrollIntoView({ block: 'nearest' });
            } else {
                el.removeAttribute('aria-selected');
            }
        });
        kolom.setAttribute('aria-activedescendant', terpilih >= 0 ? 'ts-cmd-' + terpilih : '');
    }

    function tampilkanKerangka() {
        pemuat.hidden = false;
    }

    function sembunyikanKerangka() {
        pemuat.hidden = true;
    }

    /* ---------------- Pencarian ---------------- */

    // Hasil yang bisa dihitung di browser: halaman dari sidebar + aksi cepat.
    function grupLokal(halaman, q) {
        var kunci = q.toLowerCase();
        var aksi = daftarAksi().filter(function (a) {
            return a.title.toLowerCase().indexOf(kunci) !== -1;
        });

        var grup = [];
        if (halaman.length) grup.push({ label: 'Halaman', icon: 'fas fa-arrow-right', items: halaman });
        if (aksi.length)    grup.push({ label: 'Aksi',    icon: 'fas fa-plus',        items: aksi });
        return grup;
    }

    function cari(q) {
        if (pembatal) { pembatal.abort(); pembatal = null; }

        var halaman = saringHalaman(q);

        if (!q.length) {
            // Belum mengetik apa pun: tawarkan tempat melanjutkan pekerjaan,
            // bukan kotak kosong.
            sembunyikanKerangka();
            render([
                { label: 'Terakhir dibuka', icon: 'fas fa-history',    items: riwayatTampil() },
                { label: 'Buka halaman',    icon: 'fas fa-arrow-right', items: tujuanCepat() },
                { label: 'Aksi',            icon: 'fas fa-plus',        items: daftarAksi() }
            ]);
            return;
        }

        if (q.length < MIN_HURUF) {
            // Terlalu pendek untuk menanyakan dokumen ke server; halaman &
            // aksi sudah cukup berguna dan bisa disaring seketika di sini.
            sembunyikanKerangka();
            render(grupLokal(halaman, q));
            return;
        }

        // Halaman & aksi ditampilkan lebih dulu supaya palet terasa langsung
        // menjawab, lalu dokumen menyusul saat jawabannya tiba.
        render(grupLokal(halaman, q));
        tampilkanKerangka();

        pembatal = new AbortController();
        fetch(window.TESYS_SEARCH_URL + '?q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            signal: pembatal.signal
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (data) {
                sembunyikanKerangka();
                // Sesi yang sudah berakhir mengembalikan halaman login dengan
                // status 200; tanpa penjagaan ini daftarnya jadi kosong tanpa sebab.
                if (!data || !Array.isArray(data.groups)) {
                    render(grupLokal(halaman, q));
                    return;
                }
                render(grupLokal(halaman, q).concat(data.groups));
            })
            .catch(function (e) {
                if (e && e.name === 'AbortError') return;
                sembunyikanKerangka();
            });
    }

    /* ---------------- Buka / tutup ---------------- */

    function buka(pemicu) {
        pemicuTerakhir = pemicu || document.activeElement;
        akar.hidden = false;
        void akar.offsetWidth;
        akar.classList.add('is-open');
        document.body.classList.add('ts-drawer-lock');
        kolom.value = '';
        cari('');
        kolom.focus();
    }

    function tutup() {
        if (!akar || akar.hidden) return;
        if (pembatal) { pembatal.abort(); pembatal = null; }
        akar.classList.remove('is-open');
        // Kunci gulir dipakai bersama dengan drawer. Jangan dilepas kalau masih
        // ada drawer terbuka di belakang palette.
        if (!document.querySelector('.ts-drawer-root.is-open')) {
            document.body.classList.remove('ts-drawer-lock');
        }
        window.setTimeout(function () {
            if (!akar.classList.contains('is-open')) akar.hidden = true;
        }, 200);
        if (pemicuTerakhir && typeof pemicuTerakhir.focus === 'function') {
            pemicuTerakhir.focus({ preventScroll: true });
        }
        pemicuTerakhir = null;
    }

    function siap() {
        akar   = document.getElementById('tsCmd');
        if (!akar) return;
        kolom  = document.getElementById('tsCmdInput');
        isi    = document.getElementById('tsCmdList');
        pemuat = document.getElementById('tsCmdLoading');

        kolom.addEventListener('input', function () {
            window.clearTimeout(timer);
            var q = kolom.value.trim();
            timer = window.setTimeout(function () { cari(q); }, TUNDA_KETIK);
        });

        kolom.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (hasil.length) { terpilih = (terpilih + 1) % hasil.length; tandai(); }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (hasil.length) { terpilih = (terpilih - 1 + hasil.length) % hasil.length; tandai(); }
            } else if (e.key === 'Enter') {
                if (terpilih >= 0 && hasil[terpilih]) {
                    e.preventDefault();
                    window.location.href = hasil[terpilih].url;
                }
            }
        });

        akar.addEventListener('click', function (e) {
            if (e.target === akar || e.target.classList.contains('ts-cmd-scrim')) tutup();
        });

        document.addEventListener('click', function (e) {
            var tombol = e.target.closest('[data-cmd-open]');
            if (tombol) { e.preventDefault(); buka(tombol); }
        });

        document.addEventListener('keydown', function (e) {
            var kombinasi = (e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K');
            if (kombinasi) {
                e.preventDefault();
                if (akar.hidden) buka(null); else tutup();
                return;
            }
            if (e.key === 'Escape' && !akar.hidden) {
                e.preventDefault();
                tutup();
            }
        });

        window.TesysCommand = { open: buka, close: tutup };

        // Judul topbar baru terisi setelah tesys-shell.js memindahkannya,
        // jadi riwayat dicatat saat 'load', bukan 'DOMContentLoaded'.
        if (document.readyState === 'complete') catatKunjungan();
        else window.addEventListener('load', catatKunjungan);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', siap);
    } else {
        siap();
    }
})();

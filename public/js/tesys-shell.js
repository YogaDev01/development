/* =====================================================================
   Tesys Shell — interaksi kerangka halaman (sidebar, akun, judul topbar)
   ---------------------------------------------------------------------
   Vanilla JS, tanpa dependensi. Dimuat paling akhir di layouts/app.blade.php.
   ===================================================================== */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
       1. Judul & breadcrumb halaman dipindah ke topbar.

       Blade lama menulis sendiri <h1> + <ol class="breadcrumb"> di dalam
       konten. Daripada menyunting ~90 file, elemen itu dipindahkan ke
       topbar saat halaman dimuat — hasilnya sama dengan mockup dan tidak
       ada judul dobel. Tombol aksi yang kebetulan satu baris dengan judul
       TIDAK ikut dihapus.
       --------------------------------------------------------------- */
    function hoistPageTitle() {
        var content = document.querySelector('.main > .content');
        var slotTitle = document.getElementById('tsTitle');
        var slotCrumb = document.getElementById('tsCrumb');
        if (!content || !slotTitle) return;

        // Judul: <h1> pertama yang bukan bagian dari modal/tabel.
        var h1 = content.querySelector('h1');
        if (h1 && !h1.closest('.modal')) {
            var text = h1.textContent.trim();
            if (text) slotTitle.textContent = text;
            removeAndPrune(h1);
        }

        // Breadcrumb: <ol class="breadcrumb"> pertama di luar modal.
        var crumb = content.querySelector('.breadcrumb');
        if (crumb && !crumb.closest('.modal')) {
            var parts = [].slice.call(crumb.querySelectorAll('li'))
                .map(function (li) { return li.textContent.trim(); })
                .filter(Boolean);
            if (parts.length && slotCrumb) slotCrumb.textContent = parts.join(' / ');
            removeAndPrune(crumb);
        }
    }

    // Hapus elemen; bila pembungkusnya jadi kosong (hanya sisa spasi),
    // buang pembungkusnya juga supaya tidak menyisakan celah kosong.
    function removeAndPrune(el) {
        var parent = el.parentElement;
        el.remove();
        while (parent && parent !== document.body && !parent.classList.contains('content')) {
            var hasContent = parent.children.length > 0 || parent.textContent.trim() !== '';
            if (hasContent) break;
            var next = parent.parentElement;
            parent.remove();
            parent = next;
        }
    }

    /* ---------------------------------------------------------------
       2. Klik di dalam panel akun tidak boleh menutupnya — pemilihan role
       "View As" memakai <select> di dalam panel itu. Buka/tutup panelnya
       sendiri sudah ditangani tesys-ui.js; jangan dipasang ulang di sini
       atau panel akan ter-toggle dua kali dan terlihat tidak bereaksi.
       --------------------------------------------------------------- */
    function keepUserMenuOpenOnInteraction() {
        var pop = document.getElementById('userPop');
        if (!pop) return;
        pop.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    /* ---------------------------------------------------------------
       3. Tombol perkecil/perbesar sidebar.

       Dua perilaku, satu tombol — sesuai ruang yang tersedia:

         layar lebar  : sidebar MENGECIL jadi jalur ikon (class sidebar-rail).
                        Navigasi tidak pernah hilang, hanya labelnya; ini yang
                        dibutuhkan saat membuka tabel lebar.
         layar sempit : sidebar menjadi laci yang menutupi konten
                        (class sidebar-off), seperti sebelumnya.

       Pilihan pengguna di layar lebar diingat per browser.
       --------------------------------------------------------------- */
    var STORAGE_RAIL = 'tesys.sidebar.rail';
    var STORAGE_OFF  = 'tesys.sidebar.off';   // kunci lama, dibersihkan sekali

    var narrowScreen = window.matchMedia('(max-width: 900px)');

    function initSidebarToggle() {
        /* Ada DUA tombol, bukan satu, dan keduanya perlu diikat.

           Tombol utama tinggal di dalam sidebar (di samping nama aplikasi).
           Tapi di layar sempit sidebar berubah jadi laci yang tertutup penuh,
           dan tombol yang ikut tersembunyi di dalamnya tidak bisa dipakai
           untuk membukanya lagi. Karena itu topbar tetap menyimpan satu
           tombol yang hanya muncul di lebar itu.

           Pengikatannya lewat atribut data-sidebar-toggle supaya jumlah
           tombol bisa berubah tanpa menyentuh berkas ini lagi. */
        var toggles = document.querySelectorAll('[data-sidebar-toggle]');
        var sidebar = document.getElementById('tsSidebar');
        if (!toggles.length) return;

        toggles.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();

                if (narrowScreen.matches) {
                    document.body.classList.toggle('sidebar-off');
                    return;
                }

                var rail = document.body.classList.toggle('sidebar-rail');
                toggles.forEach(function (b) {
                    b.setAttribute('aria-expanded', rail ? 'false' : 'true');
                });
                syncRailTooltips();
                try { localStorage.setItem(STORAGE_RAIL, rail ? '1' : '0'); } catch (e2) { /* mode privat */ }
            });
        });

        // Di layar sempit sidebar menjadi laci yang menutupi konten. Menekan
        // apa pun di luarnya menutup laci — termasuk lapisan gelap di
        // belakangnya, yang di layar itu menutupi tombol menu di topbar.
        document.addEventListener('click', function (e) {
            if (!narrowScreen.matches) return;
            if (document.body.classList.contains('sidebar-off')) return;
            if (sidebar && sidebar.contains(e.target)) return;
            document.body.classList.add('sidebar-off');
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !narrowScreen.matches) return;
            document.body.classList.add('sidebar-off');
        });

        // Memilih menu juga menutup laci.
        document.querySelectorAll('.sidebar .nav-item').forEach(function (item) {
            item.addEventListener('click', function () {
                if (narrowScreen.matches) document.body.classList.add('sidebar-off');
            });
        });

        // Berpindah antara layar lebar dan sempit: samakan kembali keadaannya
        // supaya laci tidak tertinggal terbuka menutupi konten.
        narrowScreen.addEventListener('change', applyStoredSidebarState);
    }

    // Dijalankan sedini mungkin supaya sidebar tidak "berkedip" saat reload.
    function applyStoredSidebarState() {
        var narrow = window.matchMedia('(max-width: 900px)').matches;
        var rail = false;
        try {
            rail = localStorage.getItem(STORAGE_RAIL) === '1';
            // Versi sebelumnya menyembunyikan sidebar sepenuhnya di layar
            // lebar. Nilai itu tidak dipakai lagi; dibuang supaya tidak ada
            // yang kebingungan mencari menunya setelah pembaruan.
            localStorage.removeItem(STORAGE_OFF);
        } catch (e) { /* abaikan */ }

        // Di layar sempit sidebar selalu mulai tertutup, dan mode rail
        // dimatikan (lacinya butuh label, bukan ikon saja).
        document.body.classList.toggle('sidebar-off', narrow);
        document.body.classList.toggle('sidebar-rail', !narrow && rail);
        if (document.querySelector('.sidebar .nav-item[data-label]')) syncRailTooltips();
    }

    /* ---------------------------------------------------------------
       3b. Nama menu untuk mode rail.

       Saat menyempit, label teks disembunyikan CSS dan digantikan tooltip
       yang membaca data-label. Diisi dari teks menunya sendiri supaya tidak
       ada daftar nama kedua yang harus dijaga.
       --------------------------------------------------------------- */
    function labelNavItems() {
        document.querySelectorAll('.sidebar .nav-item').forEach(function (item) {
            var teks = item.textContent.trim();
            if (teks) item.setAttribute('data-label', teks);
        });
        syncRailTooltips();
    }

    /* Tooltip sendiri, bukan atribut title: yang bawaan muncul terlambat,
       tampil sebagai kotak sistem, dan tidak bisa disamakan gayanya dengan
       sisa antarmuka. Elemennya satu saja, menempel di <body> supaya tidak
       terpotong oleh .nav yang menggulir; posisinya dihitung saat disentuh.

       data-label tetap dipakai sebagai sumber teksnya, dan aria-label ikut
       dipasang supaya pembaca layar tetap kebagian nama menunya — itu yang
       hilang kalau title dilepas begitu saja. */
    var tipEl = null;

    function tipNode() {
        if (!tipEl) {
            tipEl = document.createElement('div');
            tipEl.className = 'ts-tip';
            tipEl.setAttribute('role', 'presentation');
            document.body.appendChild(tipEl);
        }
        return tipEl;
    }

    function showTip(item) {
        if (!document.body.classList.contains('sidebar-rail')) return;
        var teks = item.getAttribute('data-label');
        if (!teks) return;

        var tip = tipNode();
        tip.textContent = teks;
        tip.classList.remove('show');

        var r = item.getBoundingClientRect();
        // Diukur setelah teksnya terpasang; posisinya dipatok ke tepi kanan
        // ikon, lalu dijaga supaya tidak keluar dari tepi atas/bawah layar.
        var h = tip.offsetHeight;
        var top = Math.min(
            Math.max(8, r.top + (r.height - h) / 2),
            Math.max(8, window.innerHeight - h - 8)
        );
        var z = parseFloat(getComputedStyle(document.documentElement).zoom) || 1;
        tip.style.left = ((r.right + 10) / z) + 'px';
        tip.style.top = (top / z) + 'px';
        tip.classList.add('show');
    }

    function hideTip() {
        if (tipEl) tipEl.classList.remove('show');
    }

    function syncRailTooltips() {
        var rail = document.body.classList.contains('sidebar-rail');
        document.querySelectorAll('.sidebar .nav-item[data-label]').forEach(function (item) {
            // title bawaan selalu dilepas — kalau dibiarkan, dua tooltip
            // muncul bertumpuk di mode rail.
            item.removeAttribute('title');
            if (rail) item.setAttribute('aria-label', item.getAttribute('data-label'));
            else item.removeAttribute('aria-label');
        });
        if (!rail) hideTip();
    }

    function itemDari(target) {
        return target && target.closest ? target.closest('.nav-item[data-label]') : null;
    }

    function initRailTooltips() {
        var nav = document.querySelector('.sidebar .nav');
        if (!nav) return;

        nav.addEventListener('mouseover', function (e) {
            var item = itemDari(e.target);
            if (item) showTip(item);
        });
        nav.addEventListener('mouseout', function (e) {
            var item = itemDari(e.target);
            if (item && !item.contains(e.relatedTarget)) hideTip();
        });
        nav.addEventListener('focusin', function (e) {
            var item = itemDari(e.target);
            if (item) showTip(item);
        });
        nav.addEventListener('focusout', hideTip);
        // Menu bisa tergulir & sidebar bisa melebar saat tooltip terbuka;
        // memindahkannya ikut-ikutan lebih berisik daripada menutupnya.
        nav.addEventListener('scroll', hideTip, { passive: true });
        window.addEventListener('resize', hideTip);
    }

    /* ---------------------------------------------------------------
       3c. Penanda halaman tergulir.

       Topbar melayang tanpa garis batas selama halaman masih di puncak —
       tidak ada apa pun di baliknya, jadi garis itu tidak menyatakan apa-apa.
       Begitu konten mulai lewat di bawahnya, kelas .page-scrolled dipasang
       dan CSS memunculkan garisnya (lihat tesys-ui-shell.css).

       Dihitung lewat requestAnimationFrame: peristiwa scroll bisa datang
       puluhan kali per detik, dan menyentuh classList sesering itu memaksa
       peramban menghitung ulang tata letak tanpa perlu.
       --------------------------------------------------------------- */
    function initScrollState() {
        var menunggu = false;
        // Yang menggulir kini area konten (lihat tesys-ui-shell.css); jendela
        // tetap dibaca sebagai cadangan untuk halaman tanpa shell.
        var kotak = document.querySelector('.main > .content');

        function terapkan() {
            menunggu = false;
            // Ambang 4px, bukan 0: sebagian peramban melaporkan pecahan
            // piksel saat halaman diam, dan garisnya jadi berkedip.
            var posisi = kotak ? kotak.scrollTop : window.scrollY;
            document.body.classList.toggle('page-scrolled', posisi > 4);
        }

        function saatGulir() {
            if (menunggu) return;
            menunggu = true;
            window.requestAnimationFrame(terapkan);
        }

        window.addEventListener('scroll', saatGulir, { passive: true });
        if (kotak) { kotak.addEventListener('scroll', saatGulir, { passive: true }); }

        // Halaman bisa dibuka dalam keadaan sudah tergulir (mis. kembali
        // dari halaman lain, atau tautan ber-anchor).
        terapkan();
    }

    /* ---------------------------------------------------------------
       3d. Keadaan sibuk pada tombol kirim.

       Halaman-halaman berat (daftar transaksi, ekspor, filter tanggal yang
       memuat ulang halaman) bisa perlu beberapa detik. Tanpa tanda apa pun,
       yang terjadi adalah pengguna menekan tombolnya lagi - dan untuk form
       yang membuat dokumen, itu berarti dua dokumen. Tombol dikunci begitu
       form dikirim, dengan label diganti sementara.

       Hanya untuk form biasa (bukan AJAX). Form yang menangani submit sendiri
       lewat JavaScript bisa menandai diri dengan class 'no-busy'.
       --------------------------------------------------------------- */
    function initBusyButtons() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement) || form.classList.contains('no-busy')) return;

            var tombol = form.querySelector('button[type="submit"], input[type="submit"]');
            if (!tombol || tombol.disabled) return;

            /*
              Tombol dinonaktifkan SETELAH peramban mengumpulkan nilainya;
              menonaktifkan langsung membuat nilai tombol tidak ikut terkirim.

              Pemeriksaan defaultPrevented HARUS dilakukan di dalam timeout ini,
              bukan di awal handler. Listener ini dipasang pada fase TANGKAP,
              yang berjalan SEBELUM handler halaman — dan handler halamanlah
              yang memanggil preventDefault() untuk memunculkan konfirmasi
              SweetAlert. Saat fase tangkap berjalan, defaultPrevented masih
              false, jadi pemeriksaan di awal tidak pernah menahan apa pun.

              Akibatnya dulu: pengguna menekan tombol, memilih "Batal" di
              konfirmasi, dan tombolnya tertinggal dalam keadaan terkunci
              bertuliskan "Memproses…" — tidak bisa diklik lagi sampai halaman
              dimuat ulang. Pada tombol aksi selebar ikon, tulisan itu bahkan
              terpotong jadi potongan huruf yang tidak berarti.

              Saat timeout ini berjalan, seluruh handler sudah selesai — barulah
              defaultPrevented menggambarkan keadaan yang sebenarnya.
            */
            window.setTimeout(function () {
                if (e.defaultPrevented) return;

                tombol.disabled = true;
                tombol.dataset.labelAsli = tombol.innerHTML;
                tombol.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i>Memproses…';
            }, 0);
        }, true);

        // Kembali dari halaman lain lewat tombol Back: peramban memulihkan DOM
        // apa adanya, termasuk tombol yang tadi dikunci. Dipulihkan di sini.
        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) return;
            document.querySelectorAll('button[data-label-asli]').forEach(function (b) {
                b.disabled = false;
                b.innerHTML = b.dataset.labelAsli;
                delete b.dataset.labelAsli;
            });
        });
    }

    /* ---------------------------------------------------------------
       4. Pencarian cepat di topbar → menyaring tabel halaman ini.

       Tidak ada endpoint pencarian global di aplikasi, jadi kotak ini
       diikat ke tabel yang sedang tampil: lewat API DataTables bila tabel
       sudah diinisialisasi, atau penyaringan baris sederhana bila belum.
       Kalau halaman tidak punya tabel, kotaknya tetap tersembunyi.
       --------------------------------------------------------------- */
    function initQuickSearch() {
        var wrap = document.getElementById('tsQuickSearchWrap');
        var input = document.getElementById('tsQuickSearch');
        var content = document.querySelector('.main > .content');
        if (!wrap || !input || !content) return;

        var tables = content.querySelectorAll('table');
        if (!tables.length) return;
        wrap.hidden = false;

        input.addEventListener('input', function () {
            var q = input.value.trim();

            var jq = window.jQuery;
            if (jq && jq.fn && jq.fn.dataTable) {
                var api = jq.fn.dataTable.tables({ visible: true, api: true });
                if (api.count()) {
                    api.search(q).draw();
                    return;
                }
            }

            // Tabel biasa: sembunyikan baris yang tidak memuat kata kunci.
            var needle = q.toLowerCase();
            tables.forEach(function (table) {
                table.querySelectorAll('tbody tr').forEach(function (row) {
                    row.hidden = needle !== '' && row.textContent.toLowerCase().indexOf(needle) === -1;
                });
            });
        });

        /*
           Ctrl+K SENGAJA tidak dipasang di sini. Pintasan itu milik Command
           Center (tesys-command.js); dulu keduanya terpasang bersamaan
           sehingga palet terbuka lalu fokusnya langsung dicuri kolom ini.
           Pencarian dalam halaman cukup diklik atau dicapai dengan Tab.
        */
    }

    /* ---------------------------------------------------------------
       5. Pemindah aplikasi — lambang perusahaan di puncak sidebar.

       Enam tombol aplikasi muncul turun bertahap; jeda antar tombolnya
       diatur di CSS lewat --i, jadi di sini cukup membuka/menutup saja.
       --------------------------------------------------------------- */
    function initAppSwitcher() {
        var btn = document.getElementById('appSwitchBtn');
        var panel = document.getElementById('appSwitch');
        if (!btn || !panel) return;

        function setOpen(open) {
            panel.classList.toggle('open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');

            // Latar belakang diredam & diburamkan selama panel terbuka —
            // lapisannya digambar CSS lewat kelas ini (lihat tesys-ui-shell.css).
            document.body.classList.toggle('app-switch-open', open);
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            setOpen(!panel.classList.contains('open'));
        });

        panel.addEventListener('click', function (e) { e.stopPropagation(); });

        document.addEventListener('click', function () { setOpen(false); });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !panel.classList.contains('open')) return;
            setOpen(false);
            btn.focus();
        });
    }

    /* ---------------------------------------------------------------
       6. Menu aktif selalu terlihat setelah halaman dimuat ulang.
       --------------------------------------------------------------- */
    function revealActiveNav() {
        var nav = document.querySelector('.sidebar .nav');
        if (!nav) return;

        var active = document.querySelector('.sidebar .nav-item.active');
        if (!active) { nav.scrollTop = 0; return; }

        /*
          Diukur dari posisi nyata di layar, bukan dari offsetTop: .sidebar
          memakai position:sticky sehingga menjadi offsetParent, dan hitungan
          lama ikut menghitung tinggi brand — menu paling atas pun jadi
          tergeser sehingga seksi pertama (Overview) tidak terlihat.

          Kalau menu aktif sudah kelihatan, jangan digeser sama sekali.
        */
        var kotakNav = nav.getBoundingClientRect();
        var kotakMenu = active.getBoundingClientRect();

        if (kotakMenu.top >= kotakNav.top && kotakMenu.bottom <= kotakNav.bottom) return;

        nav.scrollTop += (kotakMenu.top - kotakNav.top) - (nav.clientHeight / 2) + (kotakMenu.height / 2);
    }

    /* ---------------------------------------------------------------
       3c. Dropdown di dalam seksi sidebar (mis. "Cost Management").

       Blade sudah menandai grup yang memuat menu aktif dengan class
       "open" saat halaman dirender, jadi di sini cukup mengikat klik
       untuk buka/tutup — tidak perlu mengingat state antar halaman.
       --------------------------------------------------------------- */
    function initNavGroups() {
        document.querySelectorAll('.sidebar .nav-group').forEach(function (group) {
            var toggle = group.querySelector('.nav-group-toggle');
            if (!toggle) return;
            toggle.addEventListener('click', function () {
                var open = group.classList.toggle('open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    }

    /* ---------------------------------------------------------------
       3d. Badge titik kuning di menu Todo List.

       Dicek tiap 5 menit lewat window.tesysPoll (sudah sadar tab — lihat
       definisinya di app.blade.php, dimuat lebih dulu). Tidak menyalakan
       apa pun kalau fetch gagal; badge diam-diam tetap pada keadaan
       terakhir yang berhasil, bukan berkedip hilang.
       --------------------------------------------------------------- */
    function initTodoBadge() {
        var badge = document.getElementById('navTodoBadge');
        var url = badge && badge.getAttribute('data-url');
        if (!badge || !url || typeof window.tesysPoll !== 'function') return;

        function cek() {
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    badge.style.display = data.has_pending ? '' : 'none';
                })
                .catch(function () { /* diam — jangan ganggu sidebar kalau polling gagal */ });
        }

        window.tesysPoll(cek, 5 * 60 * 1000);
    }

    applyStoredSidebarState();

    document.addEventListener('DOMContentLoaded', function () {
        hoistPageTitle();
        keepUserMenuOpenOnInteraction();
        labelNavItems();
        initRailTooltips();
        initScrollState();
        initBusyButtons();
        initSidebarToggle();
        initNavGroups();
        initTodoBadge();
        initQuickSearch();
        initAppSwitcher();
        revealActiveNav();
    });
})();

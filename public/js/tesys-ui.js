/*
 * Tesys UI — interaksi dasar layout baru.
 * Vanilla JS, tidak butuh jQuery. Aman berdampingan dengan jQuery/AdminLTE lama.
 */
(function () {
    'use strict';

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var CSRF = csrf ? csrf.getAttribute('content') : '';

    /* ---------------- Dropdown akun (Profile / Logout) ---------------- */
    var userBtn = document.getElementById('userBtn');
    var userPop = document.getElementById('userPop');
    if (userBtn && userPop) {
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            userPop.classList.toggle('open');
            userBtn.classList.toggle('open');
        });
        document.addEventListener('click', function (e) {
            if (!userPop.contains(e.target) && !userBtn.contains(e.target)) {
                userPop.classList.remove('open');
                userBtn.classList.remove('open');
            }
        });
    }

    var menuLogout = document.getElementById('menuLogout');
    var logoutOverlay = document.getElementById('logoutOverlay');
    if (menuLogout && logoutOverlay) {
        menuLogout.addEventListener('click', function () {
            if (userPop) userPop.classList.remove('open');
            if (userBtn) userBtn.classList.remove('open');
            logoutOverlay.classList.add('open');
        });
    }

    /* ---------------- Overlay / modal generik ---------------- */
    function closeOverlay(el) { if (el) el.classList.remove('open'); }

    document.addEventListener('click', function (e) {
        var closer = e.target.closest('.js-close-modal');
        if (closer) closeOverlay(closer.closest('.overlay'));
    });
    document.querySelectorAll('.overlay').forEach(function (ov) {
        ov.addEventListener('click', function (e) { if (e.target === ov) closeOverlay(ov); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') document.querySelectorAll('.overlay.open').forEach(closeOverlay);
    });

    window.TesysUI = window.TesysUI || {};
    window.TesysUI.openModal = function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.add('open');
    };
    window.TesysUI.closeModal = function (id) { closeOverlay(document.getElementById(id)); };

    /* ---------------- Toast ----------------
       Muncul di kanan bawah, menumpuk ke atas bila ada lebih dari satu,
       dan paling banyak tiga yang tampak bersamaan — yang tertua ditutup
       lebih dulu supaya tumpukannya tidak pernah menutupi layar. Umurnya
       dua detik: cukup untuk dibaca, tidak sampai mengganggu.          */
    var TOAST_MAX  = 3;
    var TOAST_LIFE = 3000;
    var TOAST_JEDA = 450;

    function tutupToast(el) {
        if (!el || el.dataset.closing) return;
        el.dataset.closing = '1';
        el.classList.remove('show');
        setTimeout(function () { el.remove(); }, 250);
    }

    /* Muncul satu per satu: pesan yang datang bersamaan diantre supaya
       tumpukannya terlihat bertambah, bukan langsung penuh sekaligus. */
    var antrean = [];
    var jalan   = false;

    function giliranToast() {
        if (jalan || !antrean.length) return;
        jalan = true;
        tampilkanToast.apply(null, antrean.shift());
        setTimeout(function () { jalan = false; giliranToast(); }, TOAST_JEDA);
    }

    function tampilkanToast(type, title, msg) {
        var stack = document.getElementById('toastStack');
        if (!stack) return;

        var tampil = stack.querySelectorAll('.ts-toast:not([data-closing])');
        for (var i = 0; i <= tampil.length - TOAST_MAX; i++) tutupToast(tampil[i]);

        var el = document.createElement('div');
        el.className = 'ts-toast ts-toast-' + (type || 'info');
        var icon = type === 'success' ? '✓' : ((type === 'error' || type === 'warning') ? '!' : 'i');
        el.innerHTML = '<div class="ts-toast-ic">' + icon + '</div>'
            + '<div class="ts-toast-teks"><div class="ts-toast-title"></div>'
            + '<div class="ts-toast-msg"></div></div>'
            + '<button type="button" class="ts-toast-close" aria-label="Tutup">×</button>';
        el.querySelector('.ts-toast-title').textContent = title || '';
        el.querySelector('.ts-toast-msg').textContent = msg || '';
        el.querySelector('.ts-toast-close').addEventListener('click', function () { tutupToast(el); });

        /* Hitungan ditahan selagi kursor di atas toast. */
        var waktu;
        function hitung() { waktu = setTimeout(function () { tutupToast(el); }, TOAST_LIFE); }
        el.addEventListener('mouseenter', function () { clearTimeout(waktu); el.classList.add('is-hold'); });
        el.addEventListener('mouseleave', function () { el.classList.remove('is-hold'); hitung(); });

        stack.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('show'); });
        hitung();
    }

    window.TesysUI.toast = function (type, title, msg) {
        antrean.push([type, title, msg]);
        giliranToast();
    };

    /*
      Keadaan sibuk pada tombol aksi. Dipakai saat form dikirim dan halaman
      akan dimuat ulang — tanpa ini, menekan Approve tidak memberi tanda apa
      pun sampai halaman berganti.

      Label tidak dihapus, hanya disembunyikan, supaya lebar tombol tetap.
    */
    window.TesysUI.busy = function (el, aktif) {
        var node = typeof el === 'string' ? document.querySelector(el) : el;
        if (!node) return;

        if (aktif === false) {
            node.classList.remove('is-busy');
            node.removeAttribute('aria-busy');
            node.style.width = '';
            node.style.height = '';
            node.style.removeProperty('--ts-busy-color');
            return;
        }

        var kotak = node.getBoundingClientRect();
        node.style.setProperty('--ts-busy-color', window.getComputedStyle(node).color);
        if (kotak.width) {
            node.style.width = kotak.width + 'px';
            node.style.height = kotak.height + 'px';
        }
        node.classList.add('is-busy');
        node.setAttribute('aria-busy', 'true');
    };

    /*
      API toast yang dipakai halaman: toast.success('Tersimpan', 'Perubahan
      sudah disimpan.'). Membungkus TesysUI.toast yang sudah ada — sengaja
      tidak membuat implementasi kedua.

      SweetAlert tetap dipakai untuk konfirmasi dan aksi merusak; toast hanya
      untuk memberi tahu hasil sebuah aksi.
    */
    window.toast = {
        success: function (title, msg) { window.TesysUI.toast('success', title, msg); },
        error:   function (title, msg) { window.TesysUI.toast('error',   title, msg); },
        warning: function (title, msg) { window.TesysUI.toast('warning', title, msg); },
        info:    function (title, msg) { window.TesysUI.toast('info',    title, msg); }
    };

    /* ---------------- Notifikasi (bell) ---------------- */
    var notifBtn = document.getElementById('notifBtn');
    var notifPop = document.getElementById('notifPop');
    var notifList = document.getElementById('notifList');
    var notifDot = document.getElementById('notifDot');
    var notifLoaded = false;

    function renderNotif(items) {
        if (!items || !items.length) {
            notifList.innerHTML = '<div class="empty-state" style="padding:26px 16px;">'
                + '<div class="et">Tidak ada notifikasi</div>'
                + '<div class="ed">Notifikasi baru akan muncul di sini.</div></div>';
            return;
        }
        var colors = {
            success: ['var(--success-bg)', 'var(--success)', '✓'],
            danger: ['var(--danger-bg)', 'var(--danger)', '!'],
            warning: ['var(--warning-bg)', 'var(--warning)', '!'],
            info: ['var(--info-bg)', 'var(--info)', 'i']
        };
        notifList.innerHTML = items.slice(0, 8).map(function (n) {
            var c = colors[n.color || n.type] || colors.info;
            var href = n.url || '#';
            return '<a href="' + href + '" class="notif-item" style="text-decoration:none;color:inherit;">'
                + '<div class="notif-ic" style="background:' + c[0] + ';color:' + c[1] + ';">' + c[2] + '</div>'
                + '<div><div class="tname" style="font-size:12px;">' + (n.title || n.message || '') + '</div>'
                + '<div class="tsub">' + (n.time || n.created_at_human || '') + '</div></div></a>';
        }).join('');
    }

    function loadNotif() {
        if (notifLoaded || !notifList || !window.TESYS_NOTIF_URL) return;
        notifLoaded = true;
        fetch(window.TESYS_NOTIF_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var items = data.items || data.data || data.notifications || [];
                renderNotif(items);
                if (notifDot && (data.unread || items.length)) notifDot.hidden = false;
            })
            .catch(function () {
                notifList.innerHTML = '<div class="empty-state" style="padding:26px 16px;">'
                    + '<div class="et">Gagal memuat notifikasi</div>'
                    + '<div class="ed">Periksa koneksi lalu coba lagi.</div></div>';
                notifLoaded = false;
            });
    }

    if (notifBtn && notifPop) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            notifPop.classList.toggle('open');
            if (notifPop.classList.contains('open')) loadNotif();
        });
        document.addEventListener('click', function (e) {
            if (!notifPop.contains(e.target) && !notifBtn.contains(e.target)) notifPop.classList.remove('open');
        });
    }

    var notifReadAll = document.getElementById('notifReadAll');
    if (notifReadAll && window.TESYS_NOTIF_READ_ALL_URL) {
        notifReadAll.addEventListener('click', function () {
            fetch(window.TESYS_NOTIF_READ_ALL_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function () {
                if (notifDot) notifDot.hidden = true;
                window.TesysUI.toast('success', 'Notifikasi dibaca', 'Semua notifikasi ditandai sudah dibaca.');
            });
        });
    }

    /* ---------------- Tabs (data-tab / data-tab-panel) ---------------- */
    document.querySelectorAll('[data-tab-group]').forEach(function (group) {
        group.querySelectorAll('[data-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var key = tab.getAttribute('data-tab');
                group.querySelectorAll('[data-tab]').forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                document.querySelectorAll('[data-tab-panel][data-tab-owner="' + group.getAttribute('data-tab-group') + '"]')
                    .forEach(function (p) {
                        p.style.display = (p.getAttribute('data-tab-panel') === key) ? '' : 'none';
                    });
            });
        });
    });

    /* ---------------- Konfirmasi hapus (data-confirm) ---------------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-confirm')) return;
        if (form.dataset.confirmed === '1') return;
        e.preventDefault();
        var msg = form.getAttribute('data-confirm') || 'Yakin ingin melanjutkan?';
        if (window.confirm(msg)) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });

    /* ---------------- Label ↔ kontrol (aksesibilitas) ----------------
       Ratusan <label> di view tidak punya for=. Di sini dihubungkan ke
       kontrol bila .form-group label itu hanya memuat SATU kontrol; yang
       ambigu (termasuk label tanpa .form-group) dibiarkan. Input internal
       Tom Select/Flatpickr (.ts-wrapper, .flatpickr-calendar) tidak dihitung,
       jadi hasilnya tidak bergantung pada urutan inisialisasi widget.
       id hanya ditambahkan bila kontrol belum punya, atau bila id-nya
       terduplikasi (baris hasil .clone()); name/value/payload tidak disentuh. */
    var nomorLabel = 0;
    function hubungkanLabel(akar) {
        var daftar = (akar || document).querySelectorAll('label');
        for (var i = 0; i < daftar.length; i++) {
            var lb = daftar[i];
            if (lb.querySelector('input, select, textarea') || lb.closest('.custom-control')) continue;
            var wadah = lb.closest('.form-group');
            if (!wadah) continue;
            /* for= yang sudah ada dibiarkan, kecuali menunjuk kontrol di luar
               .form-group ini (label hasil .clone() masih menunjuk id aslinya). */
            if (lb.hasAttribute('for')) {
                var tujuan = document.getElementById(lb.getAttribute('for'));
                if (!tujuan || wadah.contains(tujuan)) continue;
            }
            var semua = wadah.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea');
            var kontrol = [];
            for (var k = 0; k < semua.length; k++) {
                if (!semua[k].closest('.ts-wrapper, .flatpickr-calendar')) kontrol.push(semua[k]);
            }
            if (kontrol.length !== 1) continue;
            var el = kontrol[0];
            if (!el.id || document.getElementById(el.id) !== el) el.id = 'ts-fld-' + (++nomorLabel);
            lb.setAttribute('for', el.id);
        }
    }
    document.addEventListener('DOMContentLoaded', function () { hubungkanLabel(document); });
    /* Modal/form yang diisi lewat JS: sapu saat modal dibuka. Bootstrap 4
       hanya memicu event jQuery, jadi tidak ada listener native. */
    if (window.jQuery) {
        window.jQuery(document).on('shown.bs.modal', '.modal', function () { hubungkanLabel(this); });
    }
})();

/* =====================================================================
   Tesys — Evidence Stage (carousel lampiran yang sudah tersimpan)
   ---------------------------------------------------------------------
   Satu tampilan standar untuk lampiran/evidence: header (sumber + nama
   berkas, penghitung "1 / n", buka di tab baru), panah, area tampil, dan
   baris thumbnail. Gambar dengan klik-zoom, PDF lewat iframe, berkas lain
   berupa tautan "Buka berkas".

   Item: { name, url, is_image, is_pdf, source, desc, group }
     desc  = deskripsi data (mis. Sub COA + catatan) untuk judul header; kosong → nama berkas.
     group = kunci pengelompok; >1 lampiran se-group di-highlight primary di thumbnail.
     url selalu /storage/{path} (SecureFileController + FileAccess).
     is_image / is_pdf boleh dikosongkan — ditebak dari ekstensi url.

   API:
     TesysEvidenceStage.attach(drawerId)        mode samping drawer (>= 1200px)
         -> { render(items), clear() }          tanpa item carousel tidak tampil
     TesysEvidenceStage.open(items, startIdx)   mode layar penuh
     TesysEvidenceStage.fromAttachments(atts, source)   JSON lampiran drawer -> item
     TesysEvidenceStage.fromCostRequest(detailJson)     semua lampiran satu Cost Request -> item
     TesysEvidenceStage.close()                 tutup layar penuh
     TesysEvidenceStage.isOpen()                layar penuh sedang tampil?

   Pengumpul otomatis dari markup (tanpa JS halaman):
     <div data-evidence-group>
         <a href="/storage/x.jpg" data-evidence data-evidence-name="x.jpg"
            data-evidence-source="Request" data-evidence-type="image|pdf|file">…</a>
     </div>
   Klik biasa membuka layar penuh berisi seluruh tautan [data-evidence] dalam
   grup yang sama; ctrl/cmd/shift-klik dan klik tengah tetap membuka tab baru.
   Grup = kontainer [data-evidence-group], atau semua tautan di halaman dengan nilai
   data-evidence yang sama (mis. data-evidence="po"). Klik pada berkas yang bukan
   gambar/PDF tidak dicegat (langsung buka/unduh seperti biasa).

   Tidak menyentuh data maupun hak akses — hanya menampilkan URL yang diberi.
   ===================================================================== */
(function () {
    'use strict';

    if (window.TesysEvidenceStage) return;

    var SELEKTOR_FOKUS_FORM = 'input, textarea, select, [contenteditable]';
    var SWIPE_MIN = 50; // px, supaya klik/getar kecil tidak terbaca geser

    /* ── util ── */

    // Hanya jalur relatif (/storage/...) atau http(s); skema lain (javascript:, data:) ditolak.
    function amanUrl(u) {
        u = String(u == null ? '' : u).trim();
        return (/^\/(?!\/)/.test(u) || /^https?:\/\//i.test(u)) ? u : '';
    }

    function el(tag, kelas, attrs) {
        var n = document.createElement(tag);
        if (kelas) n.className = kelas;
        if (attrs) Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
        return n;
    }

    function ikon(kelas) {
        var i = el('i', kelas);
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    function tombolIkon(kelas, kelasIkon, label) {
        var b = el('button', kelas, { type: 'button', 'aria-label': label, title: label });
        b.appendChild(ikon(kelasIkon));
        return b;
    }

    function namaDariUrl(u) {
        var p = String(u).split(/[?#]/)[0].split('/');
        try { return decodeURIComponent(p[p.length - 1]) || 'Berkas'; } catch (e) { return p[p.length - 1] || 'Berkas'; }
    }

    /* ── Pemuat thumbnail: terjadwal, bukan serentak ──
       Setiap gambar lampiran dilayani PHP (sesi + hak akses + stream), jadi 13 thumbnail
       sekaligus = 13 proses PHP paralel. Buka-tutup berulang menumpuknya (permintaan yang
       dibatalkan browser tetap diselesaikan server) dan hosting bersama jadi lambat.
       Di sini: thumbnail baru diminta saat terlihat (IntersectionObserver) dan paling
       banyak MAKS_THUMB yang berjalan bersamaan; yang tak terlihat / sudah dibuang tidak
       pernah diminta, yang sedang jalan dibatalkan. */
    var MAKS_THUMB = 3;
    var antrianThumb = [];
    var thumbAktif = 0;

    function jalankanThumb() {
        while (thumbAktif < MAKS_THUMB && antrianThumb.length) {
            var im = antrianThumb.shift();
            if (!im.isConnected || im._tesMulai) continue;
            im._tesMulai = true;
            thumbAktif++;
            (function (g) {
                var selesai = false, timer = null;
                function beres() {
                    if (selesai) return;
                    selesai = true; clearTimeout(timer); g._tesSelesai = null;
                    thumbAktif--; jalankanThumb();
                }
                g._tesSelesai = beres;
                g.addEventListener('load', beres);
                g.addEventListener('error', beres);
                timer = setTimeout(beres, 10000); // jaring pengaman: slot tak boleh macet
                g.src = g.getAttribute('data-src');
            })(im);
        }
    }

    function antrikanThumb(im) {
        antrianThumb.push(im);
        jalankanThumb();
    }

    // Batalkan unduhan thumbnail yang dibuang (drawer ditutup / daftar diganti).
    function lepasThumb(im) {
        if (!im || im.tagName !== 'IMG') return;
        var i = antrianThumb.indexOf(im);
        if (i >= 0) antrianThumb.splice(i, 1);
        if (im._tesSelesai) { im.removeAttribute('src'); im._tesSelesai(); }
    }

    function normalisasi(items) {
        var hasil = [];
        (items || []).forEach(function (it) {
            if (!it) return;
            var url = amanUrl(it.url);
            if (!url) return;
            var isImage = it.is_image, isPdf = it.is_pdf;
            if (isImage == null) isImage = /\.(jpe?g|png|gif|webp|bmp|svg|ico)(\?|#|$)/i.test(url);
            if (isPdf == null) isPdf = /\.pdf(\?|#|$)/i.test(url);
            hasil.push({
                name: it.name ? String(it.name) : namaDariUrl(url),
                url: url,
                is_image: !!isImage,
                is_pdf: !!isPdf && !isImage,
                source: it.source ? String(it.source) : '',
                desc: it.desc ? String(it.desc) : '',
                group: it.group ? String(it.group) : ''
            });
        });
        return hasil;
    }

    // Lampiran dari JSON drawer ({path, original_name|title, is_image?, is_pdf?}) -> item carousel.
    function dariLampiran(atts, source, desc, group) {
        return (atts || []).map(function (a) {
            return {
                desc: desc || '',
                group: group || '',
                url: a.path ? '/storage/' + a.path : '',
                name: a.original_name || a.title || '',
                is_image: a.is_image == null ? null : !!a.is_image,
                is_pdf: a.is_pdf == null ? null : !!a.is_pdf,
                source: source || ''
            };
        });
    }

    // Seluruh lampiran satu Cost Request (payload detail-json): Request, item Request, item Settlement, Settlement global.
    function dariCostRequest(d) {
        d = d || {};
        function gabung(a, b) { a = (a || '').trim(); b = (b || '').trim(); return a && b && a !== b ? a + ' · ' + b : (a || b); }
        var hasil = dariLampiran(d.attachments, 'Lampiran Request', 'Lampiran umum pengajuan');
        (d.details || []).forEach(function (x) {
            hasil = hasil.concat(dariLampiran(x.attachments, 'Item Request',
                gabung(x.sub_coa || x.item_label || x.description, x.notes), 'req-' + x.id));
        });
        (d.realizations || []).forEach(function (x) {
            hasil = hasil.concat(dariLampiran(x.attachments, 'Item Settlement',
                gabung(x.sub_coa || x.cr_item, x.des_settle !== '-' ? x.des_settle : ''), 'set-' + x.id));
        });
        return hasil.concat(dariLampiran(d.realization_attachments, 'Lampiran Settlement', 'Lampiran umum settlement'));
    }

    function lebarDrawerTerbuka() {
        var panel = document.querySelector('.ts-drawer-root.is-open .ts-drawer-panel');
        return panel ? panel.style.getPropertyValue('--drawer-w').trim() : '';
    }

    /* ── Stage: markup + perilaku carousel, dipakai kedua mode ── */

    function Stage(root, opsi) {
        var self = this;
        self.root = root;
        self.items = [];
        self.idx = 0;
        self.sembunyikanThumbTunggal = !!(opsi && opsi.sembunyikanThumbTunggal);

        self.src = el('span', 'tes-ev-src');
        self.nama = el('span', 'tes-ev-name');
        self.hitung = el('span', 'tes-ev-count');
        self.buka = el('a', 'tes-ev-btn', { target: '_blank', rel: 'noopener', title: 'Buka di tab baru', 'aria-label': 'Buka di tab baru' });
        self.buka.appendChild(ikon('fas fa-external-link-alt'));
        self.prev = tombolIkon('tes-ev-nav', 'fas fa-chevron-left', 'Sebelumnya');
        self.next = tombolIkon('tes-ev-nav', 'fas fa-chevron-right', 'Berikutnya');
        self.view = el('div', 'tes-ev-view');
        self.thumbs = el('div', 'tes-ev-thumbs');

        var cap = el('div', 'tes-ev-cap');
        cap.appendChild(self.src);
        cap.appendChild(self.nama);
        var head = el('div', 'tes-ev-head');
        head.appendChild(cap);
        head.appendChild(self.hitung);
        head.appendChild(self.buka);
        self.head = head;

        var main = el('div', 'tes-ev-main');
        main.appendChild(self.prev);
        main.appendChild(self.view);
        main.appendChild(self.next);
        self.main = main;

        root.appendChild(head);
        root.appendChild(main);
        root.appendChild(self.thumbs);

        self.prev.addEventListener('click', function () { self.geser(-1); });
        self.next.addEventListener('click', function () { self.geser(1); });
        self.thumbs.addEventListener('click', function (e) {
            var t = e.target.closest('.tes-ev-thumb');
            if (!t) return;
            self.idx = parseInt(t.getAttribute('data-idx'), 10) || 0;
            self.tampil();
        });

        // Geser mendatar (sentuh) pindah lampiran selama gambar tidak di-zoom.
        var sx = null, sy = null;
        self.view.addEventListener('touchstart', function (e) {
            if (self.view.classList.contains('is-zoomed')) { sx = null; return; }
            sx = e.changedTouches[0].clientX; sy = e.changedTouches[0].clientY;
        }, { passive: true });
        self.view.addEventListener('touchend', function (e) {
            if (sx === null) return;
            var dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
            sx = null;
            if (Math.abs(dx) < SWIPE_MIN || Math.abs(dx) <= Math.abs(dy)) return;
            self.geser(dx < 0 ? 1 : -1);
        }, { passive: true });
    }

    Stage.prototype.set = function (items, startIdx) {
        this.items = normalisasi(items);
        this.idx = Math.min(Math.max(startIdx || 0, 0), Math.max(this.items.length - 1, 0));
        var self = this;
        if (this.pengamatThumb) { this.pengamatThumb.disconnect(); this.pengamatThumb = null; }
        while (this.thumbs.firstChild) {
            lepasThumb(this.thumbs.firstChild.querySelector('img'));
            this.thumbs.removeChild(this.thumbs.firstChild);
        }
        var pengamat = null;
        if (window.IntersectionObserver) {
            pengamat = this.pengamatThumb = new IntersectionObserver(function (entri) {
                entri.forEach(function (en) {
                    if (!en.isIntersecting) return;
                    pengamat.unobserve(en.target);
                    antrikanThumb(en.target);
                });
            }, { root: this.thumbs, rootMargin: '0px 160px' });
        }
        this.items.forEach(function (it, i) {
            var b = el('button', 'tes-ev-thumb', { type: 'button', 'data-idx': String(i), title: (it.source ? it.source + ' · ' : '') + (it.desc || it.name) });
            if (it.is_image) {
                var im = el('img', '', { 'data-src': it.url, alt: '', decoding: 'async', fetchpriority: 'low' });
                b.appendChild(im);
                if (pengamat) pengamat.observe(im); else antrikanThumb(im);
            } else {
                b.appendChild(ikon('fas ' + (it.is_pdf ? 'fa-file-pdf' : 'fa-file-alt')));
            }
            self.thumbs.appendChild(b);
        });
        // Item dengan >1 lampiran: thumbnail-nya diberi highlight grup (warna primary).
        var hitungGrup = {};
        this.items.forEach(function (it) { if (it.group) hitungGrup[it.group] = (hitungGrup[it.group] || 0) + 1; });
        this.items.forEach(function (it, i) {
            if (!it.group || hitungGrup[it.group] < 2) return;
            var t = self.thumbs.children[i], p = self.items[i - 1], n = self.items[i + 1];
            t.classList.add('is-grouped');
            if (!p || p.group !== it.group) t.classList.add('is-group-start');
            if (!n || n.group !== it.group) t.classList.add('is-group-end');
        });
        this.thumbs.hidden = this.sembunyikanThumbTunggal && this.items.length < 2;
        this.tampil();
    };

    Stage.prototype.tampil = function () {
        var n = this.items.length, it = this.items[this.idx];
        this.view.classList.remove('is-zoomed');
        while (this.view.firstChild) this.view.removeChild(this.view.firstChild);

        this.prev.disabled = this.idx <= 0;
        this.next.disabled = this.idx >= n - 1;
        this.prev.style.display = this.next.style.display = n > 1 ? '' : 'none';
        var self = this;
        Array.prototype.forEach.call(this.thumbs.children, function (t, i) {
            t.classList.toggle('is-active', i === self.idx);
        });
        if (!it) {
            this.src.textContent = '';
            this.nama.textContent = '';
            this.hitung.textContent = '0';
            this.buka.style.display = 'none';
            return;
        }
        this.src.textContent = it.source;
        this.src.style.display = it.source ? '' : 'none';
        this.nama.textContent = it.desc || it.name;
        this.nama.title = it.name;
        this.hitung.textContent = (this.idx + 1) + ' / ' + n;
        this.buka.setAttribute('href', it.url);
        this.buka.style.display = '';

        if (it.is_image) {
            var gbr = el('img', '', { src: it.url, alt: it.name, title: 'Scroll untuk zoom · seret untuk menggeser · klik dua kali untuk reset', draggable: 'false', decoding: 'async', fetchpriority: 'high' });
            this.view.appendChild(gbr);
            this.zoomGambar(gbr);
        } else if (it.is_pdf) {
            this.view.appendChild(el('iframe', '', { src: it.url, title: it.name }));
        } else {
            var f = el('div', 'tes-ev-file');
            f.appendChild(ikon('fas fa-file-alt'));
            f.appendChild(document.createTextNode(it.name));
            var w = el('div', 'mt-2');
            var a = el('a', '', { href: it.url, target: '_blank', rel: 'noopener' });
            a.textContent = 'Buka berkas';
            w.appendChild(a);
            f.appendChild(w);
            this.view.appendChild(f);
        }
    };

    // Zoom ala WhatsApp: scroll = zoom di posisi kursor, seret = geser, klik dua kali = zoom/reset.
    // Gambar selalu di tengah area tampil, jadi transform-origin tengah dipakai sebagai acuan.
    Stage.prototype.zoomGambar = function (img) {
        var view = this.view, MAX = 6, s = 1, tx = 0, ty = 0, drag = null, geser = false;

        function batas() {
            var mx = Math.max(0, (img.offsetWidth * s - view.clientWidth) / 2);
            var my = Math.max(0, (img.offsetHeight * s - view.clientHeight) / 2);
            tx = Math.min(mx, Math.max(-mx, tx));
            ty = Math.min(my, Math.max(-my, ty));
        }
        function terapkan() {
            if (s <= 1.001) { s = 1; tx = ty = 0; }
            batas();
            img.style.transform = s === 1 ? '' : 'translate(' + tx + 'px,' + ty + 'px) scale(' + s + ')';
            view.classList.toggle('is-zoomed', s > 1);
        }
        function zoomKe(baru, cx, cy) {
            baru = Math.min(MAX, Math.max(1, baru));
            var r = view.getBoundingClientRect();
            var px = cx - (r.left + r.width / 2), py = cy - (r.top + r.height / 2);
            tx = px - (px - tx) * baru / s;
            ty = py - (py - ty) * baru / s;
            s = baru;
            terapkan();
        }

        img.addEventListener('wheel', function (e) {
            e.preventDefault();
            var d = e.deltaMode === 1 ? e.deltaY * 16 : e.deltaY;
            zoomKe(s * Math.exp(-d * 0.0015), e.clientX, e.clientY);
        }, { passive: false });

        img.addEventListener('dblclick', function (e) {
            e.preventDefault();
            if (s > 1) zoomKe(1, e.clientX, e.clientY); else zoomKe(2.5, e.clientX, e.clientY);
        });

        img.addEventListener('pointerdown', function (e) {
            if (e.button !== 0 || s <= 1) return;
            drag = { x: e.clientX, y: e.clientY, tx: tx, ty: ty };
            geser = false;
            try { img.setPointerCapture(e.pointerId); } catch (x) {}
            view.classList.add('is-dragging');
            e.preventDefault();
        });
        img.addEventListener('pointermove', function (e) {
            if (!drag) return;
            tx = drag.tx + (e.clientX - drag.x);
            ty = drag.ty + (e.clientY - drag.y);
            geser = true;
            terapkan();
        });
        function selesai(e) {
            if (!drag) return;
            drag = null;
            view.classList.remove('is-dragging');
            try { img.releasePointerCapture(e.pointerId); } catch (x) {}
        }
        img.addEventListener('pointerup', selesai);
        img.addEventListener('pointercancel', selesai);
    };

    Stage.prototype.geser = function (arah) {
        var n = this.idx + arah;
        if (n < 0 || n >= this.items.length) return;
        this.idx = n;
        this.tampil();
    };

    /* ── Mode samping drawer ── */

    var samping = {}; // id drawer -> { stage, root }

    function attach(drawerId) {
        if (samping[drawerId]) return samping[drawerId].api;
        var root = document.getElementById(drawerId);
        if (!root) return null;

        var box = el('div', 'tes-ev-side', { 'aria-label': 'Evidence' });
        var stage = new Stage(box);
        root.appendChild(box);

        // Tutup carousel saja (drawer tetap terbuka); muncul lagi saat drawer memuat data baru.
        var tutup = tombolIkon('tes-ev-btn', 'fas fa-times', 'Tutup carousel');
        stage.head.appendChild(tutup);
        tutup.addEventListener('click', function () { root.classList.remove('has-evidence'); });

        function sinkronLebar() {
            var panel = root.querySelector('.ts-drawer-panel');
            var w = panel ? panel.style.getPropertyValue('--drawer-w').trim() : '';
            if (w) root.style.setProperty('--tes-ev-w', w);
        }

        var api = {
            // Tanpa item carousel tidak tampil sama sekali (bukan pesan kosong).
            render: function (items) {
                stage.set(items, 0);
                sinkronLebar();
                root.classList.toggle('has-evidence', stage.items.length > 0);
            },
            clear: function () { api.render([]); }
        };
        samping[drawerId] = { stage: stage, root: root, api: api };

        // Drawer ditutup → carousel ikut hilang (setelah animasi keluar drawer).
        new MutationObserver(function () {
            if (root.classList.contains('is-open')) return;
            window.setTimeout(function () { if (!root.classList.contains('is-open')) api.clear(); }, 260);
        }).observe(root, { attributes: true, attributeFilter: ['class'] });

        return api;
    }

    /* ── Mode layar penuh ── */

    var ov = null;          // { box, stage }
    var pengamat = null;    // MutationObserver drawer pemilik overlay

    function pastikanOverlay() {
        if (ov) return ov;
        var box = el('div', 'tes-ev-overlay', { role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Penampil lampiran' });
        var stage = new Stage(box, { sembunyikanThumbTunggal: true });
        var tutup = tombolIkon('tes-ev-btn', 'fas fa-times', 'Tutup');
        stage.head.appendChild(tutup);
        tutup.addEventListener('click', tutupOverlay);
        // Klik area kosong (bukan gambar/PDF) menutup.
        box.addEventListener('click', function (e) {
            if (e.target === box || e.target === stage.main) tutupOverlay();
        });
        document.body.appendChild(box);
        ov = { box: box, stage: stage };
        return ov;
    }

    // Drawer terbuka dengan panggung samping yang tampil (>= 1200px) dan memuat item
    // yang diklik: cukup pilih item itu di sana, tidak perlu overlay kedua.
    function pilihDiSamping(items, startIdx) {
        var dipilih = normalisasi([items && items[startIdx]])[0];
        if (!dipilih) return false;
        return Object.keys(samping).some(function (id) {
            var s = samping[id];
            if (!s.root.classList.contains('is-open') || !s.root.classList.contains('has-evidence')) return false;
            if (getComputedStyle(s.stage.root).display === 'none') return false;
            for (var i = 0; i < s.stage.items.length; i++) {
                if (s.stage.items[i].url === dipilih.url) { s.stage.idx = i; s.stage.tampil(); return true; }
            }
            return false;
        });
    }

    function bukaOverlay(items, startIdx) {
        if (pilihDiSamping(items, startIdx)) return;
        var o = pastikanOverlay();
        o.stage.set(items, startIdx);
        if (!o.stage.items.length) return; // jangan buka overlay kosong

        // Drawer terbuka (layar lebar): overlay mengisi ruang di kiri panel drawer.
        var lebar = lebarDrawerTerbuka();
        o.box.style.right = (lebar && window.innerWidth > 640) ? 'min(' + lebar + ', calc(100vw - 32px))' : '';
        o.box.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Drawer pemilik ditutup → overlay ikut tertutup.
        var drawer = document.querySelector('.ts-drawer-root.is-open');
        if (pengamat) pengamat.disconnect();
        pengamat = null;
        if (drawer) {
            pengamat = new MutationObserver(function () {
                if (!drawer.classList.contains('is-open')) tutupOverlay();
            });
            pengamat.observe(drawer, { attributes: true, attributeFilter: ['class'] });
        }
    }

    function tutupOverlay() {
        if (!ov || !ov.box.classList.contains('show')) return;
        ov.box.classList.remove('show');
        ov.box.style.right = '';
        // Hentikan PDF/gambar yang masih termuat.
        ov.stage.set([], 0);
        // Kunci gulir milik drawer (ts-drawer-lock) dibiarkan bekerja sendiri.
        document.body.style.overflow = '';
        if (pengamat) { pengamat.disconnect(); pengamat = null; }
    }

    function terbuka() { return !!ov && ov.box.classList.contains('show'); }

    /* ── Keyboard ── */

    // Fase capture: Escape yang menutup overlay tidak ikut menutup drawer / handler halaman.
    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && terbuka()) {
            e.preventDefault();
            e.stopPropagation();
            tutupOverlay();
            return;
        }
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        // Overlay layar penuh berada di atas modal (mis. dibuka dari modal), jadi panahnya tetap aktif.
        if (!terbuka() && document.querySelector('.modal.show, .swal2-container')) return;
        if (e.target && e.target.closest && e.target.closest(SELEKTOR_FOKUS_FORM)) return;

        var aktif = null;
        if (terbuka()) aktif = ov.stage;
        else {
            Object.keys(samping).some(function (id) {
                var s = samping[id];
                if (s.root.classList.contains('is-open') && s.stage.items.length) { aktif = s.stage; return true; }
                return false;
            });
        }
        if (aktif) aktif.geser(e.key === 'ArrowRight' ? 1 : -1);
    }, true);

    /* ── Pengumpul otomatis dari markup ── */

    function itemDariTautan(t) {
        var tipe = t.getAttribute('data-evidence-type');
        return {
            url: t.getAttribute('href'),
            name: t.getAttribute('data-evidence-name') || (t.textContent || '').trim(),
            source: t.getAttribute('data-evidence-source') || '',
            is_image: tipe ? tipe === 'image' : null,
            is_pdf: tipe ? tipe === 'pdf' : null
        };
    }

    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[data-evidence]');
        if (!a || e.defaultPrevented || e.button !== 0) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return; // tab baru tetap bisa

        // Grup: kontainer [data-evidence-group]; bila tidak ada, semua tautan dengan
        // nilai data-evidence yang sama di halaman; bila nilainya kosong, tautan itu sendiri.
        var nilai = a.getAttribute('data-evidence');
        var grup = a.closest('[data-evidence-group]');
        var tautan;
        if (grup) tautan = Array.prototype.slice.call(grup.querySelectorAll('a[data-evidence]'));
        else if (nilai) tautan = Array.prototype.filter.call(document.querySelectorAll('a[data-evidence]'), function (t) { return t.getAttribute('data-evidence') === nilai; });
        else tautan = [a];

        var items = tautan.map(itemDariTautan);
        var mulai = Math.max(tautan.indexOf(a), 0);
        var dipilih = normalisasi([items[mulai]])[0];
        // URL tak aman, atau berkas yang bukan gambar/PDF (kartu "Buka berkas" tak ada gunanya
        // untuk satu klik): biarkan tautan membuka tab baru seperti biasa.
        if (!dipilih || (!dipilih.is_image && !dipilih.is_pdf)) return;

        // Item dengan url tak aman disaring normalisasi; hitung ulang posisi mulai.
        var ada = items.map(function (it) { return !!amanUrl(it.url); });
        var geser = ada.slice(0, mulai).filter(function (x) { return !x; }).length;
        e.preventDefault();
        bukaOverlay(items, mulai - geser);
    });

    window.TesysEvidenceStage = {
        attach: attach,
        open: bukaOverlay,
        fromAttachments: dariLampiran,
        fromCostRequest: dariCostRequest,
        close: tutupOverlay,
        isOpen: terbuka
    };
})();

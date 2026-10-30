{{-- Gaya & skrip bersama halaman Trouble Ticket. Hanya token dari
     tesys-ui-tokens.css; tidak ada warna baru. --}}
@push('styles')
<style>
.tt-page .tt-title { font-size:20px; font-weight:700; color:var(--text); margin:0; }
.tt-page .tt-sub   { color:var(--text-faint); font-size:13px; margin-top:2px; }

/* Input tanggal: ikon kalender di ujung kanan kotak isian. Ikonnya tembus
   klik (pointer-events:none) supaya klik di atasnya tetap membuka flatpickr.
   Pembungkus dipakai karena flatpickr altInput menyisipkan input kedua. */
.tt-date-field { position:relative; }
.tt-date-field > input { padding-right:30px; }
.tt-date-field > .tt-date-ico {
    position:absolute; right:10px; top:50%; transform:translateY(-50%);
    color:var(--text-faint); font-size:13px; pointer-events:none;
}

/* SLA */
.tt-sla { display:inline-flex; align-items:center; gap:4px; font-size:12px; white-space:nowrap; font-variant-numeric:tabular-nums; }
.tt-sla-na, .tt-sla-none, .tt-sla-ok { color:var(--text-dim); }
.tt-sla-warning { color:var(--warning); font-weight:600; }
.tt-sla-critical { color:var(--danger); font-weight:700; }
.tt-sla-tag { font-size:10px; text-transform:uppercase; letter-spacing:.04em; padding:0 5px; border-radius:var(--radius-pill); background:var(--danger-bg); color:var(--danger); }

/* Severity: kelas dari level (1 = paling parah) */
.tt-sev { font-size:11px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; }
.tt-sev-1 { color:var(--danger); }   /* Kritis — aging >= 8 hari */
.tt-sev-2 { color:var(--warning); }  /* Major  — aging 3-7 hari  */
.tt-sev-3 { color:var(--info); }     /* Minor  — aging 1-2 hari  */
.tt-sev-4, .tt-sev-5, .tt-sev-6, .tt-sev-7, .tt-sev-8, .tt-sev-9 { color:var(--text-dim); } /* Rendah */

/* Daftar */
.tt-table { font-size:12.5px; }
.tt-table th { font-size:11px; text-transform:uppercase; color:var(--text-faint); white-space:nowrap; font-weight:600; }
.tt-table th a { color:inherit; }
.tt-table td { vertical-align:middle; }
.tt-table tbody tr { cursor:pointer; }
.tt-table .tt-no { font-weight:700; color:var(--text); white-space:nowrap; }
.tt-table .tt-trunc { max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tt-filter .form-control, .tt-filter .btn { font-size:12.5px; }
.tt-filter label { font-size:11px; color:var(--text-faint); text-transform:uppercase; margin-bottom:2px; }

/* ================= Detail tiket =================
   Desktop: #ttDetail setinggi sisa layar (diatur fitDetail() di JS), jadi halaman
   tidak pernah menggulir; yang menggulir hanya isi chat & isi tab. */
.tt-detail { display:flex; flex-direction:column; }
.tt-detail .tt-head { flex:0 0 auto; }
.tt-detail .tt-head .tt-title { font-size:17px; }
.tt-detail .tt-head .tt-sub { font-size:12.5px; margin-top:0; }
.tt-detail .tt-flow { flex:0 0 auto; }
/* flex-basis 0 + tinggi 100%: isi (chat panjang) tidak boleh memanjangkan baris; yang menggulir hanya daftar chat & isi tab. */
.tt-detail .tt-panes { margin-bottom:0; }
@media (min-width: 992px) {
    .tt-detail .tt-panes { flex:1 1 0; min-height:0; flex-wrap:nowrap; }
    .tt-detail .tt-pane { height:100%; }
    /* Halaman detail memang sudah dipaskan setinggi layar oleh fitDetail();
       scrollbar halaman dimatikan supaya sisa 1-2 piksel pembulatan tidak
       memunculkan batang gulir kosong di tepi kanan (20 Okt 2026). Kelasnya
       dipasang JS, jadi halaman lain dan tampilan HP tidak ikut terkunci. */
    html.tt-detail-open, body.tt-detail-open { overflow-y:hidden; }
}
.tt-detail .tt-pane { min-height:0; }
.tt-fill { height:100%; display:flex; flex-direction:column; min-height:0; overflow:hidden; }
/* Angka yang membuka detail Cost Request — gaya pil yang sama dengan
   .cr-cell-link di halaman Cost Request (20 Okt 2026), supaya angka yang bisa
   diklik terlihat sama di kedua modul. */
/* Ditulis sebagai `body.tesys-ui a.tt-cell-link` (0-2-1), bukan
   `.tt-cell-link` (0-1-0): aturan `body.tesys-ui a` di tesys-ui-compat.css
   berkekhususan 0-1-1 dan akan memenangi warnanya. Di Cost Request persoalan
   ini tidak muncul karena elemennya <button>, bukan <a>. */
body.tesys-ui a.tt-cell-link {
    display:inline-block; background:transparent; border:none; padding:2px 8px; border-radius:var(--radius-sm);
    color:var(--text); font-weight:700; font-size:13px; line-height:1.4;
    transition:background-color .12s ease, color .12s ease;
}
body.tesys-ui a.tt-cell-link:hover,
body.tesys-ui a.tt-cell-link:focus { background:var(--charcoal); color:#fff; outline:none; text-decoration:none; }

/* Kanvas tanda tangan penerima dana (modal Settlement). touch-action:none
   supaya coretan jari tidak ikut menggulir halaman di HP. */
/* Tombol aksi berbentuk ikon di tabel settlement — selebar isinya saja. */
.tt-icon-btn { padding:2px 8px; line-height:1.2; }

.tt-signpad { width:100%; height:140px; border:1px dashed var(--border); border-radius:var(--radius-sm); background:#fff; touch-action:none; cursor:crosshair; }

/* Tabel item biaya plan (modal Create Plan & tab Tiket) — rapat, mengikuti
   kepadatan tabel lain di modul ini. */
.tt-cost-table { font-size:12.5px; }
.tt-cost-table th, .tt-cost-table td { padding:4px 6px; vertical-align:middle; }
.tt-cost-table .form-control-sm { font-size:12.5px; height:28px; }

.tt-card-head { min-height:36px; }
.tt-card-head .card-title { font-size:13.5px; }
.tt-card-ticket { font-weight:700; font-size:13px; color:var(--text); white-space:nowrap; }
.tt-head-site { font-size:16px; margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
.tt-head > .ts-status, .tt-head > .tt-sev { flex:0 0 auto; }

/* Kartu info */
.tt-info-card .card-body { padding:10px 14px; }
.tt-kv { display:flex; gap:10px; padding:4px 0; border-bottom:1px solid var(--border-soft); font-size:12.5px; align-items:baseline; }
.tt-kv:last-child { border-bottom:0; }
.tt-kv dt { flex:0 0 36%; font-weight:600; color:var(--text-faint); margin:0; }
.tt-kv dd { flex:1; margin:0; color:var(--text); min-width:0; overflow-wrap:anywhere; }

/* Chat */
.tt-chat { position:relative; display:flex; flex-direction:column; flex:1 1 0; min-height:0; }
/* Scrollbar disembunyikan di halaman detail (keputusan 19 Okt 2026) —
   gulirannya tetap jalan, hanya batangnya yang tidak digambar. */
.tt-chat-list, .tt-tab-body, .tt-info-log { scrollbar-width:none; -ms-overflow-style:none; }
.tt-chat-list::-webkit-scrollbar, .tt-tab-body::-webkit-scrollbar, .tt-info-log::-webkit-scrollbar { width:0; height:0; display:none; }
/* Log di modal Info: tingginya dibatasi supaya modal tidak memanjang. */
.tt-info-log { max-height:260px; overflow-y:auto; }
.tt-chat-list { flex:1 1 0; min-height:0; overflow-y:auto; padding:10px 14px; background:var(--card-bg); border-radius:var(--radius-md); }
.tt-msg { display:flex; gap:8px; margin-bottom:8px; transition:background var(--dur-fast, .15s); }
/* Avatar selalu ada — di kiri untuk peran lain, di kanan untuk teknisi. */
.tt-msg.is-tech { flex-direction:row-reverse; }
.tt-msg-av, .tt-event-av { overflow:hidden; }
.tt-msg-av img, .tt-event-av img { width:100%; height:100%; object-fit:cover; }
.tt-msg-av { width:26px; height:26px; flex:0 0 26px; border-radius:50%; background:var(--surface-2); border:1px solid var(--border);
    display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:var(--text-dim); }
.tt-msg-col { display:flex; flex-direction:column; max-width:78%; }
.tt-msg.is-tech .tt-msg-col { align-items:flex-end; }
.tt-bubble-head { display:flex; align-items:baseline; gap:4px; margin:0 2px 2px; }
.tt-bubble-name { font-size:11px; font-weight:600; color:var(--text-dim); }
.tt-bubble { background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius-md); padding:6px 10px; font-size:13px; }
.tt-bubble.is-plain { background:transparent; border:0; padding:2px 2px; }
.tt-bubble-body { white-space:pre-wrap; overflow-wrap:anywhere; color:var(--text); }
.tt-bubble-time { font-size:10.5px; color:var(--text-faint); font-variant-numeric:tabular-nums; }
/* Kejadian workflow rata KANAN (keputusan 12 Okt 2026), tidak lagi di tengah. */
/* Kejadian sistem: baris kiri — avatar, nama & waktu, lalu chip kejadian. */
.tt-event { display:flex; gap:8px; margin:8px 0; font-size:11.5px; color:var(--text-dim); }
.tt-event-av { width:26px; height:26px; flex:0 0 26px; border-radius:50%; background:var(--surface-2); border:1px solid var(--border);
    display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:var(--text-dim); }
.tt-event-box { flex:1; min-width:0; }
.tt-event-head { display:flex; align-items:baseline; gap:4px; font-size:12px; color:var(--text); }
.tt-event-time { color:var(--text-faint); font-size:11px; font-variant-numeric:tabular-nums; }
/* Satu kotak berisi chip kejadian dan keterangannya. */
.tt-event-card { display:inline-block; margin-top:3px; padding:5px 8px; border-radius:var(--radius-sm);
    background:var(--card-bg); border:1px solid var(--border); text-align:left; }
.tt-event-chip { display:inline-block; padding:2px 10px; border-radius:999px; background:var(--surface-2);
    border:1px solid var(--border); font-size:11.5px; font-weight:600; color:var(--text-dim); }
/* Keterangan kejadian: teks polos di samping chip (seperti log Cost Request). */
.tt-event-note { margin:4px 2px 0; color:var(--text); font-size:11.5px;
    white-space:pre-wrap; overflow-wrap:anywhere; }
/* Kejadian yang dipicu teknisi duduk di kanan, seirama gelembung pesannya. */
.tt-event.is-tech { flex-direction:row-reverse; }
.tt-event.is-tech .tt-event-box { text-align:right; }
.tt-event.is-tech .tt-event-head { justify-content:flex-end; }
.tt-event.is-tech .tt-att { align-items:flex-end; }
.tt-flash .tt-bubble, .tt-flash .tt-event-box, .tt-flash.tt-event .tt-event-box { box-shadow:0 0 0 3px var(--accent-ring); }

/* Lampiran di bubble: grid thumbnail persegi + baris berkas ringkas */
.tt-att { display:flex; flex-direction:column; gap:4px; margin-top:6px; }
.tt-bubble-body + .tt-att { margin-top:6px; }
.tt-bubble > .tt-att:first-child { margin-top:0; }
.tt-att-grid { display:grid; gap:4px; grid-template-columns:repeat(4, 60px); }
.tt-att-grid.tt-att-n1 { grid-template-columns:96px; }
.tt-att-grid.tt-att-n2 { grid-template-columns:repeat(2, 76px); }
.tt-att-grid.tt-att-n3 { grid-template-columns:repeat(3, 66px); }
.tt-att-thumb { display:block; aspect-ratio:1 / 1; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--border);
    background:var(--surface-2); position:relative; }
.tt-att-n1 .tt-att-thumb { aspect-ratio:4 / 3; }
.tt-att-thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .15s ease; }
.tt-att-thumb:hover img { transform:scale(1.04); }
.tt-att-thumb.is-more { display:none; }
.tt-att-grid.is-open .tt-att-thumb.is-more { display:block; }
.tt-att-grid.is-open .tt-att-moretile { display:none; }
.tt-att-moretile { border:1px solid var(--border); background:var(--charcoal); color:#fff; font-weight:700; font-size:16px; cursor:pointer; }
.tt-att-file { display:flex; align-items:center; gap:8px; max-width:280px; border:1px solid var(--border); border-radius:var(--radius-sm);
    padding:5px 8px; background:var(--surface); color:var(--text); font-size:12px; }
.tt-att-file:hover { border-color:var(--accent); color:var(--text); text-decoration:none; }
.tt-att-ic { color:var(--accent-text); font-size:15px; flex:0 0 auto; }
.tt-att-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; }
.tt-att-size { color:var(--text-faint); flex:0 0 auto; }
.tt-att-go { color:var(--text-faint); font-size:11px; flex:0 0 auto; }
@media (max-width: 575.98px) {
    .tt-att-grid { grid-template-columns:repeat(3, 56px); }
    .tt-att-grid.tt-att-n1 { grid-template-columns:92px; }
    .tt-att-grid.tt-att-n2 { grid-template-columns:repeat(2, 72px); }
}

/* Composer: satu baris, tumbuh sampai 4 baris (lihat autosize() di JS) */
.tt-chat-form { flex:0 0 auto; border-top:1px solid var(--border); padding:8px 10px; background:var(--card-bg); }
.tt-composer { display:flex; align-items:flex-end; gap:6px; }
.tt-input { flex:1; resize:none; overflow-y:hidden; border:1px solid var(--border); border-radius:var(--radius-md);
    padding:7px 10px; font-size:13px; line-height:20px; min-height:36px; background:var(--surface); color:var(--text); }
.tt-input:focus { outline:0; border-color:var(--accent); box-shadow:0 0 0 2px var(--accent-ring); }
.tt-icon-btn, .tt-send { flex:0 0 36px; height:36px; display:inline-flex; align-items:center; justify-content:center;
    border-radius:var(--radius-md); cursor:pointer; font-size:14px; }
.tt-icon-btn { border:1px solid var(--border); color:var(--text-dim); background:var(--surface); }
.tt-icon-btn:hover { color:var(--text); border-color:var(--border-strong); }
.tt-send { padding:0; }
.tt-send i { opacity:1; }
.tt-send:disabled { opacity:.6; cursor:default; }
.tt-pending { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:6px; align-items:center; }
.tt-pend { display:flex; align-items:center; gap:6px; border:1px solid var(--border); border-radius:var(--radius-sm);
    background:var(--surface); padding:3px 4px 3px 3px; max-width:220px; font-size:12px; }
.tt-pend img, .tt-pend-ic { width:30px; height:30px; flex:0 0 30px; border-radius:var(--radius-sm); object-fit:cover; }
.tt-pend-ic { display:inline-flex; align-items:center; justify-content:center; background:var(--surface-2); color:var(--text-dim); }
.tt-pend-name { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--text); }
.tt-pend-name small { display:block; color:var(--text-faint); font-size:10.5px; }
.tt-pend-x { border:0; background:transparent; color:var(--text-faint); padding:2px 5px; cursor:pointer; }
.tt-pend-x:hover { color:var(--danger); }
.tt-pend-clear { border:0; background:transparent; color:var(--text-dim); font-size:11.5px; text-decoration:underline; cursor:pointer; }
.tt-drop { position:absolute; inset:0; z-index:5; display:flex; align-items:center; justify-content:center;
    background:var(--accent-tint); border:2px dashed var(--accent); border-radius:var(--radius-md); pointer-events:none; }
.tt-drop[hidden] { display:none; }
.tt-drop > div { display:flex; flex-direction:column; align-items:center; gap:6px; color:var(--accent-text); font-weight:600; font-size:13px; }
.tt-drop i { font-size:26px; }
.tt-actions .btn { font-size:12.5px; }

/* Tab: tanpa scrollbar — 5 tab dibagi rata (nav-fill), label menyusut di layar sempit */
.tt-tabs { border-bottom:0; padding:0 4px; flex-wrap:nowrap; overflow:hidden; }
.tt-tabs .nav-item { min-width:0; }
.tt-tabs .nav-link { display:flex; align-items:center; justify-content:center; gap:5px; font-size:12px; font-weight:600;
    color:var(--text-dim); padding:9px 4px; border:0; border-bottom:2px solid transparent; white-space:nowrap; overflow:hidden; }
.tt-tabs .nav-link i { font-size:11.5px; opacity:.8; }
.tt-tabs .nav-link:hover { color:var(--text); }
.tt-tabs .nav-link.active { color:var(--accent-text); background:transparent; border-bottom-color:var(--accent); }
.tt-tab-count { font-size:10px; background:var(--surface-2); color:var(--text-dim); border-radius:var(--radius-pill); padding:0 5px; }
.tt-tab-body { flex:1 1 auto; min-height:0; overflow-y:auto; }

/* Summary */
.tt-hero { display:flex; align-items:center; gap:8px; padding-bottom:8px; border-bottom:1px solid var(--border-soft); }
.tt-hero-main { flex:1; min-width:0; }
.tt-hero-title { font-weight:700; font-size:14px; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tt-hero-sub { font-size:12px; color:var(--text-faint); }
.tt-hero-act { display:flex; gap:4px; }
.tt-person { display:flex; align-items:center; gap:8px; padding:8px 0; border-bottom:1px solid var(--border-soft); }
.tt-person-sm { padding:6px 0; }
.tt-avatar { width:30px; height:30px; flex:0 0 30px; border-radius:50%; background:var(--accent-tint); color:var(--accent-text);
    display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; }
.tt-person-main { flex:1; min-width:0; }
.tt-person-name { font-weight:600; font-size:13px; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tt-person-sub { font-size:11.5px; color:var(--text-faint); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tt-chips { display:flex; flex-wrap:wrap; gap:5px; margin-top:8px; }
.tt-chip { display:inline-flex; align-items:center; gap:5px; font-size:11.5px; padding:2px 8px; border-radius:var(--radius-pill);
    background:var(--surface-2); border:1px solid var(--border); color:var(--text-dim); }
.tt-chip.tt-sev { text-transform:uppercase; }
.tt-issue { margin-top:6px; font-size:13px; color:var(--text); }
.tt-sum-sla { display:grid; grid-template-columns:repeat(3, 1fr); gap:6px; margin-top:10px; }
.tt-sla-card { text-align:left; border:1px solid var(--border); border-radius:var(--radius-sm); padding:6px 8px; background:var(--surface); cursor:pointer; }
.tt-sla-card:hover { border-color:var(--accent); }
.tt-sla-card-label { display:block; font-size:10.5px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-faint); margin-bottom:2px; }
.tt-next { display:flex; gap:8px; margin-top:10px; padding:8px 10px; border-radius:var(--radius-sm); background:var(--surface-2); font-size:12.5px; color:var(--text); }
.tt-next > i { color:var(--info); margin-top:2px; }
.tt-copy { border:0; background:transparent; color:var(--text-faint); padding:0 3px; font-size:12px; cursor:pointer; }
.tt-copy:hover { color:var(--accent-text); }
/* Warna judul bagian dinaikkan dari --text-faint ke --text (20 Okt 2026):
   bobotnya memang sudah 700, tapi dengan warna paling redup ia terbaca
   selemah teks biasa, sehingga Identitas/Gangguan/Biaya Plan/SLA tidak
   tampak sebagai kepala bagian. */
.tt-sec-title { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--text); font-weight:700; margin:10px 0 2px; }

/* Tiket: grup bisa dilipat */
.tt-acc { border-bottom:1px solid var(--border-soft); }
.tt-acc > summary { list-style:none; cursor:pointer; padding:7px 0; font-size:11.5px; font-weight:700; text-transform:uppercase;
    letter-spacing:.05em; color:var(--text-dim); display:flex; justify-content:space-between; }
.tt-acc > summary::-webkit-details-marker { display:none; }
.tt-acc > summary::after { content:'\f078'; font-family:'Font Awesome 5 Free'; font-weight:900; font-size:10px; transition:transform var(--dur-fast, .15s); }
.tt-acc[open] > summary::after { transform:rotate(180deg); }
.tt-acc > dl { padding-bottom:6px; }

/* SLA */
.tt-slab { padding:8px 0; border-bottom:1px solid var(--border-soft); }
.tt-slab-head { display:flex; justify-content:space-between; align-items:center; font-size:13px; }
.tt-slab-meta { display:flex; justify-content:space-between; gap:8px; font-size:11.5px; color:var(--text-faint); }

/* Log: langkah vertikal */
.tt-steps { list-style:none; margin:0; padding:0; }
.tt-step { position:relative; display:flex; gap:10px; padding:0 0 10px; cursor:pointer; }
.tt-step:not(:last-child)::before { content:''; position:absolute; left:12px; top:26px; bottom:0; width:2px; background:var(--border); }
.tt-step-ic { width:26px; height:26px; flex:0 0 26px; border-radius:50%; background:var(--surface-2); border:1px solid var(--border);
    color:var(--text-dim); display:inline-flex; align-items:center; justify-content:center; font-size:11px; }
.tt-step:first-child .tt-step-ic { background:var(--accent-tint); color:var(--accent-text); border-color:var(--accent-tint-2); }
.tt-step:hover .tt-step-head strong { color:var(--accent-text); }
.tt-step-body { flex:1; min-width:0; font-size:12.5px; padding-top:3px; }
.tt-step-head { display:flex; justify-content:space-between; gap:6px; }
.tt-step-meta { font-size:11.5px; color:var(--text-faint); }
.tt-step-note { font-size:12px; color:var(--text-dim); white-space:pre-wrap; overflow-wrap:anywhere; }

/* Panel Action & bilah bawah hanya untuk mobile */
.tt-actions-stack { display:flex; flex-direction:column; gap:8px; }
.tt-actions-stack form, .tt-actions-stack .btn, .tt-actions-stack .dropdown { width:100%; }
.tt-pane-action, .tt-mbar { display:none; }

@media (max-width: 991.98px) {
    .tt-msg-col { max-width:90%; }
    .tt-panes .tt-pane { display:none; }
    .tt-panes[data-pane="chat"] .tt-pane-chat,
    .tt-panes[data-pane="info"] .tt-pane-info,
    .tt-panes[data-pane="action"] .tt-pane-action { display:block; }
    .tt-page .container-fluid { padding-bottom:64px; }
    .tt-pane-chat .tt-fill { height:calc(100vh - 190px); min-height:320px; }
    .tt-pane-info .tt-fill { height:auto; }
    .tt-tab-body { overflow:visible; }
    .tt-tabs .nav-link span:not(.tt-tab-count) { font-size:11.5px; }
    .tt-mbar {
        display:flex; position:fixed; left:0; right:0; bottom:0; z-index:1030;
        background:var(--card-bg); border-top:1px solid var(--border);
        padding-bottom:env(safe-area-inset-bottom);
    }
    .tt-mbar button {
        flex:1; border:0; background:transparent; padding:8px 0 6px; color:var(--text-faint);
        font-size:11px; font-weight:600; display:flex; flex-direction:column; align-items:center; gap:2px;
        border-top:2px solid transparent;
    }
    .tt-mbar button i { font-size:16px; }
    .tt-mbar button.active { color:var(--accent-text); border-top-color:var(--accent); }

    /* ---- Halaman detail tetap bisa digulir di HP (20 Okt 2026) ----
       fitDetail() melepas kelas tt-detail-open saat layar menyempit, tetapi
       kelas itu bisa tertinggal bila skrip berhenti lebih dulu karena satu
       error. Penegasan ini membuat batang gulir halaman tidak pernah hilang
       di HP, apa pun yang terjadi pada skripnya. */
    html.tt-detail-open, body.tt-detail-open { overflow-y:auto !important; }
    /* Tinggi area chat diatur CSS di HP; tinggi inline dari fitDetail() hanya
       untuk desktop dan akan membuat panel terpotong bila ikut terbawa. */
    .tt-detail { height:auto !important; }

    /* ---- Modal lebar (Create Plan, Review, Settlement) ---- */
    .modal-lg, .modal-xl { max-width:calc(100% - 16px); margin:8px auto; }
    .modal-body { max-height:calc(100vh - 170px); overflow-y:auto; }

    /* Tabel rincian tidak diperas sampai tak terbaca: ia menggulir mendatar
       di dalam .table-responsive. */
    .tt-cost-table { min-width:620px; }
    /* Sasaran sentuh tombol ikon (lampiran & e-sign) — 44px mengikuti ukuran
       minimum yang nyaman ditekan jari. */
    .tt-icon-btn { min-width:40px; min-height:36px; }
    .tt-signpad { height:170px; }

    /* Nominal Settle di HP (20 Okt 2026): kolomnya terlalu sempit, angka yang
       sedang diketik terpotong ("40.00…"). Input yang sedang difokus diangkat
       jadi kotak yang cukup besar untuk dibaca — selebar isinya saja, bukan
       selebar layar — dan ketuk di mana saja di luar untuk selesai. Tidak ada
       latar gelap: isi modal di belakangnya harus tetap terbaca jelas.
       Posisi tegak (top) dan mendatarnya (left) diisi skrip: kotak ini melebar
       KE KIRI dari tepi kanan input aslinya dan mengikuti baris yang sedang
       diisi, jadi ia tidak pernah menutupi tombol Lampiran & E-sign di
       sebelah kanannya maupun baris lain. */
    .tt-settle-amount.tt-amount-zoom {
        position:fixed; width:200px; max-width:60vw; height:auto !important;
        z-index:1061; font-size:22px !important; line-height:1.2; font-weight:600;
        text-align:center; padding:10px 12px; border-radius:10px;
        background:var(--surface); box-shadow:0 10px 28px rgba(0,0,0,.28);
    }
}
@media (max-width: 400px) {
    .tt-tabs .nav-link i { display:none; }
}

/* ---------- HP: satu irama untuk seluruh panel tahap ---------- */
@media (max-width: 767.98px) {
    .tt-page .container-fluid { padding-left:12px; padding-right:12px; }
    .tt-info-card .card-body { padding:12px; }
    .tt-stage { padding-top:12px; }
    .tt-stage-acts { grid-template-columns:1fr; gap:8px; }
    .tt-stage-title { margin-bottom:10px; }
    .tt-stage .btn { height:42px; font-size:13.5px; display:inline-flex; align-items:center; justify-content:center; }
    .tt-stage .btn.tt-btn-wide { height:42px; font-size:13px; }
    .tt-swipe { height:56px; }
    .tt-swipe-knob { width:46px; height:46px; }
    .tt-swipe-text { font-size:13.5px; padding-left:50px; }
    .tt-hero-title { font-size:15px; }
    .tt-kv dt { min-width:108px; }
    .tt-chips { gap:6px; }
    .modal-body { font-size:13px; }
    .modal-footer .btn { min-width:96px; }
}

/* ---------- Panel tahap (Remote / Plan / Trip / Progres / Approval) ----------
   Duduk di KAKI tab Summary: margin-top:auto mendorongnya ke bawah kartu,
   jadi tombolnya selalu di tempat yang sama berapa pun isi ringkasannya. */
#ttTabSummary.show { display:flex; flex-direction:column; min-height:100%; }
/* Saat tab lain dipilih, Bootstrap melepas .show lebih dulu lalu .active setelah
   transisi. Karena display ikut berubah, transitionend kadang tidak terpicu dan
   .active tertinggal — panel tahap ikut terlihat samar di tab Site/Tiket/Log.
   Keterlihatan dikunci ke .show saja. */
#ttTabSummary:not(.show) { display:none !important; }
#ttTabSummary .tt-stage:last-child { margin-top:auto; }
/* Tanpa kotak luar (keputusan 12 Okt 2026): tombol tahap berdiri langsung
   di atas kartu, dipisahkan garis tipis saja. */
.tt-stage { margin-top:12px; padding-top:10px; border-top:1px solid var(--border-soft); }
.tt-stage + .tt-stage { margin-top:8px; }
.tt-stage-title { font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:var(--text-muted); margin-bottom:8px; }
.tt-stage-acts { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.tt-stage-acts .btn-block { margin-top:0; }
.tt-stage-note { margin-top:8px; font-size:11.5px; color:var(--text-muted); }

/* Stabilo tiket yang baru dibuat di daftar: menyala lalu memudar sendiri
   sepanjang 2 detik. Hanya latar — tanpa garis tepi, yang di baris tabel
   muncul sebagai potongan merah di tiap sel. !important mengalahkan
   table-striped; animasinya berhenti di latar bening (forwards). */
.tt-row-baru > td, .tt-mcard.tt-row-baru { animation:ttBaruLuntur 2s ease-out forwards; }
@keyframes ttBaruLuntur {
    0%   { background-color:var(--danger-bg); }
    60%  { background-color:var(--danger-bg); }
    100% { background-color:transparent; }
}
/* Tombol tahap di bawah swipe: selebar panel supaya tidak duduk separuh baris,
   tapi tetap ramping — tinggi penuh seperti swipe terasa terlalu besar. */
.tt-stage .btn.tt-btn-wide { height:38px; border-radius:var(--radius-sm); font-size:12.5px; font-weight:600;
    display:inline-flex; align-items:center; justify-content:center; }

/* ---------- Geser untuk konfirmasi ----------
   Jalur gelap dengan tombol bundar merah; teks jalur meredup saat digeser,
   dan jalurnya ikut menyala hijau begitu ambang 85% terlampaui. */
.tt-swipe { position:relative; display:flex; align-items:center; height:52px; padding:5px;
    border-radius:999px; background:var(--surface-2); border:1px solid var(--border); overflow:hidden; user-select:none; }
.tt-swipe::after { content:""; position:absolute; inset:0; border-radius:999px; background:var(--positive, #1f9d55);
    opacity:0; transition:opacity .2s ease; }
.tt-swipe.is-done::after { opacity:.18; }
.tt-swipe-knob { position:absolute; left:5px; z-index:2; display:flex; align-items:center; justify-content:center;
    width:42px; height:42px; border-radius:50%; background:var(--danger); color:#fff; font-size:15px;
    box-shadow:0 2px 8px rgba(0,0,0,.28); cursor:grab; touch-action:none; transition:transform .18s ease, background .2s ease; }
.tt-swipe.is-dragging .tt-swipe-knob { cursor:grabbing; transition:background .2s ease; }
.tt-swipe.is-done .tt-swipe-knob { background:var(--positive, #1f9d55); }
.tt-swipe-text { position:relative; z-index:1; flex:1; padding-left:46px; text-align:center;
    font-size:13px; font-weight:600; letter-spacing:.02em; color:var(--text-dim); }
.tt-swipe.is-dragging .tt-swipe-text { opacity:.45; }

/* ---------- Evidence pada form close ---------- */
.tt-eviz-drop { display:flex; align-items:center; gap:8px; padding:10px; border:1px dashed var(--border); border-radius:var(--radius-sm); font-size:12px; color:var(--text-muted); background:var(--surface-2); }
.tt-eviz-drop.is-over { border-color:var(--info); color:var(--text); }
.tt-eviz-drop > i { font-size:16px; }
.tt-eviz-list { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
.tt-eviz-item { position:relative; display:flex; align-items:center; gap:6px; max-width:220px; padding:4px 24px 4px 6px; border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--surface); font-size:11.5px; }
.tt-eviz-item img { width:32px; height:32px; object-fit:cover; border-radius:3px; }
.tt-eviz-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tt-eviz-x { position:absolute; top:2px; right:4px; border:0; background:none; color:var(--text-muted); font-size:14px; line-height:1; cursor:pointer; }

/* ======================================================================
   PENAMPIL EVIDENCE
   ----------------------------------------------------------------------
   Gambar lampiran dibuka sebagai lapisan di atas halaman, bukan tab baru,
   supaya supervisor tidak kehilangan modal approval saat memeriksa bukti.
   z-index di atas modal Bootstrap (1050) dan backdrop-nya.
   ====================================================================== */
.tt-viewer { position:fixed; inset:0; z-index:1080; display:none; align-items:center; justify-content:center;
    background:rgba(8,10,14,.92); padding:48px 16px; }
.tt-viewer.is-on { display:flex; }
.tt-viewer img { max-width:100%; max-height:100%; object-fit:contain; border-radius:var(--radius-sm); }
.tt-viewer-x { position:absolute; top:10px; right:14px; width:36px; height:36px; border:0; border-radius:50%;
    background:rgba(255,255,255,.12); color:#fff; font-size:24px; line-height:1; cursor:pointer; }
.tt-viewer-x:hover { background:rgba(255,255,255,.22); }
.tt-viewer-nav { position:absolute; top:50%; transform:translateY(-50%); width:40px; height:40px; border:0; border-radius:50%;
    background:rgba(255,255,255,.12); color:#fff; font-size:16px; cursor:pointer; }
.tt-viewer-nav:hover { background:rgba(255,255,255,.22); }
.tt-viewer-prev { left:10px; }
.tt-viewer-next { right:10px; }
.tt-viewer-cap { position:absolute; left:0; right:0; bottom:12px; text-align:center; color:rgba(255,255,255,.8); font-size:12px;
    padding:0 56px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
@media (max-width: 575.98px) {
    .tt-viewer { padding:44px 8px; }
    .tt-viewer-nav { width:34px; height:34px; }
}
</style>
@endpush

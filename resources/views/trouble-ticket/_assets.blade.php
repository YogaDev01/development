{{-- Gaya & skrip bersama halaman Trouble Ticket. Hanya token dari
     tesys-ui-tokens.css; tidak ada warna baru. --}}
@push('styles')
<style>
.tt-page .tt-title { font-size:20px; font-weight:700; color:var(--text); margin:0; }
.tt-page .tt-sub   { color:var(--text-faint); font-size:13px; margin-top:2px; }

/* SLA */
.tt-sla { display:inline-flex; align-items:center; gap:4px; font-size:12px; white-space:nowrap; font-variant-numeric:tabular-nums; }
.tt-sla-na, .tt-sla-none, .tt-sla-ok { color:var(--text-dim); }
.tt-sla.is-running { color:var(--text); font-weight:600; }
.tt-sla-warning { color:var(--warning); font-weight:600; }
.tt-sla-critical { color:var(--danger); font-weight:700; }
.tt-sla-tag { font-size:10px; text-transform:uppercase; letter-spacing:.04em; padding:0 5px; border-radius:var(--radius-pill); background:var(--danger-bg); color:var(--danger); }

/* Severity: kelas dari level (1 = paling parah) */
.tt-sev { font-size:11px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; }
.tt-sev-1 { color:var(--danger); }
.tt-sev-2 { color:var(--warning); }
.tt-sev-3 { color:var(--text-dim); }
.tt-sev-4, .tt-sev-5, .tt-sev-6, .tt-sev-7, .tt-sev-8, .tt-sev-9 { color:var(--text-faint); }

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
}
.tt-detail .tt-pane { min-height:0; }
.tt-fill { height:100%; display:flex; flex-direction:column; min-height:0; overflow:hidden; }
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
.tt-chat-list { flex:1 1 0; min-height:0; overflow-y:auto; padding:10px 14px; background:var(--surface-2); }
.tt-msg { display:flex; margin-bottom:8px; transition:background var(--dur-fast, .15s); }
.tt-msg.is-mine { justify-content:flex-end; }
.tt-msg-col { display:flex; flex-direction:column; max-width:78%; }
.tt-msg.is-mine .tt-msg-col { align-items:flex-end; }
.tt-bubble-name { font-size:11px; font-weight:600; color:var(--text-dim); margin:0 0 2px 2px; }
.tt-bubble { background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius-md); padding:6px 10px; font-size:13px; }
.tt-msg.is-mine .tt-bubble { background:var(--accent-tint); border-color:var(--accent-tint-2); }
.tt-msg.is-tech .tt-bubble { border-left:3px solid var(--info); }
.tt-bubble-body { white-space:pre-wrap; overflow-wrap:anywhere; color:var(--text); }
.tt-bubble-time { font-size:10.5px; color:var(--text-faint); margin:2px 4px 0; font-variant-numeric:tabular-nums; }
.tt-event { text-align:center; margin:8px 0; font-size:11.5px; color:var(--text-dim); }
.tt-event-box { display:inline-block; max-width:90%; background:var(--card-bg); border:1px dashed var(--border-strong); border-radius:var(--radius-sm); padding:3px 10px; text-align:left; }
.tt-event-box .tt-event-note { white-space:pre-wrap; color:var(--text); margin-top:2px; }
.tt-flash .tt-bubble, .tt-flash .tt-event-box, .tt-flash.tt-event .tt-event-box { box-shadow:0 0 0 3px var(--accent-ring); }

/* Lampiran di bubble: grid thumbnail persegi + baris berkas ringkas */
.tt-att { display:flex; flex-direction:column; gap:4px; margin-top:6px; }
.tt-bubble-body + .tt-att { margin-top:6px; }
.tt-bubble > .tt-att:first-child { margin-top:0; }
.tt-att-grid { display:grid; gap:4px; grid-template-columns:repeat(4, 84px); }
.tt-att-grid.tt-att-n1 { grid-template-columns:200px; }
.tt-att-grid.tt-att-n2 { grid-template-columns:repeat(2, 120px); }
.tt-att-grid.tt-att-n3 { grid-template-columns:repeat(3, 96px); }
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
    .tt-att-grid { grid-template-columns:repeat(3, 72px); }
    .tt-att-grid.tt-att-n1 { grid-template-columns:180px; }
    .tt-att-grid.tt-att-n2 { grid-template-columns:repeat(2, 100px); }
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
.tt-sec-title { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--text-faint); font-weight:700; margin:10px 0 2px; }

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
.tt-bar { height:6px; border-radius:var(--radius-pill); background:var(--surface-2); margin:6px 0 4px; overflow:hidden; }
.tt-bar-fill { display:block; height:100%; border-radius:inherit; background:var(--success); transition:width .3s; }
.tt-bar-warning { background:var(--warning); }
.tt-bar-critical { background:var(--danger); }
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
.tt-step-gap { font-size:11px; color:var(--text-faint); white-space:nowrap; }
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
}
@media (max-width: 400px) {
    .tt-tabs .nav-link i { display:none; }
}
</style>
@endpush

@push('scripts')
<script>
/* Timer SLA: hitung dari jam SERVER. Selisih jam perangkat dikoreksi dengan
   offset (waktu server saat halaman dirender − jam perangkat), jadi jam HP yang
   salah tidak mengubah angka yang tampil. Tidak ada yang dikirim ke server. */
window.TtTimers = (function () {
    var offset = 0;

    function fmt(s) {
        var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
        if (d > 0) return d + 'h ' + h + 'j';
        if (h > 0) return h + 'j ' + (m < 10 ? '0' : '') + m + 'm';
        if (m > 0) return m + 'm';
        return s + 'dtk';
    }

    function tick() {
        var now = Date.now() + offset;
        document.querySelectorAll('.tt-sla.is-running[data-since]').forEach(function (el) {
            var s = Math.max(0, Math.floor((now - Date.parse(el.dataset.since)) / 1000));
            var val = el.querySelector('.tt-sla-val');
            if (val) val.textContent = fmt(s);
            var t = parseInt(el.dataset.target || '0', 10);
            if (t > 0) {
                el.classList.toggle('tt-sla-warning', s >= t * 0.75 && s < t);
                el.classList.toggle('tt-sla-critical', s >= t);
                el.classList.toggle('tt-sla-ok', s < t * 0.75);
            }
        });
        // Bar progres SLA (tab SLA) untuk timer yang masih berjalan.
        document.querySelectorAll('.tt-bar-fill[data-since]').forEach(function (el) {
            var s = Math.max(0, Math.floor((now - Date.parse(el.dataset.since)) / 1000));
            var t = parseInt(el.dataset.target || '0', 10);
            if (!t) return;
            el.style.width = Math.min(100, s / t * 100) + '%';
            el.classList.toggle('tt-bar-warning', s >= t * 0.75 && s < t);
            el.classList.toggle('tt-bar-critical', s >= t);
        });
    }

    return {
        start: function (serverNowIso) {
            offset = Date.parse(serverNowIso) - Date.now();
            tick();
            setInterval(tick, 1000);
        }
    };
})();
</script>
@endpush

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

/* Detail: kartu info */
.tt-info-card .card-body { padding:10px 14px; }
.tt-kv { display:flex; gap:10px; padding:5px 0; border-bottom:1px solid var(--border-soft); font-size:12.5px; }
.tt-kv:last-child { border-bottom:0; }
.tt-kv dt { flex:0 0 40%; font-weight:600; color:var(--text-faint); margin:0; }
.tt-kv dd { flex:1; margin:0; color:var(--text); min-width:0; overflow-wrap:anywhere; }

/* Detail: timeline / chat */
.tt-chat { display:flex; flex-direction:column; height:calc(100vh - 230px); min-height:480px; }
.tt-chat-list { flex:1; overflow-y:auto; padding:12px 14px; background:var(--surface-2); }
.tt-msg { display:flex; margin-bottom:10px; }
.tt-msg.is-mine { justify-content:flex-end; }
.tt-bubble { max-width:78%; background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius-md); padding:7px 10px; font-size:13px; }
.tt-msg.is-mine .tt-bubble { background:var(--accent-tint); border-color:var(--accent-tint-2); }
.tt-msg.is-tech .tt-bubble { border-left:3px solid var(--info); }
.tt-bubble-head { font-size:11px; color:var(--text-faint); margin-bottom:2px; }
.tt-bubble-head strong { color:var(--text); }
.tt-bubble-body { white-space:pre-wrap; overflow-wrap:anywhere; color:var(--text); }
.tt-event { text-align:center; margin:10px 0; font-size:11.5px; color:var(--text-dim); }
.tt-event-box { display:inline-block; max-width:90%; background:var(--card-bg); border:1px dashed var(--border-strong); border-radius:var(--radius-sm); padding:4px 10px; text-align:left; }
.tt-event-box .tt-event-note { white-space:pre-wrap; color:var(--text); margin-top:2px; }
.tt-att { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.tt-att a { display:inline-flex; align-items:center; gap:6px; border:1px solid var(--border); border-radius:var(--radius-sm); padding:3px 8px; font-size:12px; background:var(--surface); color:var(--text); max-width:240px; }
.tt-att a span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tt-att img { display:block; max-width:180px; max-height:120px; border-radius:var(--radius-sm); border:1px solid var(--border); }
.tt-chat-form { border-top:1px solid var(--border); padding:10px 12px; background:var(--card-bg); }
.tt-chat-form textarea { resize:vertical; min-height:42px; font-size:13px; }
.tt-file-list { font-size:11.5px; color:var(--text-dim); }
.tt-actions .btn { font-size:12.5px; }

@media (max-width: 991.98px) {
    .tt-chat { height:auto; min-height:0; }
    .tt-chat-list { max-height:60vh; }
    .tt-bubble { max-width:92%; }
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

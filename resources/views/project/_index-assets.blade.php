@push('styles')
<style>
.badge-purple        { color:#fff; background-color:var(--purple); }
.col-wrap-header     { white-space:normal !important; word-break:normal; overflow-wrap:normal; line-height:1.25; text-align:center; }
.po-mini-badge       { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; line-height:1; padding:0; margin:0 1px; font-size:9px; font-weight:700; border-radius:50%; text-align:center; cursor:default; box-sizing:border-box; border:2px solid transparent; }
.po-mini-badge:hover { transform:scale(1.08); }

/* Selektornya diawali body.tesys-ui BUKAN karena gaya — tesys-ui-compat.css
   memakai `body.tesys-ui .badge-success { ... !important }`, yang lebih spesifik
   daripada `.po-mini-badge.badge-success`. Tanpa awalan itu, override di bawah
   kalah dan lingkarannya tetap pucat.

   Badge biasa memakai gaya lembut (latar tipis + teks berwarna). Pada lingkaran
   18px berisi satu huruf, gaya itu nyaris tak terbaca — warnanya hilang dan
   ketiga keadaan terlihat sama. Di sini isinya dipadatkan: latar penuh, teks
   putih, sehingga Done/Partial/Not Started bisa dibedakan sekilas.
   Cincin luar (border-*) tetap memakai warna status Project Monitoring. */
body.tesys-ui .po-mini-badge.badge-success   { background:var(--success) !important; color:#fff !important; }
body.tesys-ui .po-mini-badge.badge-warning   { background:var(--warning) !important; color:#fff !important; }
body.tesys-ui .po-mini-badge.badge-danger    { background:var(--danger)  !important; color:#fff !important; }
body.tesys-ui .po-mini-badge.badge-info      { background:var(--info)    !important; color:#fff !important; }
/* Not Started sengaja TETAP redup — itu memang keadaan "belum ada apa-apa",
   dan menyolidkannya akan membuatnya bersaing dengan yang sudah berisi. */
body.tesys-ui .po-mini-badge.badge-secondary { background:var(--surface-3) !important; color:var(--text-dim) !important; }

/* Cincin luar = status milestone Project Monitoring. Dipertebal warnanya
   supaya tetap terbaca di atas isi yang kini pekat. */
body.tesys-ui .po-mini-badge.border-success   { border-color:var(--success) !important; }
body.tesys-ui .po-mini-badge.border-warning   { border-color:var(--warning) !important; }
body.tesys-ui .po-mini-badge.border-secondary { border-color:var(--border) !important; }
.po-margin-cell       { font-size:10px; line-height:1.5; }
.po-margin-cell div   { display:flex; justify-content:space-between; gap:6px; }
.po-margin-label      { color:var(--text-faint); }
/* Sub-baris "x / y No. PO" di bawah nilai PO — PO Quantity tidak dijadikan
   kolom sendiri supaya tabel tidak makin lebar; angkanya memang hanya berarti
   berdampingan dengan Nilai PO yang dijumlahkan dari No. PO tersebut. */
.po-qty-sub              { font-size:10px; line-height:1.4; color:var(--text-faint); margin-top:2px; }
.po-qty-sub.is-mismatch  { color:var(--warning); font-weight:600; }
.custom-toolbar      { display:flex; align-items:flex-start; gap:10px; flex-wrap:wrap; }
.custom-toolbar-left { display:flex; flex-direction:row; align-items:center; gap:12px; flex-wrap:wrap; }
.toolbar-row         { display:flex; align-items:center; gap:8px; }
.toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; flex-shrink:0; }
.toolbar-input       { min-width:180px; }
#customSearch input  { width:180px; border-radius:var(--radius-sm); }
.ts-wrapper          { min-width:260px; }
.ts-control .item {
    max-width: 240px;
    display: flex;
    align-items: center;
    overflow: visible;
}
.ts-control .item > div {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
}
.ts-control .item .remove { flex-shrink: 0; }
.ts-control          { border-radius:var(--radius-sm) !important; border:1px solid var(--border) !important; min-height:38px !important; padding:6px 10px !important; box-shadow:none !important; }

@media (max-width: 576px) {
    .custom-toolbar { flex-direction:column !important; align-items:stretch !important; }
    .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .custom-toolbar-right { width:100%; }
    .custom-toolbar-right .btn { width:100%; }
    #btnClearFilterPO { width:100%; }
    #poRowFilters { width:100%; }
    #poRowFilters .tcf-row-btn { width:100%; }
    .toolbar-row         { width:100%; }
    .toolbar-input       { flex:1; min-width:0; }
    #customSearch input  { width:100%; }
    .ts-wrapper          { width:100% !important; min-width:0 !important; }
    .ts-control          { width:100% !important; }
    .card-header { flex-direction:column; align-items:stretch !important; gap:10px; }
    .card-header .card-title { margin-bottom:0; }
    .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .card-header .ml-auto .btn { flex:1 1 auto; }
}
.table td,.table th { vertical-align:middle !important; }
.action-column { white-space:nowrap !important; width:1% !important; }

/* Warna bersih: semua baris putih, tanpa efek hover row */
#projectTable tbody tr,
#projectTable tbody tr:hover { background-color:var(--surface) !important; }
#projectTable tbody td { background-color:var(--surface); }

.po-code-link, .po-implement-link, .po-cell-link {
    display:inline-block; padding:2px 8px; border:0; border-radius:var(--radius-sm);
    background:transparent; font-family:inherit; font-weight:700; font-size:13px; line-height:1.4;
    color:var(--text); text-decoration:none; cursor:pointer;
    transition:background-color .12s ease, color .12s ease;
}
.po-code-link:hover, .po-code-link:focus,
.po-implement-link:hover, .po-implement-link:focus,
.po-cell-link:hover, .po-cell-link:focus {
    background:var(--charcoal); color:#fff; text-decoration:none; outline:none;
}
.po-status-link { cursor:pointer; }
.po-status-link:hover { filter:brightness(.92); }
.po-cell-link.po-neg { color:var(--danger); }
.po-cell-link.po-neg:hover, .po-cell-link.po-neg:focus { color:#fff; }

/* ── TABEL: scroll horizontal, kolom Kode & Action beku ── */
.po-hscroll { overflow-x:auto; }
.po-hscroll #projectTable { width:auto !important; min-width:100%; margin-bottom:0; }
.po-hscroll #projectTable th,
.po-hscroll #projectTable td { white-space:nowrap; }
.po-hscroll #projectTable th.col-wrap-header { white-space:nowrap !important; }

.po-hscroll #projectTable th.po-col-kode,
.po-hscroll #projectTable td.po-col-kode {
    position:sticky; left:0; z-index:3; background:var(--surface);
    box-shadow:2px 0 6px -2px rgba(0,0,0,.18);
}
.po-hscroll #projectTable th.action-column,
.po-hscroll #projectTable td.action-column {
    position:sticky; right:0; z-index:3; background:var(--surface);
    box-shadow:-2px 0 6px -2px rgba(0,0,0,.18);
}
.po-hscroll #projectTable thead th.po-col-kode,
.po-hscroll #projectTable thead th.action-column { background:var(--surface-2); z-index:4; }
.po-hscroll #projectTable tfoot th.po-col-kode,
.po-hscroll #projectTable tfoot th.action-column { background:var(--surface-2); }
.action-buttons { display:flex; align-items:center; justify-content:center; gap:4px; flex-wrap:nowrap; }
.btn-xs { padding:2px 8px; font-size:11px; border-radius:var(--radius-sm); }
.dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.dataTables_length { display:flex; align-items:center; margin-left:18px; }
.dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.dataTables_length select { appearance:none !important; background-color:var(--surface) !important; color:var(--text-dim) !important; border:1px solid var(--border) !important; border-radius:var(--radius-sm) !important; padding:4px 30px 4px 10px !important; height:36px !important; min-width:72px !important; font-size:14px !important; font-weight:600 !important; cursor:pointer; box-shadow:none !important; }
.dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.dataTables_paginate .paginate_button.current { background:var(--accent) !important; background-image:none !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important; }
.dataTables_wrapper .pagination .page-link { color:var(--accent) !important; }
#prjDetailTabs .nav-link { color:var(--text-faint); border:none; border-bottom:2px solid transparent; border-radius:0; padding:8px 16px; }
#prjDetailTabs .nav-link.active { color:var(--accent) !important; border-bottom-color:var(--accent) !important; background:transparent !important; font-weight:700; }

#prjDetailBody tr.prj-group-row { cursor:pointer; background:var(--info-bg) !important; }
#prjDetailBody tr.prj-group-row:hover { background:var(--surface-3) !important; }
#prjDetailBody tr.prj-group-row td { background:transparent !important; }
#prjDetailBody .prj-caret { transition:transform .15s ease; font-size:11px; color:var(--text-faint); }
#prjDetailBody .prj-caret.rotated { transform:rotate(90deg); }
#prjDetailBody td.prj-indent { padding-left:28px !important; }
.prj-late { color:var(--danger) !important; font-weight:700; }

/* Modal detail PO — ukuran & style disamakan dengan modal Budget Plan / Cost Request. */
.modal-dialog-xxl {
    max-width: calc(100vw - 32px);
    width: calc(100vw - 32px);
    height: calc(100vh - 32px);
    margin: 16px auto;
}
.modal-dialog-xxl .modal-content {
    height: calc(100vh - 32px);
    max-height: calc(100vh - 32px);
    display: flex;
    flex-direction: column;
}
.modal-dialog-xxl .modal-header,
.modal-dialog-xxl .modal-footer { flex: 0 0 auto; }
.modal-dialog-xxl .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
}
.modal-dialog-xxl #projectDetailContent { height: 100%; }

.prj-modal-flex { display:flex; align-items:stretch; min-height:100%; }
.prj-sidebar { width:280px; flex:0 0 280px; display:flex; flex-direction:column; }
.prj-sidebar-spacer { flex:1 1 auto; }
.prj-main-panel { flex:1 1 auto; min-width:0; }
@media (max-width:767.98px) {
    .prj-modal-flex { flex-direction:column; }
    .prj-sidebar { width:100%; flex:0 0 auto; }
}

/* TAB SUMMARY — clean grid layout */
.prj-info-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px 14px; }
.prj-info-grid-ref { display:grid; grid-template-columns:220px 1fr; gap:12px 14px; }
.prj-info-item { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:10px 14px; }
.prj-info-label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text-faint); letter-spacing:.03em; margin-bottom:4px; }
.prj-info-value { font-size:13px; color:var(--text); }
.prj-info-highlight { background:var(--warning-bg); border-color:var(--border-strong); }
.prj-info-highlight .prj-info-value { font-weight:700; color:var(--warning); }
#prjNotesToggleIcon.rotated, #prjBastToggleIcon.rotated, #prjClosedToggleIcon.rotated { transform:rotate(90deg); }

/* KPI strip (Summary & Cost) */
.prj-kpi-strip { display:flex; flex-wrap:wrap; gap:8px; }
.prj-kpi { flex:1 1 120px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-md); padding:9px 12px; }
.prj-kpi-label { display:block; font-size:9.5px; text-transform:uppercase; font-weight:700; color:var(--text-faint); letter-spacing:.03em; margin-bottom:3px; white-space:nowrap; }
.prj-kpi-value { font-size:13.5px; font-weight:700; color:var(--text); white-space:nowrap; }

@media (max-width:576px) {
    .prj-info-grid-3, .prj-info-grid-ref { grid-template-columns:1fr; }
    .prj-kpi { flex-basis:calc(50% - 4px); }
}

/* TAB PIC / ACCESS — group per tipe/role, pola pivot yang sama */
.prj-pivot-group-row { cursor:pointer; background:var(--info-bg) !important; }
.prj-pivot-group-row:hover { background:var(--surface-3) !important; }
.prj-pivot-group-row td { background:transparent !important; }
.prj-pivot-caret { transition:transform .15s ease; font-size:11px; color:var(--text-faint); }
.prj-pivot-caret.rotated { transform:rotate(90deg); }
.prj-pivot-indent { padding-left:28px !important; }

/* TAB BUDGET — semua Budget Plan digabung, grouping per Chart of Account */
#prjBudgetBody .bp-group-row { cursor:pointer; background:var(--info-bg) !important; }
#prjBudgetBody .bp-group-row:hover { background:var(--surface-3) !important; }
#prjBudgetBody .bp-group-row td { background:transparent !important; }
#prjBudgetBody td.bp-indent { padding-left:32px !important; }

/* ── Modal tabel: rapat & fit-content ── */
.prj-tab-table { font-size:12px; }
.prj-tab-table th, .prj-tab-table td { white-space:nowrap; padding:6px 10px; }
.prj-tab-table th { color:var(--text-faint); font-size:11px; font-weight:700; }
.prj-tab-table td.prj-col-wide, .prj-tab-table th.prj-col-wide { white-space:normal; width:auto; min-width:220px; }
.prj-tab-table .btn-xs { padding:1px 7px; font-size:10px; line-height:1.4; }
.prj-copyable { cursor:pointer; border-bottom:1px dashed var(--border); }
.prj-copyable:hover { color:var(--text); border-bottom-color:var(--text); }

/* Toast copy */
#prjCopyToast {
    position:fixed; left:50%; bottom:32px; transform:translateX(-50%) translateY(20px);
    background:var(--charcoal); color:#fff; font-size:12px; padding:8px 16px; border-radius:var(--radius-pill);
    opacity:0; pointer-events:none; transition:opacity .2s, transform .2s; z-index:3000;
}
#prjCopyToast.show { opacity:1; transform:translateX(-50%) translateY(0); }

/* ── TAB COST/SUMMARY — group card ── */
.prj-summary-cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(235px, 1fr)); gap:10px; align-items:stretch; width:100%; }
.prj-groupcard { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:12px 14px; display:flex; flex-direction:column; }
.prj-kpi-strip .prj-groupcard { flex:1 1 240px; }
.prj-groupcard-title { font-size:10px; text-transform:uppercase; letter-spacing:.04em; font-weight:800; color:var(--text-faint); margin-bottom:8px; }
.prj-groupcard-row { display:flex; justify-content:space-between; gap:12px; font-size:12px; padding:3px 0; align-items:baseline; }
.prj-groupcard-row > span:first-child { color:var(--text-faint); }
.prj-groupcard-row.total { border-top:1px solid var(--border); margin-top:4px; padding-top:6px; }
.prj-groupcard-row.total > span:first-child { color:var(--text); font-weight:700; }
.prj-groupcard-row > span:last-child { font-weight:700; white-space:nowrap; text-align:right; }
.prj-groupcard-sub { font-size:10.5px; color:var(--text-faint); padding:0 0 2px; }

/* ── TAB ACCESS — org chart ── */
.prj-org { display:flex; flex-direction:column; align-items:center; gap:0; padding:8px 0; }
.prj-org-tier { display:flex; flex-wrap:wrap; justify-content:center; gap:14px; position:relative; }
.prj-org-tier + .prj-org-tier { margin-top:34px; }
.prj-org-tier + .prj-org-tier::before {
    content:""; position:absolute; top:-22px; left:50%; width:2px; height:22px; background:var(--surface-3);
}
.prj-org-node {
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:10px 14px; min-width:170px;
    text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.06);
}
.prj-org-node .role { display:inline-block; font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; padding:1px 7px; border-radius:var(--radius-lg); margin-bottom:5px; }
.prj-org-node .nm { font-size:12px; font-weight:700; color:var(--text); }
.prj-org-node .grp { font-size:10px; color:var(--text-faint); margin-top:2px; }

/* ── TAB LOG — chat bubbles ── */
.prj-chat { display:flex; flex-direction:column; gap:14px; padding:4px 2px; }
.prj-chat-row { display:flex; gap:10px; max-width:78%; }
.prj-chat-row.mine { align-self:flex-end; flex-direction:row-reverse; text-align:right; }
.prj-chat-av { width:30px; height:30px; border-radius:50%; background:var(--info-bg); color:var(--text-dim); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; }
.prj-chat-row.mine .prj-chat-av { background:var(--charcoal); color:#fff; }
.prj-chat-bubble { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:8px 12px; font-size:12px; }
.prj-chat-row.mine .prj-chat-bubble { border-color:var(--border); }
.prj-chat-meta { font-size:10px; color:var(--text-faint); margin-bottom:4px; }
.prj-chat-note { margin-top:6px; font-size:11.5px; line-height:1.45; white-space:pre-line; color:var(--text); }

/* ── IMAGE VIEWER (carousel + zoom) ── */
#imgViewerOverlay {
    position:fixed; inset:0; background:rgba(0,0,0,.92); z-index:2000;
    display:none; align-items:center; justify-content:center; flex-direction:column;
}
#imgViewerOverlay.show { display:flex; }
#imgViewerStage { position:relative; width:100%; height:100%; display:flex; align-items:center; justify-content:center; overflow:hidden; }
#imgViewerImg {
    max-width:90vw; max-height:82vh; object-fit:contain; cursor:zoom-in;
    transition:transform .15s ease; transform-origin:center center; user-select:none; -webkit-user-drag:none;
}
#imgViewerImg.zoomed { cursor:grab; }
#imgViewerImg.dragging { cursor:grabbing; transition:none; }
#imgViewerClose {
    position:absolute; top:16px; right:20px; z-index:10; background:rgba(255,255,255,.12); border:none; color:#fff;
    width:38px; height:38px; border-radius:50%; font-size:20px; display:flex; align-items:center; justify-content:center; cursor:pointer;
}
#imgViewerClose:hover { background:rgba(255,255,255,.25); }
.imgViewerNav {
    position:absolute; top:50%; transform:translateY(-50%); background:rgba(255,255,255,.12); border:none; color:#fff;
    width:44px; height:44px; border-radius:50%; font-size:20px; display:flex; align-items:center; justify-content:center; cursor:pointer;
}
.imgViewerNav:hover { background:rgba(255,255,255,.25); }
#imgViewerPrev { left:16px; }
#imgViewerNext { right:16px; }
#imgViewerCounter { position:absolute; bottom:16px; left:50%; transform:translateX(-50%); color:#fff; font-size:12px; background:rgba(255,255,255,.12); padding:4px 12px; border-radius:var(--radius-lg); }
#imgViewerName { position:absolute; top:20px; left:20px; z-index:10; color:#fff; font-size:12px; opacity:.85; max-width:60vw; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
@media (max-width:576px) {
    .imgViewerNav { width:36px; height:36px; font-size:16px; }
    #imgViewerPrev { left:6px; } #imgViewerNext { right:6px; }
}

.font-weight-600 { font-weight:600; }

/* --- Filter dropdown: bedakan header grup vs item --- */
.ts-dropdown .optgroup-header,
.selectize-dropdown .optgroup-header {
    font-weight: 700 !important;
    font-size: 11px !important;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text) !important;
    background: var(--surface-2) !important;
    padding: 8px 12px !important;
    border-top: 1px solid var(--border);
    cursor: default;
}
.ts-dropdown .optgroup:first-child .optgroup-header,
.selectize-dropdown .optgroup:first-child .optgroup-header {
    border-top: none;
}
/* item di dalam grup diberi jarak (indent) */
.ts-dropdown .optgroup .option,
.selectize-dropdown .optgroup .option {
    padding-left: 26px !important;
}
</style>
@endpush

@push('scripts')
@php $currentUserRoleJs = strtolower(Auth::user()->role->name ?? ''); @endphp
<script>
var currentUserRole = '{{ $currentUserRoleJs }}';
var currentUserId = parseInt('{{ (int) Auth::id() }}', 10) || 0;
var canSubmitBast = ['superadmin', 'head'].includes(currentUserRole);
var hideMoney = currentUserRole === 'head';        // Role head: nominal disembunyikan
function maskMoney(v) { return hideMoney ? '•••' : (v || '-'); }

$(function () {

    /*
    |--------------------------------------------------------------------------
    | WARNA KONFIRMASI & KEADAAN SIBUK
    |--------------------------------------------------------------------------
    | Pola yang sama dengan Cost Request. Warna tombol Swal diambil dari token
    | desain, bukan biru/merah/hijau bawaan Bootstrap, supaya dialog konfirmasi
    | sewarna dengan tombol di halaman.
    |
    | Catatan penting untuk halaman ini: hampir semua aksi PO memakai
    | target="_blank" — hasilnya terbuka di tab baru dan halaman daftar tetap
    | di tempat. Tanda sibuk hanya dipasang pada form yang benar-benar
    | memuat ulang halaman ini; kalau tidak, spinner-nya akan menggantung
    | selamanya karena tidak ada apa pun yang menggantikan halaman.
    */
    function tokenWarna(nama, cadangan) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || cadangan;
    }
    var WARNA_UTAMA  = tokenWarna('--accent', '#92232b');
    var WARNA_BAHAYA = tokenWarna('--danger', '#9a3030');
    var WARNA_SETUJU = tokenWarna('--success', '#227a53');
    var WARNA_BATAL  = tokenWarna('--text-muted', '#6a6e72');

    function sibukkan(form) {
        if (form.target && form.target !== '_self') return;
        var tombol = form.querySelector('button[type="submit"]');
        if (tombol && window.TesysUI && TesysUI.busy) TesysUI.busy(tombol, true);
    }

    $(document).on('submit',
        '#frmFinanceApprove, #frmFinanceRevise, #frmSubmitClosing, #frmVerifyClosing,'
        + ' #frmRejectClosing, #frmCloseProject',
        function () { sibukkan(this); });

    /*
    |--------------------------------------------------------------------------
    | KOLOM TOGGLE — Budget/Realization/Payment disembunyikan default, bisa
    | dimunculkan lagi lewat dropdown "Kolom". Preferensi disimpan di localStorage
    | supaya tetap tersimpan sampai user memilih ubah lagi.
    |--------------------------------------------------------------------------
    */
    // key -> index kolom (urutan render, kolom 0 "No" disembunyikan DataTable).
    var colVisMap = {
        type: 4, uploaded: 6, margin: 7, contract: 8, povalue: 9, budget: 10,
        varcost: 11, payment: 12, profit: 13, overhead: 14, fixedcost: 15, netprofit: 16
    };
    var colVisDefault = {
        type: true, uploaded: true, margin: true, povalue: true, contract: true, budget: false,
        varcost: false, payment: false, profit: true, overhead: false, fixedcost: false, netprofit: true
    };

    /*
    | Kolom uang dibatasi peran: hanya Superadmin/Approver/Finance Manager
    | (Project::FULL_EDIT_ROLES) yang boleh melihatnya. Peran lain dipaksa
    | melihat Budget & Variable Cost saja — dipaksa, bukan sekadar default,
    | karena preferensi di localStorage bisa saja menyimpan keadaan lama dari
    | sesi peran lain di browser yang sama.
    */
    var bolehKolomFinansial = @json($bolehKolomFinansial);
    var kolomUangTerbatas = ['margin', 'contract', 'povalue', 'payment', 'profit',
                             'overhead', 'fixedcost', 'netprofit'];
    var kolomWajibTampil  = ['budget', 'varcost'];

    function getColVisPref(key) {
        if (!bolehKolomFinansial) {
            if (kolomUangTerbatas.indexOf(key) !== -1) return false;
            if (kolomWajibTampil.indexOf(key) !== -1) return true;
        }

        var stored = localStorage.getItem('poColVis_' + key);
        return stored === null ? !!colVisDefault[key] : stored === '1';
    }

    function setColVisPref(key, visible) {
        localStorage.setItem('poColVis_' + key, visible ? '1' : '0');
    }

    /*
    |--------------------------------------------------------------------------
    | DATATABLE
    |--------------------------------------------------------------------------
    */
    var table = $('#projectTable').DataTable({
        autoWidth: false, pageLength: 10, lengthChange: true, searching: true,
        order: [], // pertahankan urutan asli dari server (input terbaru di atas)
        columnDefs: [
            // 0=No(hidden) 1=Kode 2=Scope 3=Status 4=Tipe 5=Progress 6=Uploaded
            // 7=Margin 8=Contract 9=PO Value 10=Budget 11=Variable Cost
            // 12=Received Payment 13=Gross Profit 14=Overhead 15=Fixed Cost
            // 16=Net Profit 17=Action
            { targets: [0], visible: false, orderable: false },
            { orderable: false, targets: [6, 7, 17] },
            { targets: [4],  visible: getColVisPref('type') },
            { targets: [6],  visible: getColVisPref('uploaded') },
            { targets: [7],  visible: getColVisPref('margin') },
            { targets: [8],  visible: getColVisPref('contract') },
            { targets: [9],  visible: getColVisPref('povalue') },
            { targets: [10], visible: getColVisPref('budget') },
            { targets: [11], visible: getColVisPref('varcost') },
            { targets: [12], visible: getColVisPref('payment') },
            { targets: [13], visible: getColVisPref('profit') },
            { targets: [14], visible: getColVisPref('overhead') },
            { targets: [15], visible: getColVisPref('fixedcost') },
            { targets: [16], visible: getColVisPref('netprofit') }
        ],
        dom: 'rt<"row mt-3 align-items-center"<"col-md-6 col-12 mb-2 mb-md-0"i><"col-md-6 col-12 d-flex flex-wrap justify-content-between justify-content-md-end align-items-center gap-2"lp>>',
        language: {
            lengthMenu: 'Page Size _MENU_',
            /* emptyTable = belum ada data sama sekali; zeroRecords = ada data tapi
               tidak ada yang cocok dengan pencarian/filter. Dua keadaan berbeda. */
            emptyTable: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-folder-open"></i></span>'
                + '<p class="ts-empty-title">Belum ada Purchase Order</p>'
                + '<p class="ts-empty-msg">Belum ada PO pada rentang tanggal yang dipilih.</p></div>',
            zeroRecords: '<div class="ts-empty ts-empty-compact">'
                + '<span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-search"></i></span>'
                + '<p class="ts-empty-title">Tidak ada yang cocok</p>'
                + '<p class="ts-empty-msg">Tidak ada Purchase Order yang cocok dengan pencarian atau filter yang sedang aktif.</p></div>',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data', paginate: { previous: 'Previous', next: 'Next' }
        }
    });

    $('#projectTable_filter').hide();
    $('#customSearch').html('<input type="search" id="searchInput" class="form-control form-control-sm" placeholder="Cari data...">');
    $('#searchInput').on('keyup', function () { table.search(this.value).draw(); });

    // Bungkus HANYA tabelnya dengan scroller horizontal, supaya baris info +
    // page size + pagination di bawah TIDAK ikut bergeser saat tabel di-scroll.
    // (.table-responsive juga jadi anchor untuk chip filter TesysColumnFilter.)
    $('#projectTable').wrap('<div class="po-hscroll table-responsive"></div>');

    // Sinkronkan checkbox "Kolom" dengan visibilitas kolom saat ini, lalu wire toggle-nya.
    $('.col-vis-toggle').each(function () {
        var key = $(this).data('column');
        if (colVisMap[key] == null) return;
        $(this).prop('checked', table.column(colVisMap[key]).visible());
    });
    $('.col-vis-toggle').on('change', function () {
        var key = $(this).data('column');
        if (colVisMap[key] == null) return;
        var visible = $(this).is(':checked');
        table.column(colVisMap[key]).visible(visible);
        setColVisPref(key, visible);
        try { table.columns.adjust(); } catch (e) {}
    });
    $('#colVisDropdown .dropdown-menu').on('click', function (e) { e.stopPropagation(); });

    // Tabel selalu scroll horizontal — kolom Kode & Action beku (lihat CSS .po-hscroll).
    table.on('column-visibility.dt', function () { try { table.columns.adjust(); } catch (e) {} });

    /*
    | FOOTER KALKULASI — total Nilai PO, Budget & Cost Request dari SEMUA
    | baris yang lolos filter/search saat ini (bukan cuma baris di halaman aktif).
    */
    function moneyFmtPO(v) {
        var sign = v < 0 ? '-' : '';
        return sign + Math.abs(Math.round(v)).toLocaleString('id-ID');
    }

    function recalcFooterPO() {
        var totalValue = 0, totalContract = 0, totalBp = 0, totalCr = 0, totalPayment = 0, totalProfit = 0, totalNet = 0;
        table.rows({ search: 'applied' }).every(function () {
            var node = this.node();
            totalValue    += parseFloat(node.querySelector('.col-raw-value')?.dataset.raw)    || 0;
            totalContract += parseFloat(node.querySelector('.col-raw-contract')?.dataset.raw) || 0;
            totalBp       += parseFloat(node.querySelector('.col-raw-bp')?.dataset.raw)       || 0;
            totalCr       += parseFloat(node.querySelector('.col-raw-cr')?.dataset.raw)       || 0;
            totalPayment  += parseFloat(node.querySelector('.col-raw-payment')?.dataset.raw)  || 0;
            totalProfit   += parseFloat(node.querySelector('.col-raw-profit')?.dataset.raw)   || 0;
            totalNet      += parseFloat(node.querySelector('.col-raw-net')?.dataset.raw)      || 0;
        });
        $('#poFooterValue').text(moneyFmtPO(totalValue));
        $('#poFooterContract').text(moneyFmtPO(totalContract));
        $('#poFooterBp').text(moneyFmtPO(totalBp));
        $('#poFooterCr').text(moneyFmtPO(totalCr));
        $('#poFooterPayment').text(moneyFmtPO(totalPayment));
        $('#poFooterProfit').text(moneyFmtPO(totalProfit));
        $('#poFooterNet').text(moneyFmtPO(totalNet));
    }

    table.on('draw', recalcFooterPO);
    recalcFooterPO();

    /* Export Excel mengikuti filter yang SEDANG aktif di tabel — lihat
       public/js/tesys-export-filtered.js untuk alasan & perilaku fallback-nya. */
    if (window.TesysExportFiltered) {
        TesysExportFiltered.wire('#btnExportPO', table, '{{ route('project.export-excel') }}');
    }


    var poFilterColumns = [
        { key: 'status',   label: 'Status', valueLabels: {
            DRAFT: 'Draft', SUBMITTED: 'Submitted', WAITING_PO: 'Waiting PO', ACTIVE: 'Active',
            PROGRESS_DONE: 'Progress Done', PAID: 'Paid', CLOSING: 'Closing', CLOSED: 'Closed'
        } },
        { key: 'customer', label: 'Customer' },
        { key: 'pm',       label: 'Manager' },
        { key: 'type',     label: 'Tipe' },
    ];
    if (currentUserRole !== 'head') {
        poFilterColumns.splice(2, 0, { key: 'financing', label: 'Financing' });
    }

    TesysColumnFilter.initRowFilters('#projectTable', '#poRowFilters', poFilterColumns);

    // Pre-filter dari query string (?customer=..&manager=..&status=..) — dipakai
    // link dari Summary Finance (Collection Performance / PO Summary) supaya
    // user mendarat dengan filter Customer/Manager/Status yang relevan sudah
    // aktif, tanpa perlu set manual. Dipanggil SETELAH initRowFilters() sesuai
    // kontrak TesysColumnFilter.setRowFilter().
    (function () {
        var params = new URLSearchParams(window.location.search);
        var qCustomer = params.get('customer');
        var qManager  = params.get('manager');
        var qStatus   = params.get('status');
        if (qCustomer) TesysColumnFilter.setRowFilter('#projectTable', 'customer', qCustomer);
        if (qManager)  TesysColumnFilter.setRowFilter('#projectTable', 'pm', qManager);
        if (qStatus)   TesysColumnFilter.setRowFilter('#projectTable', 'status', qStatus);

        /* ?code=<kode PO> — tautan kode PO dari kartu Active Cash Advance di
           Summary. Kode PO bukan salah satu kolom berfilter, jadi dipakai
           sebagai pencarian teks; kotak "Cari" ikut terisi supaya penyaringnya
           jadi keadaan yang terlihat dan bisa dihapus user, bukan penyempitan
           diam-diam yang membuat baris lain seolah hilang. */
        var qCode = params.get('code');
        if (qCode) {
            $('#searchInput').val(qCode);
            table.search(qCode).draw();
        }
    })();

    $('#refreshTable').on('click', function () { location.reload(); });

    $('#btnClearFilterPO').on('click', function () {
        TesysColumnFilter.clearAll('#projectTable');
        TesysWorkflowStepper.clear('projectTable');

        /* Pencarian teks ikut dibersihkan. Sebelumnya tidak, karena kotak "Cari"
           selalu diisi user sendiri sehingga ia tahu isinya. Begitu halaman ini
           bisa dibuka lewat tautan ?code=<kode PO> dari Summary, kotak itu terisi
           TANPA user mengetiknya — menekan Clear Filter lalu melihat daftar tetap
           menyisakan satu baris terbaca seperti tombolnya rusak. */
        $('#searchInput').val('');
        table.search('').draw();

        /* ?code= dihapus dari URL supaya memuat ulang halaman tidak memasang
           kembali filter yang barusan dibersihkan. */
        var url = new URL(window.location.href);
        if (url.searchParams.has('code')) {
            url.searchParams.delete('code');
            window.history.replaceState({}, '', url.toString());
        }
    });

    /*
    |--------------------------------------------------------------------------
    | SUBMIT CONFIRM
    |--------------------------------------------------------------------------
    */
    $(document).on('submit', '.frmSubmitProject', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Submit PO?',
            text: 'PO akan dikirim ke Finance untuk direview.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: WARNA_UTAMA, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Submit', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    $(document).on('submit', '.frmDeleteProject', function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Hapus PO?', text: 'Data tidak dapat dikembalikan.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: WARNA_BAHAYA, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) { sibukkan(form); form.submit(); } });
    });

    /*
    |--------------------------------------------------------------------------
    | FINANCE APPROVE
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btn-finance-approve', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#financeApproveText').text('Review PO: ' + name);
        $('#frmFinanceApprove').attr('action', '/project/' + id + '/finance-approve');
        $('#frmFinanceApprove')[0].reset();
        $('#poNumberGroup').hide();
        $('#mdlFinanceApprove').modal('show');
    });

    $('#approvalTypeSelect').on('change', function () {
        if ($(this).val() === 'ACTIVE') {
            $('#poNumberGroup').show();
            $('#poNumberGroup input').prop('required', true);
        } else {
            $('#poNumberGroup').hide();
            $('#poNumberGroup input').prop('required', false);
        }
    });

    /*
    |--------------------------------------------------------------------------
    | FINANCE REVISE
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btn-finance-revise', function () {
        var id = $(this).data('id');
        $('#frmFinanceRevise').attr('action', '/project/' + id + '/finance-revise');
        $('#frmFinanceRevise')[0].reset();
        $('#mdlFinanceRevise').modal('show');
    });

    /*
    /*
    |--------------------------------------------------------------------------
    | TANDAI BAST LENGKAP
    |--------------------------------------------------------------------------
    | Submit BAST (upload file) sekarang dilakukan di halaman terpisah
    | (/project/{id}/submit-bast). Di sini cuma aksi ringan: konfirmasi bahwa
    | semua BAST untuk No. PO ini (bisa 1-4 dokumen) sudah selesai di-upload.
    */
    $(document).on('click', '.btn-complete-po-bast', function () {
        var id = $(this).data('id');
        var po = $(this).data('po');
        var action = '/project/po-number/' + id + '/complete-bast';

        if (typeof Swal === 'undefined') {
            if (!confirm('BAST No. PO ' + po + ' sudah diupload semua?')) return;
            $('#frmCompletePoBast').attr('action', action).submit();
            return;
        }
        Swal.fire({
            title: 'BAST sudah diupload semua?',
            text: 'No. PO ' + po + ' akan ditandai BAST lengkap.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: WARNA_SETUJU, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Lengkap', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) {
                $('#frmCompletePoBast').attr('action', action).submit();
            }
        });
    });

    $(document).on('click', '.btn-delete-po-number', function () {
        var id = $(this).data('id');
        var po = $(this).data('po');
        if (typeof Swal === 'undefined') {
            if (!confirm('Hapus No. PO ' + po + '?')) return;
            $('#frmDeletePoNumber').attr('action', '/project/po-number/' + id).submit();
            return;
        }
        Swal.fire({
            title: 'Hapus No. PO?',
            text: 'No. PO ' + po + ' beserta lampirannya akan dihapus.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: WARNA_BAHAYA, cancelButtonColor: WARNA_BATAL,
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (r.isConfirmed) {
                $('#frmDeletePoNumber').attr('action', '/project/po-number/' + id).submit();
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | SUBMIT CLOSING
    |--------------------------------------------------------------------------
    */
    var closingIdx2 = 0;

    function makeClosingAttRow2(required) {
        var i = closingIdx2++;
        return '<div class="d-flex align-items-center mb-2" style="gap:8px;">' +
            '<input type="text" name="closing_attachments[' + i + '][title]" class="form-control closing-att-title" style="max-width:220px;text-transform:uppercase;" placeholder="Judul lampiran..." ' + (required ? 'required' : '') + '>' +
            '<input type="file" name="closing_attachments[' + i + '][file]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" ' + (required ? 'required' : '') + '>' +
            '<button type="button" class="btn btn-sm btn-danger btn-remove-closing-att2"><i class="fas fa-trash"></i></button>' +
        '</div>';
    }

    $(document).on('click', '.btn-submit-closing', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#closingProjectName').text(name);
        $('#frmSubmitClosing').attr('action', '/project/' + id + '/submit-closing');
        $('#frmSubmitClosing')[0].reset();
        closingIdx2 = 0;
        $('#closingAttList2').html(makeClosingAttRow2(true));
        $('#mdlSubmitClosing').modal('show');
    });

    $('#btnAddClosingAtt2').on('click', function () {
        $('#closingAttList2').append(makeClosingAttRow2(false));
    });

    $(document).on('click', '.btn-remove-closing-att2', function () {
        if ($('#closingAttList2 .d-flex').length > 1) $(this).closest('.d-flex').remove();
    });

    /*
    |--------------------------------------------------------------------------
    | VERIFY CLOSING
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btn-verify-closing', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#verifyClosingText').text('Verifikasi MoM Closing untuk PO: ' + name + '. Status akan berubah menjadi CLOSED.');
        $('#frmVerifyClosing').attr('action', '/project/' + id + '/verify-closing');
        $('#frmVerifyClosing textarea[name="notes"]').val('');
        $('#mdlVerifyClosing').modal('show');
    });

    /*
    |--------------------------------------------------------------------------
    | FINANCE CLOSE — PAID langsung ke CLOSED
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btn-finance-close', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#financeCloseText').text('Tutup PO: ' + name + '. Status akan berubah menjadi CLOSED.');
        $('#frmFinanceClose').attr('action', '/project/' + id + '/finance-close');
        $('#frmFinanceClose textarea[name="notes"]').val('');
        $('#mdlFinanceClose').modal('show');
    });

    /*
    |--------------------------------------------------------------------------
    | REJECT CLOSING
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btn-reject-closing', function () {
        var id = $(this).data('id');
        $('#frmRejectClosing').attr('action', '/project/' + id + '/reject-closing');
        $('#frmRejectClosing textarea[name="notes"]').val('');
        $('#mdlRejectClosing').modal('show');
    });

    /*
    |--------------------------------------------------------------------------
    | CLOSE PROJECT
    |--------------------------------------------------------------------------
    */
    var closingIdx = 0;

    function makeClosingAttRow(required) {
        var i = closingIdx++;
        return '<div class="d-flex align-items-center mb-2" style="gap:8px;">' +
            '<input type="text" name="closing_attachments[' + i + '][title]" class="form-control closing-att-title" style="max-width:220px;text-transform:uppercase;" placeholder="Judul lampiran..." ' + (required ? 'required' : '') + '>' +
            '<input type="file" name="closing_attachments[' + i + '][file]" class="form-control-file flex-grow-1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" ' + (required ? 'required' : '') + '>' +
            '<button type="button" class="btn btn-xs btn-danger btn-remove-closing-att"><i class="fas fa-trash"></i></button>' +
        '</div>';
    }

    $(document).on('click', '.btn-close-project', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#closeProjectText').text('Tutup PO: ' + name + '. Pastikan MoM sudah siap diupload.');
        $('#frmCloseProject').attr('action', '/project/' + id + '/close');
        $('#frmCloseProject')[0].reset();
        closingIdx = 0;
        $('#closingAttList').html(makeClosingAttRow(true));
        $('#mdlCloseProject').modal('show');
    });

    $('#btnAddClosingAtt').on('click', function () {
        $('#closingAttList').append(makeClosingAttRow(false));
    });

    $(document).on('click', '.btn-remove-closing-att', function () {
        if ($('#closingAttList .d-flex').length > 1) $(this).closest('.d-flex').remove();
    });

    /*
    |--------------------------------------------------------------------------
    | MODAL DETAIL
    |--------------------------------------------------------------------------
    */
    var statusColors = {
        'DRAFT':'secondary','SUBMITTED':'warning',
        'WAITING_PO':'info','ACTIVE':'success','CLOSED':'dark'
    };

    // Grouping/collapse tabel Milestone pada modal Detail PO (berdasarkan Project)
    $(document).on('click', '#prjDetailBody .prj-group-row', function () {
        var target = $(this).data('target');
        $('#prjDetailBody .' + target).toggle();
        $(this).find('.prj-caret').toggleClass('rotated');
    });

    // Grouping/collapse tabel PIC & Access (pivot generik berdasarkan tipe/role)
    $(document).on('click', '.prj-pivot-group-row', function () {
        var target = $(this).data('target');
        $(this).closest('table').find('.' + target).toggle();
        $(this).find('.prj-pivot-caret').toggleClass('rotated');
    });

    // Tab Budget: grup per Chart of Account. Klik baris grup = buka/tutup sub-item.
    function resetGroupCrState($groupRows) {
        $groupRows.find('.prj-cr-toggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    }
    $(document).on('click', '#prjBudgetBody .bp-group-row', function () {
        var target = $(this).data('target');
        var $subRows = $('#prjBudgetBody .' + target);
        var expanding = $subRows.first().is(':hidden');
        if (expanding) {
            $subRows.show();
        } else {
            $subRows.hide();
            $('#prjBudgetBody .' + target + '-crall').hide();
            resetGroupCrState($subRows);
        }
        $(this).find('.bp-caret').toggleClass('rotated', expanding);
    });

    // Sub-item budget -> dropdown daftar Cost Request yang memakainya (baris penuh, di bawah baris sub-item)
    $(document).on('click', '.prj-cr-toggle', function (e) {
        e.stopPropagation();
        var target = $(this).data('target');
        $('#prjBudgetBody .' + target).toggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Tab Info: toggle Notes / BAST / Closing Info
    $(document).on('click', '#prjNotesToggle', function () {
        $('#prjNotesCollapse').slideToggle(150);
        $('#prjNotesToggleIcon').toggleClass('rotated');
    });
    $(document).on('click', '#prjBastToggle', function () {
        $('#prjBastCollapse').slideToggle(150);
        $('#prjBastToggleIcon').toggleClass('rotated');
    });
    $(document).on('click', '#prjClosedToggle', function () {
        $('#prjClosedCollapse').slideToggle(150);
        $('#prjClosedToggleIcon').toggleClass('rotated');
    });

    /* ── IMAGE VIEWER — carousel + zoom, untuk tab Attachment ── */
    (function () {
        var overlay  = document.getElementById('imgViewerOverlay');
        var stageImg = document.getElementById('imgViewerImg');
        var counter  = document.getElementById('imgViewerCounter');
        var images   = [];
        var idx      = 0;
        var zoomed   = false;
        var isDragging = false, dragStartX = 0, dragStartY = 0, imgX = 0, imgY = 0;
        var justSwiped = false;   // menandai klik yang sebenarnya gerakan geser

        function renderViewer() {
            var item = images[idx];
            if (!item) return;
            resetZoom();
            stageImg.src = item.full;
            $('#imgViewerName').text(item.name || '');
            counter.textContent = (idx + 1) + ' / ' + images.length;
            /* Panah hanya ada kalau memang ada tujuannya: tidak tampil untuk
               lampiran tunggal, tidak ada panah kiri di lampiran pertama dan
               tidak ada panah kanan di lampiran terakhir. */
            var banyak = images.length > 1;
            $('#imgViewerPrev').css('display', (banyak && idx > 0) ? 'flex' : 'none');
            $('#imgViewerNext').css('display', (banyak && idx < images.length - 1) ? 'flex' : 'none');
        }

        function resetZoom() {
            zoomed = false;
            imgX = 0; imgY = 0;
            stageImg.classList.remove('zoomed', 'dragging');
            stageImg.style.transform = 'translate(0px, 0px) scale(1)';
            stageImg.style.cursor = 'zoom-in';
        }

        function applyTransform() {
            stageImg.style.transform = 'translate(' + imgX + 'px, ' + imgY + 'px) scale(' + (zoomed ? 2.4 : 1) + ')';
        }

        function openViewer(groupImages, startIndex) {
            images = groupImages;
            idx = startIndex;
            renderViewer();
            overlay.classList.add('show');
            $('body').css('overflow', 'hidden');
        }

        function closeViewer() {
            overlay.classList.remove('show');
            $('body').css('overflow', '');
        }

        function showNext() { if (idx < images.length - 1) { idx++; renderViewer(); } }
        function showPrev() { if (idx > 0) { idx--; renderViewer(); } }

        // Attachment tab (tabel): tombol View untuk gambar -> carousel dalam grup tabelnya.
        $(document).on('click', '.prj-att-view-img', function () {
            var $btns = $(this).closest('table').find('.prj-att-view-img');
            var groupImages = $btns.map(function () {
                return { full: $(this).data('full'), name: $(this).data('name') };
            }).get();
            var startIndex = $btns.index(this);
            openViewer(groupImages, startIndex < 0 ? 0 : startIndex);
        });

        $('#imgViewerClose').on('click', closeViewer);
        $('#imgViewerNext').on('click', showNext);
        $('#imgViewerPrev').on('click', showPrev);
        $(overlay).on('click', function (e) {
            // Klik di area kosong menutup viewer — kecuali kalau klik itu
            // ujung dari gerakan geser antar lampiran.
            if (justSwiped) { justSwiped = false; return; }
            if (e.target === overlay) closeViewer();
        });

        $(document).on('keydown', function (e) {
            if (!overlay.classList.contains('show')) return;
            if (e.key === 'Escape') closeViewer();
            else if (e.key === 'ArrowRight') showNext();
            else if (e.key === 'ArrowLeft') showPrev();
        });

        stageImg.addEventListener('dblclick', function () {
            zoomed = !zoomed;
            if (!zoomed) { imgX = 0; imgY = 0; }
            stageImg.classList.toggle('zoomed', zoomed);
            stageImg.style.cursor = zoomed ? 'grab' : 'zoom-in';
            applyTransform();
        });
        stageImg.addEventListener('wheel', function (e) {
            e.preventDefault();
            zoomed = e.deltaY < 0 ? true : false;
            if (!zoomed) { imgX = 0; imgY = 0; }
            stageImg.classList.toggle('zoomed', zoomed);
            stageImg.style.cursor = zoomed ? 'grab' : 'zoom-in';
            applyTransform();
        }, { passive: false });

        stageImg.addEventListener('mousedown', function (e) {
            if (!zoomed) return;
            isDragging = true;
            dragStartX = e.clientX - imgX;
            dragStartY = e.clientY - imgY;
            stageImg.classList.add('dragging');
        });
        document.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            imgX = e.clientX - dragStartX;
            imgY = e.clientY - dragStartY;
            applyTransform();
        });
        document.addEventListener('mouseup', function () {
            isDragging = false;
            stageImg.classList.remove('dragging');
        });

        /* ── GESER KIRI/KANAN untuk pindah lampiran ─────────────────────────
           Saat gambar TIDAK di-zoom, tarikan mendatar (mouse maupun sentuh)
           berpindah ke lampiran sebelum/sesudahnya — pola yang sama dengan
           galeri ponsel. Saat di-zoom, tarikan tetap menggeser gambar
           (pan), jadi keduanya tidak saling rebutan. */
        var SWIPE_MIN = 50;          // px, biar klik/getar kecil tidak terbaca geser
        var swipeX = null, swipeY = null;

        function swipeStart(x, y) { if (!zoomed) { swipeX = x; swipeY = y; } }

        function swipeEnd(x, y) {
            if (swipeX === null || images.length < 2) { swipeX = swipeY = null; return; }
            var dx = x - swipeX, dy = y - swipeY;
            swipeX = swipeY = null;
            if (Math.abs(dx) < SWIPE_MIN || Math.abs(dx) <= Math.abs(dy)) return;
            if (dx < 0 && idx >= images.length - 1) return;   // sudah di lampiran terakhir
            if (dx > 0 && idx <= 0) return;                   // sudah di lampiran pertama
            justSwiped = true;
            if (dx < 0) showNext(); else showPrev();
        }

        stageImg.addEventListener('mousedown', function (e) { swipeStart(e.clientX, e.clientY); });
        document.addEventListener('mouseup', function (e) {
            if (!overlay.classList.contains('show')) { swipeX = swipeY = null; return; }
            swipeEnd(e.clientX, e.clientY);
        });

        stageImg.addEventListener('touchstart', function (e) {
            var t = e.changedTouches[0];
            swipeStart(t.clientX, t.clientY);
        }, { passive: true });
        stageImg.addEventListener('touchend', function (e) {
            var t = e.changedTouches[0];
            swipeEnd(t.clientX, t.clientY);
        }, { passive: true });

        /* Geser juga boleh lewat area kosong di kiri/kanan gambar. */
        overlay.addEventListener('mousedown', function (e) {
            if (e.target === stageImg) return;
            swipeStart(e.clientX, e.clientY);
        });
    })();

    /* ── Copy-to-clipboard (tab PIC: whatsapp / email) ── */
    var _prjToastT;
    function prjShowCopyToast() {
        var $t = $('#prjCopyToast').addClass('show');
        clearTimeout(_prjToastT);
        _prjToastT = setTimeout(function () { $t.removeClass('show'); }, 1600);
    }
    $(document).on('click', '.prj-copy', function () {
        var txt = $(this).attr('data-copy');
        if (txt == null || txt === '') return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(txt).then(prjShowCopyToast, function () {});
        } else {
            var $tmp = $('<textarea>').val(txt).css({ position: 'fixed', opacity: 0 }).appendTo('body');
            $tmp[0].select();
            try { document.execCommand('copy'); prjShowCopyToast(); } catch (e) {}
            $tmp.remove();
        }
    });

    /* Keadaan kosong ringkas untuk ruang sempit di dalam modal — bentuknya
       sama dengan komponen keadaan kosong yang dipakai tabel dan drawer. */
    function kosongRingkas(pesan) {
        var aman = $('<div>').text(pesan == null ? '' : String(pesan)).html();
        return '<div class="ts-empty ts-empty-compact"><p class="ts-empty-msg">' + aman + '</p></div>';
    }

    /* Markup kerangka disimpan sekali karena saat pemuatan gagal isinya
       diganti pesan galat; tanpa ini pembukaan berikutnya kehilangan kerangka. */
    var kerangkaDetailPo = null;
    function pulihkanKerangkaPo() {
        var el = $('#projectDetailLoading');
        if (kerangkaDetailPo === null) kerangkaDetailPo = el.html();
        else el.html(kerangkaDetailPo);
    }

    function openProjectDetail(id, tabHref) {
        window._prjPendingTab = tabHref || null;
        pulihkanKerangkaPo();
        $('#projectDetailLoading').show();
        $('#projectDetailContent').hide();
        $('#mdlDetailProject').modal('show');

        $('#btnOpenBastPage').attr('href', '/project/' + id + '/submit-bast');
        $('.prj-can-submit-bast').toggle(canSubmitBast);

        $.get('/project/' + id + '/detail-json', function (d) {
            $('#mdlProjectTitle').text(d.name);
            $('#mdlProjectSubtitle').text('Kode: ' + (d.code || '-'));

            $('#prjCode').text(d.code || '-');
            $('#prjStatusBadge').html('<span class="badge badge-' + (statusColors[d.status]||'secondary') + ' px-3 py-1">' + d.status_label + '</span>');
            $('#prjCustomer').text(d.customer);
            $('#prjManager').text((d.manager || '-').toUpperCase());
            $('#prjValue').text(d.contract_formatted);
            $('#prjPoValue').text(d.value_formatted);
            $('#prjInputBy').text(d.input_by);

            // Sidebar extra stats — dihitung dari budget_plans (sumber sama dgn tab Budget)
            var totalBudgetAmt = 0, totalUsedAmt = 0;
            d.budget_plans.forEach(function(bp) {
                totalBudgetAmt += parseInt(bp.amount) || 0;
                totalUsedAmt   += parseInt(bp.used)   || 0;
            });
            var totalVarAmt  = totalBudgetAmt - totalUsedAmt;
            var varColorSide = totalVarAmt < 0 ? 'var(--danger)' : 'var(--success)';

            var sideExtra = '';
            sideExtra += '<hr style="border-color:rgba(255,255,255,0.08);">';
            sideExtra += '<div class="mb-3"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Budget</span>';
            sideExtra += '<div style="color:var(--accent);font-size:14px;font-weight:700;margin-top:4px;">' + d.budget_plans.length + ' Plan &bull; ' + totalBudgetAmt.toLocaleString('id-ID') + '</div></div>';
            sideExtra += '<div class="mb-3"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Total Used</span>';
            sideExtra += '<div style="color:var(--warning);font-size:14px;font-weight:700;margin-top:4px;">' + totalUsedAmt.toLocaleString('id-ID') + '</div></div>';
            sideExtra += '<div class="mb-3"><span style="color:var(--text-faint);font-size:10px;text-transform:uppercase;font-weight:600;">Variance</span>';
            sideExtra += '<div style="color:' + varColorSide + ';font-size:14px;font-weight:700;margin-top:4px;">' + totalVarAmt.toLocaleString('id-ID') + '</div></div>';
            $('#prjSideExtra').html(sideExtra);

            // Tab Info
            $('#infoCode').text(d.code || '-');
            $('#infoName').text(d.name);
            $('#infoStatus').html('<span class="badge badge-' + (statusColors[d.status]||'secondary') + ' px-2 py-1">' + d.status_label + '</span>');
            $('#infoCustomer').text(d.customer);
            $('#infoManager').text((d.manager || '-').toUpperCase());
            $('#infoDoneBy').text(d.financing ? (d.financing + ' - ' + d.financing_label) : '-');
            $('#infoType').html(d.type
                ? '<span class="badge badge-' + (d.type_color || 'secondary') + ' px-2 py-1">' + d.type_label + '</span>'
                : '-');
            $('#infoPoNumber').text(d.po_number || '-');

            /* PO Quantity = ekspektasi manual, jumlah No. PO = yang benar-benar
               sudah dientri. Ditampilkan berdampingan supaya selisihnya terlihat
               tanpa harus membuka tab No. PO. */
            (function () {
                var aktual = d.po_numbers_count || 0;
                var teks = d.po_quantity ? (aktual + ' / ' + d.po_quantity) : (aktual + ' (belum diset)');
                if (d.po_adjustment_required) {
                    teks += ' <span class="badge badge-warning ml-1" style="font-size:9px;">PO Adjustment Required</span>';
                }
                $('#infoPoQuantity').html(teks);
            })();

            $('#infoToP').text(d.term_of_payment || '-');

            // KPI — kartu terkelompok (Value / Budget / Cost / Profitability / Timeline)
            (function renderSummaryKpi() {
                var c = d.cost_summary || {};
                var rpf = function (v) { return Math.round(v || 0).toLocaleString('id-ID'); };
                var contractN = parseInt(d.contract || 0);
                var poAccum = (d.po_numbers || []).reduce(function (s, p) { return s + (parseFloat(p.nominal) || 0); }, 0);
                var valueDiff = contractN - poAccum;
                var budgetVar = (totalBudgetAmt || 0) - (totalUsedAmt || 0);

                /* Satu informasi satu baris (.ts-deflist di tesys-ui-components.css).
                   Sebelumnya tiap kelompok jadi kartu berkotak dan disusun grid —
                   yang paling menonjol malah wadahnya, dan angka-angka tidak bisa
                   dibandingkan dengan menyusuri satu kolom. */
                /* Pemanggil di bawah memakai penanda 'total' (warisan kelas lama).
                   Dipetakan di satu tempat ini ke 'is-total' milik .ts-deflist,
                   supaya tidak ada satu pun baris total yang terlewat karena
                   penggantian nama. */
                function kelasBaris(cls) { return cls === 'total' ? 'is-total' : (cls || ''); }

                function row(label, val, cls) {
                    return '<div class="ts-deflist-row ' + kelasBaris(cls) + '">'
                         + '<span class="ts-deflist-label">' + label + '</span>'
                         + '<span class="ts-deflist-value">' + maskMoney(val) + '</span></div>';
                }
                function rowPlain(label, val, cls) {
                    return '<div class="ts-deflist-row ' + kelasBaris(cls) + '">'
                         + '<span class="ts-deflist-label">' + label + '</span>'
                         + '<span class="ts-deflist-value">' + val + '</span></div>';
                }
                function card(title, rows) {
                    return '<div class="ts-deflist-head">' + title + '</div>' + rows;
                }

                var valueCard = card('Value',
                    row('Contract', d.contract_formatted) +
                    row('PO Value', rpf(poAccum)) +
                    (valueDiff !== 0 ? row('Selisih', (valueDiff < 0 ? '-' : '') + rpf(Math.abs(valueDiff)), 'total') : ''));

                var budgetCard = card('Budget',
                    row('Budget', rpf(totalBudgetAmt)) +
                    row('Used', rpf(totalUsedAmt)) +
                    row('Variance', (budgetVar < 0 ? '-' : '') + rpf(Math.abs(budgetVar)), 'total'));

                var costCard = card('Cost',
                    row('Variable Cost', c.variable_cost_formatted) +
                    row('Overhead', c.overhead_formatted) +
                    row('Fixed Cost', c.fixed_cost_formatted) +
                    row('Total Cost', c.total_cost_formatted, 'total'));

                var profitCard = card('Profitability',
                    row('Received Payment', c.payment_total_formatted) +
                    row('Gross Profit', c.gross_profit_formatted) +
                    row('Net Profit', c.net_profit_formatted, 'total'));

                // Timeline: estimate (plan) vs actual, dari rentang milestone
                var monNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                function fmtD(raw) {
                    if (!raw) return '-';
                    var p = raw.split('-');
                    return parseInt(p[2], 10) + ' ' + (monNames[parseInt(p[1], 10) - 1] || '') + ' ' + p[0];
                }
                var pStart = [], pEnd = [], aStart = [], aEnd = [];
                (d.details || []).forEach(function (det) {
                    if (det.plan_start_raw) pStart.push(det.plan_start_raw);
                    if (det.plan_end_raw) pEnd.push(det.plan_end_raw);
                    if (det.actual_start_raw) aStart.push(det.actual_start_raw);
                    if (det.actual_end_raw) aEnd.push(det.actual_end_raw);
                });
                var today = new Date().toISOString().slice(0, 10);

                function tlBlock(label, arrS, arrE, cls, allowOngoing) {
                    var s = arrS.length ? arrS.slice().sort()[0] : null;
                    var e = arrE.length ? arrE.slice().sort()[arrE.length - 1] : null;
                    if (!s) return rowPlain(label, '<span class="text-muted">belum ada</span>', cls);
                    var end = e && e >= s ? e : (allowOngoing ? today : null);
                    var dur = end ? dayDiff(s, end) + ' hari' : '-';
                    var rangeTxt = fmtD(s) + ' – ' + (e && e >= s ? fmtD(e) : (allowOngoing ? 'sekarang' : '-'));
                    return rowPlain(label, dur, cls) +
                        '<div class="ts-deflist-sub">' + rangeTxt + '</div>';
                }

                var timelineCard = card('Timeline',
                    tlBlock('Estimate', pStart, pEnd, '', false) +
                    tlBlock('Actual', aStart, aEnd, 'total', true));

                /* Dua kolom di layar lebar supaya daftar ini tidak jadi satu
                   kolom yang memanjang; tiap kolom tetap satu informasi satu
                   baris. Kelompok dibagi rata: nilai & anggaran di kiri, biaya &
                   laba di kanan — urutan yang sama dengan cara orang membacanya. */
                $('#prjSummaryKpi').html(
                    '<div class="ts-deflist ts-deflist-2col">'
                  + '<div>' + valueCard + budgetCard + timelineCard + '</div>'
                  + '<div>' + costCard + profitCard + '</div>'
                  + '</div>'
                );
            })();
            $('#infoSubmittedAt').text(d.submitted_at || '-');
            $('#infoApprovedBy').text(d.approved_by + (d.approved_at ? ' · ' + d.approved_at : ''));
            $('#infoNotes').text(d.notes || '-');

            if (d.no_bast) {
                $('#prjBastToggleWrap').show();
                $('#infoBastNo').text(d.no_bast);
                $('#infoBastTgl').text(d.tanggal_bast || '-');
                $('#infoBastNilai').html('<strong style="color:var(--info);">' + d.nilai_bast_formatted + '</strong>');
                $('#infoBastNotes').text(d.bast_notes || '-');
                $('#infoBastBy').text(d.bast_submitted_by + (d.bast_submitted_at ? ' · ' + d.bast_submitted_at : ''));
            } else {
                $('#prjBastToggleWrap').hide();
            }

            if (d.status === 'CLOSED') {
                $('#prjClosedToggleWrap').show();
                $('#infoClosedBy').text(d.closed_by + (d.closed_at ? ' · ' + d.closed_at : ''));
                $('#infoClosingNotes').text(d.closing_notes || '-');
            } else {
                $('#prjClosedToggleWrap').hide();
            }

            // Tab Project Detail — expand/collapse by Project, badge status, merah bila terlambat
            $('#prjDetailCount').text(d.details.length);

            var prjStatusColors = { 'NOT_STARTED':'secondary', 'ON_GOING':'warning', 'DONE':'success' };

            function isLate(planRaw, actualRaw) {
                // Terlambat bila actual sudah terisi dan lebih besar dari plan,
                // ATAU plan sudah lewat tapi actual belum terisi sama sekali.
                if (!planRaw) return false;
                if (actualRaw) return actualRaw > planRaw;
                return planRaw < new Date().toISOString().slice(0, 10);
            }

            function dayDiff(fromRaw, toRaw) {
                var from = new Date(fromRaw), to = new Date(toRaw);
                return Math.round((to - from) / 86400000);
            }

            // Remark = selisih Plan Finish vs Actual Finish (atau vs hari ini kalau belum selesai).
            function milestoneRemark(planEndRaw, actualEndRaw) {
                if (!planEndRaw) return '-';
                if (actualEndRaw) {
                    var diff = dayDiff(planEndRaw, actualEndRaw);
                    if (diff > 0) return '<span class="prj-late">+' + diff + ' hari</span>';
                    if (diff < 0) return '<span style="color:var(--success);">-' + Math.abs(diff) + ' hari</span>';
                    return '<span class="badge badge-success px-2 py-1">On Time</span>';
                }
                var today = new Date().toISOString().slice(0, 10);
                if (planEndRaw < today) {
                    return '<span class="prj-late">Belum selesai, lewat ' + dayDiff(planEndRaw, today) + ' hari</span>';
                }
                return '-';
            }

            // Durasi berjalan PO: actual start paling awal vs actual finish paling akhir
            (function renderMilestoneDuration() {
                var starts = [], ends = [];
                (d.details || []).forEach(function (det) {
                    if (det.actual_start_raw) starts.push(det.actual_start_raw);
                    if (det.actual_end_raw) ends.push(det.actual_end_raw);
                });
                if (!starts.length) { $('#prjMilestoneDuration').html(''); return; }
                var minStart = starts.sort()[0];
                var today = new Date().toISOString().slice(0, 10);
                var maxEnd = ends.length ? ends.sort()[ends.length - 1] : null;
                var finished = maxEnd && maxEnd >= minStart;
                var refEnd = finished ? maxEnd : today;
                var days = dayDiff(minStart, refEnd);
                $('#prjMilestoneDuration').html(
                    '<span class="badge badge-info px-2 py-1" style="font-size:11px;">' +
                    '<i class="fas fa-stopwatch mr-1"></i>Berjalan ' + days + ' hari' +
                    '</span> <span class="text-muted" style="font-size:11px;">(' + minStart + ' → ' +
                    (finished ? maxEnd : 'sekarang') + ')</span>'
                );
            })();

            var groups = {};
            var groupOrder = [];
            (d.details || []).forEach(function (det) {
                var key = det.cluster || '-';
                if (!groups[key]) { groups[key] = []; groupOrder.push(key); }
                groups[key].push(det);
            });

            var detHtml = '';
            groupOrder.forEach(function (key, g) {
                var items = groups[key];
                detHtml += '<tr class="prj-group-row" data-target="prj-grp-' + g + '">' +
                    '<td colspan="9">' +
                        '<i class="fas fa-chevron-right prj-caret rotated mr-2"></i>' +
                        '<strong>' + key + '</strong> ' +
                        '<span class="text-muted" style="font-size:11px;">(' + items.length + ' milestone)</span>' +
                    '</td>' +
                '</tr>';

                items.forEach(function (det, i) {
                    var lateStart = isLate(det.plan_start_raw, det.actual_start_raw);
                    var lateEnd   = isLate(det.plan_end_raw, det.actual_end_raw);
                    var statusBadge = det.status
                        ? '<span class="badge badge-' + (prjStatusColors[det.status] || 'secondary') + ' px-2 py-1" style="font-size:10px;">' + (det.status_label || det.status) + '</span>'
                        : '-';
                    var monBtn = det.id
                        ? '<a href="/project-monitoring/' + d.id + '?detail=' + det.id + '" target="_blank" class="btn btn-secondary btn-xs" title="Project Monitoring: ' + (det.stage || '') + '"><i class="fas fa-binoculars"></i></a>'
                        : '-';

                    detHtml += '<tr class="prj-grp-' + g + '">' +
                        '<td class="text-center prj-indent">' + (i + 1) + '</td>' +
                        '<td class="prj-col-wide">' + det.stage + '</td>' +
                        '<td>' + statusBadge + '</td>' +
                        '<td>' + (det.plan_start || '-') + '</td>' +
                        '<td>' + (det.plan_end || '-') + '</td>' +
                        '<td class="' + (lateStart ? 'prj-late' : '') + '">' + (det.actual_start || '-') + '</td>' +
                        '<td class="' + (lateEnd ? 'prj-late' : '') + '">' + (det.actual_end || '-') + '</td>' +
                        '<td>' + milestoneRemark(det.plan_end_raw, det.actual_end_raw) + '</td>' +
                        '<td class="text-center">' + monBtn + '</td>' +
                    '</tr>';
                });
            });
            $('#prjDetailBody').html(detHtml || '<tr><td colspan="9" class="text-center text-muted py-3">Belum ada data detail.</td></tr>');

            // Tab PIC — grouped by tipe (Internal/Customer), collapsible
            var picGroups = {
                'Internal': (d.pics_internal || []),
                'Customer': (d.pics_customer || [])
            };
            var picBadge = { 'Internal': 'secondary', 'Customer': 'primary' };
            var allPicsCount = (d.pics_internal || []).length + (d.pics_customer || []).length;
            $('#prjPicCount').text(allPicsCount);
            var picHtml = '';
            Object.keys(picGroups).forEach(function (type, gi) {
                var items = picGroups[type];
                if (!items.length) return;
                var groupClass = 'prj-pic-grp-' + gi;
                picHtml += '<tr class="prj-pivot-group-row" data-target="' + groupClass + '">' +
                    '<td colspan="5" style="font-weight:700;">' +
                        '<i class="fas fa-chevron-right mr-2 prj-pivot-caret rotated"></i>' +
                        '<span class="badge badge-' + picBadge[type] + ' px-2 py-1 mr-1" style="font-size:10px;">' + type + '</span>' +
                        '<span class="text-muted" style="font-size:11px;">(' + items.length + ' PIC)</span>' +
                    '</td>' +
                '</tr>';
                items.forEach(function (p, i) {
                    var phoneCell = p.phone
                        ? '<span class="prj-copyable prj-copy" data-copy="' + p.phone + '">' + p.phone + '</span>'
                        : '-';
                    var emailCell = p.email
                        ? '<span class="prj-copyable prj-copy" data-copy="' + p.email + '">' + p.email + '</span>'
                        : '-';
                    picHtml += '<tr class="' + groupClass + '">' +
                        '<td class="text-center prj-pivot-indent">' + (i + 1) + '</td>' +
                        '<td>' + (p.pic || '-') + '</td>' +
                        '<td>' + (p.position || '-') + '</td>' +
                        '<td>' + phoneCell + '</td>' +
                        '<td>' + emailCell + '</td>' +
                    '</tr>';
                });
            });
            $('#prjPicBody').html(picHtml || '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada PIC.</td></tr>');

            var invStatusColors = {
                'DRAFT': 'secondary', 'SUBMITTED': 'info', 'VERIFIED': 'info',
                'APPROVED': 'primary', 'SENT': 'warning', 'RECEIVED': 'purple', 'PARTIALLY_PAID': 'warning',
                'PAID': 'success', 'CANCELLED': 'dark', 'OVERDUE': 'danger',
            };

            // ── Tab No. PO ──────────────────────────────────────────────
            // Label status yang tampak di tab ini memakai istilah "menunggu
            // tahap berikutnya" (bukan nama status mentah).
            var poNumStageLabel = {
                'RELEASED': 'Waiting BAST',
                'BAST_SUBMITTED': 'Waiting Invoice',
                'INVOICED': 'Waiting Payment'
            };
            $('#prjPoNumberCount').text((d.po_numbers || []).length);
            var poNumHtml = '';
            var poNumGrandTotal = 0;
            (d.po_numbers || []).forEach(function (p) {
                poNumGrandTotal += parseFloat(p.nominal) || 0;
                var attHtml = (p.attachments || []).length
                    ? p.attachments.map(function (a) {
                        return '<a href="/storage/' + a.path + '" target="_blank" class="d-block"><i class="fas fa-paperclip mr-1"></i>' + (a.title || 'File') + '</a>';
                    }).join('')
                    : '<span class="text-muted">-</span>';

                var noBastHtml, nilaiBastHtml;
                if (p.status === 'RELEASED') {
                    noBastHtml = '<span class="badge badge-secondary px-2 py-1" style="font-size:10px;">Waiting BAST</span>';
                    nilaiBastHtml = '<span class="text-muted">-</span>';
                } else {
                    var bastAttHtml = (p.bast_attachments || []).length
                        ? p.bast_attachments.map(function (a) {
                            return '<a href="/storage/' + a.path + '" target="_blank" class="d-block"><i class="fas fa-paperclip mr-1"></i>' + (a.title || 'File') + '</a>';
                        }).join('')
                        : '';
                    noBastHtml = '<div>' + (p.no_bast || '-') + '</div>' +
                        '<div class="text-muted" style="font-size:10px;">' + (p.tanggal_bast || '-') + '</div>' +
                        bastAttHtml;
                    nilaiBastHtml = (p.nilai_bast_formatted || '-');

                    if (p.status === 'PARTIAL' && canSubmitBast) {
                        noBastHtml += '<button type="button" class="btn btn-outline-success btn-xs mt-1 btn-complete-po-bast" ' +
                            'data-id="' + p.id + '" data-po="' + (p.po_number || '') + '">' +
                            '<i class="fas fa-check-double mr-1"></i>Tandai Lengkap</button>';
                    }
                }

                poNumHtml += '<tr>' +
                    '<td class="prj-col-wide">' + (p.po_number || '-') + '</td>' +
                    '<td class="text-right">' + (p.nominal_formatted || '-') + '</td>' +
                    '<td>' + (p.release_date || '-') + '</td>' +
                    '<td><span class="badge badge-' + (p.status_color || 'secondary') + ' px-2 py-1" style="font-size:10px;">' + (poNumStageLabel[p.status] || p.status_label || p.status) + '</span></td>' +
                    '<td>' + noBastHtml + '</td>' +
                    '<td class="text-right">' + nilaiBastHtml + '</td>' +
                    '<td>' + attHtml + '</td>' +
                    '<td class="text-center">' +
                        (p.status === 'RELEASED'
                            ? '<button type="button" class="btn btn-outline-danger btn-xs btn-delete-po-number" data-id="' + p.id + '" data-po="' + (p.po_number || '') + '"><i class="fas fa-trash"></i></button>'
                            : '-') +
                    '</td>' +
                '</tr>';
            });
            $('#prjPoNumberBody').html(poNumHtml || '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada No. PO.</td></tr>');
            $('#prjPoNumberGrandTotal').text(Math.round(poNumGrandTotal).toLocaleString('id-ID'));

            // ── Tab Cost ───────────────────────────────────────────────
            (function renderPrjCostTab() {
                var c = d.cost_summary || {};
                var crs = d.cost_requests || [];
                $('#prjCostCount').text(crs.length);

                // Satu informasi satu baris, sama dengan tab Summary.
                function row(label, val, cls) {
                    return '<div class="ts-deflist-row ' + (cls === 'total' ? 'is-total' : (cls || '')) + '">'
                         + '<span class="ts-deflist-label">' + label + '</span>'
                         + '<span class="ts-deflist-value">' + maskMoney(val) + '</span></div>';
                }
                $('#prjCostSummary').html(
                    '<div class="ts-deflist">' +
                        '<div class="ts-deflist-head">Cost</div>' +
                        row('Total Request', c.total_request_formatted) +
                        row('Total Refund', c.total_refund_formatted) +
                        row('Variable Cost', c.variable_cost_formatted, 'total') +
                        row('Overhead', c.overhead_formatted) +
                        row('Fixed Cost', c.fixed_cost_formatted) +
                        row('Total Cost', c.total_cost_formatted, 'total') +
                    '</div>'
                );

                var crStatusColor = {
                    'PAID':'success','REALIZED':'success','CLOSED':'dark',
                };
                var crRows = crs.map(function (cr) {
                    return '<tr>' +
                        '<td>' + (cr.code || '-') + '</td>' +
                        '<td>' + (cr.date || '-') + '</td>' +
                        '<td class="prj-col-wide">' + (cr.description || '-') + '</td>' +
                        '<td>' + (cr.beneficiary || '-') + '</td>' +
                        '<td class="text-right">' + maskMoney(cr.request_formatted) + '</td>' +
                        '<td class="text-right">' + maskMoney(cr.refund ? cr.refund_formatted : '-') + '</td>' +
                        '<td><span class="badge badge-' + (cr.status_color || crStatusColor[cr.status] || 'secondary') + ' px-2 py-1" style="font-size:9px;">' + (cr.status_label || cr.status) + '</span></td>' +
                    '</tr>';
                }).join('');
                $('#prjCostBody').html(crRows || '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada Cost Request.</td></tr>');
            })();

            // ── Tab Invoice ────────────────────────────────────────────
            (function renderPrjInvoiceTab() {
                var invs = d.invoices || [];
                $('#prjInvoiceCount').text(invs.length);
                var totVal = 0;
                var rows = invs.map(function (inv) {
                    totVal += parseInt(inv.captured_total || 0);
                    var ds = inv.display_status || inv.status;
                    var viewBtn = inv.id
                        ? '<a href="/invoice-cash-receipt?open=' + inv.id + '" target="_blank" class="btn btn-info btn-xs" title="View"><i class="fas fa-eye"></i></a>'
                        : '-';
                    return '<tr>' +
                        '<td>' + (inv.code || '-') + '</td>' +
                        '<td class="prj-col-wide">' + (inv.po_number || '<span class="text-muted">-</span>') + '</td>' +
                        '<td>' + (inv.payment_term || '-') + '</td>' +
                        '<td>' + (inv.invoice_date || '-') + '</td>' +
                        '<td class="' + (inv.is_overdue ? 'prj-late' : '') + '">' + (inv.due_date || '-') + '</td>' +
                        '<td class="text-right prj-money-col">' + maskMoney(inv.captured_total_formatted) + '</td>' +
                        '<td class="prj-money-col">' + (inv.received_date || '<span class="text-muted">-</span>') + '</td>' +
                        '<td><span class="badge badge-' + (invStatusColors[ds] || 'secondary') + ' px-2 py-1" style="font-size:9px;">' + ds + '</span></td>' +
                        '<td class="text-center">' + viewBtn + '</td>' +
                    '</tr>';
                }).join('');
                $('#prjInvoiceBody').html(rows || '<tr><td colspan="9" class="text-center text-muted py-3">Belum ada Invoice.</td></tr>');
                $('#prjInvoiceFootValue').text(maskMoney(totVal.toLocaleString('id-ID')));
            })();

            // Tab Budget — SEMUA Budget Plan digabung jadi satu, grouping by Chart of Account.
            // Tiap sub-item (baris detail) bisa di-expand lagi untuk lihat daftar Cost Request
            // yang memakainya (kode CR + catatan + nilai realisasi/pengajuan).
            $('#prjBudgetCount').text(d.budget_plans.length);
            (function renderPrjBudgetTab() {
                var moneyFmt = function (v) { return Math.round(v).toLocaleString('id-ID'); };
                var NOB_RED = 'var(--danger)', NOB_YELLOW = 'var(--warning)', OK_GREEN = 'var(--success)';
                var nobBadge = '<span class="badge badge-danger ml-1" style="font-size:9px;">NOB</span>';

                if (!d.budget_plans.length) {
                    $('#prjBudgetBody').html(kosongRingkas('Belum ada Budget Plan.'));
                    return;
                }

                var totalBudget = 0, totalUsed = 0;
                var groups = {}, groupOrder = [];
                d.budget_plans.forEach(function (bp) {
                    totalBudget += parseInt(bp.amount || 0);
                    totalUsed   += parseInt(bp.used || 0);
                    (bp.details || []).forEach(function (det) {
                        var acc = det.account || '-';
                        if (!groups[acc]) { groups[acc] = []; groupOrder.push(acc); }
                        groups[acc].push(det);
                    });
                });
                groupOrder.sort(function (a, b) { return a.localeCompare(b); });
                var totalVar = totalBudget - totalUsed;
                var totalVarColor = totalVar < 0 ? NOB_RED : OK_GREEN;

                var summaryHtml = '<div class="d-flex align-items-center flex-wrap mb-2" style="gap:10px;font-size:12px;">' +
                    '<span class="text-muted">' + d.budget_plans.length + ' Budget Plan &middot; Total Budget:</span> <strong style="color:var(--info);">' + moneyFmt(totalBudget) + '</strong>' +
                    '<span class="text-muted">Used:</span> <strong style="color:var(--warning);">' + moneyFmt(totalUsed) + '</strong>' +
                    '<span class="text-muted">Var:</span> <strong style="color:' + totalVarColor + ';">' + moneyFmt(totalVar) + '</strong>' +
                '</div>';

                var rows = '';
                if (groupOrder.length) {
                    groupOrder.forEach(function (acc, gi) {
                        var items = groups[acc];
                        var gBudget = 0, gUsed = 0, gRemaining = 0, anyItemNob = false;
                        items.forEach(function (det) {
                            gBudget += det.total; gUsed += det.used; gRemaining += det.remaining;
                            if (det.used > det.total) anyItemNob = true;
                        });

                        var groupClass = 'prj-budget-grp-' + gi;
                        var isNobCategoryFull = gUsed > gBudget;
                        var isNobCategoryPartial = !isNobCategoryFull && anyItemNob;
                        var gColor = isNobCategoryFull ? NOB_RED : (isNobCategoryPartial ? NOB_YELLOW : (gRemaining >= 0 ? OK_GREEN : NOB_RED));
                        var gTextColor = isNobCategoryFull ? NOB_RED : (isNobCategoryPartial ? NOB_YELLOW : '');

                        rows += '<tr class="bp-group-row" data-target="' + groupClass + '" style="cursor:pointer;">' +
                            '<td colspan="3" style="font-weight:700;' + (gTextColor ? 'color:' + gTextColor + ';' : '') + '">' +
                                '<i class="fas fa-chevron-right mr-2 bp-caret" style="font-size:10px;transition:transform .15s;display:inline-block;"></i>' +
                                acc + ' <span class="badge badge-secondary ml-1" style="font-size:9px;">' + items.length + '</span>' +
                                (isNobCategoryFull ? nobBadge : '') +
                            '</td>' +
                            '<td class="text-right" style="font-weight:700;' + (gTextColor ? 'color:' + gTextColor + ';' : '') + '">' + moneyFmt(gBudget) + '</td>' +
                            '<td class="text-right" style="font-weight:700;' + (gTextColor ? 'color:' + gTextColor + ';' : '') + '">' + moneyFmt(gUsed) + '</td>' +
                            '<td class="text-right" style="font-weight:700;color:' + gColor + ';">' + moneyFmt(gRemaining) + '</td>' +
                        '</tr>';

                        items.forEach(function (det, ii) {
                            var isNobItem = det.used > det.total;
                            var remColor = isNobItem ? NOB_RED : (det.remaining >= 0 ? OK_GREEN : NOB_RED);
                            var rowColor = isNobItem ? 'color:' + NOB_RED + ';' : '';
                            var qtyUnit = det.qty + (det.unit ? ' ' + det.unit : '');
                            var crDetails = det.cr_details || [];
                            var crBlockClass = groupClass + '-cr-' + ii;

                            var crToggleBtn = crDetails.length
                                ? '<button type="button" class="btn btn-outline-secondary prj-cr-toggle" data-target="' + crBlockClass + '-row" style="font-size:9px;padding:0 6px;line-height:1.5;white-space:nowrap;flex-shrink:0;">' +
                                    crDetails.length + ' CR <i class="fas fa-chevron-down ml-1" style="font-size:8px;"></i></button>'
                                : '';

                            rows += '<tr class="' + groupClass + '" style="display:none;' + rowColor + '">' +
                                '<td class="bp-indent prj-col-wide">' +
                                    '<div class="d-flex justify-content-between align-items-center" style="gap:8px;">' +
                                        '<span>' + (det.deskripsi || '-') + (isNobItem ? nobBadge : '') + '</span>' +
                                        crToggleBtn +
                                    '</div>' +
                                '</td>' +
                                '<td class="text-right">' + det.amount_formatted + '</td>' +
                                '<td class="text-center">' + qtyUnit + '</td>' +
                                '<td class="text-right"><strong>' + det.total_formatted + '</strong></td>' +
                                '<td class="text-right">' + det.used_formatted + '</td>' +
                                '<td class="text-right" style="color:' + remColor + ';">' + det.remaining_formatted + '</td>' +
                            '</tr>';

                            if (crDetails.length) {
                                var crRows = crDetails.map(function (cr) {
                                    var viewBtn = cr.cr_id
                                        ? '<a href="/budget-request?open=' + cr.cr_id + '" target="_blank" class="btn btn-info" style="padding:1px 6px;font-size:10px;" title="View CR Detail"><i class="fas fa-eye"></i></a>'
                                        : '';
                                    return '<tr>' +
                                        '<td class="text-nowrap">' + cr.cr_date_formatted + '</td>' +
                                        '<td>' + (cr.notes || '-') + '</td>' +
                                        '<td class="text-nowrap">' + cr.beneficiary + '</td>' +
                                        '<td class="text-right text-nowrap">' + cr.unit_price_formatted + '</td>' +
                                        '<td class="text-center text-nowrap">' + cr.qty + '</td>' +
                                        '<td class="text-right text-nowrap"><strong>' + cr.amount_formatted + '</strong></td>' +
                                        '<td class="text-center text-nowrap">' + viewBtn + '</td>' +
                                    '</tr>';
                                }).join('');
                                var crTableHtml = '<table class="table table-sm table-bordered mb-0" style="font-size:11px;">' +
                                    '<thead style="background:var(--surface-2);"><tr>' +
                                        '<th scope="col" class="text-nowrap" style="width:1%;">Date</th><th scope="col">Description</th><th scope="col" class="text-nowrap" style="width:1%;">Beneficiary</th>' +
                                        '<th scope="col" class="text-right text-nowrap" style="width:1%;">Price</th><th scope="col" class="text-center text-nowrap" style="width:1%;">Qty</th>' +
                                        '<th scope="col" class="text-right text-nowrap" style="width:1%;">Total</th><th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>' +
                                    '</tr></thead>' +
                                    '<tbody>' + crRows + '</tbody>' +
                                '</table>';

                                rows += '<tr class="' + crBlockClass + '-row ' + groupClass + '-crall" style="display:none;">' +
                                    '<td colspan="6" style="background:var(--surface-2);padding:10px 14px;">' + crTableHtml + '</td>' +
                                '</tr>';
                            }
                        });
                    });
                } else {
                    rows = '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada detail budget.</td></tr>';
                }

                var tableHtml = '<div class="table-responsive"><table class="table table-bordered prj-tab-table mb-0">' +
                    '<thead style="background:var(--surface-2);"><tr>' +
                        '<th scope="col" class="prj-col-wide">Chart of Account</th>' +
                        '<th scope="col" class="text-right">Price</th>' +
                        '<th scope="col" class="text-center">Qty</th>' +
                        '<th scope="col" class="text-right">Budget</th>' +
                        '<th scope="col" class="text-right">Used</th>' +
                        '<th scope="col" class="text-right">Variance</th>' +
                    '</tr></thead><tbody>' + rows + '</tbody></table></div>';

                $('#prjBudgetBody').html(summaryHtml + tableHtml);
            })();

            // Tab Access — bagan organisasi (head → manager → supervisor saja)
            (function renderPrjAccessTab() {
                $('#prjUserCount').text(d.access_users.length);

                var agBadges = (d.access_groups || []).length
                    ? (d.access_groups || []).map(function (name) {
                        return '<span class="badge badge-info px-2 py-1 mr-1 mb-1" style="font-size:10px;">' + name + '</span>';
                    }).join('')
                    : '<span class="text-muted" style="font-size:12px;">Belum ada Access Group yang di-tag.</span>';
                var agBlock = '<div class="mb-3"><strong style="font-size:12px;">Access Group PO ini:</strong><div class="mt-1">' + agBadges + '</div></div>';

                var tiers = [
                    { key: 'head',       label: 'Head',       color: '#3c3c45' },
                    { key: 'manager',    label: 'Manager',    color: 'var(--info)' },
                    { key: 'supervisor', label: 'Supervisor', color: '#0891B2' },
                ];
                var byRole = {};
                (d.access_users || []).forEach(function (u) {
                    var r = (u.role || '').toLowerCase();
                    (byRole[r] = byRole[r] || []).push(u);
                });

                var tierHtml = tiers.map(function (t) {
                    var users = byRole[t.key] || [];
                    if (!users.length) return '';
                    var nodes = users.map(function (u) {
                        return '<div class="prj-org-node">' +
                            '<span class="role" style="background:' + t.color + '1a;color:' + t.color + ';">' + t.label + '</span>' +
                            '<div class="nm">' + (u.name || '-') + '</div>' +
                            '<div class="grp">' + (u.access_group || '-') + '</div>' +
                        '</div>';
                    }).join('');
                    return '<div class="prj-org-tier">' + nodes + '</div>';
                }).filter(Boolean).join('');

                if (!tierHtml) {
                    $('#prjUserBody').html(agBlock + '<p class="text-muted py-3 mb-0">Tidak ada Head / Manager / Supervisor dengan akses ke PO ini.</p>');
                    return;
                }
                $('#prjUserBody').html(agBlock + '<div class="prj-org">' + tierHtml + '</div>');
            })();

            // Edit button
            var canEdit = (d.status === 'DRAFT' || d.status === 'SUBMITTED' || d.status === 'WAITING_PO');
            if (canEdit) {
                $('#prjEditBtn').attr('href', '/project/' + d.id + '/edit').show();
            } else {
                $('#prjEditBtn').hide();
            }

            // Tab Attachment — tabel rapat, aksi cukup ikon mata
            function renderPrjAttTable(items) {
                var rows = items.map(function (a, i) {
                    var url = '/storage/' + a.path;
                    var typeLabel = a.is_image ? 'IMG' : (a.is_pdf ? 'PDF' : 'DOC');
                    var typeBadge = a.is_image ? 'info' : (a.is_pdf ? 'danger' : 'secondary');
                    var viewBtn = a.is_image
                        ? '<button aria-label="View" type="button" class="btn btn-info btn-xs prj-att-view-img" data-full="' + url + '" data-name="' + (a.title || '') + '" title="View"><i class="fas fa-eye"></i></button>'
                        : '<a href="' + url + '" target="_blank" rel="noopener" class="btn btn-info btn-xs" title="View"><i class="fas fa-eye"></i></a>';
                    return '<tr>' +
                        '<td class="text-center" style="width:1%;">' + (i + 1) + '</td>' +
                        '<td class="prj-col-wide">' + (a.title || a.file_name || 'File') + '</td>' +
                        '<td class="text-center" style="width:1%;"><span class="badge badge-' + typeBadge + '" style="font-size:9px;">' + typeLabel + '</span></td>' +
                        '<td class="text-center" style="width:1%;">' + viewBtn + '</td>' +
                    '</tr>';
                }).join('');
                return '<div class="table-responsive mb-2"><table class="table table-bordered prj-tab-table mb-0">' +
                    '<thead style="background:var(--surface-2);"><tr>' +
                        '<th scope="col" class="text-center">No</th>' +
                        '<th scope="col" class="prj-col-wide">Judul</th>' +
                        '<th scope="col" class="text-center">Tipe</th>' +
                        '<th scope="col" class="text-center">Aksi</th>' +
                    '</tr></thead><tbody>' + rows + '</tbody></table></div>';
            }
            var totalAtt = 0;
            var attHtml = '';
            var attTypes = { general: 'General', closing: 'MoM Closing' };
            var attBadge = { general: 'secondary', closing: 'dark' };
            Object.keys(d.attachments).forEach(function(type) {
                var items = d.attachments[type];
                if (!items || !items.length) return;
                totalAtt += items.length;
                attHtml += '<div class="mb-1 mt-2"><span class="badge badge-' + (attBadge[type]||'secondary') + '" style="font-size:10px;">' + (attTypes[type]||type) + '</span></div>';
                attHtml += renderPrjAttTable(items);
            });
            $('#prjAttCount').text(totalAtt);
            $('#prjAttBody').html(attHtml || kosongRingkas('Belum ada lampiran.'));

            // Tab Log — gaya chat: log dari user aktif di kanan, lainnya di kiri
            var logHtml = '';
            (d.logs || []).slice().reverse().forEach(function (l) {
                var mine = l.user_id && currentUserId && l.user_id === currentUserId;
                var actor = l.actor || 'System';
                var initials = actor.trim().split(/\s+/).map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase() || '?';
                logHtml += '<div class="prj-chat-row ' + (mine ? 'mine' : '') + '">' +
                    '<div class="prj-chat-av">' + initials + '</div>' +
                    '<div>' +
                        '<div class="prj-chat-meta">' + (mine ? '' : '<strong>' + actor + '</strong> · ') + l.date + '</div>' +
                        '<div class="prj-chat-bubble">' +
                            '<span class="badge badge-' + l.action_color + ' px-2 py-1" style="font-size:9px;">' + l.action_label + '</span>' +
                            (l.status_from && l.status_to ? ' <span style="font-size:10px;opacity:.75;">' + l.status_from + ' → ' + l.status_to + '</span>' : '') +
                            (l.notes ? '<div class="prj-chat-note">' + l.notes + '</div>' : '') +
                        '</div>' +
                    '</div>' +
                '</div>';
            });
            $('#prjLogBody').html(logHtml || kosongRingkas('Belum ada riwayat persetujuan.'));

            var pendingTab = window._prjPendingTab;
            if (pendingTab && $('#prjDetailTabs a[href="' + pendingTab + '"]').length) {
                $('#prjDetailTabs a[href="' + pendingTab + '"]').tab('show');
            } else {
                $('#prjDetailTabs a:first').tab('show');
            }
            window._prjPendingTab = null;
            $('#projectDetailLoading').hide();
            $('#projectDetailContent').show();

        }).fail(function () {
            $('#projectDetailLoading').html(
                '<div class="ts-empty"><span class="ts-empty-ic" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></span>' +
                '<p class="ts-empty-title">Detail gagal dimuat</p>' +
                '<p class="ts-empty-msg">Periksa koneksi Anda, lalu buka kembali Purchase Order ini.</p></div>'
            );
            if (window.toast) toast.error('Gagal memuat', 'Detail Purchase Order tidak bisa ditampilkan.');
        });
    }

    $(document).on('click', '.btn-detail-project, .po-code-link', function () {
        openProjectDetail($(this).data('id'));
    });
    $(document).on('click', '.po-status-link', function () {
        openProjectDetail($(this).data('id'), '#prjPaneLogs');
    });
    $(document).on('click', '.po-budget-link', function () {
        openProjectDetail($(this).data('id'), '#prjPaneBudgets');
    });
    $(document).on('click', '.po-value-link', function () {
        openProjectDetail($(this).data('id'), '#prjPanePoNumbers');
    });
    $(document).on('click', '.po-cost-link', function () {
        openProjectDetail($(this).data('id'), '#prjPaneCost');
    });
    $(document).on('click', '.po-invoice-link', function () {
        openProjectDetail($(this).data('id'), '#prjPaneInvoice');
    });
    $(document).on('click', '.po-summary-link', function () {
        openProjectDetail($(this).data('id'), '#prjPaneInfo');
    });

    $('#mdlDetailProject').on('hidden.bs.modal', function () {
        $('#projectDetailContent').hide();
        $('#projectDetailLoading').show();
        $('#prjNotesCollapse, #prjBastCollapse, #prjClosedCollapse').hide();
        $('#prjNotesToggleIcon, #prjBastToggleIcon, #prjClosedToggleIcon').removeClass('rotated');
    });

    // Auto-buka modal detail bila diakses dengan ?open=<id> (mis. dari To Do).
    // Parameternya dibuang dari URL setelah dipakai, supaya reload / redirect
    // back() tidak membuka modal detail itu lagi.
    (function () {
        var params = new URLSearchParams(window.location.search);
        var openId = params.get('open');
        if (!openId) return;

        params.delete('open');
        var sisa = params.toString();
        window.history.replaceState({}, '',
            window.location.pathname + (sisa ? '?' + sisa : '') + window.location.hash);

        openProjectDetail(openId);
    })();

});
</script>
@endpush
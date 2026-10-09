@push('styles')
<style>
.master-page .custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.master-page .custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.master-page .toolbar-row         { display:flex; align-items:center; gap:8px; }
.master-page .toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; margin-bottom:0; }
.master-page .toolbar-input input { width:200px; border-radius:var(--radius-sm); }
.master-page .table td, .master-page .table th { vertical-align:middle !important; }
.master-page table.dataTable { width:100% !important; }
.master-page .dataTables_wrapper .row:last-child { margin-top:18px; align-items:center; }
.master-page .dataTables_info { padding-top:0 !important; font-size:14px; color:var(--text-faint); }
.master-page .dataTables_length { display:flex; align-items:center; margin-left:18px; }
.master-page .dataTables_length label { display:flex; align-items:center; gap:10px; margin-bottom:0; font-size:14px; font-weight:600; color:var(--text-faint); }
.master-page .dataTables_paginate .paginate_button { border-radius:var(--radius-sm) !important; margin:0 2px; }
.master-page .dataTables_wrapper .pagination .page-item.active .page-link {
    background:var(--accent) !important; border-color:var(--accent-dark) !important; color:#fff !important; font-weight:700 !important;
}
.master-page .dataTables_wrapper .pagination .page-link { color:var(--accent) !important; }
.master-page .master-tabs .nav-link { font-size:13px; font-weight:600; color:var(--text-dim); }
.master-page .master-tabs .nav-link.active { background:var(--accent); color:#fff; }
#mdlMaster .master-form-section {
    font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
    color:var(--text-faint); border-bottom:1px solid var(--border);
    padding-bottom:6px; margin:6px 0 14px;
}
#mdlMaster .master-form-section:not(.is-first) { margin-top:10px; }
#mdlMaster .form-group { margin-bottom:14px; }
#mdlMaster .tss-btn { height:38px; font-weight:400; }
/* Kolom selebar isinya; kolom col-trunc dipotong dan melebar saat baris di-hover. */
.master-compact th, .master-compact td { white-space:nowrap; width:1%; }
.master-compact th.col-trunc, .master-compact td.col-trunc { width:auto; }
.master-compact .cell-trunc { display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle; }
.master-compact tbody tr:hover .cell-trunc { white-space:normal; overflow:visible; }
.site-dw-tabs .nav-link { font-size:13px; font-weight:600; color:var(--text-dim); padding:6px 12px; }
.site-dw-tabs .nav-link.active { color:var(--accent); }
.site-dw-map { border:1px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; height:220px; }
.site-dw-map iframe { width:100%; height:100%; border:0; display:block; }
.site-dw-map img { width:100%; height:100%; object-fit:cover; display:block; }
.site-dw-pic { border:1px solid var(--border); border-radius:var(--radius-sm); padding:8px 10px; margin-bottom:8px; }
.master-page .detail-table th { width:35%; color:var(--text-dim); font-weight:600; }
@media (max-width: 767.98px) {
    .master-page .card-header { flex-direction:column; gap:10px; align-items:stretch !important; }
    .master-page .card-header .ml-auto { margin-left:0 !important; flex-wrap:wrap; }
    .master-page .custom-toolbar,
    .master-page .custom-toolbar-left { flex-direction:column !important; align-items:stretch !important; width:100%; }
    .master-page .custom-toolbar-right,
    .master-page .custom-toolbar-right .btn,
    .master-page .toolbar-row,
    .master-page .toolbar-input input { width:100%; }
    .master-page .toolbar-input { flex:1; min-width:0; }
}
</style>
@endpush

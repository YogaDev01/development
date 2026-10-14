@push('styles')
<style>
.master-page .custom-toolbar      { display:flex; align-items:flex-start; gap:15px; flex-wrap:wrap; }
.master-page .custom-toolbar-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.master-page .toolbar-row         { display:flex; align-items:center; gap:8px; }
.master-page .toolbar-label       { min-width:50px; font-weight:600; color:var(--text-dim); white-space:nowrap; margin-bottom:0; }
.master-page .toolbar-input input { width:200px; border-radius:var(--radius-sm); }
.master-page .table td, .master-page .table th { vertical-align:middle !important; }
.master-page table.dataTable { width:100% !important; }
/*
| Tabel ringkas (master-compact) selnya nowrap, jadi lebarnya bisa melebihi
| kartu — mis. saat halaman di-zoom. Lebar 100% yang dipaksa membuat kolom
| terakhir terpotong; di sini tabel boleh melebar dan wadah .table-responsive
| yang menggesernya, dengan scrollbar yang terlihat.
*/
.master-page .table-responsive { overflow-x:auto; }
.master-page table.master-compact { width:auto !important; min-width:100%; }
.master-page .table-responsive::-webkit-scrollbar { height:8px; }
.master-page .table-responsive::-webkit-scrollbar-thumb { background:var(--border-strong); border-radius:4px; }
.master-page .table-responsive::-webkit-scrollbar-track { background:var(--border-soft); }
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
/*
| Lebar kolom dibiarkan mengikuti isinya (auto layout), bukan dipaksa `width:1%`
| dengan satu kolom `width:auto` yang menyerap seluruh sisa ruang — itu yang
| membuat kolom bertanda `truncate` (mis. Nama Site) jauh lebih lebar dari
| kolom lain saat isi tabel lebih sempit dari kartunya. Kolom col-trunc tetap
| dipotong dengan elipsis dan melebar saat barisnya di-hover.
*/
.master-compact th, .master-compact td { white-space:nowrap; }
.master-compact .cell-trunc { display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:middle; }
.master-compact tbody tr:hover .cell-trunc { white-space:normal; overflow:visible; }
/*
| Nilai kolom yang bisa diklik untuk membuka drawer (`drawer_link`).
| Gayanya mengikuti .cr-number-btn di daftar Cost Request: tulisan tetap gelap,
| saat disorot jadi putih di atas latar charcoal.
*/
.cell-link {
    display:inline-block; background:transparent; border:none; padding:2px 8px; border-radius:var(--radius-sm);
    color:var(--text); font:inherit; line-height:1.4; text-align:left; cursor:pointer;
    transition:background-color .12s ease, color .12s ease;
}
.cell-link:hover, .cell-link:focus { background:var(--charcoal); color:#fff; outline:none; }
/* Keterangan kecil di bawah nilai kolom (lihat `sub` di master()['columns']). */
.cell-sub { font-size:11px; color:var(--text-faint); line-height:1.3; margin-top:2px; }
/* Kolom ringkas yang rincian lengkapnya muncul saat disorot (title). */
.cell-hint { border-bottom:1px dotted var(--border-strong); cursor:help; }
/* Tab drawer site tetap satu baris walau labelnya panjang (Preventive
   Maintenance); bila tidak muat, barisnya digeser mendatar. */
.site-dw-tabs { flex-wrap:nowrap; overflow-x:auto; overflow-y:hidden; }
.site-dw-tabs::-webkit-scrollbar { height:4px; }
.site-dw-tabs::-webkit-scrollbar-thumb { background:var(--border-strong); border-radius:2px; }
.site-dw-tabs .nav-item { flex:0 0 auto; }
.site-dw-tabs .nav-link { font-size:13px; font-weight:600; color:var(--text-dim); padding:6px 12px; white-space:nowrap; }
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

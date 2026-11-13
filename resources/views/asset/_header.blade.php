{{--
    Header bersama halaman Asset Management: judul, keterangan, remah jejak,
    tombol aksi (slot $actions), daftar galat validasi, dan gaya kecil modul.
    Param: $title, $desc (opsional), $actions (HTML opsional).
--}}
@once
@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
<style>
    /* Panel & tabel mengikuti kartu x-tw.card / tabel Cost Request (token radius-md, shadow-xs, surface-2). */
    .ast-panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-xs); margin-bottom: 16px; overflow: hidden; }
    .ast-panel-h { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-bottom: 1px solid var(--border); font-weight: 600; color: var(--text); font-size: 13px; }
    .ast-panel-h .ast-r { margin-left: auto; display: flex; gap: 6px; }
    .ast-panel-b { padding: 14px; }
    .ast-table { width: 100%; font-size: 12.5px; margin: 0; }
    .ast-table th { font-size: 12px; font-weight: 600; color: var(--text-dim); background: var(--surface-2); white-space: nowrap; padding: 9px 12px; border-bottom: 1px solid var(--border-strong); }
    .ast-table td { padding: 9px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .ast-table tbody tr:hover td { background: var(--surface-2); }
    .ast-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .ast-sub { font-size: 11px; color: var(--text-faint); }
    .ast-code { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 12px; }
    .ast-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 12px 14px; }
    .ast-toolbar .form-control { width: auto; min-width: 140px; }
    .ast-bars > div { display: grid; grid-template-columns: 130px 1fr 44px; gap: 8px; align-items: center; font-size: 12px; margin-bottom: 8px; }
    .ast-bar { height: 8px; background: var(--bg); border-radius: 4px; overflow: hidden; }
    .ast-bar span { display: block; height: 100%; background: var(--primary); }
    .ast-tabs { border-bottom: 1px solid var(--border); margin-bottom: 14px; }
    .ast-tabs .nav-link { font-size: 13px; color: var(--text-dim); }
    .ast-tabs .nav-link.active { color: var(--text); font-weight: 600; }
    @media (max-width: 680px) { .ast-toolbar .form-control { flex: 1 1 45%; min-width: 0; } .ast-bars > div { grid-template-columns: 90px 1fr 36px; } }
</style>
@endpush
@endonce

{{-- Judul dipindah shell ke topbar; kepala halaman mengikuti pola Cost Request. --}}
<div class="tw:sr-only">
    <h1>{{ $title }}</h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="#">Asset Management</a></li>
        <li class="breadcrumb-item active">{{ $title }}</li>
    </ol>
</div>
<x-tw.page-header class="tw:py-4 tw:mb-4" eyebrow="Asset Management" :title="$title" :description="$desc ?? null">
    @if(!empty($actions))
        <x-slot:actions>
            <div class="d-flex flex-wrap" style="gap:6px;">{!! $actions !!}</div>
        </x-slot:actions>
    @endif
</x-tw.page-header>

@if($errors->any())
    <div class="alert alert-danger py-2" style="font-size:13px;">
        <ul class="mb-0 pl-3">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

{{--
    Header bersama halaman Asset Management: judul, keterangan, remah jejak,
    tombol aksi (slot $actions), daftar galat validasi, dan gaya kecil modul.
    Param: $title, $desc (opsional), $actions (HTML opsional).
--}}
@once
@push('styles')
<style>
    .ast-panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); margin-bottom: 16px; }
    .ast-panel-h { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-bottom: 1px solid var(--border); font-weight: 600; color: var(--text); font-size: 13px; }
    .ast-panel-h .ast-r { margin-left: auto; display: flex; gap: 6px; }
    .ast-panel-b { padding: 14px; }
    .ast-table { width: 100%; font-size: 12.5px; margin: 0; }
    .ast-table th { font-size: 11px; text-transform: uppercase; letter-spacing: .3px; color: var(--text-dim); background: var(--bg); white-space: nowrap; padding: 8px 10px; border-bottom: 1px solid var(--border); }
    .ast-table td { padding: 7px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .ast-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .ast-sub { font-size: 11px; color: var(--text-faint); }
    .ast-code { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 12px; }
    .ast-toolbar { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px 14px; border-bottom: 1px solid var(--border); }
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

<div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap" style="gap:10px;">
    <div>
        <h1 class="m-0" style="font-size:20px;font-weight:700;color:var(--text);">{{ $title }}</h1>
        @if(!empty($desc))
            <div style="color:var(--text-faint);font-size:13px;margin-top:2px;">{{ $desc }}</div>
        @endif
    </div>
    <div class="d-flex flex-wrap" style="gap:6px;">
        {!! $actions ?? '' !!}
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger py-2" style="font-size:13px;">
        <ul class="mb-0 pl-3">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

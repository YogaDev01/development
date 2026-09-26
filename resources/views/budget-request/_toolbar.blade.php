{{-- Toolbar satu tab (search, filter, kolom, export, tambah). Diinisialisasi JS saat tabelnya dimuat. --}}
@php
    $hideable = [
        'tanggal'  => 'Tanggal',
        'paid_at'  => 'Tanggal Paid',
        'pengaju'  => 'Pengaju',
        'penerima' => 'Penerima',
    ];
    if ($tab === 'ca') {
        $hideable['realisasi'] = 'Settlement';
        $hideable['refund']    = 'Refund';
    }
    $canAdd = Auth::user()->hasRole(['superadmin','manager','supervisor']);
@endphp

<div class="custom-toolbar mb-3 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
    <div class="custom-toolbar-left d-flex align-items-center flex-wrap" style="gap:8px;">
        <div class="toolbar-row">
            <span class="toolbar-label">Cari :</span>
            <div class="toolbar-input cr-search-box"></div>
        </div>
        <button aria-label="Clear Filter" type="button" class="btn btn-outline-secondary btn-sm cr-btn-clear" title="Clear Filter">
            <i class="fas fa-eraser"></i>
        </button>
        <div class="cr-row-filters d-flex flex-wrap" style="gap:8px;"></div>
    </div>
    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
        <div class="dropdown cr-col-dropdown">
            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-toggle="dropdown">
                <i class="fas fa-columns mr-1"></i> Kolom
            </button>
            <div class="dropdown-menu dropdown-menu-right p-2" style="min-width:200px;max-height:340px;overflow-y:auto;">
                @foreach($hideable as $key => $label)
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input cr-col-vis-toggle" id="crColVis_{{ $tab }}_{{ $key }}" data-column="{{ $key }}">
                    <label class="custom-control-label" for="crColVis_{{ $tab }}_{{ $key }}" style="font-size:12px;">{{ $label }}</label>
                </div>
                @endforeach
            </div>
        </div>
        <a href="{{ url('/budget-request/export') }}" class="btn btn-info btn-sm cr-btn-export" data-tab="{{ $tab }}">
            <i class="fas fa-file-excel mr-1"></i> Export Excel
        </a>
        @if($canAdd && $tab !== 'db')
        <a href="{{ url('/budget-request/create') }}" class="btn btn-success btn-sm">
            <i class="fas fa-plus mr-1"></i> Tambah Request
        </a>
        @endif
    </div>
</div>

{{-- Toolbar satu tab (search, filter, kolom, export, tambah). Diinisialisasi JS saat tabelnya dimuat. --}}
@php
    $hideable = [
        'tanggal'  => 'Tanggal',
        'paid_at'  => 'Tanggal Paid',
        'pengaju'  => 'Pengaju',
        'pic'      => 'PIC & Penerima',
    ];
    if ($tab === 'ca') {
        $hideable['realisasi'] = 'Settlement';
        $hideable['refund']    = 'Refund';
        $hideable['claim']     = 'Expense Claim';
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
        <div class="cr-pop cr-filter-dropdown">
            <button type="button" class="btn btn-outline-secondary btn-sm cr-pop-toggle cr-filter-toggle"
                    aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>
            <div class="cr-pop-menu cr-filter-panel p-3" style="min-width:420px;">
                <div class="cr-filter-title">FILTER</div>
                {{-- Diisi TesysColumnFilter: satu tombol per field, masing-masing
                     membuka daftar bercentang dengan kotak pencarian. --}}
                <div class="cr-row-filters"></div>
                {{-- Tanpa tombol "Terapkan": filter langsung berlaku begitu nilainya
                     dicentang, jadi tombol itu hanya menambah satu klik. --}}
                <div class="cr-filter-foot">
                    <button type="button" class="btn btn-primary btn-sm cr-filter-reset">
                        <i class="fas fa-undo mr-1"></i>Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="cr-pop cr-col-dropdown">
            <button type="button" class="btn btn-outline-secondary btn-sm cr-pop-toggle">
                <i class="fas fa-columns mr-1"></i> Kolom
            </button>
            <div class="cr-pop-menu p-2" style="min-width:200px;max-height:340px;overflow-y:auto;">
                @foreach($hideable as $key => $label)
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input cr-col-vis-toggle" id="crColVis_{{ $tab }}_{{ $key }}" data-column="{{ $key }}">
                    <label class="custom-control-label" for="crColVis_{{ $tab }}_{{ $key }}" style="font-size:12px;">{{ $label }}</label>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="custom-toolbar-right d-flex flex-wrap" style="gap:8px;">
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

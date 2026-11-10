{{-- Toolbar tabel CR (search, filter, kolom, export, bulk). Diinisialisasi JS saat tabelnya dimuat.
     "Tambah Request" kini ada di kepala halaman (index.blade.php), bukan di toolbar. --}}
@php
    // Bawaan tampil/sembunyi tiap kolom ada di JS (crVisDefault, index.blade.php).
    $hideable = [
        'type'       => 'Tipe',
        'tanggal'    => 'Tanggal',
        'paid_at'    => 'Tanggal Paid',
        'pengaju'    => 'Requestor',
        'pic'        => 'PIC',
        'request'    => 'Request',
        'realisasi'  => 'Settlement',
        'refund'     => 'Refund',
        'claim'      => 'Expense Claim',
        'cost'       => 'Cost',
        'close_date' => 'Close Date',
    ];
    // Sama dengan _table: bar bulk hanya untuk peran yang berwenang di tahap pengajuan.
    $canBulk = Auth::user()->hasRole(
        array_merge(['superadmin'], ...array_values(\App\Models\BudgetRequest::bulkStageRoles()))
    );
@endphp

<div class="cr-toolbar tw:mb-3 tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
    <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2 tw:max-sm:w-full">
        {{-- Kotak cari diisi JS (input pencarian dibuat saat tabel dimuat). Label "Cari :" lama sudah disembunyikan
             CSS di semua lebar; nama aksesibelnya ada di aria-label input. --}}
        <div class="cr-search-box tw:max-sm:w-full"></div>
        <x-tw.button variant="ghost" size="sm" class="cr-btn-clear tw:max-sm:min-h-12" title="Clear Filter" aria-label="Clear Filter">
            <i class="fas fa-eraser" aria-hidden="true"></i>
        </x-tw.button>
        <div class="cr-pop cr-filter-dropdown">
            <x-tw.button variant="secondary" size="sm" class="cr-pop-toggle cr-filter-toggle tw:max-sm:min-h-12"
                    aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-filter" aria-hidden="true"></i> Filter
            </x-tw.button>
            <div class="cr-pop-menu cr-filter-panel p-3" style="min-width:420px;">
                <div class="cr-filter-title">FILTER</div>
                {{-- Diisi TesysColumnFilter: satu tombol per field, masing-masing
                     membuka daftar bercentang dengan kotak pencarian. --}}
                <div class="cr-row-filters"></div>
                {{-- Tanpa tombol "Terapkan": filter langsung berlaku begitu nilainya
                     dicentang, jadi tombol itu hanya menambah satu klik. --}}
                <div class="cr-filter-foot">
                    <x-tw.button size="sm" class="cr-filter-reset">
                        <i class="fas fa-undo" aria-hidden="true"></i> Reset
                    </x-tw.button>
                </div>
            </div>
        </div>

        <div class="cr-pop cr-col-dropdown">
            <x-tw.button variant="secondary" size="sm" class="cr-pop-toggle tw:max-sm:min-h-12">
                <i class="fas fa-columns" aria-hidden="true"></i> Kolom
            </x-tw.button>
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
    <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2 tw:max-sm:w-full">
        @if($canBulk)
        {{-- Muncul hanya saat ada baris dicentang. Diisi/dikendalikan JS (index.blade.php):
             kelas tampil/sembunyi Bootstrap pada bar ini DITOGGLE JS, jangan diganti kelas Tailwind. --}}
        <div class="cr-bulk-bar d-none tw:flex-wrap tw:items-center tw:gap-2 tw:rounded-md tw:border tw:border-accent-soft tw:bg-accent-tint tw:py-1 tw:pl-3 tw:pr-1" data-tab="{{ $tab }}">
            <span class="cr-bulk-info tw:font-label tw:tabular-nums"></span>
            <x-tw.button size="sm" class="cr-bulk-approve">
                <i class="fas fa-check" aria-hidden="true"></i><span class="cr-bulk-approve-label">Approve</span>
            </x-tw.button>
            <x-tw.button variant="danger" size="sm" class="cr-bulk-reject">
                <i class="fas fa-times" aria-hidden="true"></i><span>Reject</span>
            </x-tw.button>
            <x-tw.button variant="ghost" size="sm" class="cr-bulk-clear" title="Batalkan pilihan" aria-label="Batalkan pilihan">
                <i class="fas fa-times" aria-hidden="true"></i>
            </x-tw.button>
        </div>
        @endif
        <x-tw.button href="{{ url('/budget-request/export') }}" variant="secondary" size="sm" class="cr-btn-export tw:max-sm:min-h-12" data-tab="{{ $tab }}">
            <i class="fas fa-file-excel" aria-hidden="true"></i> Export Excel
        </x-tw.button>
    </div>
</div>

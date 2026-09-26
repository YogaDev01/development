{{-- Isi tabel satu tab (fragment HTML, dimuat AJAX). Kerangka tab ada di index.blade.php. --}}
@php
    $tid    = 'tbl' . strtoupper($tab);      // tblCA / tblCP / tblDB
    $isDb   = $tab === 'db';
    $mode   = $tab;                           // 'ca' | 'cp' (dipakai _rows)
    $canAdd = Auth::user()->hasRole(['superadmin','manager','supervisor']);
@endphp

@if($truncated)
<div class="alert alert-warning py-2 px-3 mb-3" style="font-size:12.5px;">
    <i class="fas fa-exclamation-triangle mr-1"></i>
    Menampilkan <strong>{{ number_format($maxRows, 0, ',', '.') }}</strong> Cost Request terbaru dari total
    <strong>{{ number_format($total, 0, ',', '.') }}</strong> pada rentang tanggal ini.
    Persempit rentang tanggal, atau gunakan <strong>Export Excel</strong> untuk data lengkap.
</div>
@endif

<div class="cr-table-wrap">
    <table id="{{ $tid }}" class="table table-bordered mb-0 cr-tbl" data-tab="{{ $tab }}">
        <thead class="bg-light">
            <tr>
                <th scope="col" width="4%" class="all">No</th>
                <th scope="col" data-col="code" class="all text-nowrap cr-col-code" style="width:1%;">Code</th>
                <th scope="col" data-col="desc" class="all cr-col-desc">Description</th>
                @if($isDb)
                <th scope="col" data-col="jenis" class="text-nowrap" style="width:1%;">Jenis</th>
                @endif
                <th scope="col" data-col="tanggal" class="text-nowrap" style="width:1%;">Tanggal</th>
                <th scope="col" data-col="paid_at" class="text-nowrap" style="width:1%;">Tanggal Paid</th>
                <th scope="col" data-col="pengaju" class="text-nowrap" style="width:1%;">Requestor</th>
                <th scope="col" data-col="penerima" class="text-nowrap" style="width:1%;">Beneficiary</th>
                {{-- PIC = pemegang beneficiary. Ditaruh persis di sebelah Beneficiary karena
                     keduanya menjawab "uangnya untuk siapa" pada dua tingkat yang berbeda. --}}
                <th scope="col" data-col="pic" class="text-nowrap" style="width:1%;">PIC</th>
                @if($isDb)
                <th scope="col" class="text-right text-nowrap" style="width:1%;" data-col="cost">Cost</th>
                @else
                <th scope="col" class="text-right text-nowrap" style="width:1%;" data-col="request">Request</th>
                @endif
                @if($tab === 'ca')
                <th scope="col" class="text-right text-nowrap" style="width:1%;" data-col="realisasi">Settlement</th>
                <th scope="col" class="text-right text-nowrap" style="width:1%;" data-col="refund">Refund</th>
                {{-- Kebalikan Refund: settlement melebihi pengajuan, jadi masih ada
                     kekurangan bayar yang ditagihkan balik. --}}
                <th scope="col" class="text-right text-nowrap" style="width:1%;" data-col="claim">Expense Claim</th>
                @endif
                <th scope="col" data-col="status" class="text-nowrap" style="width:1%;">Status</th>
                @unless($isDb)
                <th scope="col" class="text-center all action-column" style="width:1%;">Action</th>
                @endunless
            </tr>
        </thead>
        <tbody>
            @if($isDb)
                @include('budget-request._rows_db', ['rows' => $rows])
            @else
                @include('budget-request._rows', ['rows' => $rows, 'mode' => $mode])
            @endif
        </tbody>
        <tfoot>
            <tr style="background:var(--surface-2);">
                @if($tab === 'ca')
                <th colspan="8" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                <th class="text-right text-nowrap cr-foot-amount" style="font-size:12px;color:var(--info);"></th>
                <th class="text-right text-nowrap cr-foot-realisasi" style="font-size:12px;color:var(--success);"></th>
                <th class="text-right text-nowrap cr-foot-var" style="font-size:12px;"></th>
                <th class="text-right text-nowrap cr-foot-claim" style="font-size:12px;"></th>
                <th colspan="2"></th>
                @elseif($tab === 'cp')
                <th colspan="8" class="text-right" style="font-size:12px;">Total (data tampil)</th>
                <th class="text-right text-nowrap cr-foot-amount" style="font-size:12px;color:var(--info);"></th>
                <th colspan="2"></th>
                @else
                <th colspan="9" class="text-right" style="font-size:12px;">Total Cost (data tampil)</th>
                <th class="text-right text-nowrap cr-foot-cost" style="font-size:12px;color:var(--info);"></th>
                <th colspan="1"></th>
                @endif
            </tr>
        </tfoot>
    </table>

    {{-- Keadaan kosong: template (bukan baris tbody) karena DataTables yang merender baris. --}}
    <template class="cr-empty-template">
        <x-empty title="Belum ada Cost Request"
                 message="Belum ada pengajuan pada rentang tanggal dan filter yang sedang dipakai. Coba perlebar rentang tanggalnya, atau buat pengajuan baru.">
            @if($canAdd && ! $isDb)
                <x-slot:action>
                    <a href="{{ url('/budget-request/create') }}" class="ts-action-primary">
                        <i class="fas fa-plus"></i><span class="ts-al">Tambah Request</span>
                    </a>
                </x-slot:action>
            @endif
        </x-empty>
    </template>
</div>

@extends('layouts.app')

@section('title', 'Pengadaan Aset')

@php
    $rp = fn ($v) => number_format((int) $v, 0, ',', '.');
    $today = now()->toDateString();
@endphp

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title' => 'Pengadaan Aset',
        'desc'  => 'Purchase Request aset, penerimaan barang (otomatis jadi aset), dan riwayat perolehan. Pembayaran tetap lewat Cost Request.',
    ])

    <ul class="nav ast-tabs">
        @foreach(['pr' => 'Purchase Request', 'receiving' => 'Penerimaan Aset', 'history' => 'Riwayat Perolehan'] as $k => $l)
            <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('asset.procurement.index', ['tab' => $k]) }}">{{ $l }}</a></li>
        @endforeach
    </ul>

    @if($tab === 'pr')
    <div class="ast-panel">
        <div class="ast-toolbar"><button type="button" class="btn btn-primary btn-sm ml-auto" data-toggle="modal" data-target="#prNew"><i class="fas fa-plus mr-1"></i>Buat Purchase Request</button></div>
        <div class="table-responsive"><table class="ast-table">
            <thead><tr><th>Nomor</th><th>Barang</th><th>Kategori</th><th>Departemen</th><th class="num">Qty</th><th class="num">Diterima</th><th class="num">Estimasi Total</th><th>Dibutuhkan</th><th>Pemohon</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($requests as $pr)
                <tr>
                    <td class="ast-code">{{ $pr->number }}</td>
                    <td style="white-space:normal;">{{ $pr->item_name }}<div class="ast-sub">{{ $pr->justification }}</div>@if($pr->reject_reason)<div class="ast-sub text-danger">Ditolak: {{ $pr->reject_reason }}</div>@endif</td>
                    <td>{{ $pr->category?->name }}</td><td>{{ $pr->department?->name ?? '-' }}</td>
                    <td class="num">{{ $pr->qty }}</td><td class="num">{{ $pr->receivedQty() }}</td>
                    <td class="num">{{ $rp($pr->totalEstimate()) }}</td><td>{{ $pr->needed_date?->format('d M Y') ?? '-' }}</td>
                    <td>{{ $pr->requester?->name }}</td>
                    <td><x-status :status="$pr->status" context="asset" />@if($pr->approver)<div class="ast-sub">{{ $pr->approver->name }}</div>@endif</td>
                    <td class="text-right text-nowrap">
                        @if($pr->status === 'PENDING' && $canManage)
                            <form method="POST" action="{{ route('asset.procurement.pr.approve', $pr) }}" class="d-inline" onsubmit="return confirm('Setujui {{ $pr->number }}?')">@csrf<button class="btn btn-success btn-sm">Setujui</button></form>
                            <button type="button" class="btn btn-outline-danger btn-sm" data-reject-url="{{ route('asset.procurement.pr.reject', $pr) }}" data-reject-title="Tolak {{ $pr->number }}">Tolak</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="11"><x-empty title="Belum ada Purchase Request" message="Ajukan kebutuhan aset baru di sini sebelum pembelian." /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @elseif($tab === 'receiving')
    <div class="ast-panel">
        <div class="ast-toolbar"><span class="ast-sub align-self-center">Setiap unit yang diterima langsung didaftarkan sebagai aset berstatus Available.</span>
            <button type="button" class="btn btn-primary btn-sm ml-auto" data-toggle="modal" data-target="#grNew"><i class="fas fa-plus mr-1"></i>Catat Penerimaan</button></div>
        <div class="table-responsive"><table class="ast-table">
            <thead><tr><th>Nomor</th><th>Tanggal</th><th>Barang</th><th>PR</th><th>Supplier / PO</th><th>Lokasi</th><th class="num">Qty</th><th class="num">Harga Satuan</th><th class="num">Aset Dibuat</th><th>Penerima</th></tr></thead>
            <tbody>
            @forelse($receivings as $gr)
                <tr>
                    <td class="ast-code">{{ $gr->number }}</td><td>{{ $gr->received_date->format('d M Y') }}</td>
                    <td>{{ $gr->item_name }}<div class="ast-sub">{{ $gr->category?->name }}</div></td>
                    <td class="ast-code">{{ $gr->purchaseRequest?->number ?? '-' }}</td>
                    <td>{{ $gr->supplier ?: '-' }}<div class="ast-sub">{{ $gr->po_number }}</div></td>
                    <td>{{ $gr->location?->name }}</td><td class="num">{{ $gr->qty }}</td><td class="num">{{ $rp($gr->unit_cost) }}</td>
                    <td class="num"><a href="{{ route('asset.index', ['q' => $gr->item_name]) }}">{{ $gr->assets_count }}</a></td>
                    <td>{{ $gr->receiver?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="10"><x-empty title="Belum ada penerimaan" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @else
    <div class="ast-panel"><div class="table-responsive"><table class="ast-table">
        <thead><tr><th>Tgl Perolehan</th><th>Kode</th><th>Aset</th><th>Kategori</th><th>Jenis</th><th>Supplier / PO</th><th class="num">Harga Perolehan</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($history as $a)
            <tr>
                <td>{{ $a->acquisition_date?->format('d M Y') }}</td>
                <td>@if($a->trashed())<span class="ast-code">{{ $a->code }}</span> <span class="ast-sub">(dihapus)</span>@else<a href="{{ route('asset.show', $a) }}" class="ast-code">{{ $a->code }}</a>@endif</td>
                <td>{{ $a->name }}</td><td>{{ $a->category?->name }}</td>
                <td>{{ \App\Models\Asset::ACQUISITION_TYPES[$a->acquisition_type] ?? $a->acquisition_type }}</td>
                <td>{{ $a->supplier ?: '-' }}<div class="ast-sub">{{ $a->po_number }}</div></td>
                <td class="num">{{ $rp($a->cost) }}</td><td><x-status :status="$a->status" context="asset" /></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty title="Belum ada perolehan aset" /></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    @endif
</div></div>

<div class="modal fade" id="prNew" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('asset.procurement.pr.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Buat Purchase Request Aset</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body"><div class="form-row">
                <div class="form-group col-md-8"><label class="small font-weight-bold">Nama Barang <span class="text-danger">*</span></label><input name="item_name" class="form-control form-control-sm" maxlength="255" required></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Kategori <span class="text-danger">*</span></label>
                    <select name="asset_category_id" class="form-control form-control-sm" required><option value="">— Pilih —</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-4"><label class="small font-weight-bold">Departemen</label>
                    <select name="department_id" class="form-control form-control-sm"><option value="">—</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-2"><label class="small font-weight-bold">Qty <span class="text-danger">*</span></label><input type="number" name="qty" class="form-control form-control-sm" min="1" max="100" value="1" required></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Estimasi / Unit (Rp) <span class="text-danger">*</span></label><input type="number" name="estimated_unit_cost" class="form-control form-control-sm" min="0" step="1" required></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Dibutuhkan</label><input type="date" name="needed_date" class="form-control form-control-sm" min="{{ $today }}"></div>
                <div class="form-group col-12 mb-0"><label class="small font-weight-bold">Justifikasi <span class="text-danger">*</span></label><textarea name="justification" class="form-control form-control-sm" rows="2" maxlength="2000" required></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Ajukan</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="grNew" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('asset.procurement.receiving.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Catat Penerimaan Aset</h5><button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body"><div class="form-row">
                <div class="form-group col-md-6"><label class="small font-weight-bold">Dari Purchase Request</label>
                    <select name="asset_purchase_request_id" id="grPr" class="form-control form-control-sm"><option value="">— Tanpa PR —</option>
                        @foreach($approvedPr as $pr)<option value="{{ $pr->id }}" data-item="{{ $pr->item_name }}" data-cat="{{ $pr->asset_category_id }}" data-cost="{{ $pr->estimated_unit_cost }}" data-left="{{ $pr->qty - $pr->receivedQty() }}">{{ $pr->number }} — {{ $pr->item_name }} (sisa {{ $pr->qty - $pr->receivedQty() }})</option>@endforeach</select></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Tanggal Terima <span class="text-danger">*</span></label><input type="date" name="received_date" class="form-control form-control-sm" value="{{ $today }}" max="{{ $today }}" required></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Lokasi <span class="text-danger">*</span></label>
                    <select name="asset_location_id" class="form-control form-control-sm" required><option value="">— Pilih —</option>@foreach($locations as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-6"><label class="small font-weight-bold">Nama Barang <span class="text-danger">*</span></label><input name="item_name" id="grItem" class="form-control form-control-sm" maxlength="255" required></div>
                <div class="form-group col-md-6"><label class="small font-weight-bold">Kategori <span class="text-danger">*</span></label>
                    <select name="asset_category_id" id="grCat" class="form-control form-control-sm" required><option value="">— Pilih —</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Merek</label><input name="brand" class="form-control form-control-sm" maxlength="100"></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Model</label><input name="model" class="form-control form-control-sm" maxlength="150"></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Supplier</label><input name="supplier" class="form-control form-control-sm" maxlength="255"></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">No. PO / Faktur</label><input name="po_number" class="form-control form-control-sm" maxlength="100"></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Qty <span class="text-danger">*</span></label><input type="number" name="qty" id="grQty" class="form-control form-control-sm" min="1" max="100" value="1" required></div>
                <div class="form-group col-md-3"><label class="small font-weight-bold">Harga / Unit (Rp) <span class="text-danger">*</span></label><input type="number" name="unit_cost" id="grCost" class="form-control form-control-sm" min="0" step="1" required></div>
                <div class="form-group col-md-6"><label class="small font-weight-bold">Serial Number</label><textarea name="serial_numbers" class="form-control form-control-sm" rows="2" maxlength="5000" placeholder="Satu per baris, urut sesuai unit"></textarea></div>
                <div class="form-group col-12 mb-0"><label class="small font-weight-bold">Catatan</label><textarea name="notes" class="form-control form-control-sm" rows="2" maxlength="2000"></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button><button class="btn btn-primary btn-sm">Simpan & Daftarkan Aset</button></div>
        </form>
    </div>
</div>

@include('asset._scripts')
@endsection

@push('scripts')
<script>
$('#grPr').on('change', function () {
    var o = $(this).find(':selected');
    if (!o.val()) return;
    $('#grItem').val(o.data('item')); $('#grCat').val(o.data('cat'));
    $('#grCost').val(o.data('cost')); $('#grQty').val(o.data('left')).attr('max', o.data('left'));
});
</script>
@endpush

@extends('layouts.app')

@section('title', $asset->code)

@php $rp = fn ($v) => number_format((int) $v, 0, ',', '.'); @endphp

@section('content')
<div class="content"><div class="container-fluid">
    @include('asset._header', [
        'title'   => $asset->code . ' — ' . $asset->name,
        'desc'    => ($asset->category?->name ?? '-') . ' · ' . ($asset->location?->name ?? 'Tanpa lokasi') . ' · Pemegang: ' . $asset->holderName(),
        'actions' => '<a href="' . route('asset.index') . '" class="btn btn-secondary btn-sm">Kembali</a>'
            . ($asset->isDisposed() ? '' : '<a href="' . route('asset.edit', $asset) . '" class="btn btn-primary btn-sm"><i class="fas fa-edit mr-1"></i>Ubah</a>'),
    ])

    <div class="mb-3 d-flex flex-wrap align-items-center" style="gap:8px;">
        <x-status :status="$asset->status" context="asset" size="md" />
        <span class="ast-sub">Kondisi: <b>{{ $asset->conditionLabel() }}</b></span>
        @if($asset->trashed())<span class="badge badge-danger">Dihapus: {{ $asset->delete_reason }}</span>@endif
    </div>

    <x-stat-cards :cards="[
        ['Harga Perolehan', 'Rp ' . $rp($asset->cost), 'info', 'fas fa-coins'],
        ['Akumulasi Penyusutan', 'Rp ' . $rp(\App\Support\AssetDepreciation::accumulated($asset)), 'warning', 'fas fa-chart-area'],
        ['Nilai Buku', 'Rp ' . $rp($book), 'success', 'fas fa-chart-line'],
        ['Umur', $asset->life_years . ' tahun', 'secondary', 'fas fa-hourglass-half', \App\Models\AssetCategory::METHODS[$asset->depreciation_method] ?? ''],
    ]" />

    <ul class="nav ast-tabs mt-3" role="tablist">
        @foreach(['overview' => 'Ringkasan', 'movement' => 'Pergerakan', 'maintenance' => 'Maintenance & Inspeksi', 'depreciation' => 'Penyusutan', 'files' => 'Lampiran', 'log' => 'Riwayat'] as $k => $l)
            <li class="nav-item"><a class="nav-link {{ $loop->first ? 'active' : '' }}" data-toggle="tab" href="#tab-{{ $k }}" role="tab">{{ $l }}</a></li>
        @endforeach
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
            <div class="row">
                <div class="col-lg-7"><div class="ast-panel"><div class="ast-panel-h">Detail</div><div class="ast-panel-b">
                    <table class="ast-table"><tbody>
                        @foreach([
                            'Tipe' => $asset->type, 'Merek / Model' => trim($asset->brand . ' ' . $asset->model), 'Serial Number' => $asset->serial_number,
                            'Departemen' => $asset->department?->name, 'Custodian' => $asset->custodian_name,
                            'Tgl Perolehan' => $asset->acquisition_date?->format('d M Y'), 'Jenis Perolehan' => \App\Models\Asset::ACQUISITION_TYPES[$asset->acquisition_type] ?? $asset->acquisition_type,
                            'Supplier' => $asset->supplier, 'No. PO / Faktur' => $asset->po_number, 'Garansi s/d' => $asset->warranty_end?->format('d M Y'),
                            'Nilai Residu' => 'Rp ' . $rp($asset->residual_value), 'Didaftarkan oleh' => $asset->creator?->name,
                            'Tgl Disposal' => $asset->disposed_at?->format('d M Y'),
                        ] as $k => $v)
                            <tr><td class="ast-sub" style="width:38%">{{ $k }}</td><td>{{ $v ?: '-' }}</td></tr>
                        @endforeach
                    </tbody></table>
                    @if($asset->description)<p class="mt-2 mb-0 small">{{ $asset->description }}</p>@endif
                </div></div></div>
                <div class="col-lg-5"><div class="ast-panel"><div class="ast-panel-h">Spesifikasi</div><div class="ast-panel-b">
                    @if($asset->specs)
                        <table class="ast-table"><tbody>@foreach($asset->specs as $k => $v)<tr><td class="ast-sub" style="width:40%">{{ $k }}</td><td>{{ $v }}</td></tr>@endforeach</tbody></table>
                    @else <x-empty title="Belum ada spesifikasi" :compact="true" /> @endif
                </div></div>
                @if($canManage && ! $asset->trashed() && $asset->movements->isEmpty() && $asset->maintenances->isEmpty() && $asset->inspections->isEmpty())
                    <div class="ast-panel"><div class="ast-panel-h">Hapus Aset</div><div class="ast-panel-b">
                        <form method="POST" action="{{ route('asset.destroy', $asset) }}" onsubmit="return confirm('Hapus aset ini?')">
                            @csrf @method('DELETE')
                            <input name="delete_reason" class="form-control form-control-sm mb-2" placeholder="Alasan hapus (salah input, duplikat, …)" maxlength="500" required>
                            <button class="btn btn-danger btn-sm">Hapus</button>
                            <div class="ast-sub mt-1">Hanya untuk aset tanpa riwayat transaksi. Aset yang sudah dipakai dihapus lewat Disposal.</div>
                        </form>
                    </div></div>
                @endif
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-movement" role="tabpanel"><div class="ast-panel"><div class="table-responsive">
            <table class="ast-table"><thead><tr><th>Nomor</th><th>Jenis</th><th>Tanggal</th><th>Dari</th><th>Ke</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($asset->movements as $m)
                <tr><td class="ast-code">{{ $m->number }}</td><td>{{ \App\Models\AssetMovement::KINDS[$m->kind] }}</td><td>{{ $m->movement_date->format('d M Y') }}</td>
                    <td>{{ $m->fromLocation?->name ?? '-' }}<div class="ast-sub">{{ $m->fromEmployee?->name }}</div></td>
                    <td>{{ $m->toLocation?->name ?? '-' }}<div class="ast-sub">{{ $m->toEmployee?->name }}</div></td>
                    <td><x-status :status="$m->isOverdue() ? 'OVERDUE' : $m->status" context="asset" /></td>
                    <td>@if($m->kind !== 'DISPOSAL')<a href="{{ route('asset.movements.document', $m) }}" target="_blank" class="small">Dokumen</a>@endif</td></tr>
            @empty <tr><td colspan="7"><x-empty title="Belum ada pergerakan" :compact="true" /></td></tr> @endforelse
            </tbody></table>
        </div></div></div>

        <div class="tab-pane fade" id="tab-maintenance" role="tabpanel">
            <div class="ast-panel"><div class="ast-panel-h">Maintenance</div><div class="table-responsive">
                <table class="ast-table"><thead><tr><th>Nomor</th><th>Jenis</th><th>Masalah</th><th>Mulai</th><th>Selesai</th><th class="num">Biaya</th><th>Status</th></tr></thead><tbody>
                @forelse($asset->maintenances as $m)
                    <tr><td class="ast-code">{{ $m->number }}</td><td>{{ \App\Models\AssetMaintenance::TYPES[$m->maintenance_type] ?? $m->maintenance_type }}</td><td>{{ $m->problem }}</td>
                        <td>{{ $m->start_date->format('d M Y') }}</td><td>{{ $m->end_date?->format('d M Y') ?? '-' }}</td>
                        <td class="num">{{ $rp($m->actual_cost ?: $m->estimated_cost) }}</td><td><x-status :status="$m->status" context="asset" /></td></tr>
                @empty <tr><td colspan="7"><x-empty title="Belum ada maintenance" :compact="true" /></td></tr> @endforelse
                </tbody></table></div></div>
            <div class="ast-panel"><div class="ast-panel-h">Jadwal</div><div class="table-responsive">
                <table class="ast-table"><tbody>
                @forelse($asset->schedules as $s)
                    <tr><td>{{ $s->title }}<div class="ast-sub">Setiap {{ $s->interval_days }} hari</div></td><td>{{ $s->next_due_date->format('d M Y') }}</td><td><x-status :status="$s->dueState()" context="asset" /></td></tr>
                @empty <tr><td><x-empty title="Belum ada jadwal" :compact="true" /></td></tr> @endforelse
                </tbody></table></div></div>
            <div class="ast-panel"><div class="ast-panel-h">Inspeksi</div><div class="table-responsive">
                <table class="ast-table"><tbody>
                @forelse($asset->inspections as $i)
                    <tr><td class="ast-code">{{ $i->number }}</td><td>{{ $i->inspection_date->format('d M Y') }}</td><td>{{ $i->inspector_name }}</td>
                        <td><x-status :status="$i->result" context="asset" /></td><td>{{ $i->findings }}</td></tr>
                @empty <tr><td><x-empty title="Belum ada inspeksi" :compact="true" /></td></tr> @endforelse
                </tbody></table></div></div>
        </div>

        <div class="tab-pane fade" id="tab-depreciation" role="tabpanel"><div class="ast-panel">
            <div class="ast-panel-h">Jadwal Penyusutan<span class="ast-r ast-sub">Informasional — tidak diposting ke jurnal/Closing.</span></div>
            <div class="table-responsive"><table class="ast-table">
                <thead><tr><th>Tahun ke-</th><th>Akhir Periode</th><th class="num">Penyusutan</th><th class="num">Akumulasi</th><th class="num">Nilai Buku</th></tr></thead>
                <tbody>@foreach($schedule as $r)
                    <tr><td>{{ $r['year'] }}</td><td>{{ \Carbon\Carbon::parse($r['period_end'])->format('d M Y') }}</td>
                        <td class="num">{{ $rp($r['depreciation']) }}</td><td class="num">{{ $rp($r['accumulated']) }}</td><td class="num">{{ $rp($r['book_value']) }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </div></div>

        <div class="tab-pane fade" id="tab-files" role="tabpanel"><div class="ast-panel"><div class="ast-panel-b">
            @forelse($asset->attachments as $f)
                <div class="d-flex align-items-center mb-2" style="gap:8px;">
                    <i class="fas fa-paperclip"></i><a href="{{ route('secure-file', $f->path) }}" target="_blank">{{ $f->original_name }}</a>
                    <span class="ast-sub">{{ number_format($f->size / 1024, 0) }} KB · {{ $f->created_at?->format('d M Y') }}</span>
                    @if($canManage)
                        <form method="POST" action="{{ route('asset.attachments.destroy', [$asset, $f]) }}" class="ml-auto" onsubmit="return confirm('Hapus lampiran?')">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0">Hapus</button></form>
                    @endif
                </div>
            @empty <x-empty title="Belum ada lampiran" :compact="true" /> @endforelse
            @unless($asset->trashed())
                <form method="POST" action="{{ route('asset.attachments.store', $asset) }}" enctype="multipart/form-data" class="mt-3 d-flex flex-wrap" style="gap:8px;">
                    @csrf
                    <input type="file" name="attachments[]" multiple required class="form-control-file" style="max-width:320px;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    <button class="btn btn-secondary btn-sm">Unggah</button>
                </form>
            @endunless
        </div></div></div>

        <div class="tab-pane fade" id="tab-log" role="tabpanel"><div class="ast-panel"><table class="ast-table"><tbody>
            @forelse($asset->logs as $l)
                <tr><td style="width:170px" class="ast-sub">{{ $l->created_at?->format('d M Y H:i') }}</td><td>{{ $l->user?->name ?? 'Sistem' }}</td><td>{{ $l->description }}</td></tr>
            @empty <tr><td><x-empty title="Belum ada riwayat" :compact="true" /></td></tr> @endforelse
        </tbody></table></div></div>
    </div>
</div></div>
@endsection

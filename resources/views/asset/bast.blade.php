<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $m->number }}</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 12.5px; color: #222; margin: 0; padding: 24px; }
    .doc { max-width: 760px; margin: 0 auto; border: 1px solid #ccc; padding: 28px; }
    h2 { text-align: center; margin: 0; font-size: 16px; letter-spacing: .5px; }
    .no { text-align: center; color: #666; margin: 4px 0 18px; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    td { border: 1px solid #ccc; padding: 6px 10px; vertical-align: top; }
    td.k { width: 34%; background: #f6f6f6; }
    .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 36px; text-align: center; }
    .sign .line { height: 70px; border-bottom: 1px solid #222; }
    .bar { text-align: right; max-width: 760px; margin: 0 auto 10px; }
    @media print { .bar { display: none; } body { padding: 0; } .doc { border: 0; } }
</style>
</head>
<body>
@php
    $judul = [
        'HANDOVER' => 'BERITA ACARA SERAH TERIMA ASET',
        'RETURN'   => 'BERITA ACARA PENGEMBALIAN ASET',
        'TRANSFER' => 'BERITA ACARA TRANSFER ASET',
        'CHECKOUT' => 'BUKTI PEMINJAMAN ASET',
    ][$m->kind];
    $cond = \App\Models\Asset::CONDITIONS;
    $a = $m->asset;
    [$pihak1, $pihak2] = match ($m->kind) {
        'HANDOVER', 'CHECKOUT' => [$m->requester?->name, $m->toEmployee?->name],
        'RETURN'               => [$m->fromEmployee?->name, $m->requester?->name],
        default                => [$m->fromEmployee?->name ?? $m->requester?->name, $m->toEmployee?->name ?? $m->approver?->name],
    };
@endphp
<div class="bar"><button onclick="window.print()">Cetak</button></div>
<div class="doc">
    <h2>{{ $judul }}</h2>
    <div class="no">No. {{ $m->number }} · {{ $m->movement_date->translatedFormat('d F Y') }}</div>

    <p>Pada tanggal tersebut di atas telah dilakukan {{ strtolower(\App\Models\AssetMovement::KINDS[$m->kind]) }} aset dengan rincian sebagai berikut:</p>

    <table>
        <tr><td class="k">Kode Aset</td><td>{{ $a?->code }}</td></tr>
        <tr><td class="k">Nama Aset</td><td>{{ $a?->name }}</td></tr>
        <tr><td class="k">Kategori</td><td>{{ $a?->category?->name }}</td></tr>
        <tr><td class="k">Merek / Model</td><td>{{ trim(($a?->brand ?? '') . ' ' . ($a?->model ?? '')) ?: '-' }}</td></tr>
        <tr><td class="k">Serial Number</td><td>{{ $a?->serial_number ?: '-' }}</td></tr>
        <tr><td class="k">Dari</td><td>{{ $m->fromLocation?->name ?? '-' }}{{ $m->fromEmployee ? ' — ' . $m->fromEmployee->name : '' }}</td></tr>
        <tr><td class="k">Ke</td><td>{{ $m->toLocation?->name ?? '-' }}{{ $m->toEmployee ? ' — ' . $m->toEmployee->name : '' }}</td></tr>
        <tr><td class="k">Kondisi</td><td>{{ $cond[$m->condition_after ?? $m->condition_before] ?? '-' }}</td></tr>
        @if($m->kind === 'CHECKOUT')<tr><td class="k">Rencana Kembali</td><td>{{ $m->expected_return_date?->format('d M Y') }}</td></tr>@endif
        @if($m->reason)<tr><td class="k">Alasan</td><td>{{ $m->reason }}</td></tr>@endif
        @if($m->notes)<tr><td class="k">Catatan</td><td>{!! nl2br(e($m->notes)) !!}</td></tr>@endif
        <tr><td class="k">Status</td><td>{{ \App\Support\WorkflowStatus::label($m->status, 'asset') }}{{ $m->approver ? ' oleh ' . $m->approver->name : '' }}</td></tr>
    </table>

    <p>Penerima bertanggung jawab menjaga aset tersebut dan mengembalikannya dalam kondisi baik sesuai ketentuan perusahaan.</p>

    <div class="sign">
        <div><div>Yang Menyerahkan</div><div class="line"></div><div>{{ $pihak1 ?: '(....................)' }}</div></div>
        <div><div>Yang Menerima</div><div class="line"></div><div>{{ $pihak2 ?: '(....................)' }}</div></div>
    </div>
</div>
</body>
</html>

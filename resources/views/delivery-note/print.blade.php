<?php
$company = ['name' => 'PT TEREX', 'logo' => 'ts_logo_dark.png'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Surat Jalan {{ $deliveryNote->code }}</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Cambria, Georgia, 'Times New Roman', serif;
        color: #1a1a1a;
        font-size: 13px;
        margin: 0;
        padding: 24px;
    }
    .sheet { max-width: 800px; margin: 0 auto; }
    .toolbar { text-align: right; margin-bottom: 16px; }
    .toolbar button {
        background: #1f2937; color: #fff; border: none; padding: 8px 16px;
        border-radius: 6px; font-size: 13px; cursor: pointer;
    }
    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1f2937; padding-bottom: 12px; margin-bottom: 20px; }
    .header .company { display: flex; align-items: center; gap: 12px; }
    .header img { height: 48px; }
    .header .company-name { font-size: 16px; font-weight: bold; margin: 0; }
    .header .company-address { font-size: 11px; color: #555; margin: 2px 0 0; max-width: 320px; }
    .header .doc-title { text-align: right; }
    .header .doc-title h1 { font-size: 22px; margin: 0; letter-spacing: 1px; color: #1f2937; }
    .header .doc-title .code { font-size: 13px; font-weight: bold; margin-top: 4px; }

    .meta { display: flex; justify-content: space-between; margin-bottom: 20px; gap: 20px; }
    .meta .block { font-size: 12px; flex: 1; }
    .meta .block strong { display: block; font-size: 11px; text-transform: uppercase; color: #888; margin-bottom: 4px; }

    table.details { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.details th, table.details td { border: 1px solid #ccc; padding: 8px; font-size: 12px; }
    table.details th { background: #f3f4f6; text-align: left; }
    table.details td.num { text-align: right; }
    table.details .fit { width: 1%; white-space: nowrap; }

    .notes-block { font-size: 12px; margin-bottom: 24px; }
    .notes-block strong { display: block; font-size: 11px; text-transform: uppercase; color: #888; margin-bottom: 4px; }

    .signature-row { display: flex; justify-content: space-between; margin-top: 50px; }
    .signature-row .box { text-align: center; width: 220px; font-size: 12px; }
    .signature-row .space { height: 70px; }
    .signature-row .name { border-top: 1px solid #333; padding-top: 6px; font-weight: bold; }
    .signature-row .role { font-size: 11px; color: #666; margin-top: 2px; }

    @media print {
        @page { margin: 0; }
        .toolbar { display: none; }
        body { padding: 24px; }
        .no-print { display: none !important; }
    }
</style>
</head>
<body>
<div class="sheet">

    <div class="toolbar">
        <button onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <div class="header">
        <div class="company">
            <img src="{{ asset('dist/img/' . $company['logo']) }}" alt="{{ $company['name'] }}">
            <div>
                <p class="company-name">{{ $company['name'] }}</p>
                <p class="company-address">Jl. Arjuna Raya No 42A</p>
                <p class="company-address">Kel. Jati Asih, Kec. Jati Asih, Kota Bekasi, 17423</p>
                <p class="company-address">Telp: 0822-9389-8749, Email: finance@terex.id</p>
            </div>
        </div>
        <div class="doc-title">
            <h1>SURAT JALAN</h1>
            <div class="code">{{ $deliveryNote->code }}</div>
        </div>
    </div>

    <div class="meta">
        <div class="block">
            <strong>Pengirim</strong>
            {{ $company['name'] }}<br>
            Jl. Arjuna Raya No 42A<br>
            Kel. Jati Asih, Kec. Jati Asih, Kota Bekasi, 17423
        </div>
        <div class="block" style="text-align:right;">
            <strong>Penerima</strong>
            {{ $deliveryNote->customer->name ?? '-' }}<br>
            {{ $deliveryNote->customer->address ?? '-' }}
            @if($deliveryNote->customer->npwp ?? null)
                <br>NPWP: {{ $deliveryNote->customer->npwp }}
            @endif
        </div>
    </div>

    <div class="meta">
        <div class="block">
            <strong>No. Surat Jalan</strong>
            {{ $deliveryNote->code }}
        </div>
        <div class="block" style="text-align:right;">
            <strong>Tanggal Kirim</strong>
            {{ $deliveryNote->sent_at ? $deliveryNote->sent_at->format('d/m/Y') : '-' }}
        </div>
    </div>

    <table class="details">
        <thead>
            <tr>
                <th scope="col" class="fit">No</th>
                <th scope="col" class="fit">No. Invoice</th>
                <th scope="col" class="fit">No. PO</th>
                <th scope="col">Purchase Order</th>
                <th scope="col" class="fit">Tanggal Invoice</th>
            </tr>
        </thead>
        <tbody>
            @foreach($deliveryNote->items as $i => $item)
            @php $inv = $item->invoice; @endphp
            <tr>
                <td class="fit">{{ $i + 1 }}</td>
                <td class="fit">{{ $inv->code ?? '-' }}</td>
                <td class="fit">{{ $inv->poNumber->po_number ?? '-' }}</td>
                <td>{{ $inv->po->name ?? '-' }}</td>
                <td class="fit">{{ $inv->invoice_date ? $inv->invoice_date->format('d/m/Y') : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($deliveryNote->notes)
    <div class="notes-block">
        <strong>Catatan</strong>
        {{ $deliveryNote->notes }}
    </div>
    @endif

    <div class="signature-row">
        <div class="box">
            Pembuat
            <div class="space"></div>
            <div class="name">Farhana</div>
            <div class="role">{{ $company['name'] }}</div>
        </div>
        <div class="box">
            Pengirim
            <div class="space"></div>
            <div class="name">&nbsp;</div>
        </div>
        <div class="box">
            Penerima
            <div class="space"></div>
            <div class="name">&nbsp;</div>
            <div class="role">{{ $deliveryNote->customer->name ?? '-' }}</div>
        </div>
    </div>

</div>
</body>
</html>

@php
    $company = ['name' => 'PT TEREX', 'logo' => 'ts_logo_dark.png'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Invoice {{ $invoice->code }}</title>
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
    .header .invoice-title { text-align: right; }
    .header .invoice-title h1 { font-size: 22px; margin: 0; letter-spacing: 1px; color: #1f2937; }
    .header .invoice-title .code { font-size: 13px; font-weight: bold; margin-top: 4px; }

    .meta { display: flex; justify-content: space-between; margin-bottom: 20px; }
    .meta .block { font-size: 12px; max-width: 360px; word-wrap: break-word; }
    .meta .block strong { display: block; font-size: 11px; text-transform: uppercase; color: #888; margin-bottom: 4px; }

    .currency-note { text-align: right; font-size: 10px; color: #888; font-style: italic; margin-bottom: 6px; }

    table.details { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.details th, table.details td { border: 1px solid #ccc; padding: 8px; font-size: 12px; }
    table.details th { background: #f3f4f6; text-align: left; }
    table.details td.num { text-align: right; }
    .deduction { color: #000; }

    .totals-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 24px; }
    .totals-left { flex: 1; padding-top: 5px; }

    .summary { width: 320px; flex-shrink: 0; }
    .summary table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .summary td { padding: 5px 4px; }
    .summary td.label { color: #555; }
    .summary td.val { text-align: right; }
    .summary tr.total td { border-top: 2px solid #1f2937; font-weight: bold; font-size: 14px; padding-top: 8px; }
    .terbilang { text-align: left; font-size: 11px; font-style: italic; color: #444; }
    .dpp-note { margin-top: 8px; font-size: 9.5px; font-style: italic; color: #777; line-height: 1.4; }

    .bank-box { border: 1px solid #ccc; border-radius: 6px; padding: 12px 14px; font-size: 12px; margin: 16px 0 0; max-width: 320px; }
    .bank-box strong { display: block; font-size: 11px; text-transform: uppercase; color: #888; margin-bottom: 6px; }

    .thanks { margin-top: 16px; font-size: 12px; font-style: italic; color: #444; }

    .signature { display: flex; justify-content: flex-end; margin-top: 40px; margin-bottom: 20px; }
    .signature .box { text-align: center; width: 190px; font-size: 12px; }
    .signature .space { height: 120px; }
    .signature .name { border-top: 1px solid #333; padding-top: 6px; font-weight: bold; letter-spacing: 2px; }

    .attachments-title { font-size: 14px; font-weight: bold; color: #1f2937; border-bottom: 2px solid #1f2937; padding-bottom: 6px; margin: 30px 0 16px; }
    .attachment-page { margin-bottom: 20px; }
    .attachment-caption { font-size: 12px; font-weight: bold; margin-bottom: 8px; color: #333; }
    .attachment-image { max-width: 100%; border: 1px solid #ddd; border-radius: 6px; }
    .file-list { display: flex; flex-direction: column; gap: 8px; }
    .file-item { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; color: #1a1a1a; text-decoration: none; }
    .file-item:hover { background: #f9fafb; }
    .file-icon { font-size: 16px; }

    @media print {
        @page { margin: 0; }
        .toolbar { display: none; }
        body { padding: 24px; }
        .file-item { color: #1a1a1a; }
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
        <div class="invoice-title">
            <h1>INVOICE</h1>
            <div class="code">{{ $invoice->code }}</div>
        </div>
    </div>

    @php
        // No PO kadang diisi gabungan "PO NO. ... AGREEMENT NO. ..." dalam satu
        // field (tidak ada kolom terpisah untuk nomor agreement) — dipecah jadi
        // baris baru sebelum "AGREEMENT NO." supaya tidak jadi 1 baris panjang.
        $poNoRaw = $invoice->poNumber->po_number ?? '-';
        $poNoDisplay = preg_replace('/\s*(AGREEMENT\s+NO\.?)/i', '<br>$1', e($poNoRaw), 1);
    @endphp
    <div class="meta">
        <div class="block">
            <strong>No PO</strong>
            {!! $poNoDisplay !!}
            <br><br>
            <strong>Ditagihkan Kepada</strong>
            {{ $invoice->po->customer->name ?? '-' }}<br>
            {{ $invoice->po->customer->address ?? '-' }}
        </div>
        <div class="block" style="text-align:right;">
            <strong>Tanggal Invoice</strong>
            {{ $invoice->invoice_date->format('d/m/Y') }}
            <br><br>
            <strong>Jatuh Tempo</strong>
            {{ $invoice->due_date->format('d/m/Y') }}
            <br><br>
            <strong>Payment Term</strong>
            {{ $invoice->payment_term_label }}
        </div>
    </div>

    <div class="currency-note">Seluruh nominal dalam Rupiah (IDR)</div>

    @php
        $hasDeduction = $invoice->details->sum('deduction') > 0;
    @endphp
    <table class="details">
        <thead>
            <tr>
                <th scope="col" style="width:36px;">No</th>
                <th scope="col">Deskripsi</th>
                <th scope="col" class="num" style="width:110px;">Harga Satuan</th>
                <th scope="col" class="num" style="width:80px;">Qty</th>
                @if($hasDeduction)
                <th scope="col" class="num" style="width:100px;">Deduction</th>
                @endif
                <th scope="col" class="num" style="width:120px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->details as $i => $d)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $d->description }}</td>
                <td class="num">{{ number_format($d->unit_price, 0, ',', '.') }}</td>
                <td class="num">{{ $d->qty_unit_label }}</td>
                @if($hasDeduction)
                <td class="num deduction">{{ $d->deduction > 0 ? '(' . number_format($d->deduction, 0, ',', '.') . ')' : '-' }}</td>
                @endif
                <td class="num">{{ number_format($d->total, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-row">
        <div class="totals-left">
            <div class="terbilang">Terbilang: {{ \App\Support\Terbilang::rupiah((int) $invoice->captured_total) }}</div>

            @if($invoice->sourceFund)
            <div class="bank-box">
                <strong>Pembayaran Ditujukan Ke</strong>
                {{ $invoice->sourceFund->bank->name ?? '-' }} — {{ $invoice->sourceFund->no_account ?? '-' }}<br>
                a.n. {{ $invoice->sourceFund->name }}
            </div>
            @endif

            <div class="thanks">Mohon dilakukan pemotongan PPh Final Pasal 4 ayat (2) sebesar 2,65% dan penyampaian Bupot kepada PT TEREX paling lambat tanggal 20 bulan berikutnya.</div>
            <div class="thanks">Thank you for your business!</div>
        </div>

        <div class="summary">
            <table>
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="val">{{ number_format($invoice->subtotal + $invoice->total_deduction, 0, ',', '.') }}</td>
                </tr>
                @if($invoice->billing_percentage_label)
                <tr>
                    <td class="label">{{ $invoice->billing_percentage_label }}</td>
                    <td class="val">{{ number_format($invoice->billed_amount, 0, ',', '.') }}</td>
                </tr>
                @endif
                @if($invoice->total_deduction > 0)
                <tr>
                    <td class="label">Total Deduction</td>
                    <td class="val deduction">({{ number_format($invoice->total_deduction, 0, ',', '.') }})</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Tax</td>
                    <td class="val">{{ number_format($invoice->tax_amount, 0, ',', '.') }}</td>
                </tr>
                {{-- MN-07 — PPh dipotong di muka ikut dicetak, supaya angka
                     Total Tagihan di kertas bisa direkonsiliasi baris per baris
                     oleh customer. Baris ini hanya muncul kalau ada potongan. --}}
                @if($invoice->withholding_tax > 0)
                <tr>
                    <td class="label">Withholding Tax (PPh)</td>
                    <td class="val deduction">({{ number_format($invoice->withholding_tax, 0, ',', '.') }})</td>
                </tr>
                @endif
                <tr class="total">
                    <td>Total Tagihan</td>
                    <td class="val">{{ number_format($invoice->captured_total, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="signature">
        <div class="box">
            {{ $company['name'] }}
            <div class="space"></div>
            <div class="name">Aye Assagaf</div>
        </div>
    </div>

    @php
        $allAttachments = collect();
        foreach (($invoice->po->attachments ?? collect()) as $a) {
            $allAttachments->push(['title' => $a->title ?? $a->file_name, 'path' => $a->file_path, 'group' => 'Lampiran PO']);
        }
        foreach (($invoice->attachments ?? collect()) as $a) {
            $allAttachments->push(['title' => $a->title ?? $a->file_name, 'path' => $a->file_path, 'group' => 'Dokumen Pendukung Invoice']);
        }
        $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    @endphp

    @if($allAttachments->isNotEmpty())
        <div class="no-print">
        <div class="attachments-title">Lampiran</div>

        {{-- Gambar ditampilkan penuh satu halaman per lampiran --}}
        @foreach($allAttachments as $a)
            @if(in_array(strtolower(pathinfo($a['path'], PATHINFO_EXTENSION)), $imageExt))
                <div class="attachment-page">
                    <div class="attachment-caption">{{ $a['group'] }} — {{ $a['title'] }}</div>
                    <img src="{{ asset('storage/' . $a['path']) }}" class="attachment-image" alt="{{ $a['title'] }}">
                </div>
            @endif
        @endforeach

        {{-- Dokumen non-gambar (PDF/DOC/XLS) ditampilkan sebagai daftar tautan --}}
        @php $fileAttachments = $allAttachments->reject(fn($a) => in_array(strtolower(pathinfo($a['path'], PATHINFO_EXTENSION)), $imageExt)); @endphp
        @if($fileAttachments->isNotEmpty())
        <div class="file-list">
            <div class="attachment-caption" style="margin-bottom:8px;">Dokumen Lainnya</div>
            @foreach($fileAttachments as $a)
                <a href="{{ asset('storage/' . $a['path']) }}" target="_blank" class="file-item">
                    <span class="file-icon">&#128196;</span>
                    <span>{{ $a['group'] }} — {{ $a['title'] }}</span>
                </a>
            @endforeach
        </div>
        @endif
        </div>
    @endif

</div>
</body>
</html>

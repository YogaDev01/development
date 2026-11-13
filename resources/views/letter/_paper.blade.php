{{-- Kertas surat: dipakai tab Surat (pratinjau) dan halaman cetak. --}}
@php
    $d     = $letter->detail;
    $items = $d->items ?? [];
    $meta  = collect($d->meta ?? [])->filter(fn ($m) => ($m[1] ?? '') !== '');
    $to    = $letter->type === 'external' ? ($letter->customer->name ?? '') : $letter->recipientName();
    $toAddr = $letter->type === 'external' ? ($letter->customer->address ?? '') : '';
@endphp
<div class="letter-paper">
    <div class="lp-head">
        <div class="lp-title">{{ strtoupper($letter->category->name) }}</div>
        <div>No. {{ $letter->letter_no }}</div>
    </div>

    @if ($meta->isNotEmpty())
        <table class="lp-meta">
            @foreach ($meta as $m)
                <tr><td>{{ $m[0] }}</td><td>: {{ $m[1] }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($to)
        <p class="lp-to"><b>{{ $letter->type === 'external' ? 'Kepada Yth.' : 'Ditujukan kepada' }}</b><br>{{ $to }}@if ($toAddr)<br><span class="lp-sub">{{ $toAddr }}</span>@endif</p>
    @endif

    @if ($d->header)<p class="lp-subject"><b>{{ $d->header }}</b></p>@endif
    @if ($d->body)<div class="lp-body">{{ $d->body }}</div>@endif

    @if (count($items))
        <table class="lp-items">
            <thead><tr><th>No</th><th>Deskripsi</th><th class="r">Harga</th><th class="r">Qty</th><th class="r">Jumlah</th></tr></thead>
            <tbody>
            @foreach ($items as $i => $it)
                <tr>
                    <td>{{ $i + 1 }}</td><td>{{ $it['desc'] }}</td>
                    <td class="r">{{ number_format($it['price'] ?? 0, 0, ',', '.') }}</td>
                    <td class="r">{{ rtrim(rtrim(number_format($it['qty'] ?? 0, 2, ',', '.'), '0'), ',') }} {{ $it['unit'] ?? '' }}</td>
                    <td class="r">{{ number_format(($it['price'] ?? 0) * ($it['qty'] ?? 0), 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="r">Subtotal</td><td class="r">{{ number_format($d->subtotal(), 0, ',', '.') }}</td></tr>
                @if ($d->tax > 0)
                    <tr><td colspan="4" class="r">PPN {{ rtrim(rtrim(number_format($d->tax, 2, ',', '.'), '0'), ',') }}%</td><td class="r">{{ number_format($d->taxAmount(), 0, ',', '.') }}</td></tr>
                @endif
                <tr><td colspan="4" class="r"><b>Total</b></td><td class="r"><b>{{ number_format($d->grandTotal(), 0, ',', '.') }}</b></td></tr>
            </tfoot>
        </table>
    @endif

    @if ($d->note)<p class="lp-note"><i>{{ $d->note }}</i></p>@endif
    @if ($d->closing)<p>{{ $d->closing }}</p>@endif

    @if ($letter->signatures->isNotEmpty())
        <div class="lp-sign">
            @foreach ($letter->signatures as $s)
                <div class="lp-sb">
                    <div class="lp-line">{{ $s->signed_at ? 'Telah ditandatangani' : '' }}</div>
                    <div class="lp-nm">{{ $s->signer->name ?? '-' }}</div>
                    <div class="lp-sub">{{ $s->sign_as }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<style>
.letter-paper{background:#fff;border:1px solid #e3e5e8;border-radius:6px;padding:34px 42px;font-family:Georgia,"Times New Roman",serif;font-size:13.5px;line-height:1.7;color:#111}
.letter-paper .lp-head{border-bottom:2px solid #111;padding-bottom:10px;margin-bottom:18px;text-align:center}
.letter-paper .lp-title{font-weight:700;font-size:16px}
.letter-paper .lp-meta td{padding:0 12px 0 0;vertical-align:top}
.letter-paper .lp-body{white-space:pre-wrap;margin-bottom:14px}
.letter-paper .lp-sub{color:#555;font-size:12px}
.letter-paper .lp-items{width:100%;border-collapse:collapse;margin:10px 0 14px}
.letter-paper .lp-items th,.letter-paper .lp-items td{border:1px solid #999;padding:5px 8px}
.letter-paper .r{text-align:right}
.letter-paper .lp-sign{display:flex;flex-wrap:wrap;gap:28px;margin-top:36px}
.letter-paper .lp-sb{flex:1 1 170px;text-align:center}
.letter-paper .lp-line{height:56px;display:grid;place-items:center;color:#166534;font-size:12px}
.letter-paper .lp-nm{border-top:1px solid #111;padding-top:5px;font-weight:600}
@media (max-width:900px){.letter-paper{padding:22px 20px}}
</style>

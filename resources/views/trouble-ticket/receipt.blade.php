{{-- Kwitansi digital: dirender dompdf menjadi PDF, jadi gayanya inline dan tidak
     memakai token CSS aplikasi. Variabel: SettlementReceiptService::terbitkan(). --}}
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
    h1 { font-size: 18px; letter-spacing: 3px; margin: 0; }
    .kepala { width: 100%; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
    .kepala td { vertical-align: top; }
    .kanan { text-align: right; }
    .isi { width: 100%; border-collapse: collapse; }
    .isi td { padding: 4px 0; vertical-align: top; }
    .isi td.label { width: 120px; color: #444; }
    .jumlah { font-size: 15px; font-weight: bold; }
    .ttd { width: 100%; margin-top: 8px; }
    .ttd td { vertical-align: bottom; }
    .ttd img { height: 70px; }
    .kecil { font-size: 9px; color: #444; }
</style>
</head>
<body>
    <table class="kepala">
        <tr>
            <td><strong>PT TEREX</strong><br><span class="kecil">Jl. Arjuna Raya No 42A, Kel. Jati Asih, Kec. Jati Asih, Kota Bekasi, 17423</span></td>
            <td class="kanan"><h1>KWITANSI</h1>No. {{ $nomor }}</td>
        </tr>
    </table>

    <table class="isi">
        <tr><td class="label">Telah terima dari</td><td>{{ $teknisi ?? '-' }} (PT TEREX)</td></tr>
        <tr><td class="label">Uang sejumlah</td><td class="jumlah">Rp {{ number_format($jumlah, 0, ',', '.') }}</td></tr>
        <tr><td class="label">Terbilang</td><td><em>{{ \App\Support\Terbilang::rupiah($jumlah) }}</em></td></tr>
        <tr><td class="label">Untuk pembayaran</td><td>{{ $keperluan }}</td></tr>
        <tr><td class="label">Kategori</td><td>{{ $kategori }}</td></tr>
        @if($vendor)<tr><td class="label">Vendor</td><td>{{ $vendor }}</td></tr>@endif
        <tr><td class="label">Referensi</td><td>{{ $ticket ? 'Tiket ' . $ticket . ' · ' : '' }}Cost Request {{ $cr }}</td></tr>
        @if($bukti)<tr><td class="label">Bukti terlampir</td><td>{{ implode(', ', $bukti) }}</td></tr>@endif
    </table>

    <table class="ttd">
        <tr>
            <td class="kecil">Dibuat digital {{ $waktu->format('d/m/Y H:i') }} WIB.<br>Tanda tangan dibubuhkan penerima di perangkat {{ $teknisi ?? 'teknisi' }}.</td>
            <td class="kanan">
                {{ $tanggal->format('d/m/Y') }}<br>Penerima,<br>
                <img src="{{ $ttd }}" alt=""><br>
                <strong>{{ $penerima }}</strong>
            </td>
        </tr>
    </table>
</body>
</html>

<h1>Summary (Finance)</h1>
<p class="pnd-lead">
    Halaman Summary merangkum posisi keuangan lintas modul: anggaran, realisasi
    biaya, collection dari customer, dan posisi kas — dalam satu tampilan.
</p>

<h2>Siapa yang mengakses</h2>
<div class="pnd-roles">
    <span class="pnd-role">Superadmin</span>
    <span class="pnd-role">Finance</span>
    <span class="pnd-role">Finance Manager</span>
    <span class="pnd-role">Approver / CEO (tampilan executive)</span>
</div>

<h2>Isi halaman</h2>
<ul>
    <li><strong>Kartu ringkas (stat cards)</strong> — total budget, total realisasi, sisa anggaran, outstanding collection, dan saldo kas.</li>
    <li><strong>Breakdown per cost center / project</strong> — melihat kontribusi tiap unit.</li>
    <li><strong>Tren periode</strong> — perbandingan antar bulan.</li>
</ul>

<h2>Cara membaca</h2>
<ol class="pnd-steps">
    <li>Pilih <strong>periode</strong> (bulan / rentang) di bagian atas.</li>
    <li>Bandingkan <strong>Realisasi</strong> terhadap <strong>Budget</strong> untuk melihat penyerapan anggaran.</li>
    <li>Cek <strong>Outstanding Collection</strong> — nilai invoice yang belum tertagih penuh (lihat definisi di panduan <a href="{{ route('panduan.show', 'invoice-cash-receipt') }}">Invoice &amp; Cash Receipt</a>).</li>
    <li>Gunakan angka kas untuk memperkirakan kemampuan pembayaran Cost Request yang antre.</li>
</ol>

<div class="pnd-callout">
    <i class="fas fa-info-circle"></i>
    <span>Angka realisasi biaya di Summary mengikuti Cost Request yang sudah berstatus
    <code>PAID</code>, <code>REALIZED</code>, atau <code>CLOSED</code> — pengajuan yang
    masih dalam approval belum dihitung sebagai realisasi.</span>
</div>

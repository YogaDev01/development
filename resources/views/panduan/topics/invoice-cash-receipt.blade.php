<h1>Invoice &amp; Cash Receipt</h1>
<p class="pnd-lead">
    Modul ini menerbitkan invoice ke customer atas pekerjaan yang sudah BAST, dan
    mencatat penerimaan pembayaran (cash receipt) hingga invoice lunas.
</p>

<h2>Siapa yang mengakses</h2>
<div class="pnd-roles">
    <span class="pnd-role">Superadmin</span>
    <span class="pnd-role">Finance / Finance Manager</span>
    <span class="pnd-role">Approver / CEO (verify / approve)</span>
</div>

<h2>Status invoice</h2>
<div class="pnd-status-flow">
    <span class="pnd-chip">DRAFT</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">SUBMITTED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">VERIFIED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">APPROVED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">SENT</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">RECEIVED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">PARTIALLY_PAID</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">PAID</span>
</div>
<p>
    <strong>OVERDUE</strong> tidak disimpan sebagai status — dihitung otomatis untuk
    invoice <code>SENT</code> / <code>RECEIVED</code> / <code>PARTIALLY_PAID</code>
    yang sudah melewati tanggal jatuh tempo.
</p>

<h2>Menerbitkan invoice</h2>
<ol class="pnd-steps">
    <li>Klik <strong>Buat invoice</strong> — pilih project / No. PO yang <strong>sudah BAST</strong>.</li>
    <li>Isi nilai, PPN, termin, dan tanggal jatuh tempo; tambahkan baris detail.</li>
    <li>Simpan Draft, lalu <strong>Submit</strong> untuk diperiksa &rarr; <strong>Verify</strong> &rarr; <strong>Approve</strong>.</li>
    <li>Setelah <strong>SENT</strong> (dikirim ke customer), catat bukti terima (<code>RECEIVED</code>) bila ada surat jalan / tanda terima.</li>
</ol>

<h2>Mencatat pembayaran (Cash Receipt)</h2>
<ol class="pnd-steps">
    <li>Buka invoice, klik <strong>Tambah Cash Receipt</strong>.</li>
    <li>Isi tanggal, bank tujuan, dan nominal yang diterima.</li>
    <li>Jika akumulasi &lt; nilai invoice &rarr; status <code>PARTIALLY_PAID</code>; jika sama/lebih &rarr; <code>PAID</code>.</li>
    <li>Saat <code>PAID</code>, status No. PO / PO terkait ikut menjadi <code>PAID</code>.</li>
</ol>

<h2>Definisi Outstanding (Summary Collection)</h2>
<p>
    <strong>Outstanding = invoice <code>RECEIVED</code> / <code>PARTIALLY_PAID</code>
    ditambah invoice Overdue.</strong> Invoice <code>SENT</code> yang belum jatuh
    tempo <em>tidak</em> dihitung sebagai outstanding. Tersedia filter per bulan
    (<code>col_month</code>).
</p>

<div class="pnd-callout warn">
    <i class="fas fa-exclamation-triangle"></i>
    <span>Invoice tidak dapat dibuat bila BAST pekerjaan terkait belum selesai —
    lengkapi dulu proses di modul <a href="{{ route('panduan.show', 'purchase-order') }}">Purchase Order</a>.</span>
</div>

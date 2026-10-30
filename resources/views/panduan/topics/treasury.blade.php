<h1>Treasury (Daily Transaction)</h1>
<p class="pnd-lead">
    Treasury mengelompokkan Cost Request yang sudah <code>APPROVED</code> ke dalam
    <strong>batch pembayaran harian</strong>, memintakan persetujuan approver atas
    batch, lalu menandainya sebagai dibayar.
</p>

<h2>Siapa yang mengakses</h2>
<div class="pnd-roles">
    <span class="pnd-role">Superadmin</span>
    <span class="pnd-role">Finance</span>
    <span class="pnd-role">Treasury</span>
    <span class="pnd-role">Approver (persetujuan batch)</span>
</div>

<h2>Status batch</h2>
<div class="pnd-status-flow">
    <span class="pnd-chip">DRAFT</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">PENDING_APPROVER</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">DONE</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">PAID</span>
</div>
<p>Tipe batch: <strong>CASH_OUT</strong> (pembayaran) dan <strong>TOP_UP</strong> (pengisian kas / kas kecil).</p>

<h2>Alur kerja</h2>
<ol class="pnd-steps">
    <li>Buka <strong>Finance &rarr; Treasury</strong>, klik <strong>Buat batch</strong> (pilih tanggal &amp; tipe).</li>
    <li><strong>Tambah item</strong> — pilih Cost Request berstatus APPROVED yang akan dibayar hari itu. Batch tersimpan sebagai <code>DRAFT</code>: CR tetap APPROVED tetapi sudah dipesan (tidak bisa masuk batch lain).</li>
    <li>Selama DRAFT, item bisa dikeluarkan (<strong>Keluarkan</strong>) atau seluruh draft dihapus (<strong>Hapus Draft</strong>). CEO belum bisa meng-approve draft.</li>
    <li><strong>Submit</strong> ke approver — status menjadi <code>PENDING_APPROVER</code> dan CR di dalamnya berpindah ke <code>QUEUED</code>.</li>
    <li>Approver <strong>menyetujui</strong> (batch <code>DONE</code>) atau <strong>menolak</strong> (batch / item tertentu).</li>
    <li><strong>Mark paid</strong> — batch menjadi <code>PAID</code>. Cost Request di dalamnya berpindah: Cash Advance &rarr; <code>PAID</code>, tipe lain &rarr; <code>CLOSED</code>.</li>
</ol>

<h2>Ekspor</h2>
<ul>
    <li><strong>Export daftar</strong> batch dan <strong>export detail</strong> per batch tersedia untuk rekonsiliasi bank.</li>
</ul>

<div class="pnd-callout">
    <i class="fas fa-info-circle"></i>
    <span>Satu Cost Request hanya bisa berada di satu batch aktif. Jika di-reject dari
    batch, CR kembali ke <code>APPROVED</code> dan dapat dimasukkan ke batch lain.</span>
</div>

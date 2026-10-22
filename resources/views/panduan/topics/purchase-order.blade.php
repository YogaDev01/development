<h1>Purchase Order</h1>
<p class="pnd-lead">
    Modul Purchase Order (menu <strong>Finance &rarr; Purchase Order</strong>)
    mencatat PO dari customer, mengaktifkan proyek, mengelola rincian No. PO per
    scope of work, dan memproses BAST.
</p>

<h2>Siapa yang mengakses</h2>
<div class="pnd-roles">
    <span class="pnd-role">Superadmin</span>
    <span class="pnd-role">Head (buat / submit / BAST)</span>
    <span class="pnd-role">Finance / Finance Manager (approve / aktivasi)</span>
</div>

<h2>Status proyek / PO</h2>
<div class="pnd-status-flow">
    <span class="pnd-chip">DRAFT</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">WAITING_PO</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">ACTIVE</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">PROGRESS_DONE</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">CLOSING</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">CLOSED</span>
</div>
<p>
    <strong>PROGRESS_DONE</strong> tercapai otomatis begitu seluruh No. PO sudah
    BAST, dan kembali ke <strong>ACTIVE</strong> bila ada No. PO yang dibatalkan
    penyelesaiannya.
</p>
<p>
    <strong>PARTIALLY_INVOICED</strong> berlaku otomatis bila sebagian (belum semua)
    No. PO sudah ditagih, dan <strong>PARTIALLY_PAID</strong> bila sebagian No. PO
    sudah Paid. Keduanya berlaku dari <strong>ACTIVE</strong> maupun
    <strong>PROGRESS_DONE</strong>, bisa kembali bila statusnya turun, dan menjadi
    <strong>PAID</strong> saat seluruh No. PO Paid.
</p>

<h2>Alur kerja</h2>
<ol class="pnd-steps">
    <li><strong>Buat proyek / PO</strong> (Head) — isi customer, cluster, nilai kontrak, overhead, fixed cost, dan Project Manager Internal.</li>
    <li><strong>Submit</strong> ke Finance untuk diperiksa.</li>
    <li><strong>Finance approve</strong> atau <strong>revise</strong>. Setelah approve, proyek menjadi <code>WAITING_PO</code>.</li>
    <li><strong>Aktivasi</strong> (Head / Finance) — lengkapi form aktivasi dan tambahkan baris <strong>No. PO</strong> per item scope of work. Proyek menjadi <code>ACTIVE</code>.</li>
    <li>Selama berjalan, perbarui <strong>status tiap No. PO</strong> (mis. PO Received) dan unggah dokumen pendukung.</li>
    <li><strong>Submit BAST</strong> (Head) — bisa beberapa No. PO sekaligus lewat halaman Submit BAST. Finance melakukan <strong>verify / reject closing</strong>.</li>
    <li>Setelah semua BAST terverifikasi, proyek masuk <code>CLOSING</code> lalu <code>CLOSED</code>.</li>
</ol>

<h2>Budget dari form PO (opsional)</h2>
<p>
    Saat membuat atau mengedit PO yang masih <strong>DRAFT</strong>, Head dapat mengisi satu
    <strong>Budget</strong> langsung di form PO (deskripsi dan detail biaya; detail bisa diisi massal lewat <strong>Download Template</strong> dan <strong>Import Excel</strong>).
    Budget ini <strong>tidak punya approval sendiri</strong>: ia ikut alur PO &mdash; saat PO di-submit
    budget menjadi <code>CHECKED</code>, saat Finance verify menjadi <code>VERIFIED</code>, dan saat
    Approver approve menjadi <code>ACTIVE</code> (juga untuk Early Start), sehingga baru bisa dipakai
    Cost Request setelah PO di-approve. Bila PO dikembalikan untuk revisi, budget ikut kembali ke
    <code>DRAFT</code>. Saat PO ditutup (<code>CLOSED</code>), budget yang masih <code>ACTIVE</code>
    atau <code>CLOSING</code> otomatis menjadi <code>CLOSED</code>.
</p>
<ul>
    <li>PO Early Start yang diedit kembali ke DRAFT: budget yang sudah <code>ACTIVE</code> tetap <code>ACTIVE</code> dan read-only; submit, verify, dan approve ulang PO tidak menggerakkannya lagi. PO yang budget-nya sudah berjalan (bukan DRAFT) tidak dapat dihapus.</li>
    <li>Kosongkan bagian Budget bila PO tidak membawa budget &mdash; PO berjalan seperti biasa.</li>
    <li>Detail budget hanya bisa diubah lewat <strong>Edit PO</strong> selagi berstatus DRAFT; selebihnya tampil read-only.</li>
    <li>Budget yang salah dibuat dapat dihapus lewat tombol <strong>Hapus Budget dari PO ini</strong> di Edit PO, selama PO dan budget masih <code>DRAFT</code>. Penghapusan permanen (beserta detail, log, dan lampiran) dan tidak bisa dibatalkan; budget yang sudah dipakai dokumen lain tidak dapat dihapus.</li>
    <li>PO berstatus <code>VERIFIED</code> muncul di Todo List Approver sebagai &ldquo;PO &mdash; Approval Approver&rdquo;; budget yang ikut PO disetujui bersamanya.</li>
    <li>Budget tambahan tetap dibuat dari menu <strong>Budget Plan</strong> (alur approval Budget Plan biasa).</li>
</ul>

<h2>Fitur pendukung</h2>
<ul>
    <li><strong>Import Excel</strong> untuk Project + Milestone dan untuk daftar No. PO.</li>
    <li><strong>Export daftar PO ke Excel</strong> (superadmin &amp; approver).</li>
    <li>Kolom biaya proyek: <strong>Contract</strong>, <strong>Overhead</strong>, <strong>Fixed Cost</strong>.</li>
</ul>

<div class="pnd-callout warn">
    <i class="fas fa-exclamation-triangle"></i>
    <span>Billing (Invoice) baru bisa dibuat setelah BAST No. PO terkait selesai —
    gerbang ini dijaga di modul Invoice &amp; Cash Receipt.</span>
</div>

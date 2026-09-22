<h1>Budgeting (Budget Plan)</h1>
<p class="pnd-lead">
    Budget Plan adalah rencana anggaran per cost center untuk satu periode.
    Rencana ini menjadi acuan plafon saat Cost Request diajukan.
</p>

<h2>Siapa yang mengakses</h2>
<div class="pnd-roles">
    <span class="pnd-role">Superadmin</span>
    <span class="pnd-role">Finance / Finance Manager</span>
    <span class="pnd-role">Head (jalur Service Delivery)</span>
</div>
<p>
    Menu muncul di dua tempat dengan konteks berbeda: <strong>Finance &rarr; Budgeting</strong>
    (<code>?from=finance</code>) dan jalur <strong>Project</strong> (<code>?from=delivery</code>).
</p>

<h2>Status Budget Plan</h2>
<div class="pnd-status-flow">
    <span class="pnd-chip">DRAFT</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">SUBMITTED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">ACTIVE</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">CLOSED</span>
</div>
<p>Dari <code>SUBMITTED</code>, penyetuju dapat menolak sehingga status menjadi <code>REJECTED</code> dan rencana dikembalikan untuk diperbaiki.</p>

<h2>Menyusun rencana</h2>
<ol class="pnd-steps">
    <li>Klik <strong>Tambah</strong> — modal form terbuka dengan <strong>sidebar cost center</strong> di kiri dan <strong>tab-tab</strong> pengelompokan isian.</li>
    <li>Pilih periode dan cost center, lalu isi nominal per baris akun/kegiatan.</li>
    <li>Warna baris mengikuti <strong>NOB (Not of Budget)</strong> untuk memudahkan pembacaan.</li>
    <li>Lampirkan dokumen pendukung bila perlu.</li>
    <li>Simpan sebagai <strong>Draft</strong>. Selama Draft, rencana masih bisa diubah.</li>
    <li>Klik <strong>Submit</strong> (butuh minimal 1 baris detail) untuk mengirim ke persetujuan.</li>
</ol>

<h2>Setelah aktif</h2>
<ul>
    <li>Setiap perubahan tercatat di <strong>log</strong> Budget Plan.</li>
    <li>Nilai realisasi terhadap plan terlihat di halaman Summary dan Reports.</li>
    <li>Tabel mendukung <strong>sorting kolom</strong> dan <strong>filter</strong> ala Excel di header.</li>
</ul>

<div class="pnd-callout tip">
    <i class="fas fa-lightbulb"></i>
    <span>Pola tampilan Budget Plan (ukuran modal, sidebar, tab, warna NOB, sort,
    filter) dijadikan acuan desain untuk halaman index/show modul lain.</span>
</div>

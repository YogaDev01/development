<h1>Trouble Ticket</h1>
<p class="pnd-lead">
    Trouble Ticket mencatat gangguan di site dan penanganannya oleh teknisi. Biaya yang
    dibutuhkan di lapangan diajukan lewat tab <strong>Pengajuan</strong> pada tiket, terpisah
    dari jadwal kunjungan.
</p>

<h2>Siapa yang mengakses</h2>
<div class="pnd-roles">
    <span class="pnd-role">Teknisi (mengajukan biaya)</span>
    <span class="pnd-role">Supervisor team yang sama (review)</span>
    <span class="pnd-role">Superadmin</span>
</div>

<h2>Plan visit dan pengajuan biaya terpisah</h2>
<ul>
    <li><strong>Create Plan</strong> hanya menentukan tanggal kunjungan. Tidak ada isian biaya di sana.</li>
    <li><strong>Pengajuan biaya</strong> bisa dibuat kapan saja selama tiket belum <em>Closed</em>, dan satu tiket boleh punya banyak pengajuan.</li>
</ul>

<h2>Mengajukan biaya (teknisi)</h2>
<ol class="pnd-steps">
    <li>Buka tiket, pilih tab <strong>Pengajuan</strong>, lalu tekan <strong>Ajukan Biaya</strong>.</li>
    <li>Pilih <strong>Jenis</strong> (Cash Advance atau jenis Cost Request lain) dan <strong>Budget Plan</strong>. Untuk budget proyek, pilih juga <strong>Project</strong> (di Cost Request kolom ini tersimpan sebagai cluster).</li>
    <li>Jenis selain Cash Advance meminta <strong>Beneficiary</strong>. Pada Cash Advance, penerima dana otomatis beneficiary Internal Anda.</li>
    <li>Isi baris biaya: Sub-item budget, deskripsi, harga, dan qty. Total dihitung otomatis.</li>
    <li>Jenis selain Cash Advance <strong>wajib melampirkan berkas di setiap baris</strong> (nota/bukti). Cash Advance tidak wajib.</li>
    <li>Tekan <strong>Simpan Draft</strong> atau langsung <strong>Submit</strong> untuk diajukan ke supervisor.</li>
</ol>

<h2>Review (supervisor team yang sama)</h2>
<ul>
    <li><strong>Approve</strong> membuat <strong>Cost Request berstatus Draft</strong> atas nama Anda, lengkap dengan rincian dan lampirannya. Proses selanjutnya mengikuti alur Cost Request biasa.</li>
    <li><strong>Reject</strong> wajib beralasan; teknisi merevisi lalu mengajukan lagi.</li>
    <li><strong>Ubah</strong> boleh dilakukan sebelum approve. Setiap perubahan (baris ditambah/dihapus/diubah, total lama menjadi baru) tercatat di chat dan tab Log tiket.</li>
    <li>Anda tidak bisa me-review pengajuan buatan Anda sendiri.</li>
</ul>

<div class="pnd-callout">
    <i class="fas fa-info-circle"></i>
    <span>Halaman tiket <strong>tidak menampilkan</strong> sisa pagu, peringatan NOB, maupun kuota Cash Advance.
    Pemeriksaan itu terjadi di modul Cost Request saat CR di-submit. Pengajuan yang menunggu review juga muncul
    di <strong>Todo List</strong> supervisor.</span>
</div>

<h2>Setelah CR dibayar</h2>
<p>Setiap Cost Request yang berasal dari tiket ini dicatat pembayarannya di chat dan Log, lalu dapat di-settle dari tab
Pengajuan atau panel tahap tiket — satu tombol Settlement per CR.</p>

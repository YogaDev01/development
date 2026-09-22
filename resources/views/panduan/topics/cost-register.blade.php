<h1>Cost Register</h1>
<p class="pnd-lead">
    Cost Register adalah buku besar biaya — daftar seluruh realisasi biaya yang
    sudah dibayar, sebagai sumber rekonsiliasi dan audit.
</p>

<h2>Siapa yang mengakses</h2>
<div class="pnd-roles">
    <span class="pnd-role">Superadmin</span>
    <span class="pnd-role">Finance</span>
</div>

<h2>Isi</h2>
<ul>
    <li>Setiap baris = satu biaya yang sudah terbayar, ditarik dari Cost Request dan batch Treasury.</li>
    <li>Kolom umum: tanggal bayar, cost center, project, sumber dana, tipe biaya, nomor CR, nomor batch, dan nominal.</li>
    <li>Untuk Cash Advance, nilai yang tercatat mengikuti <strong>realisasi</strong> (setelah settle), bukan nilai advance awal.</li>
</ul>

<h2>Cara pakai</h2>
<ol class="pnd-steps">
    <li>Buka <strong>Finance &rarr; Cost Register</strong>.</li>
    <li>Gunakan filter periode / cost center / project sesuai kebutuhan.</li>
    <li>Cocokkan totalnya dengan mutasi bank dan angka realisasi di Summary.</li>
    <li>Ekspor bila perlu lampiran laporan.</li>
</ol>

<div class="pnd-callout">
    <i class="fas fa-info-circle"></i>
    <span>Cost Register bersifat <strong>hanya-baca</strong>. Koreksi dilakukan di
    dokumen sumber (Cost Request / batch Treasury), lalu Cost Register menyesuaikan.</span>
</div>

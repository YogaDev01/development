<h1>Project Monitoring</h1>
<p class="pnd-lead">
    Project Monitoring memantau pelaksanaan proyek pada level <strong>milestone</strong>:
    rencana vs aktual, status pengerjaan, progres kumulatif, dan diskusi tim per
    milestone maupun per Cost Request.
</p>

<h2>Siapa yang mengakses &amp; batasan</h2>
<div class="pnd-roles">
    <span class="pnd-role">Superadmin</span>
    <span class="pnd-role">Head</span>
    <span class="pnd-role">Project Manager (proyek yang di-assign)</span>
    <span class="pnd-role">Supervisor (hanya Update Log)</span>
</div>
<ul>
    <li>PM hanya melihat proyek yang menjadi tanggung jawabnya (berdasarkan access group / assignment).</li>
    <li><strong>Supervisor</strong> boleh menulis log aktivitas, tetapi <strong>tidak boleh</strong> mengubah status atau mengisi finish milestone.</li>
</ul>

<h2>Status milestone</h2>
<div class="pnd-status-flow">
    <span class="pnd-chip">NOT_STARTED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">ON_GOING</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">DONE</span>
</div>
<p>Manager dapat mengubah status kapan pun tanpa alur ACC/Tolak.</p>

<h2>Mengelola milestone</h2>
<ol class="pnd-steps">
    <li>Buka <strong>Project &rarr; Project Monitoring</strong>, pilih proyek.</li>
    <li><strong>Start Milestone</strong> (NOT_STARTED &rarr; ON_GOING) — wajib mengisi <strong>Actual Start</strong> (default hari ini bila dikosongkan).</li>
    <li>Selama berjalan, gunakan <strong>Update Log</strong> untuk mencatat progres/kendala; sebut rekan dengan <code>@</code> untuk mengirim notifikasi.</li>
    <li>Isi <strong>Actual Finish</strong> saat milestone selesai — status menjadi DONE. Setelah terisi, tanggal finish tidak bisa diubah lagi.</li>
</ol>

<h2>Membaca progres</h2>
<ul>
    <li><strong>Plan vs Actual</strong> per milestone dan secara kumulatif untuk seluruh proyek.</li>
    <li><strong>Kurva-S</strong> membandingkan rencana progres terhadap realisasi sepanjang waktu.</li>
    <li>Milestone yang <strong>lewat rencana</strong> namun belum DONE ditandai sebagai keterlambatan dan muncul di Service Delivery Summary.</li>
</ul>

<h2>Diskusi &amp; Cost Request</h2>
<p>
    Setiap milestone dan setiap Cost Request pada proyek punya kanal aktivitas
    sendiri. Pilih milestone atau Cost Request lebih dulu sebelum menulis pesan —
    sistem menolak pesan tanpa konteks.
</p>

<div class="pnd-callout tip">
    <i class="fas fa-lightbulb"></i>
    <span>Mention (<code>@nama</code>) mengirim notifikasi ke pengguna terkait dan
    tercatat di feed Dashboard "Last Activity".</span>
</div>

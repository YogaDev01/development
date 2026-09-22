<h1>Cost Request</h1>
<p class="pnd-lead">
    Cost Request (menu <strong>Finance &rarr; Cost Request</strong>) adalah pengajuan
    penggunaan biaya. Persetujuannya berjenjang dan alur setelah disetujui berbeda
    antara <strong>Cash Advance</strong> dan tipe lain.
</p>

<h2>Siapa yang mengakses</h2>
<div class="pnd-roles">
    <span class="pnd-role">Pengaju (Head / PM)</span>
    <span class="pnd-role">Head (Check)</span>
    <span class="pnd-role">Finance (Verify)</span>
    <span class="pnd-role">Approver / CEO (Approve)</span>
    <span class="pnd-role">Treasury (pembayaran)</span>
</div>

<h2>Alur persetujuan</h2>
<div class="pnd-status-flow">
    <span class="pnd-chip">DRAFT</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">SUBMITTED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">CHECKED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">VERIFIED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">APPROVED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">QUEUED</span>
</div>
<ul>
    <li><strong>SUBMITTED &rarr; CHECKED</strong> — oleh Head (approve / reject).</li>
    <li><strong>CHECKED &rarr; VERIFIED</strong> — oleh Finance (approve / reject).</li>
    <li><strong>VERIFIED &rarr; APPROVED</strong> — oleh Approver / CEO.</li>
    <li><strong>APPROVED &rarr; QUEUED</strong> — saat CR dimasukkan ke batch <a href="{{ route('panduan.show', 'treasury') }}">Treasury / Daily Transaction</a>. Label tombol: <strong>"Process"</strong>.</li>
</ul>

<h2>Setelah QUEUED — dua jalur</h2>
<h3>Cash Advance (siklus penuh)</h3>
<div class="pnd-status-flow">
    <span class="pnd-chip">QUEUED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">PAID</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">REALIZED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">CLOSED</span>
</div>
<p>Setelah dibayar (<code>PAID</code>), pengaju wajib <strong>menyelesaikan</strong> (label <strong>"Settle"</strong>) dengan bukti realisasi; selisih dikembalikan/ditagih, lalu <code>CLOSED</code>.</p>

<h3>Tipe non–Cash Advance</h3>
<div class="pnd-status-flow">
    <span class="pnd-chip">QUEUED</span><span class="pnd-arrow">&rarr;</span>
    <span class="pnd-chip">CLOSED</span>
</div>
<p>Langsung <code>CLOSED</code> saat ditandai paid — tidak ada tahap realisasi terpisah.</p>

<h2>Membuat Cost Request</h2>
<ol class="pnd-steps">
    <li>Klik <strong>Tambah</strong>, pilih <strong>tipe</strong> CR — sistem menampilkan <em>hint alur kerja</em> sesuai tipe.</li>
    <li>Isi header (project / cost center / sumber dana) dan tambahkan <strong>item</strong> permintaan beserta nominal.</li>
    <li>Unggah <strong>lampiran per item</strong> dan/atau <strong>lampiran global</strong> untuk seluruh CR.</li>
    <li>Simpan sebagai Draft, lalu <strong>Submit</strong>.</li>
</ol>

<h2>Takeout item saat approval</h2>
<p>
    Head, Finance, atau CEO dapat <strong>mengeluarkan (takeout)</strong> atau
    <strong>mengembalikan (restore)</strong> item tertentu selama proses approval.
    Nilai yang diproses otomatis berkurang sesuai item yang dikeluarkan.
</p>
<div class="pnd-callout warn">
    <i class="fas fa-exclamation-triangle"></i>
    <span>Bila <strong>seluruh</strong> item dikeluarkan, Cost Request otomatis
    berstatus <code>REJECTED</code>. Dari REJECTED, manajer dapat memilih
    <strong>Revise</strong> (kembali ke Draft) atau <strong>Cancel</strong>.</span>
</div>

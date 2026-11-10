<h1>Expense</h1>
<p class="pnd-lead">
    Expense (dulu Cost Register) mencatat biaya di luar Cost Request — tanpa budgeting —
    yang diajukan Finance Manager, disetujui CEO, lalu dibayar lewat Treasury.
</p>

<h2>Siapa yang mengakses</h2>
<div class="pnd-roles">
    <span class="pnd-role">Finance Manager</span>
    <span class="pnd-role">CEO (Approver)</span>
    <span class="pnd-role">Superadmin</span>
</div>

<h2>Isi</h2>
<ul>
    <li>Satu Expense = header (Beneficiary, Rekening Sumber, catatan, lampiran) + rincian per item.</li>
    <li>Setiap rincian memilih <strong>Akun</strong>; kategori biaya (Variable Cost / Fixed Cost / Overhead)
        mengikuti kategori akun tersebut.</li>
    <li>Setiap rincian memilih <strong>Pembebanan</strong>: akun Variable Cost wajib ke satu PO; Fixed Cost / Overhead
        bisa ke satu PO, seluruh PO yang dibuat pada tahun tertentu, beberapa PO terpilih, atau Cost Center (Non-PO).</li>
    <li>Pembebanan tahunan dan PO terpilih dibagi proporsional nilai PO:
        <em>Nilai × (Nilai PO ÷ Total Nilai PO)</em>, hanya untuk PO yang sudah disetujui. Pembagian dihitung ulang
        otomatis, jadi porsi berubah bila ada PO baru di tahun itu.</li>
    <li>Nilai Expense bisa dibebankan ke beberapa bulan: <strong>bagi rata</strong> atau <strong>bagi manual</strong>
        (tiap bulan tidak boleh 0 dan jumlahnya harus sama dengan nilai Expense).</li>
    <li>Biaya PO baru dihitung <strong>setelah dananya keluar lewat Treasury</strong> (batch dibayar &rarr;
        <em>Closed</em>, atau <em>Paid</em> untuk Expense Cash Advance). Expense yang masih
        <em>Draft</em>, <em>Submitted</em>, <em>Approved</em>, <em>Process</em>, <em>Rejected</em>, atau
        <em>Cancelled</em> belum menambah biaya PO mana pun.</li>
    <li>Kolom <strong>Overhead</strong> dan <strong>Fixed Cost</strong> pada PO adalah
        <strong>jumlah</strong> dari nilai yang diisi manual di form PO + Expense yang dibebankan ke PO itu.
        Karena dijumlahkan, biaya yang sudah dicatat lewat Expense jangan diisi ulang di form PO
        &mdash; nanti terhitung dua kali.</li>
    <li>Data Cost Register lama (Fixed Cost / Overhead per satu PO) tetap tampil dengan kode CREG-xxxxx.</li>
</ul>

<h2>Alur</h2>
<ol class="pnd-steps">
    <li>Finance Manager membuka <strong>Cost Management &rarr; Expense</strong>, klik <strong>Tambah Expense</strong>,
        isi rincian, lalu <strong>Simpan Draft</strong>.</li>
    <li>Finance Manager klik <strong>Ajukan</strong> (Beneficiary wajib terisi) &rarr; status <em>Submitted</em>.</li>
    <li>CEO <strong>Approve</strong> atau <strong>Reject</strong> (dengan alasan). Expense yang ditolak bisa
        <strong>Revisi</strong> (kembali ke Draft untuk diedit lalu diajukan ulang) atau <strong>Batalkan</strong>.
        Selama Draft, Expense bisa diedit.</li>
    <li>Expense <em>Approved</em> dimasukkan Treasury ke batch pembayaran (<em>Process</em>), lalu
        <em>Closed</em> setelah batch dibayar.</li>
</ol>

<div class="pnd-callout">
    <i class="fas fa-info-circle"></i>
    <span>Expense yang sudah diajukan tidak bisa dihapus; batalkan selama belum masuk batch Treasury.
    Expense Cash Advance: setelah dibayar, Finance Manager klik <strong>Settle</strong> untuk mengisi realisasi
    dan bukti; CEO menyetujui atau menolak. Kekurangan bayar dibayar lewat Treasury; kelebihan dicatat sebagai refund.</span>
</div>

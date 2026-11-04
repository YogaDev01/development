# Changelog

Sumber kebenarannya ada di `config/changelog.php` (ditampilkan sebagai panel
"What's New" di Dashboard). Berkas ini cerminannya untuk dibaca lewat repo /
git tanpa perlu membuka aplikasi.

## [3.2.1] - 2026-11-04 — Penyempurnaan Settlement, Treasury, Tampilan Tabel & Perbaikan PPh Invoice

Form Settlement kembali terisi dari item pengajuan dengan Sub COA terkunci, batch Treasury punya drawer di daftar, daftar Beneficiary lebih ringan, Surat Jalan bisa diexport, dan footer tabel di semua halaman tampil lebih bersih.

### Baru
- Settlement: bila total Settle lebih kecil dari nilai pengajuan, tersedia form opsional Bukti Transfer Pengembalian (PDF/JPG/PNG, maks. 10 MB).
- Settlement: badge Sub Utama / Sub Pecahan pada baris item yang dipecah dan badge Wajib pada Lampiran Item yang belum diisi.
- Treasury: detail batch tampil di drawer pada daftar, dengan aksi Submit, Hapus draft, Keluarkan item, Takeout/Reject, dan Tandai PAID.
- Penampil lampiran (carousel evidence) bersama di drawer dan halaman detail; klik gambar untuk zoom, PDF tampil langsung.
- Nilai di samping label Kode (Kode, Kode PO, Batch Code) bisa diklik untuk disalin.
- Export Excel daftar Surat Jalan, mengikuti filter yang aktif di tabel.
- Daftar Invoice & Cash Receipt: panel komentar/log berupa drawer dengan info singkat.
- Form pembayaran di halaman invoice kini punya kolom PPh opsional.

### Berubah
- Form Submit Settlement kembali terisi 1:1 dari item pengajuan (tombol Auto Fill dihapus); Sub COA baris item dan pecahannya dikunci sesuai item asal, hanya baris tambahan yang boleh memilih Sub COA.
- Lampiran Item wajib untuk baris Settle > 0 saat Submit (draft tidak wajib); Keterangan (kini "Log History Note") dan Lampiran Settlement global tidak lagi wajib. Kartu Bukti Settlement global dihapus dari form.
- Footer tabel (info jumlah, Page Size, Previous/Next) di semua halaman daftar tampil tanpa border/panel, mengikuti Cost Request.
- Daftar Beneficiary memuat baris per tab secara lazy sehingga halaman lebih ringan.
- Master PIC: Token dan Nominal hanya tampil untuk PIC internal; Nominal memakai pemisah ribuan dan Token ikut pencarian.

### Perbaikan
- Total invoice (captured_total) kini selalu mengikuti PPh saat sinkron dari Cash Receipt, termasuk pada data lama yang sudah basi; ditambah perintah `invoice:repair-captured-total` (dry run, `--apply` untuk menyimpan) yang dijalankan manusia.
- Cash Receipt: pembuatan menolak invoice ganda, dan header yang CANCELLED atau pernah punya alokasi tidak bisa dihapus.
- Rincian Settlement (modal Cost Request & Todo List): untuk item yang dipecah jadi beberapa baris, kolom Var kini tampil di baris terakhir item, bukan di baris pertama yang angkanya belum lengkap. Nilainya tidak berubah.

## [3.2.0] - 2026-10-30 — Approval Massal, Settlement Fleksibel, Draft Batch Treasury & Catatan Log

Approval kini bisa dilakukan massal dari daftar Cost Request, Budget Plan, PO, dan Treasury. Settlement Cash Advance lebih fleksibel (pilih Sub COA, pecah baris, baris tambahan), batch Treasury bisa disimpan sebagai Draft, Cost Register mengikuti alur Cost Payment, dan setiap tab Log punya catatan gaya chat.

### Baru
- Approve/Reject massal dari daftar Cost Request (tab Cash Advance & Cost Payment) untuk tahap Submitted, Checked, dan Verified; konfirmasi menampilkan total rupiah, jumlah CR NOB, dan CR dengan item takeout. CR yang statusnya sudah berubah otomatis dilewati dan dilaporkan.
- Approve massal tahap settlement Cash Advance (Head Check, Finance Verify, Finance Manager Approve/Close, Approver Second Approve) dari daftar Cost Request.
- Approve/Reject massal di daftar Budget Plan, Verify/Approve/Minta Revisi massal di daftar PO, serta Approve/Reject massal batch di daftar Treasury (maksimal 50 data per proses).
- Settlement Custom COA: tiap baris settlement boleh memilih Sub COA lain dari Budget Plan yang sama, satu item boleh dipecah menjadi beberapa baris, dan boleh ada baris tambahan tanpa item asal. Used per sub-item Budget Plan mengikuti Sub COA baris settlement.
- Form Submit Settlement dimulai kosong dengan tombol Auto Fill (menyalin item pengajuan) dan Tambah Baris; kolom Harga Satuan, Qty, dan Settle. Bila total Settle melebihi nilai pengajuan, Keterangan dan Lampiran Settlement wajib diisi.
- Batch Treasury bisa disimpan sebagai Draft: item dapat ditambah atau dikeluarkan dan draft dapat dihapus tanpa mengubah status sumbernya, lalu diajukan ke Approver lewat tombol Submit.
- Treasury: Approver kini bisa memilih Takeout (item kembali ke antrean) atau Reject (Cost Request ditolak) per item batch.
- Cost Register mengikuti alur Cost Payment: Draft → Submitted → Approved (oleh Approver) → Process → Closed, dengan rincian per item, riwayat status, catatan, dan approve/minta revisi.
- Budget Plan: tombol Tambah COA untuk menambah baris Account/Sub COA bernilai 0 pada Budget Plan yang sudah Active tanpa mengubah nilai budget.
- Catatan gaya chat di tab Log Cost Request, Budget Plan, PO, batch Treasury, dan Cost Register.
- Todo List: antrean baru untuk Finance/Approver - approval Cost Register, batch Treasury Draft, serta verifikasi closing Budget Plan dan PO.
- Lebar kolom tabel bisa diseret di lebih banyak daftar; lebar tersimpan per pengguna di peramban, klik dua kali untuk kembali ke bawaan.

### Berubah
- Settlement yang ditolak (Head, Finance, Finance Manager, atau Approver) kini berstatus Settle Rejected, bukan kembali ke Paid; pengaju merevisi lalu mengajukan ulang lewat form yang sama.
- Drawer Cost Request dan Todo List menampilkan Perbandingan per COA / Sub COA serta Rincian Settlement per baris dengan penanda "Baris tambahan" dan "Pindah dari".
- Template dan impor Excel Rincian Settlement menambah kolom Budget Detail ID (Sub COA); template lama tetap bisa diimpor.
- Data Cost Register lama dipetakan ke alur baru: Submitted menjadi Approved (siap batch) dan Paid menjadi Closed.
- Tampilan diperhalus: font Inter, lapisan gaya baru untuk tabel, modal, tombol, dan badge di seluruh halaman, serta komponen UI baru (prefix tw:) yang dibangun tanpa proses build di server.
- Lampiran per item dan Note per baris di form settlement kini opsional.

### Perbaikan
- Lampiran per baris settlement tetap menempel pada barisnya setelah settlement ditolak lalu diajukan ulang.
- Dokumentasi alur settlement dikoreksi agar sesuai perilaku aplikasi (reject berhenti di Settle Rejected).

## [3.1.1] - 2026-10-24 — Perbaikan Tampilan Bottom Navbar Setelah Deploy

Bottom navbar di ponsel kembali tampil rapi setelah deploy: tombol tidak lagi polos dan logo tidak membesar.

### Perbaikan
- Bottom navbar yang tidak tampak dan berganti tombol tanpa gaya serta logo perusahaan raksasa setelah deploy, karena peramban masih memakai berkas CSS lama dari cache. Versi aset dinaikkan agar CSS terbaru dimuat.
- Logo pada tombol pemilih aplikasi di bottom navbar diberi ukuran bawaan sehingga tidak membesar walau CSS belum termuat.

## [3.1.0] - 2026-10-22 — Tampilan Desktop Lebih Luas, Pengajuan Biaya Tiket & Budget Plan di PO

Aplikasi di desktop kini tampil setara zoom 80% agar lebih luas, tabel data didesain ulang
berlapis, dan modal detail memenuhi tinggi layar. Trouble Ticket mendapat tab Pengajuan biaya
(pra-Cost Request), dan Budget Plan kini bisa diisi langsung di form PO.

### Baru
- Baris tabel yang dicentang diberi penanda aksen di tepi kiri, dan kolom pertama tabel menempel saat tabel digulir mendatar.
- Footer tabel (jumlah data dan pagination) tampil dalam satu panel tersendiri.
- Trouble Ticket: tab Pengajuan untuk mengajukan biaya lapangan (banyak pengajuan per tiket, revisi, lampiran per baris). Supervisor team teknisi me-review, boleh mengubah isi, lalu menyetujui - pengajuan yang disetujui otomatis menjadi Cost Request berstatus Draft.
- Todo List: antrean "Trouble Ticket - Review Pengajuan" untuk supervisor dan "PO - Approval Approver" untuk PO berstatus Verified.
- Budget Plan bundled: Head/superadmin dapat mengisi satu Budget Plan langsung di form PO; statusnya ikut approval PO, dan bisa dihapus lewat Edit PO selama masih Draft.
- Panduan: topik Trouble Ticket di aplikasi Operations dan keterangan Budget Plan di form PO.

### Berubah
- Tampilan desktop dibuat setara zoom peramban 80%: lebih luas dan lebih kecil. Layar tablet dan ponsel tidak berubah.
- Tabel data sejajar dengan tepi halaman, label "Cari :" dan judul "Daftar ..." dihapus karena sudah ada di toolbar dan topbar.
- Alur status (stepper) tampil tanpa kartu putih, dengan node bulat dan garis tipis; jaraknya ke toolbar pencarian dirapatkan.
- Modal ditempatkan di tengah layar; modal detail Purchase Order, Budgeting, dan Cost Request memenuhi tinggi layar dan sedikit lebih lebar.
- Trouble Ticket: Create Plan kini hanya untuk jadwal kunjungan; biaya diajukan lewat tab Pengajuan. Pembayaran dan settlement dicatat per Cost Request, dan data biaya lama tetap terbaca.
- Plan Finish dan auto-closing Budget Plan berbasis tanggal dihapus. Budget Plan milik PO kini otomatis Closed saat PO ditutup; Budget Plan non-PO tetap ditutup manual.
- Budget Plan bundled tidak lagi digandakan di Todo List dan Summary Task karena sudah muncul sebagai tugas PO.

### Perbaikan
- Latar gelap modal menutup seluruh layar, dan menu aksi serta dropdown filter muncul tepat di tempatnya.
- Batang gulir halaman Todo List kembali berada di tepi kanan layar.

## [3.0.9] - 2026-10-21 — Tampilan Mobile yang Lebih Rapi di Seluruh Aplikasi

Seluruh halaman kini lebih bersih dan lega di layar ponsel: padding kartu tidak lagi
berlapis, toolbar dan alur status lebih ringkas, semua daftar data tampil sebagai kartu,
dan Todo List beserta detailnya nyaman dipakai dengan satu tangan.

### Baru
- Todo List di ponsel: worklist, Tugas/Assignment, panel detail Cost Request, dan modal View Budget tampil sebagai kartu ringkas; angka anggaran disusun dua kolom dan pilihan "Pilih semua" tetap tersedia.
- Semua daftar data di area halaman - termasuk tabel yang dimuat ulang lewat filter - ikut tampil sebagai kartu di ponsel.

### Berubah
- Di ponsel, kartu halaman hanya punya satu lapis padding sehingga isi tidak lagi menjorok jauh dari tepi layar.
- Toolbar daftar di ponsel: pencarian selebar layar, tombol filter berjajar, dan tombol utama (Tambah/Buat) mengisi satu baris penuh.
- Alur status (stepper) di atas daftar Cost Request, Budgeting, PO, Collection, dan lainnya menjadi satu baris yang bisa digeser di ponsel.
- Halaman Profil di ponsel: sampul lebih pendek, foto di tengah, dan identitas tampil sebagai teks biasa.

### Perbaikan
- Panel detail Cost Request di Todo List tidak lagi terpotong di sisi kiri layar ponsel.
- Kolom tabel Todo List tidak lagi saling menimpa di layar sempit.
- Judul kartu tidak lagi tertimpa label status, dan isi bertumpuk (catatan + nama, badge anggota) tidak lagi terhimpit.
- Form filter Team tidak lagi terpotong di tepi kanan, dan ikon cari Trouble Ticket tidak lagi menimpa teks.

## [3.0.8] - 2026-10-19 — Impor & Ekspor Site, Stop Chat Tiket & Landing Sesuai Peran

Master Site kini bisa diimpor dan diekspor, diskusi tiket hanya ditutup oleh
superadmin lewat Stop Chat, dan setiap pengguna mendarat langsung di aplikasi
yang sesuai perannya.

### Baru
- Master Site bisa diimpor dari berkas dan diekspor, mengikuti pola master data lainnya.
- Stop Chat: diskusi tiket tetap terbuka walau tiket sudah Closed, dan hanya superadmin yang bisa menghentikan atau membukanya kembali.

### Berubah
- Setelah login, pengguna langsung mendarat di aplikasi yang sesuai perannya - teknisi ke Operations - tanpa mampir di halaman pemilih aplikasi.
- Tiket di tahap On Site tidak lagi menampilkan form penutupan: pengerjaan di site dimulai dulu, tiket ditutup dari In Progress.
- Hak akses Site dan Trouble Ticket dirapikan agar tombol yang tampil sesuai dengan yang benar-benar boleh dijalankan.

### Perbaikan
- Pilihan Site Keeper diambil dari PIC berjenis Site Keeper di Personal Master, bukan lagi dari seluruh akun pengguna yang tidak ada kaitannya dengan penjaga site.
- Pilihan Teknisi Backup tidak lagi dibatasi customer site, sehingga teknisi yang memang tersedia tidak hilang dari daftar.
- Popup pemilih tanggal tetap melebar menyamping saat tombol Terapkan ditampilkan.

## [3.0.7] - 2026-10-19 — Tampilan Kartu Mobile pada Daftar Data

Daftar data (tabel) kini tampil sebagai kartu di layar ponsel, tampilan listing di desktop dan mobile lebih rapi tanpa bingkai ganda, dan tersedia perintah untuk membuat BAST dari invoice lama.

### Baru
- Daftar data di layar 640px ke bawah tampil sebagai kartu: setiap nilai diberi label kolom, status di baris judul, tombol aksi tetap terjangkau, dan baris total menjadi kartu ringkasan di atas daftar. Tabel pivot Finance Summary dan Trouble Ticket (punya tampilan kartu sendiri) tidak ikut.
- Perintah bast:create-from-invoice (default dry run, dapat di-rollback) untuk membuat BAST dari invoice lama bagi No. PO yang belum punya BAST; nilai dan status Invoice serta status No. PO tidak berubah.

### Berubah
- Kartu pembungkus daftar data dilepas bingkainya sehingga tabel langsung berada di margin halaman, baik di desktop maupun mobile.

### Perbaikan
- Popup periode custom (datepicker) dengan tombol Terapkan kini tetap berbentuk landscape.
- Perintah invoice:split-synthetic-bast dan rollback-nya tidak lagi berakhir dengan galat palsu pada pemeriksaan akhir run walaupun pemecahan BAST berhasil.

## [3.0.6] - 2026-10-19 — Penagihan Invoice per BAST

Invoice kini ditagih per BAST (1 Invoice = 1 BAST), satu No. PO bisa memiliki banyak BAST, dan tersedia perintah migrasi data lama beserta pengaman dan rollback.

### Baru
- No. PO kini bisa memiliki banyak BAST; setiap submission BAST menjadi satu baris BAST beserta lampirannya, dan Superadmin dapat menghapus BAST yang belum ditagih (soft delete dengan alasan).
- Form Invoice memilih BAST yang ditagih: nilai Invoice sebelum PPN harus sama persis dengan nilai BAST, dengan pengecekan langsung di form create/edit.
- Status No. PO mengikuti BAST dan Invoice: PARTIAL, BAST_SUBMITTED (Tandai Lengkap), INVOICED saat semua BAST tertagih, dan PAID saat semua Invoice-nya Paid. Invoice lama tanpa BAST tetap memakai alur lama.
- Perintah migrasi data lama (default dry run, hasil CSV, dapat di-rollback): invoice:link-bast, bast:mark-backfill, invoice:split-synthetic-bast, dan invoice:report-po-paid-drop.
- Tampilan Invoice (daftar, detail, cetak) menampilkan No. BAST; Modal Detail PO menampilkan selisih Contract terhadap BAST + Restitusi.
- Cost Request: badge Rejected dan Settle Rejected di awal stepper; lampiran di side modal dibuka dalam carousel; tombol Terapkan periode custom kini ada di dalam datepicker.
- Cash Receipt: tombol Hapus untuk bukti terima Draft yang belum teralokasi, dropdown customer bisa dicari, dan bukti transfer bisa di-drag & drop atau di-paste.
- Perintah budget:close-by-closed-po (default dry run) untuk menutup Budget Plan Active milik PO yang sudah Closed.

### Berubah
- Submit BAST yang membuat total BAST melebihi nominal No. PO kini diblokir.
- Pembuatan, perubahan, pembatalan, dan pembayaran Invoice serta Cash Receipt memakai penguncian baris No. PO, BAST, dan Invoice dengan urutan tetap agar tidak terjadi penagihan ganda.
- Collection: kolom Payment kini hanya menampilkan nilai cash receipt, tanpa settle PPh.
- Catat Penerimaan: Diterima + PPh tidak boleh melebihi Sisa Tagihan; bila kurang dari Sisa Tagihan, wajib konfirmasi dan komentar yang tersimpan di Log & Approval invoice terkait.

### Perbaikan
- Kolom alasan pada dialog Hapus BAST kini bisa diklik dan diisi.

## [3.0.5] - 2026-10-17 — Status Partially Invoiced & Partially Paid pada Purchase Order

Workflow Purchase Order mendapat dua status baru yang mengikuti progres
penagihan dan pembayaran No. PO, dan kolom uang di daftar PO kini bisa
diurutkan menurut angka.

### Baru
- Status PO Partially Invoiced (sudah ada tagihan, termasuk penagihan sebagian atas BAST Partial, tetapi belum semua No. PO tertagih penuh) dan Partially Paid (sebagian No. PO sudah Paid), dihitung otomatis dari status No. PO, berlaku dari Active maupun Waiting Invoice, dan menjadi Paid saat seluruh No. PO Paid.
- Kedua status tampil di stepper, filter, dan label status di daftar PO.

### Perbaikan
- Kolom uang di daftar PO (Contract sampai Net Profit) kini diurutkan menurut nilai angka, bukan menurut teks (9.000.000 tidak lagi mendahului 175.700.000).

## [3.0.4] - 2026-10-17 — Restitusi PO, Invoice tanpa PPN & Budget Otomatis Closed

Purchase Order mendapat kolom Restitusi, modal detail menampilkan nilai
Invoice sebelum PPN, dan Budget Plan ikut CLOSED saat PO ditutup.

### Baru
- Purchase Order punya kolom Restitusi (input nominal di form Tambah/Edit PO, tampil di antara Contract dan PO Value pada daftar PO).
- Tab Invoice pada modal detail PO punya kolom Invoice berisi nilai sebelum PPN, beserta totalnya.

### Berubah
- Saat PO CLOSED, Budget Plan berstatus ACTIVE atau CLOSING miliknya otomatis menjadi CLOSED.

### Perbaikan
- Nilai Budget pada daftar PO kini ikut menghitung Budget Plan berstatus CLOSING, tidak hanya ACTIVE dan CLOSED.

## [3.0.3] - 2026-10-16 — Teknisi Backup per Site & Pemakaian Anggaran Mengikuti Settlement

Kandidat Change Teknisi kini diambil dari daftar Teknisi Backup yang
ditetapkan per site, dan pemakaian anggaran mengikuti nilai settlement
untuk Cash Advance yang sudah disettle.

### Baru
- Site punya tab Teknisi Backup: daftar teknisi cadangan per site, dipilih dari teknisi milik customer yang sama dan boleh lebih dari satu.

### Berubah
- Change Teknisi mengambil kandidat dari daftar Teknisi Backup site - diisi manusia di Master Data > Site, menggantikan penentuan otomatis berdasarkan homebase/cluster terdekat.
- Plan Visit hanya boleh dibuat atau diubah oleh teknisi yang ditugaskan, supervisor tiket, atau peran penyetuju penutupan.

### Perbaikan
- Pemakaian anggaran (kolom Used di Budgeting dan pengecekan NOB) memakai nilai settlement untuk Cash Advance yang sudah diajukan settlement-nya - termasuk settlement bernilai 0 saat dana dikembalikan utuh - dan nilai pengajuan untuk selainnya.
- Tombol pada panel Filter tidak lagi berlipat ganda setelah tabel dimuat ulang, misalnya saat rentang tanggal di Cost Request diganti.

## [3.0.2] - 2026-10-15 — Preventive Maintenance Site, Change Teknisi Terdekat & Pemilih Rentang Tanggal

Site mendapat tab Preventive Maintenance dan Document, Change Teknisi
mengurutkan kandidat dari yang terdekat, dan filter tanggal di banyak modul
memakai satu kotak rentang dengan dua kalender.

### Baru
- Site: tab Preventive Maintenance (satu catatan per kunjungan, lengkap dengan serial number perangkat sebelum dan sesudah) dan tab Document untuk berkas site.
- Trouble Ticket: Change Teknisi menawarkan kandidat berurut dari yang terdekat - homebase sama dulu, lalu satu cluster, baru sisanya berdasarkan jarak.
- Satu form Buat Trouble Ticket bisa menyimpan beberapa tiket sekaligus untuk site dan teknisi yang sama.
- Detail invoice: Surat Jalan bisa dilihat lewat modal langsung dari tab Log, tanpa berpindah halaman dan kehilangan posisi.

### Berubah
- Filter tanggal memakai satu kotak rentang dengan dua kalender berdampingan dan pemilih bulan/tahun yang bisa dicari - dipakai di Cost Request, Cash Management, Daily Transaction, Reports, dan riwayat aktivitas di Profil.
- Pengguna yang belum memilih aplikasi sendiri langsung dibawa ke aplikasi yang sesuai perannya, dan aplikasi yang sudah punya modul tidak lagi mampir ke halaman pemilih.

### Perbaikan
- Refund Cash Advance: pengajuan yang belum pernah disubmit settlement-nya tidak lagi tertukar dengan settlement bernilai 0 - yang pertama refundnya 0, yang kedua refundnya senilai penuh dana yang diterima.

## [3.0.1] - 2026-10-13 — Trouble Ticket Alur 7 Tahap, Site Master Lebih Ringkas & Perbaikan Settlement

Trouble Ticket kini memakai alur 7 tahap dengan SLA travel bertahap dan persetujuan
penutupan, form Site dan alur statusnya disamakan dengan SLAmeet, serta beberapa
perbaikan teks Settlement dan export Cost Request.

### Baru
- Trouble Ticket memakai alur 7 tahap, dengan persetujuan penutupan sebelum tiket ditutup.
- Master RCA berjenjang, dan severity tiket dihitung otomatis dari aging.
- SLA travel dihitung bertahap mengikuti tahap tiket.
- Pengaturan Trouble Ticket: kebijakan SLA punya nama sendiri, dan rentang aging tiap severity bisa diatur dari halaman Pengaturan - tidak lagi tetap di kode.
- Cost Request: filter Last Update dengan rentang cepat 12 jam sampai 1 tahun, plus pilihan rentang tanggal sendiri.

### Berubah
- Form dan tabel Site Master disederhanakan; form Site dan alur status tiket disamakan dengan SLAmeet.
- Teks baris Request dan Settle di tab Settlement kini memakai catatan yang diketik pengaju/penginput, bukan awalan "Akun - Sub COA" yang berulang.
- Invoice yang dicetak menampilkan Total = Invoice + PPN; baris PPh dipotong di muka tidak lagi dicetak.
- Notifikasi lonceng muncul begitu aksinya selesai tanpa menunggu antrean; pesan WhatsApp tetap lewat antrean seperti sebelumnya.
- Trouble Ticket masuk ke grup navigasi bersama modul operasional lainnya, dan tampilannya di layar HP dirapikan.

### Perbaikan
- Kolom Transaction ID dan Account di export Cost Request kini merujuk batch Treasury yang sama.
- Qty di Summary Purchase Order kini menghitung No. PO, bukan jumlah project; Qty Payment hanya menghitung No. PO yang sudah diterima pembayarannya.
- Form Settlement: tombol Simpan tetap bisa menyimpan draft yang belum lengkap - pemeriksaan kelengkapan hanya berlaku saat Submit.

## [3.0.0] - 2026-10-09 — Penugasan, Profil Baru, Jejak Aktivitas & Alur Proyek Dua Langkah

Rilis besar: modul Penugasan antar-pengguna, halaman Profil yang dirombak
(info, tim, pengaturan, jejak aktivitas), pencatatan jejak aktivitas pengguna,
lupa sandi lewat kode email, serta alur persetujuan Purchase Order dua langkah
(Finance verifikasi, lalu Approver menyetujui) dengan status settlement baru.

### Baru
- Modul Penugasan: tugas bebas antar-pengguna (siapa pun bisa menugaskan siapa pun), lengkap dengan lampiran, linimasa perubahan, serta status Selesai/Batal/Buka lagi. Penugasan juga muncul di Todo List.
- Halaman Profil dirombak: tab Info, Tim, Pengaturan, Todo, dan Jejak Aktivitas; unggah foto profil & foto sampul, pilihan aplikasi default saat masuk, serta pengaturan notifikasi.
- Jejak Aktivitas Pengguna: catatan terpusat siapa melakukan apa dan kapan, bisa dilihat di tab Profil dan per-pengguna dari master User.
- Lupa Sandi di halaman masuk: reset sandi lewat kode yang dikirim ke email, dengan pembatasan percobaan agar aman.
- Pengingat Todo otomatis (dijalankan terjadwal) dan ringkasan waktu respon di Todo List.
- Budget Plan: ekspor terpisah untuk Biaya dan Budget, serta penyimpanan draft realisasi agar isian tidak hilang.

### Berubah
- Persetujuan Purchase Order kini dua langkah: Finance memverifikasi dulu, lalu Approver menyetujui — dengan opsi Early Start, Waiting PO, dan Active, serta jalur revisi dari Finance.
- Alur settlement Cost Request menambah status Settle Rejected sehingga penolakan settlement punya keadaan tersendiri dan bisa ditindaklanjuti dengan jelas.
- Halaman masuk didesain ulang agar lebih rapi dan selaras dengan tampilan aplikasi.

## [2.5.10] - 2026-10-07 — Rekap Collection di Daftar Purchase Order

Daftar Purchase Order kini menampilkan Invoice, PPN, PPH, dan Total per PO
dengan rumus yang sama dengan Daftar Invoice, dan angkanya bisa diklik untuk
membuka Collection yang sudah tersaring.

### Baru
- Daftar Purchase Order menampilkan rekap Collection per PO: Invoice, PPN, PPH, dan Total - memakai nama dan rumus yang sama persis dengan Daftar Invoice, dijumlahkan dari seluruh invoice PO tersebut.
- Keempat angka itu bisa diklik untuk membuka Collection di tab baru dengan daftar yang sudah tersaring ke PO bersangkutan.

### Berubah
- Kolom Received Payment di Daftar Purchase Order digantikan rekap Collection yang lebih rinci.
- Pencarian dari tautan luar mengisi kotak Cari di Collection, bukan menyaring diam-diam, sehingga terlihat kenapa daftarnya terpotong dan bisa dihapus.

### Perbaikan
- Invoice berstatus Cancelled tidak ikut dijumlah dalam rekap Collection per PO, agar nilai tagihannya tidak terlalu besar.

## [2.5.9] - 2026-10-06 — Refund & Biaya Terpakai Langsung Mengikuti Settlement

Refund Cash Advance dan nilai biaya terpakai kini langsung mengikuti settlement
begitu settlement diisi, tidak lagi menunggu settlement disetujui — sejalan dengan
Expense Claim yang sudah lebih dulu tampil sejak realisasi diinput.

### Berubah
- Refund Cash Advance (Pengajuan - Settlement) kini tampil begitu settlement diisi, tidak lagi menunggu settlement disetujui; berlaku di detail CR, modal Budget Plan, Purchase Order, dan export.
- Nilai biaya terpakai per CR (kolom realisasi di modal Budget Plan dan Variable Cost di Purchase Order) memakai nilai settlement begitu settlement Cash Advance diisi; tipe selain Cash Advance tetap memakai nilai pengajuan.

### Perbaikan
- Variable Cost di Purchase Order kini memakai aturan yang sama dengan rincian Cost Request, sehingga kedua angka tidak lagi bisa berbeda untuk CR yang sama.

## [2.5.8] - 2026-10-05 — Catat Penerimaan Tanpa Muat Ulang, Filter Checklist & Penyaring Daftar Panjang

Memilih customer di Catat Penerimaan tidak lagi membuang isian yang sudah
diketik, filter Cash Management bisa dipilih lebih dari satu, dan daftar
panjang di form batch Treasury, Surat Jalan, serta Cash Management kini
punya penyaringnya sendiri.

### Baru
- Catat Penerimaan: pencarian customer di halaman Cash Management, dan penyaring manager (PM Internal) di form batch Treasury maupun penyaring customer di form Surat Jalan - menyaring hanya menyembunyikan grup lain, pilihan yang sudah dicentang tidak ikut terlepas.
- Kolom di modal View Budget bisa diurutkan; pengurutan bekerja di tingkat grup Chart of Account sehingga sub-itemnya selalu ikut induknya.
- Kode Cost Request di worklist bisa diklik untuk membuka drawer detailnya, dan kode PO membuka modul Purchase Order langsung pada data yang dituju.

### Berubah
- Memilih customer di Catat Penerimaan tidak lagi memuat ulang halaman - hanya tabel invoice yang berubah, sehingga tanggal, rekening, dan catatan yang sudah diisi tetap utuh.
- Filter Arah, Rekening, dan Sumber di Cash Management kini berupa checklist yang bisa dipilih lebih dari satu; tautan lama dengan satu nilai tetap bekerja.
- Popover filter tidak lagi memuat tombol Reset di halaman yang sudah punya tombol hapus filter tersendiri di bilah kendali.
- Cost Register menampilkan keterangan status pengajuan di Treasury (Di Treasury / Dalam Batch) pada barisnya.

### Perbaikan
- Modal yang dibuka dari dalam drawer tidak lagi tertutup panel drawer, dan drawer yang dibuka dari dalam modal tampil di atasnya.
- Centang di section Treasury Todo List dilepas saat grup PO-nya ditutup, dan grup yang masih tertutup dibuka lebih dulu saat memilih semua - batch tidak lagi bisa berisi Cost Request yang tidak pernah terlihat.

## [2.5.7] - 2026-10-04 — Rincian Todo List Seragam & Total Tanpa Item Takeout

Tabel rincian di Todo List kini sama persis dengan drawer Cost Request, dan
item yang di-takeout tidak lagi ikut dijumlah di subtotal maupun total.

### Berubah
- Tabel rincian di Todo List disamakan dengan drawer Cost Request: kolomnya sejajar dan pengelompokan per Chart of Account memakai susunan yang sama.
- Lampiran per baris di Todo List dibuka lewat satu ikon view di kolom Action, tidak lagi digelar inline; PDF dan dokumen ikut dalam viewer sebagai kartu nama berkas.

### Perbaikan
- Item yang di-takeout tidak lagi ikut dijumlah di subtotal grup maupun total bawah, sehingga angkanya konsisten dengan nilai Cost Request yang benar-benar diproses.

## [2.5.6] - 2026-10-02 — Catatan Perubahan Settlement, Urutan Nominal & Rincian per Chart of Account

Baris Settlement yang melebihi nilai pengajuan kini wajib disertai catatan
perubahan, kolom nominal diurutkan sebagai angka di seluruh tabel, dan
rincian Cost Request dikelompokkan per Chart of Account.

### Baru
- Catatan perubahan per baris Settlement: alasan kenapa Description Settle atau nilainya berbeda dari pengajuan, dan wajib diisi bila nilai Settle melebihi nilai Request.
- Kolom Bank di Daftar PIC menandai In-House untuk PIC yang sudah punya beneficiary terhubung, menggantikan rekening yang dulu disimpan di PIC.

### Berubah
- Kolom nominal di seluruh tabel kini diurutkan sebagai angka, bukan teks - 900.000 tidak lagi dianggap lebih besar dari 1.000.000.
- Rincian Cost Request pada tab Request dan Settlement dikelompokkan per Chart of Account, mengikuti tampilan modal Budgeting.
- Lampiran per baris dibuka lewat satu tombol view di kolom Action, bukan digelar sebagai grid thumbnail; PDF dan dokumen ikut dalam viewer sebagai kartu nama berkas.
- Tab Settlement hanya tampil untuk Cash Advance, dan kembali ke tab Info bila CR yang dibuka tidak memilikinya.

## [2.5.5] - 2026-10-02 — Expense Claim di View Budget Sesuai Daftar Worklist

Angka Expense Claim pada modal View Budget kini cocok dengan kolom Expense
Claim di daftar Todo List, dan baris yang memunculkannya ditandai langsung
di tempat angkanya.

### Baru
- Penanda New Claim dan Claim Approved muncul tepat di baris yang memunculkannya di modal View Budget, tidak hanya di ringkasan Chart of Account-nya.

### Berubah
- Modal View Budget: angka Expense Claim kini sama dengan kolom Expense Claim di daftar worklist - kelebihan tiap baris dibagi sebanding terhadap selisih bersih satu Cost Request, bukan dijumlah mentah sehingga baris yang hemat terabaikan.
- Rincian Cash Advance di modal View Budget diambil dari baris Settlement bila settlement-nya sudah ada, dan ditandai Settlement; selama belum ada, baris pengajuan yang dipakai.

## [2.5.4] - 2026-10-02 — Keandalan Transaksi Saat Diproses Bersamaan

Rilis perbaikan: transaksi keuangan yang diproses dua orang (atau dua tab) pada
saat yang sama kini ditangani dengan aman - pembayaran, batch Treasury,
settlement, penerimaan kas, invoice, dan Surat Jalan tidak lagi bisa tercatat
ganda. Pengguna yang kalah cepat mendapat pesan untuk memuat ulang halaman.

### Berubah
- Status invoice kini menyebut pihak yang benar: verifikasi oleh Accounting / Finance Manager, persetujuan oleh Finance Manager (sebelumnya tertulis CEO).
- Bila data yang sedang diproses baru saja diubah pengguna lain, aksi dibatalkan dengan pesan "Status sudah berubah" atau "baru saja diproses pengguna lain - muat ulang halaman", alih-alih menimpa hasil orang lain.

### Perbaikan
- Satu Cost Request tidak lagi bisa masuk ke dua batch Treasury aktif saat dua batch dibuat bersamaan.
- Mark Paid batch Treasury yang ditekan dua kali tidak lagi menggandakan lampiran bukti transfer, log, atau menimpa biaya bank.
- Verifikasi settlement yang dilakukan bersamaan tidak lagi membuat Second Payment (Expense Claim) ganda.
- Second Payment dari batch yang ditolak atau dikeluarkan dari batch kini bisa dimasukkan ke batch berikutnya (sebelumnya tertahan dan Cost Request-nya tidak bisa diselesaikan).
- Alokasi bukti terima tidak lagi bisa melebihi uang yang diterima, dan tidak bisa tercatat pada bukti terima yang sedang dibatalkan.
- Pencatatan penerimaan untuk beberapa invoice sekaligus kini selalu membaca sisa tagihan terbaru.
- Revise, approval massal, dan takeout item Cost Request tidak lagi menimpa keputusan pengguna lain; Cost Request yang sudah dibatalkan tidak bisa hidup kembali, dan takeout item terakhir selalu menolak CR secara otomatis.
- Approval massal Cost Request hanya memproses CR yang memang boleh dilihat pengguna tersebut.
- Verifikasi, approval, revisi, dan pembatalan invoice, pencatatan pembayaran, settle PPh, serta Surat Jalan aman saat diproses bersamaan; satu invoice tidak lagi bisa masuk ke dua Surat Jalan.
- Penomoran dokumen tidak lagi gagal (error 500) saat dua dokumen pertama sebuah periode atau PO baru disimpan bersamaan.

## [2.5.3] - 2026-10-01 — Filter Active Cash Advance, Tab Beneficiary & Hak Akses Budgeting

Kartu Active Cash Advance di Summary kini bisa disaring per PIC, Aging, dan
PO tanpa memuat ulang halaman, Beneficiary mendapat tab seperti PIC Master,
dan antrean Budgeting di Todo List mengikuti hak akses yang sama dengan
halaman Budgeting.

### Baru
- Kartu Active Cash Advance di Summary punya filter PIC, Aging, dan PO yang bekerja langsung tanpa memuat ulang halaman - subtotal PIC, subtotal Manager, dan Grand Total ikut dihitung ulang.
- Kode PO pada kartu Active Cash Advance bisa diklik untuk membuka modul Purchase Order bagi role yang berkepentingan; role lain tetap melihat kodenya.
- Kolom PO Reference pada Active Cash Advance, menunjukkan PO yang membiayai Cash Advance lewat Budget Plan-nya.
- Beneficiary punya tab Internal, Outsider, dan Database seperti PIC Master; berpindah tab tidak memuat ulang halaman dan kartu ringkasannya ikut menyesuaikan.
- Todo List: tombol View Budget pada header grup PO dan ringkasan Budget Plan milik tiap Cost Request.

### Berubah
- Daftar pilihan pada filter kolom ikut menyempit mengikuti tab yang sedang aktif, sehingga tidak lagi menawarkan nilai yang pasti mengosongkan tabel.
- Nama file pada Lampiran Tambahan di form Cost Request bisa diklik untuk diperiksa, termasuk berkas yang masuk lewat drag & drop atau tempel.
- Master PIC: tab Outsider memuat seluruh jenis selain Internal (Customer, External, Site Keeper); pemisahan per jenis tetap lewat filter Type.

### Perbaikan
- Antrean Budgeting di Todo List memakai aturan hak akses yang sama dengan halaman Budgeting, sehingga tidak lagi menampilkan Budget Plan dari PO yang tidak boleh dilihat.
- Pemakaian anggaran tidak lagi menghitung Cost Request yang sudah tuntas Second Approve, termasuk yang kembali masuk batch Treasury untuk membayar Expense Claim.
- Item yang di-takeout tidak ikut dijumlah di Todo List, agar grand total-nya sama dengan nilai Cost Request yang disetujui.

## [2.5.2] - 2026-10-01 — Perbaikan Beneficiary, Riwayat Pengajuan & Hitungan Pemakaian Anggaran

Rilis perbaikan: halaman Beneficiary kembali bisa dibuka, riwayat pengajuan
beneficiary di Todo List lebih mudah dibaca, dan CR yang sedang di tahap Settle
Approve / Second Approve kini ikut terhitung di Used, Variable Cost, pengecekan
NOB, dan laporan.

### Berubah
- Cost Request: rentang tanggal bawaan kini dimulai dari awal bulan berjalan, bukan 12 bulan ke belakang.
- Todo List: riwayat pengajuan beneficiary di detail CR kini berupa tabel yang bisa diurutkan per kolom, dengan baris subtotal dan header yang tetap terlihat saat digulir, kolom Status, dan tombol View yang langsung membuka detail CR di halaman Cost Request.
- Label status settlement kini "Settle Checked" dan "Settle Verified", sehingga tidak tertukar dengan Checked/Verified pada tahap pengajuan.

### Perbaikan
- Halaman Beneficiary tidak lagi error 500 setelah kolom rekening dihapus dari Master PIC; panel detail PIC tidak lagi menampilkan No. Rekening PIC.
- Used Budget Plan (daftar, total per plan, dan per rincian): CR berstatus Settle Approved / Second Approved dan expense claim yang sudah masuk batch Treasury tidak lagi hilang dari hitungan.
- Purchase Order: Actual Used dan Variable Cost ikut menghitung CR di tahap Settle Approved / Second Approved; drawer detail CR dan Daily Transaction memakai aturan yang sama.
- Pengecekan NOB kini menganggap CR di tahap Settle Approved / Second Approved sudah memakai budget, sehingga sisa budget tidak tampak lebih besar dari kenyataan.
- Finance Summary & Report: CR yang sudah di-approve CEO (belum masuk batch Treasury) serta CR di tahap Settle Approved / Second Approved kini ikut terhitung.

## [2.5.1] - 2026-10-01 — Cash Advance per PIC, Detail Budget & Hitungan Anggaran Lebih Akurat

Rilis lanjutan yang memperjelas pemakaian anggaran: tab Cash Advance per PIC di
Budget Plan, Detail Budget di Todo List, hitungan Used/Expense Claim/Approved yang
saling eksklusif, serta riwayat persetujuan dan filter tabel yang lebih rapi.

### Baru
- Detail Budget Plan: tab Cash Advance baru yang mengelompokkan Cash Advance per PIC (total, sudah settle, masih menggantung, nilai), bisa dibuka per PIC untuk melihat tiap CR beserta tanggal Paid dan aging-nya.
- Todo List: tombol Detail Budget di section Budgeting membuka modal dengan tab Budget (rincian per Chart of Account), PO, dan Attachment; satu tombol View Budget per grup PO di worklist Cost Request.
- View Budget: kolom Expense Claim dan Approved dipisah sehingga Variance tidak pernah menghitung satu rupiah dua kali.

### Berubah
- Perhitungan kolom anggaran per CoA: CR yang sudah masuk batch Treasury (QUEUED) kini dihitung di Used, bukan Approved; expense claim yang masih direview masuk kolom Expense Claim, yang sudah Second Approved masuk Approved.
- Riwayat persetujuan (Cost Request, Budget Plan, Purchase Order) memakai label berbahasa Indonesia yang lebih ringkas dan tidak lagi menampilkan nama peran pelaku.
- Panel filter kolom tampil sebagai grid dua kolom dengan tombol Reset yang lebih jelas; pemilih jumlah baris tabel diseragamkan dengan tombol halaman.
- Todo List lebih ringan: rincian CoA Budget Plan tidak lagi dihitung untuk setiap baris saat halaman dibuka, melainkan dimuat saat Detail Budget diklik.

### Perbaikan
- CR yang sudah sampai tahap Settle Approved atau Second Approved tidak lagi hilang dari hitungan pemakaian anggaran.
- Second Payment yang masuk batch Treasury hanya mencatat satu baris riwayat, tidak lagi tampak terduplikasi.

## [2.5.0] - 2026-10-01 — Komentar Invoice, View Budget di Todo List & Toolbar Seragam

Invoice kini bisa dikomentari langsung dari daftarnya dan komentarnya masuk
ke Log & Approval, section Budgeting di Todo List ikut punya tombol View
Budget, dan tombol aksi di seluruh halaman daftar dikumpulkan pada satu
bilah kendali.

### Baru
- Komentar bebas pada Invoice: bisa ditulis langsung dari daftar Collection dan tercatat di Log & Approval bersama peristiwa lainnya.
- Panel Info Cost Request menampilkan riwayat pengajuan sebelumnya untuk beneficiary yang sama.
- Tombol View Budget kini juga tersedia di section Budgeting pada Todo List, memakai modal breakdown Chart of Account yang sama dengan section Cost Request dan Settlement.
- Cost Request: panel Filter berisi satu tombol per kolom, langsung berlaku tanpa tombol Terapkan, dan bisa direset sekaligus.

### Berubah
- Tombol Tambah dan Export dipindah ke bilah kendali daftar sejajar dengan Cari, Filter, dan Kolom, sehingga tidak lagi tersebar di dua tempat - berlaku di Master PIC, Cost Center, Employee, Payroll Run, Budgeting, dan Collection.
- Export Excel Cost Request selalu memuat seluruh kolom, tidak lagi mengikuti kolom yang kebetulan sedang tampil di layar.
- Master PIC tidak lagi menyimpan bank dan nomor rekening; data rekening cukup dipegang Beneficiary agar tidak ada dua sumber yang bisa berbeda.

### Perbaikan
- Kolom Cost pada Cost Request tidak lagi bergantung pada catatan pembayaran yang bisa kosong, sehingga nilainya tidak tampil nol untuk pengajuan yang sudah dibayar.
- Filter kolom lebih rapi mengikuti daftar yang sedang tampil, dan sisa skrip tabel lama yang tidak terpakai dibuang.

## [2.4.5] - 2026-09-30 — Deskripsi Panjang, Lampiran Settlement & Beneficiary Lebih Jelas

Kolom deskripsi yang sempit kini bisa dibuka penuh di Cost Request maupun
Settlement, lampiran settlement yang sudah tersimpan bisa diperiksa dan
diganti, dan pilihan Beneficiary menyebutkan bank serta nomor rekeningnya.

### Baru
- Kolom Description di form Cost Request dan Settlement bisa dibuka penuh lewat ikon di ujung kanannya - teks panjang yang terpotong bisa dibaca dan disunting di pop-up, lengkap dengan sisa karakter.
- Lampiran yang sudah tersimpan di Settlement kini bisa dibuka untuk diperiksa dan dibuang satu per satu, sama seperti lampiran yang baru dipilih.

### Berubah
- Pilihan Beneficiary menampilkan nama bank dan nomor rekening, sehingga penerima dana bernama mirip tidak lagi tertukar.

### Perbaikan
- Beneficiary yang sudah dinonaktifkan tetap muncul saat mengedit Cost Request yang memakainya, agar penerima dananya tidak tergantikan diam-diam.
- Deskripsi per baris Cost Request dibatasi 255 karakter di server, sejalan dengan batas pada formnya.

## [2.4.4] - 2026-09-30 — Summary Progress Bertahap, Role Supervisor & Batch Second Payment

Tabel Summary tab Progress kini memecah nilai No. PO sesuai tahap yang sudah
dilalui, Second Payment yang sudah disetujui bisa langsung dibatch Treasury
dari Todo List, dan role Supervisor tersedia untuk diatur hak aksesnya.

### Baru
- Role Supervisor ditambahkan; hak aksesnya diatur lewat User & Access > Menu Access seperti role lain.
- Second Payment yang sudah Second Approve ikut muncul di daftar Buat Batch milik Treasury di Todo List, tidak perlu lagi dicari manual di Daily Transaction.
- Form Settlement: penghitung sisa karakter pada kolom catatan, dan lampiran bisa dibuka lebih dulu untuk diperiksa sebelum dikirim.

### Berubah
- Summary tab Progress: nilai kini mengalir bertahap antar kolom - Waiting BAST, Waiting Invoice, Waiting Paid, dan Closed memuat selisih tiap tahap, sehingga No. PO yang baru sebagian ditagih atau dibayar terlihat terbelah dan jumlah seluruh kolom tetap sama dengan Contract.
- Nama kolom Summary diseragamkan dengan modul Purchase Order: Waiting Payment menjadi Waiting Paid, dan Close menjadi Closed.

### Perbaikan
- Lampiran berupa video ditolak saat unggah di form Settlement, tidak lagi lolos lalu gagal di server.
- Catatan per baris Settlement dibatasi 255 karakter di server, sejalan dengan batas pada formnya.

## [2.4.3] - 2026-09-29 — Auto-Closing Budget Plan, Jenis PIC Baru & Filter Bertingkat

Budget Plan kini punya tanggal rencana selesai dan pindah sendiri ke Closing
saat jatuh tempo, Master PIC bertambah jenis External dan Site Keeper, dan
filter kolom di seluruh tabel jadi bertingkat sehingga pilihannya selalu
masuk akal.

### Baru
- Budget Plan punya tanggal rencana selesai (Plan Finish); begitu tanggalnya terlewati, budget yang masih Active dipindahkan otomatis ke Closing lewat penjadwalan harian.
- Status Closing kini membedakan dua keadaan: jatuh tempo (menunggu manager mengajukan berkas closing) dan sudah diajukan (menunggu verifikasi Finance), sehingga Todo List tiap peran tidak tercampur.
- Master PIC: dua jenis baru, External dan Site Keeper, lengkap dengan tab, hitungan per jenis, dan penanda PIC internal yang belum punya akun User.
- Akun User dibuatkan untuk PIC internal yang belum memilikinya (peran staff, kata sandi awal wajib diganti saat login pertama).
- Role Collection mendapat akses menu Cash Receipt (M-03).

### Berubah
- Filter kolom kini bertingkat: setelah satu filter dipilih, pilihan di dropdown lain ikut menyempit ke nilai yang benar-benar ada, tidak lagi menawarkan kombinasi yang pasti mengosongkan tabel.
- Tampilan halaman tidak lagi berkedip saat dimuat - sidebar langsung tergambar pada keadaan terakhirnya dan isi halaman ditahan sampai markup lengkap.
- Tabel Cash Receipt, Surat Jalan, Milestone, Project Master, dan Forecast diseragamkan dengan toolbar, filter, dan pengaturan kolom yang sama.
- Summary: kolom Contract disembunyikan secara bawaan agar kolom antrean lebih dekat ke nama PO; tetap bisa dimunculkan lewat menu Kolom.

## [2.4.2] - 2026-09-27 — Settlement Boleh Bernilai 0

Penyesuaian form Settlement Cost Request: item yang tidak terpakai atau dananya
dikembalikan kini bisa disettle dengan nilai 0 tanpa lampiran.

### Berubah
- Cost Request > Submit Settlement: kolom Settle per item boleh diisi 0 (atau dikosongkan = 0).
- Lampiran Item hanya wajib untuk baris yang nilai Settle-nya lebih dari 0; baris bernilai 0 tidak perlu lampiran.

## [2.4.1] - 2026-09-27 — Approval Massal di Todo List & Menu Access per Aplikasi

Rilis lanjutan yang mempercepat pekerjaan approval harian: Todo List mendukung
approve/reject massal, menu access diatur per aplikasi Tesys dengan Master Data
bersama, dan beberapa perbaikan tombol approval serta migrasi.

### Baru
- Todo List: approve/reject massal (multi-select) untuk Check & Verify Settlement, Second Approve, approval Budget Plan (Head/Finance/CEO), proses batch Treasury, serta Verify & Approve Invoice. Tiap baris tetap diproses lewat aksi satuannya, jadi aturan, log, dan notifikasinya sama.
- Atur Menu Access di Role Master kini dikelompokkan per aplikasi (Tesys Finance, HR, R&D, Operations, Project) dengan pilih-semua per aplikasi, dan disusun otomatis dari peta menu yang sama dengan sidebar.
- Cost Request: tombol "Tolak settlement" untuk Manager pada CR yang Settlement-nya sudah terverifikasi.

### Berubah
- Master Data kini satu daftar bersama untuk semua aplikasi: menu Master Data yang tampil di sidebar Finance, Logistics, HR, dan lainnya sama persis, hanya ditentukan menu access role.
- Treasury (buat batch): Second Payment kini dikelompokkan per PM Internal bersama Cost Request biasa; setiap grup bisa dibuka-tutup, dipilih sekaligus, dan menampilkan ringkasan nilainya.
- Finance Summary: kolom Closed dipindah sebelum VAR, dan kolom VAR dibekukan di sisi kanan sehingga tetap terlihat saat tabel digulir.

### Perbaikan
- Tombol approval Cost Request kini mengikuti peran per tahap yang sama dengan server, sehingga tidak lagi hilang untuk peran yang sebenarnya berhak (mis. cost_control di tahap Checked).
- Bulk Close settlement dari Todo List: CR yang punya Expense Claim kini di-Settle Approve, tidak di-Close.
- Migrasi update daily_transactions yang ikut terjalankan ulang setelah ganti nama tidak lagi gagal "Data truncated for column status" pada basis data yang sudah memakai status DONE.

## [2.4.0] - 2026-09-26 — Stabilitas, Keamanan Data & Perampingan Aplikasi

Rilis besar yang merapikan fondasi aplikasi: sesi tidak lagi mati saat form
panjang sedang diisi, dokumen keuangan tidak bisa ikut terhapus bersama data
master, nilai rupiah miliaran tidak lagi menyebabkan error, halaman error
tampil rapi, dan fitur yang tidak dipakai (Tesys AI) dilepas agar aplikasi
lebih ringan.

### Baru
- Sesi dijaga tetap hidup selama form panjang (Cost Request, Budget Plan) sedang diisi, dengan peringatan sebelum sesi benar-benar habis — isian tidak lagi hilang karena 419.
- Halaman error sendiri (403, 404, 419, 429, 500) dengan penjelasan berbahasa Indonesia dan jalan keluar yang jelas, menggantikan layar error bawaan.
- Akun yang dinonaktifkan langsung kehilangan sesinya saat itu juga, tidak lagi menunggu sesinya kedaluwarsa sendiri.
- Pemantauan antrean notifikasi terjadwal: penumpukan job tercatat sebagai peringatan sehingga worker yang mati bisa ketahuan lebih cepat.

### Berubah
- Fitur Tesys AI dilepas beserta seluruh layanan pendukungnya; aplikasi jadi lebih ringan dan tidak lagi bergantung pada layanan pihak ketiga.
- Dashboard disederhanakan menjadi sapaan dan panel riwayat rilis; ringkasan pekerjaan tetap lengkap di Todo List dan badge lonceng.
- Pemuatan aset (CSS/JS) memakai satu penanda versi, memangkas puluhan akses berkas di setiap permintaan halaman.
- Global Search, Invoice, Budget Plan, Cost Request, Project, dan Forecast dirapikan penulisan kodenya agar hak akses dan alur datanya konsisten.

### Perbaikan
- Nilai uang di atas Rp 2,14 miliar (Fixed Cost, Cost Register, arus kas) tidak lagi menyebabkan error 500 — kolomnya diperlebar.
- Menghapus data master (user, beneficiary, cluster) tidak lagi bisa ikut menghapus Cost Request beserta rincian dan riwayat persetujuannya; kini ditolak di tingkat basis data.
- Berkas migrasi dengan spasi pada namanya diganti nama agar aman dijalankan di semua lingkungan.

## [2.3.3] - 2026-09-26 — Cost Request: Tab Cash Advance, Cost Payment & Database

Halaman Cost Request kini dipisah menjadi tiga tab sesuai alurnya, dan data
setiap tab baru dimuat saat tab dibuka sehingga halaman lebih ringan.

### Baru
- Tab Cash Advance: khusus Cash Advance dengan alur penuh sampai Settlement.
- Tab Cost Payment: semua pengajuan selain Cash Advance — setelah Process langsung Closed, tanpa Settlement.
- Tab Database: gabungan Cash Advance dan Cost Payment dengan kolom Cost (nilai Settlement untuk Cash Advance, nilai Request untuk Cost Payment).

### Berubah
- Data tabel tiap tab baru dimuat saat tab dibuka, sehingga halaman Cost Request terbuka lebih cepat.
- Export Excel mengikuti tab, filter, dan kolom yang sedang aktif di tabel.

## [2.3.2] - 2026-09-23 — Lampiran Lama Bisa Dibuka Kembali

Lampiran yang diunggah sebelum sistem penyimpanan berkas diperketat (mis.
dokumen Purchase Order lama) sempat tidak bisa dibuka (404). Sekarang bisa
dibuka kembali dengan pemeriksaan hak akses yang sama.

### Perbaikan
- Lampiran lama (Purchase Order, Invoice, Cost Request, dan lainnya) yang sebelumnya tampil "404 Not Found" kini bisa dibuka kembali.

## [2.3.1] - 2026-09-23 — Stabilitas & Kecepatan Aplikasi

Rilis perbaikan yang berfokus pada error 500 yang sempat muncul berulang di
Dashboard dan Treasury. Beban server dikurangi di beberapa titik, halaman
Todo List / Purchase Order / Budgeting jadi jauh lebih cepat, dan antrean
notifikasi yang sempat macet dijalankan kembali.

### Baru
- Settlement: Close dan Reject sekaligus untuk beberapa Cost Request (multi-select) di tahap Finance Manager, serta Reject satuan langsung dari Todo List.

### Berubah
- Dashboard untuk sementara hanya menampilkan sapaan dan What's New; antrean pekerjaan tetap lengkap di halaman Todo List.
- Tombol Todo di top bar dihapus — daftar tugas dibuka dari menu Todo List di sidebar.
- Notifikasi WhatsApp dinonaktifkan sementara; notifikasi lonceng di aplikasi tetap berjalan.
- Notifikasi yang tertunda lebih dari 6 jam masuk ke riwayat lonceng sebagai sudah dibaca dan tidak dikirim ke WhatsApp, supaya tidak ada banjir pesan lama.
- Nama penanda tangan pada cetak Invoice diperbarui menjadi Aye Assagaf.

### Perbaikan
- Dashboard dan Treasury tidak lagi terkena error 500 berulang yang hanya hilang setelah menghapus cache browser.
- Halaman Project Monitoring tidak lagi memuat semua foto lampiran sekaligus (penyebab lonjakan beban server); foto dimuat saat terlihat di layar dan disimpan di browser.
- Notifikasi lonceng yang sejak 21 September tertahan di antrean kini terkirim kembali.
- Todo List hingga 4× lebih cepat, Purchase Order 3×, dan Budgeting 2× — angka yang ditampilkan tidak berubah.
- Pembaruan lonceng notifikasi dan chat monitoring berhenti di tab yang sedang tidak dibuka, mengurangi beban server.

## [2.3.0] - 2026-09-22 — Perombakan Beneficiary Master & Detail Forecast

Beneficiary Master kini menampilkan dan mengelola PIC penanggung jawab secara
langsung, filter Kategori/Bank/Status jadi satu jenis kendali, dan detail
Forecast pindah dari halaman tersendiri ke modal — sejalan dengan modul lain.
Beberapa perbaikan bug yang berdampak pada data turut disertakan.

### Baru
- Kolom PIC pada Beneficiary Master kini menampilkan penanggung jawab beserta jabatannya, dan mencari nama PIC menciutkan hasil jadi satu baris per orang dengan daftar anggotanya di modal Detail PIC.
- Modal Detail PIC: rekening milik PIC itu sendiri (field baru pada Master PIC), serta aksi Edit/Pindah PIC/Hapus per beneficiary tanpa keluar dari modal.
- Tombol "Tambahkan Beneficiary" pada modal Detail PIC — mengaitkan beneficiary yang sudah terdaftar ke PIC tersebut, tanpa membuat data baru.
- Tombol Hapus pada baris hasil pencarian PIC menghapus PIC beserta datanya di Master PIC (beneficiary-nya tidak ikut terhapus, hanya kehilangan PIC).
- Detail Forecast kini berupa modal (sama seperti Cost Request / Budget Plan), lengkap dengan tombol Edit, Activate/Close, dan isi Actual Amount di dalamnya.

### Berubah
- Filter Kategori dan Status pada Beneficiary Master digabung dengan filter Bank menjadi satu jenis kendali ceklis (tanpa reload), dengan daftar pilihan yang selalu lengkap walau datanya belum ada.
- Kolom Action Beneficiary Master menampilkan Hapus langsung, tidak lagi disembunyikan di balik menu titik tiga.
- Kolom Action Forecast disederhanakan jadi View & Hapus; Edit dan seluruh alur (Activate/Close/isi Actual) pindah ke dalam modal detail.
- Forecast berstatus ACTIVE kini bisa diedit dan dihapus (sebelumnya hanya CLOSED yang terkunci sepenuhnya, ACTIVE ikut terkunci padahal belum final).
- Approval Invoice (VERIFIED → APPROVED) menjadi tugas Finance Manager, bukan Approver, sesuai alur kerja yang berlaku.

### Perbaikan
- Forecast dengan rencana pengeluaran (bernilai bersih negatif) tidak lagi gagal disimpan — kolom header Forecast diubah menjadi bertanda (signed).
- Lampiran per item pada Cost Request (gambar/dokumen) yang sebelumnya gagal dimuat (500) karena salah nama tabel kini bisa dibuka kembali.
- Tombol Clear Filter pada Beneficiary Master benar-benar mengosongkan hasil pencarian, tidak lagi menyisakan baris tersaring.
- Halaman Beneficiary Master tidak lagi menampilkan dua scrollbar bertumpuk pada modal Detail PIC.

## [2.2.0] - 2026-09-21 — Second Payment Dibayar Lewat Treasury

Kekurangan bayar hasil Settlement (Second Payment) tidak lagi cukup ditandai
lunas dari To-Do List. Sekarang ia masuk batch Treasury seperti pengeluaran
lain: ada baris kas, bukti transfer, approval CEO, dan jejak di riwayat CR.

### Berubah
- Nomor Cost Request 4 digit lebih pendek: slot cluster dihapus, jadi CR-{bulan}{tahun}{kode PO}{urut} — contoh CR-0926008500180039 kini terbit sebagai CR-092600850039. Kode lama tidak diubah.
- Second Payment kini dipilih saat membuat batch Treasury, sejajar Cost Request dan Cost Register.
- Tombol "Mark Paid" Second Payment di To-Do List dihapus — bagian itu sekarang informatif saja dan menampilkan kode batch yang membayarnya.
- Second Payment ikut file transfer bank dan Export batch, dengan nominal selisihnya saja dan Reference berakhiran "-2".
- Riwayat Cost Request mencatat Second Payment masuk batch, disetujui CEO, dibayar, atau di-takeout.
- Modal detail Cost Request menampilkan status Second Payment beserta batch Treasury-nya, bukan sekadar "Pending".

### Perbaikan
- Second Payment tidak bisa lagi masuk ke dua batch sekaligus.
- Batch yang memuat Second Payment tidak lagi menghitung ganda rincian Chart of Account maupun nilai Summary Export.

## [2.1.0] - 2026-09-18 — Pengamanan Data, Ketahanan Transaksi & Jaring Uji

Hasil audit menyeluruh: lampiran keuangan tidak lagi bisa diunduh tanpa login,
approval tidak bisa dobel, nomor dokumen tidak bisa bentrok, dan penghapusan
pembayaran meninggalkan jejak. Tidak ada perubahan alur kerja.

### Perbaikan
- Pengecekan NOB kini menghitung Cost Request berstatus Checked — sebelumnya sisa anggaran terlihat lebih besar dari kenyataan.
- Dua klik Approve bersamaan tidak lagi menghasilkan log dan notifikasi ganda.
- Nomor Invoice / Cost Request / Daily Transaction / Surat Jalan tidak lagi bisa bentrok atau terpakai ulang.
- Pembayaran tidak bisa lagi melebihi sisa tagihan saat dua Cash Receipt dicatat bersamaan.
- Cash Receipt bernominal nol tidak lagi bisa tersimpan.
- Membuka daftar Purchase Order tidak lagi diam-diam mengubah data.

### Berubah
- Lampiran hanya bisa dibuka setelah login dan sesuai hak akses modulnya.
- Menghapus Cash Receipt sekarang wajib beralasan dan datanya tetap tersimpan untuk audit.
- Notifikasi dikirim lewat antrean, jadi approval tidak lagi menunggu API WhatsApp.
- Daftar panjang dibatasi 1.000 baris terbaru dengan pemberitahuan; angka ringkasan tetap dari seluruh data.
- Form Invoice (tambah/ubah/lihat) memakai tata letak menurun.

## [2.0.0] - 2026-09-15 — Rilis Besar: Settlement, Modul Team & Finance Forecast

Alur Settlement Cash Advance dirombak jadi 3 tahap approval, PO & Invoice kini
menghitung nilai/jatuh tempo otomatis, dan dua modul baru — Team serta Finance
Forecast — mulai tersedia.

### Baru
- Modul Team: struktur organisasi (Department, Team, Team Member) dengan Organization Chart dinamis berdasarkan level.
- Akses PO kini bisa diberikan lewat Team → Access Group, selain assignment langsung per user.
- Modul Finance Forecast: rencana vs realisasi per periode, dengan status Draft → Active → Closed, Variance & Achievement %, serta auto-close terjadwal.
- Second Payment otomatis pada Cost Request Cash Advance saat Settlement melebihi nilai pengajuan awal.
- Lampiran per item (selain global) pada Settlement Cost Request.
- Kolom "On Review" pada modal View Budget Todo List, memisahkan nilai pengajuan yang masih pra-approval dari yang sudah Approved.
- Section "Treasury — Buat Batch Pembayaran" di Todo List (CR Approved yang siap dibatch, sebelumnya tidak muncul di worklist manapun).
- PO Quantity + alert "PO Adjustment Required" saat jumlah No. PO tidak sesuai ekspektasi.
- Invoice: tombol "Tandai Diterima" dan Jatuh Tempo Aktual (dihitung dari tanggal terima, bukan tanggal cetak invoice).
- Invoice: Cancel & Revise (khusus Finance Manager) untuk mengembalikan invoice SENT ke DRAFT tanpa kehilangan histori.
- 6 KPI card baru + Active Cash Advance + PO Summary (dengan formula VAR yang bisa dipilih) pada Summary Finance.
- Popup "What's New" otomatis saat membuka Dashboard, bisa dimatikan per rilis lewat centang "jangan tampilkan lagi".

### Perubahan
- Settlement Cash Advance kini 3 tahap approval: Head Check → Finance Verify → CEO Close (sebelumnya 1 langkah Finance langsung Close). Label "Realization" diganti "Settlement" di seluruh tampilan.
- Batas maksimal nilai Settlement (tidak boleh melebihi pengajuan) dihapus — kelebihan sekarang ditagihkan lewat Second Payment.
- Nilai PO tidak lagi diinput manual, otomatis dihitung dari total nominal seluruh No. PO.
- Budget Plan otomatis masuk tahap Closing begitu PO terkait sudah Paid.
- Urutan tab Summary Finance: Executive → Collection → Budget (sebelumnya Executive → Budget → Collection). Tabel Customer Outstanding & Manager Collection Performance digabung jadi satu tabel dengan toggle grouping.
- Tampilan/header puluhan halaman (Create/Edit/Detail) dirapikan agar konsisten dengan pola modern yang sudah dipakai di semua halaman index — tanpa mengubah warna tema.
- Proteksi delete User yang masih menjadi Project Manager atau punya Cost Request.
- Source Fund wajib diisi saat membuat batch Treasury maupun Invoice; dropdown Source Fund di Invoice hanya menampilkan rekening yang mengandung "PT".

## [1.5.0] - 2026-09-08 — Penyempurnaan Summary Collection

Definisi Outstanding pada Summary Collection dibakukan dan ditambahkan filter
periode.

### Baru
- Filter bulan (col_month) pada halaman Summary Collection.

### Perubahan
- Outstanding = invoice berstatus RECEIVED / PARTIALLY_PAID ditambah invoice Overdue; invoice SENT yang belum jatuh tempo tidak lagi dihitung.
- Logika Outstanding & Overdue dipusatkan pada satu closure bersama agar konsisten antar tampilan.

## [1.4.0] - 2026-09-01 — Project Manager & kolom biaya proyek

PM Internal kini berbasis data user, dan proyek memiliki kolom biaya kontrak /
overhead / fixed cost.

### Baru
- Kolom Contract, Overhead, dan Fixed Cost pada data proyek.
- Pemilihan Project Manager Internal dari daftar user.

### Perubahan
- PM Internal mengacu ke projects.manager_id (relasi ke tabel users), menggantikan input teks bebas.

## [1.3.0] - 2026-08-25 — Lampiran per item pada Cost Request

Form Cost Request mendukung lampiran di tingkat item maupun global.

### Baru
- Lampiran per item (request detail attachment) pada form Cost Request.
- Lampiran global pada Cost Request.
- Hint alur kerja yang muncul saat memilih tipe Cost Request.

### Perubahan
- Proses simpan detail (syncDetails) melakukan upsert berdasarkan id item agar lampiran tidak hilang saat edit.

## [1.2.1] - 2026-08-18 — Takeout item saat approval Cost Request

Approver dapat mengeluarkan atau mengembalikan item Cost Request per baris
tanpa menolak seluruh pengajuan.

### Baru
- Head / Finance / CEO dapat takeout / restore item (request detail) selama proses approval.
- Nilai yang diproses otomatis berkurang sesuai item yang dikeluarkan.

### Perubahan
- Bila seluruh item dikeluarkan, Cost Request otomatis berstatus REJECTED.

## [1.2.0] - 2026-08-13 — Pemisahan alur kerja Cost Request

Alur Cost Request dibedakan antara Cash Advance dan tipe lainnya.

### Perubahan
- Cash Advance memakai siklus penuh: QUEUED -> PAID -> REALIZED -> CLOSED.
- Tipe Cost Request lain langsung QUEUED -> CLOSED saat mark-paid.
- Label status diselaraskan: QUEUED = "Process", REALIZED = "Settle".

## [1.1.0] - 2026-08-04 — Perombakan tampilan Budget Plan

Halaman Budget Plan mendapat pola UI baru yang kemudian menjadi acuan halaman
index / show lainnya.

### Baru
- Modal form Budget Plan dengan ukuran & tata letak baru.
- Sidebar cost center di dalam form.
- Tab-tab pengelompokan isian.
- Pewarnaan baris per NOB (Nature of Business).
- Sorting kolom dan filter pada tabel.
- Lampiran dan log perubahan Budget Plan.

## [1.0.0] - 2026-07-24 — Rilis perdana Tesys (Terex System)

Aplikasi operasional & keuangan Terex mulai digunakan: manajemen proyek,
budgeting, cost request, invoicing, collection, payroll, dan dashboard
terpadu.

### Baru
- Dashboard terpadu: panel Need Action, Reminder, dan Last Activity per pengguna.
- Modul Project & Project Monitoring: aktivasi proyek, detail, stage, dan lampiran.
- Modul Budget Plan dan Budget Request beserta realisasinya.
- Modul Cost Request dengan alur approval berjenjang.
- Modul Invoice & Cash Receipt dan Summary Collection.
- Modul Payroll Run, Daily Transaction, dan Finance Summary.
- Master data: Customer, Beneficiary, Bank, Cluster, Cluster Group, Cost Center, Source Fund, Stage, Request Type, Master PIC.
- Manajemen akses: User, Role, Access Group, middleware CheckRole, dan mode "View As".
- TesysAI (Beta): asisten tanya-jawab operasional dengan riwayat percakapan.

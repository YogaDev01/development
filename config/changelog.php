<?php

/*
|--------------------------------------------------------------------------
| Tesys Changelog / Release Notes
|--------------------------------------------------------------------------
|
| Sumber tunggal riwayat versi aplikasi. Ditampilkan di Dashboard
| (panel riwayat rilis) dan dicerminkan di CHANGELOG.md pada root project.
|
| Cara menambah rilis baru:
|   1. Tambahkan entri baru di PALING ATAS array 'releases'.
|   2. Update 'current' ke nomor versi terbaru.
|   3. Perbarui CHANGELOG.md agar sinkron.
|
| Format versi: MAJOR.MINOR.PATCH (semver)
|   - PATCH : perbaikan bug / penyesuaian kecil tanpa perubahan alur.
|   - MINOR : fitur baru yang backward-compatible.
|   - MAJOR : perubahan besar / breaking pada alur kerja.
|
| Catatan: tanggal rilis 1.1.0 - 1.5.0 bersifat perkiraan (belum ada
| histori git terpisah); sesuaikan bila ada tanggal pasti.
|
*/

return [

    // Versi yang sedang berjalan.
    'current' => '2.4.1',

    // Riwayat rilis, terbaru di atas.
    'releases' => [

        [
            'version' => '2.4.1',
            'date'    => '2026-09-27',
            'title'   => 'Approval Massal di Todo List & Menu Access per Aplikasi',
            'summary' => 'Rilis lanjutan yang mempercepat pekerjaan approval harian: Todo List mendukung approve/reject massal, menu access diatur per aplikasi Tesys dengan Master Data bersama, dan beberapa perbaikan tombol approval serta migrasi.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Todo List: approve/reject massal (multi-select) untuk Check & Verify Settlement, Second Approve, approval Budget Plan (Head/Finance/CEO), proses batch Treasury, serta Verify & Approve Invoice. Tiap baris tetap diproses lewat aksi satuannya, jadi aturan, log, dan notifikasinya sama.',
                        'Atur Menu Access di Role Master kini dikelompokkan per aplikasi (Tesys Finance, HR, R&D, Operations, Project) dengan pilih-semua per aplikasi, dan disusun otomatis dari peta menu yang sama dengan sidebar.',
                        'Cost Request: tombol "Tolak settlement" untuk Manager pada CR yang Settlement-nya sudah terverifikasi.',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Berubah',
                    'items' => [
                        'Master Data kini satu daftar bersama untuk semua aplikasi: menu Master Data yang tampil di sidebar Finance, Logistics, HR, dan lainnya sama persis, hanya ditentukan menu access role.',
                        'Treasury (buat batch): Second Payment kini dikelompokkan per PM Internal bersama Cost Request biasa; setiap grup bisa dibuka-tutup, dipilih sekaligus, dan menampilkan ringkasan nilainya.',
                        'Finance Summary: kolom Closed dipindah sebelum VAR, dan kolom VAR dibekukan di sisi kanan sehingga tetap terlihat saat tabel digulir.',
                    ],
                ],
                [
                    'type'  => 'fixed',
                    'label' => 'Perbaikan',
                    'items' => [
                        'Tombol approval Cost Request kini mengikuti peran per tahap yang sama dengan server, sehingga tidak lagi hilang untuk peran yang sebenarnya berhak (mis. cost_control di tahap Checked).',
                        'Bulk Close settlement dari Todo List: CR yang punya Expense Claim kini di-Settle Approve, tidak di-Close.',
                        'Migrasi update daily_transactions yang ikut terjalankan ulang setelah ganti nama tidak lagi gagal "Data truncated for column status" pada basis data yang sudah memakai status DONE.',
                    ],
                ],
            ],
        ],

        [
            'version' => '2.4.0',
            'date'    => '2026-09-26',
            'title'   => 'Stabilitas, Keamanan Data & Perampingan Aplikasi',
            'summary' => 'Rilis besar yang merapikan fondasi aplikasi: sesi tidak lagi mati saat form '
                       . 'panjang sedang diisi, dokumen keuangan tidak bisa ikut terhapus bersama data '
                       . 'master, nilai rupiah miliaran tidak lagi menyebabkan error, halaman error '
                       . 'tampil rapi, dan fitur yang tidak dipakai (Tesys AI) dilepas agar aplikasi '
                       . 'lebih ringan.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Sesi dijaga tetap hidup selama form panjang (Cost Request, Budget Plan) sedang diisi, dengan peringatan sebelum sesi benar-benar habis — isian tidak lagi hilang karena 419.',
                        'Halaman error sendiri (403, 404, 419, 429, 500) dengan penjelasan berbahasa Indonesia dan jalan keluar yang jelas, menggantikan layar error bawaan.',
                        'Akun yang dinonaktifkan langsung kehilangan sesinya saat itu juga, tidak lagi menunggu sesinya kedaluwarsa sendiri.',
                        'Pemantauan antrean notifikasi terjadwal: penumpukan job tercatat sebagai peringatan sehingga worker yang mati bisa ketahuan lebih cepat.',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Berubah',
                    'items' => [
                        'Fitur Tesys AI dilepas beserta seluruh layanan pendukungnya; aplikasi jadi lebih ringan dan tidak lagi bergantung pada layanan pihak ketiga.',
                        'Dashboard disederhanakan menjadi sapaan dan panel riwayat rilis; ringkasan pekerjaan tetap lengkap di Todo List dan badge lonceng.',
                        'Pemuatan aset (CSS/JS) memakai satu penanda versi, memangkas puluhan akses berkas di setiap permintaan halaman.',
                        'Global Search, Invoice, Budget Plan, Cost Request, Project, dan Forecast dirapikan penulisan kodenya agar hak akses dan alur datanya konsisten.',
                    ],
                ],
                [
                    'type'  => 'fixed',
                    'label' => 'Perbaikan',
                    'items' => [
                        'Nilai uang di atas Rp 2,14 miliar (Fixed Cost, Cost Register, arus kas) tidak lagi menyebabkan error 500 — kolomnya diperlebar.',
                        'Menghapus data master (user, beneficiary, cluster) tidak lagi bisa ikut menghapus Cost Request beserta rincian dan riwayat persetujuannya; kini ditolak di tingkat basis data.',
                        'Berkas migrasi dengan spasi pada namanya diganti nama agar aman dijalankan di semua lingkungan.',
                    ],
                ],
            ],
        ],

        [
            'version' => '2.3.3',
            'date'    => '2026-09-26',
            'title'   => 'Cost Request: Tab Cash Advance, Cost Payment & Database',
            'summary' => 'Halaman Cost Request kini dipisah menjadi tiga tab sesuai alurnya, dan data '
                       . 'setiap tab baru dimuat saat tab dibuka sehingga halaman lebih ringan.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Tab Cash Advance: khusus Cash Advance dengan alur penuh sampai Settlement.',
                        'Tab Cost Payment: semua pengajuan selain Cash Advance — setelah Process langsung Closed, tanpa Settlement.',
                        'Tab Database: gabungan Cash Advance dan Cost Payment dengan kolom Cost (nilai Settlement untuk Cash Advance, nilai Request untuk Cost Payment).',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Berubah',
                    'items' => [
                        'Data tabel tiap tab baru dimuat saat tab dibuka, sehingga halaman Cost Request terbuka lebih cepat.',
                        'Export Excel mengikuti tab, filter, dan kolom yang sedang aktif di tabel.',
                    ],
                ],
            ],
        ],

        [
            'version' => '2.3.2',
            'date'    => '2026-09-23',
            'title'   => 'Lampiran Lama Bisa Dibuka Kembali',
            'summary' => 'Lampiran yang diunggah sebelum sistem penyimpanan berkas diperketat '
                       . '(mis. dokumen Purchase Order lama) sempat tidak bisa dibuka (404). '
                       . 'Sekarang bisa dibuka kembali dengan pemeriksaan hak akses yang sama.',
            'sections' => [
                [
                    'type'  => 'fixed',
                    'label' => 'Perbaikan',
                    'items' => [
                        'Lampiran lama (Purchase Order, Invoice, Cost Request, dan lainnya) yang sebelumnya tampil "404 Not Found" kini bisa dibuka kembali.',
                    ],
                ],
            ],
        ],

        [
            'version' => '2.3.1',
            'date'    => '2026-09-23',
            'title'   => 'Stabilitas & Kecepatan Aplikasi',
            'summary' => 'Rilis perbaikan yang berfokus pada error 500 yang sempat muncul berulang di '
                       . 'Dashboard dan Treasury. Beban server dikurangi di beberapa titik, halaman '
                       . 'Todo List / Purchase Order / Budgeting jadi jauh lebih cepat, dan antrean '
                       . 'notifikasi yang sempat macet dijalankan kembali.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Settlement: Close dan Reject sekaligus untuk beberapa Cost Request (multi-select) di tahap Finance Manager, serta Reject satuan langsung dari Todo List.',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Berubah',
                    'items' => [
                        'Dashboard untuk sementara hanya menampilkan sapaan dan What\'s New; antrean pekerjaan tetap lengkap di halaman Todo List.',
                        'Tombol Todo di top bar dihapus — daftar tugas dibuka dari menu Todo List di sidebar.',
                        'Notifikasi WhatsApp dinonaktifkan sementara; notifikasi lonceng di aplikasi tetap berjalan.',
                        'Notifikasi yang tertunda lebih dari 6 jam masuk ke riwayat lonceng sebagai sudah dibaca dan tidak dikirim ke WhatsApp, supaya tidak ada banjir pesan lama.',
                        'Nama penanda tangan pada cetak Invoice diperbarui menjadi Aye Assagaf.',
                    ],
                ],
                [
                    'type'  => 'fixed',
                    'label' => 'Perbaikan',
                    'items' => [
                        'Dashboard dan Treasury tidak lagi terkena error 500 berulang yang hanya hilang setelah menghapus cache browser.',
                        'Halaman Project Monitoring tidak lagi memuat semua foto lampiran sekaligus (penyebab lonjakan beban server); foto dimuat saat terlihat di layar dan disimpan di browser.',
                        'Notifikasi lonceng yang sejak 21 September tertahan di antrean kini terkirim kembali.',
                        'Todo List hingga 4× lebih cepat, Purchase Order 3×, dan Budgeting 2× — angka yang ditampilkan tidak berubah.',
                        'Pembaruan lonceng notifikasi dan chat monitoring berhenti di tab yang sedang tidak dibuka, mengurangi beban server.',
                    ],
                ],
            ],
        ],

        [
            'version' => '2.3.0',
            'date'    => '2026-09-22',
            'title'   => 'Perombakan Beneficiary Master & Detail Forecast',
            'summary' => 'Beneficiary Master kini menampilkan dan mengelola PIC penanggung jawab '
                       . 'secara langsung, filter Kategori/Bank/Status jadi satu jenis kendali, dan '
                       . 'detail Forecast pindah dari halaman tersendiri ke modal — sejalan dengan '
                       . 'modul lain. Beberapa perbaikan bug yang berdampak pada data turut disertakan.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Kolom PIC pada Beneficiary Master kini menampilkan penanggung jawab beserta jabatannya, dan mencari nama PIC menciutkan hasil jadi satu baris per orang dengan daftar anggotanya di modal Detail PIC.',
                        'Modal Detail PIC: rekening milik PIC itu sendiri (field baru pada Master PIC), serta aksi Edit/Pindah PIC/Hapus per beneficiary tanpa keluar dari modal.',
                        'Tombol "Tambahkan Beneficiary" pada modal Detail PIC — mengaitkan beneficiary yang sudah terdaftar ke PIC tersebut, tanpa membuat data baru.',
                        'Tombol Hapus pada baris hasil pencarian PIC menghapus PIC beserta datanya di Master PIC (beneficiary-nya tidak ikut terhapus, hanya kehilangan PIC).',
                        'Detail Forecast kini berupa modal (sama seperti Cost Request / Budget Plan), lengkap dengan tombol Edit, Activate/Close, dan isi Actual Amount di dalamnya.',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Berubah',
                    'items' => [
                        'Filter Kategori dan Status pada Beneficiary Master digabung dengan filter Bank menjadi satu jenis kendali ceklis (tanpa reload), dengan daftar pilihan yang selalu lengkap walau datanya belum ada.',
                        'Kolom Action Beneficiary Master menampilkan Hapus langsung, tidak lagi disembunyikan di balik menu titik tiga.',
                        'Kolom Action Forecast disederhanakan jadi View & Hapus; Edit dan seluruh alur (Activate/Close/isi Actual) pindah ke dalam modal detail.',
                        'Forecast berstatus ACTIVE kini bisa diedit dan dihapus (sebelumnya hanya CLOSED yang terkunci sepenuhnya, ACTIVE ikut terkunci padahal belum final).',
                        'Approval Invoice (VERIFIED → APPROVED) menjadi tugas Finance Manager, bukan Approver, sesuai alur kerja yang berlaku.',
                    ],
                ],
                [
                    'type'  => 'fixed',
                    'label' => 'Perbaikan',
                    'items' => [
                        'Forecast dengan rencana pengeluaran (bernilai bersih negatif) tidak lagi gagal disimpan — kolom header Forecast diubah menjadi bertanda (signed).',
                        'Lampiran per item pada Cost Request (gambar/dokumen) yang sebelumnya gagal dimuat (500) karena salah nama tabel kini bisa dibuka kembali.',
                        'Tombol Clear Filter pada Beneficiary Master benar-benar mengosongkan hasil pencarian, tidak lagi menyisakan baris tersaring.',
                        'Halaman Beneficiary Master tidak lagi menampilkan dua scrollbar bertumpuk pada modal Detail PIC.',
                    ],
                ],
            ],
        ],

        [
            'version' => '2.2.0',
            'date'    => '2026-09-21',
            'title'   => 'Second Payment Dibayar Lewat Treasury',
            'summary' => 'Kekurangan bayar hasil Settlement (Second Payment) tidak lagi cukup ditandai '
                       . 'lunas dari To-Do List. Sekarang ia masuk batch Treasury seperti pengeluaran '
                       . 'lain: ada baris kas, bukti transfer, approval CEO, dan jejak di riwayat CR.',
            'sections' => [
                [
                    'type'  => 'changed',
                    'label' => 'Berubah',
                    'items' => [
                        'Nomor Cost Request 4 digit lebih pendek: slot cluster dihapus, jadi CR-{bulan}{tahun}{kode PO}{urut} - contoh CR-0926008500180039 kini terbit sebagai CR-092600850039. Kode lama tidak diubah.',
                        'Second Payment kini dipilih saat membuat batch Treasury, sejajar Cost Request dan Cost Register.',
                        'Tombol "Mark Paid" Second Payment di To-Do List dihapus — bagian itu sekarang informatif saja dan menampilkan kode batch yang membayarnya.',
                        'Second Payment ikut file transfer bank dan Export batch, dengan nominal selisihnya saja dan Reference berakhiran "-2".',
                        'Riwayat Cost Request mencatat Second Payment masuk batch, disetujui CEO, dibayar, atau di-takeout.',
                        'Modal detail Cost Request menampilkan status Second Payment beserta batch Treasury-nya, bukan sekadar "Pending".',
                    ],
                ],
                [
                    'type'  => 'fixed',
                    'label' => 'Perbaikan',
                    'items' => [
                        'Second Payment tidak bisa lagi masuk ke dua batch sekaligus.',
                        'Batch yang memuat Second Payment tidak lagi menghitung ganda rincian Chart of Account maupun nilai Summary Export.',
                    ],
                ],
            ],
        ],

        [
            'version' => '2.1.0',
            'date'    => '2026-09-18',
            'title'   => 'Pengamanan Data, Ketahanan Transaksi & Jaring Uji',
            'summary' => 'Hasil audit menyeluruh: lampiran keuangan tidak lagi bisa diunduh tanpa login, '
                       . 'approval tidak bisa dobel, nomor dokumen tidak bisa bentrok, dan penghapusan '
                       . 'pembayaran meninggalkan jejak. Tidak ada perubahan alur kerja.',
            'sections' => [
                [
                    'type'  => 'fixed',
                    'label' => 'Perbaikan',
                    'items' => [
                        'Pengecekan NOB kini menghitung Cost Request berstatus Checked — sebelumnya sisa anggaran terlihat lebih besar dari kenyataan.',
                        'Dua klik Approve bersamaan tidak lagi menghasilkan log dan notifikasi ganda.',
                        'Nomor Invoice / Cost Request / Daily Transaction / Surat Jalan tidak lagi bisa bentrok atau terpakai ulang.',
                        'Pembayaran tidak bisa lagi melebihi sisa tagihan saat dua Cash Receipt dicatat bersamaan.',
                        'Cash Receipt bernominal nol tidak lagi bisa tersimpan.',
                        'Membuka daftar Purchase Order tidak lagi diam-diam mengubah data.',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Berubah',
                    'items' => [
                        'Lampiran hanya bisa dibuka setelah login dan sesuai hak akses modulnya.',
                        'Menghapus Cash Receipt sekarang wajib beralasan dan datanya tetap tersimpan untuk audit.',
                        'Notifikasi dikirim lewat antrean, jadi approval tidak lagi menunggu API WhatsApp.',
                        'Daftar panjang dibatasi 1.000 baris terbaru dengan pemberitahuan; angka ringkasan tetap dari seluruh data.',
                        'Form Invoice (tambah/ubah/lihat) memakai tata letak menurun.',
                    ],
                ],
            ],
        ],

        [
            'version' => '2.0.0',
            'date'    => '2026-09-15',
            'title'   => 'Rilis Besar: Settlement, Modul Team & Finance Forecast',
            'summary' => 'Alur Settlement Cash Advance dirombak jadi 3 tahap approval, PO & Invoice '
                       . 'kini menghitung nilai/jatuh tempo otomatis, dan dua modul baru — Team serta '
                       . 'Finance Forecast — mulai tersedia.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Modul Team: struktur organisasi (Department, Team, Team Member) dengan Organization Chart dinamis berdasarkan level.',
                        'Akses PO kini bisa diberikan lewat Team → Access Group, selain assignment langsung per user.',
                        'Modul Finance Forecast: rencana vs realisasi per periode, dengan status Draft → Active → Closed, Variance & Achievement %, serta auto-close terjadwal.',
                        'Second Payment otomatis pada Cost Request Cash Advance saat Settlement melebihi nilai pengajuan awal.',
                        'Lampiran per item (selain global) pada Settlement Cost Request.',
                        'Kolom "On Review" pada modal View Budget Todo List, memisahkan nilai pengajuan yang masih pra-approval dari yang sudah Approved.',
                        'Section "Treasury — Buat Batch Pembayaran" di Todo List (CR Approved yang siap dibatch, sebelumnya tidak muncul di worklist manapun).',
                        'PO Quantity + alert "PO Adjustment Required" saat jumlah No. PO tidak sesuai ekspektasi.',
                        'Invoice: tombol "Tandai Diterima" dan Jatuh Tempo Aktual (dihitung dari tanggal terima, bukan tanggal cetak invoice).',
                        'Invoice: Cancel & Revise (khusus Finance Manager) untuk mengembalikan invoice SENT ke DRAFT tanpa kehilangan histori.',
                        '6 KPI card baru + Active Cash Advance + PO Summary (dengan formula VAR yang bisa dipilih) pada Summary Finance.',
                        'Popup "What\'s New" otomatis saat membuka Dashboard, bisa dimatikan per rilis lewat centang "jangan tampilkan lagi".',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Perubahan',
                    'items' => [
                        'Settlement Cash Advance kini 3 tahap approval: Head Check → Finance Verify → CEO Close (sebelumnya 1 langkah Finance langsung Close). Label "Realization" diganti "Settlement" di seluruh tampilan.',
                        'Batas maksimal nilai Settlement (tidak boleh melebihi pengajuan) dihapus — kelebihan sekarang ditagihkan lewat Second Payment.',
                        'Nilai PO tidak lagi diinput manual, otomatis dihitung dari total nominal seluruh No. PO.',
                        'Budget Plan otomatis masuk tahap Closing begitu PO terkait sudah Paid.',
                        'Urutan tab Summary Finance: Executive → Collection → Budget (sebelumnya Executive → Budget → Collection). Tabel Customer Outstanding & Manager Collection Performance digabung jadi satu tabel dengan toggle grouping.',
                        'Tampilan/header puluhan halaman (Create/Edit/Detail) dirapikan agar konsisten dengan pola modern yang sudah dipakai di semua halaman index — tanpa mengubah warna tema.',
                        'Proteksi delete User yang masih menjadi Project Manager atau punya Cost Request.',
                        'Source Fund wajib diisi saat membuat batch Treasury maupun Invoice; dropdown Source Fund di Invoice hanya menampilkan rekening yang mengandung "PT".',
                    ],
                ],
            ],
        ],

        [
            'version' => '1.5.0',
            'date'    => '2026-09-08',
            'title'   => 'Penyempurnaan Summary Collection',
            'summary' => 'Definisi Outstanding pada Summary Collection dibakukan dan '
                       . 'ditambahkan filter periode.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Filter bulan (col_month) pada halaman Summary Collection.',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Perubahan',
                    'items' => [
                        'Outstanding = invoice berstatus RECEIVED / PARTIALLY_PAID ditambah invoice Overdue; invoice SENT yang belum jatuh tempo tidak lagi dihitung.',
                        'Logika Outstanding & Overdue dipusatkan pada satu closure bersama agar konsisten antar tampilan.',
                    ],
                ],
            ],
        ],

        [
            'version' => '1.4.0',
            'date'    => '2026-09-01',
            'title'   => 'Project Manager & kolom biaya proyek',
            'summary' => 'PM Internal kini berbasis data user, dan proyek memiliki '
                       . 'kolom biaya kontrak / overhead / fixed cost.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Kolom Contract, Overhead, dan Fixed Cost pada data proyek.',
                        'Pemilihan Project Manager Internal dari daftar user.',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Perubahan',
                    'items' => [
                        'PM Internal mengacu ke projects.manager_id (relasi ke tabel users), menggantikan input teks bebas.',
                    ],
                ],
            ],
        ],

        [
            'version' => '1.3.0',
            'date'    => '2026-08-25',
            'title'   => 'Lampiran per item pada Cost Request',
            'summary' => 'Form Cost Request mendukung lampiran di tingkat item maupun global.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Lampiran per item (request detail attachment) pada form Cost Request.',
                        'Lampiran global pada Cost Request.',
                        'Hint alur kerja yang muncul saat memilih tipe Cost Request.',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Perubahan',
                    'items' => [
                        'Proses simpan detail (syncDetails) melakukan upsert berdasarkan id item agar lampiran tidak hilang saat edit.',
                    ],
                ],
            ],
        ],

        [
            'version' => '1.2.1',
            'date'    => '2026-08-18',
            'title'   => 'Takeout item saat approval Cost Request',
            'summary' => 'Approver dapat mengeluarkan atau mengembalikan item Cost Request '
                       . 'per baris tanpa menolak seluruh pengajuan.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Head / Finance / CEO dapat takeout / restore item (request detail) selama proses approval.',
                        'Nilai yang diproses otomatis berkurang sesuai item yang dikeluarkan.',
                    ],
                ],
                [
                    'type'  => 'changed',
                    'label' => 'Perubahan',
                    'items' => [
                        'Bila seluruh item dikeluarkan, Cost Request otomatis berstatus REJECTED.',
                    ],
                ],
            ],
        ],

        [
            'version' => '1.2.0',
            'date'    => '2026-08-13',
            'title'   => 'Pemisahan alur kerja Cost Request',
            'summary' => 'Alur Cost Request dibedakan antara Cash Advance dan tipe lainnya.',
            'sections' => [
                [
                    'type'  => 'changed',
                    'label' => 'Perubahan',
                    'items' => [
                        'Cash Advance memakai siklus penuh: QUEUED -> PAID -> REALIZED -> CLOSED.',
                        'Tipe Cost Request lain langsung QUEUED -> CLOSED saat mark-paid.',
                        'Label status diselaraskan: QUEUED = "Process", REALIZED = "Settle".',
                    ],
                ],
            ],
        ],

        [
            'version' => '1.1.0',
            'date'    => '2026-08-04',
            'title'   => 'Perombakan tampilan Budget Plan',
            'summary' => 'Halaman Budget Plan mendapat pola UI baru yang kemudian menjadi '
                       . 'acuan halaman index / show lainnya.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Modal form Budget Plan dengan ukuran & tata letak baru.',
                        'Sidebar cost center di dalam form.',
                        'Tab-tab pengelompokan isian.',
                        'Pewarnaan baris per NOB (Nature of Business).',
                        'Sorting kolom dan filter pada tabel.',
                        'Lampiran dan log perubahan Budget Plan.',
                    ],
                ],
            ],
        ],

        [
            'version' => '1.0.0',
            'date'    => '2026-07-24',
            'title'   => 'Rilis perdana Tesys (Terex System)',
            'summary' => 'Aplikasi operasional & keuangan Terex mulai digunakan: '
                       . 'manajemen proyek, budgeting, cost request, invoicing, '
                       . 'collection, payroll, dan dashboard terpadu.',
            'sections' => [
                [
                    'type'  => 'added',
                    'label' => 'Baru',
                    'items' => [
                        'Dashboard terpadu: panel Need Action, Reminder, dan Last Activity per pengguna.',
                        'Modul Project & Project Monitoring: aktivasi proyek, detail, stage, dan lampiran.',
                        'Modul Budget Plan dan Budget Request beserta realisasinya.',
                        'Modul Cost Request dengan alur approval berjenjang.',
                        'Modul Invoice & Cash Receipt dan Summary Collection.',
                        'Modul Payroll Run, Daily Transaction, dan Finance Summary.',
                        'Master data: Customer, Beneficiary, Bank, Cluster, Cluster Group, Cost Center, Source Fund, Stage, Request Type, Master PIC.',
                        'Manajemen akses: User, Role, Access Group, middleware CheckRole, dan mode "View As".',
                        'TesysAI (Beta): asisten tanya-jawab operasional dengan riwayat percakapan.',
                    ],
                ],
            ],
        ],

    ],

];

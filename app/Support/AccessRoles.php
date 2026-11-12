<?php

namespace App\Support;

/**
 * Kelompok kewenangan sebagai satu sumber kebenaran.
 *
 * Nama peran di aplikasi ini tersebar sebagai string di belasan daftar yang
 * harus tetap sinkron: 30+ gerbang `role:` di routes/web.php, ditambah
 * konstanta di FinanceSummaryController (PO_VALUE_ROLES, EXECUTIVE_ROLES,
 * FULL_SCOPE_ROLES, COLLECTION_ROLES), ProjectMonitoringController
 * (MENTION_BYPASS_ROLES, HEAD_ROLES), BeneficiaryController (MANAGE_ROLES),
 * Project (FULL_EDIT_ROLES), dan sebuah daftar literal di
 * ProjectController::canViewProjectJson().
 *
 * Komentar di routes/web.php sendiri sudah mencatat gejalanya dengan tepat:
 * kalau dua daftar yang seharusnya sama ternyata berbeda, "tombolnya terlihat
 * lalu menabrak 403, atau sebaliknya hilang padahal role-nya berhak". Itu
 * bukan kemungkinan teoretis — itu sudah terjadi berulang kali.
 *
 * Kelas ini TIDAK menggantikan RoleDirectory. RoleDirectory menjawab "siapa
 * saja pemegang peran X" (untuk mengirim notifikasi); kelas ini menjawab
 * "peran mana yang berwenang atas X" (untuk memutuskan akses). Keduanya soal
 * peran, tapi dipakai di tempat yang berbeda dan tidak boleh dicampur.
 *
 * Pengisiannya bertahap: kelompok yang sudah dipakai dipindahkan ke sini
 * seiring modulnya disentuh, bukan diborong sekaligus.
 */
final class AccessRoles
{
    /**
     * Melihat SELURUH Purchase Order, tanpa dibatasi Access Group.
     *
     * Sebelum 26 Sep 2026 halaman /project tidak menyaring baris sama sekali:
     * siapa pun pemegang menu list-po melihat seluruh PO perusahaan beserta
     * nilai kontrak dan marginnya. Itu membatalkan aturan privasi yang dijaga
     * ketat di Finance Summary (nilai PO disembunyikan dari Manager) dan di
     * Project Monitoring (baris dibatasi Access Group) — cukup membuka
     * halaman List PO untuk melewatinya.
     *
     * Daftar ini sengaja PENDEK. Peran lain tetap bisa membuka halamannya,
     * hanya melihat PO yang memang ada di Access Group-nya (individu ∪ team).
     *
     * 6 Nov 2026 (blueprint team-akses-organisasi, K4): fungsi Finance —
     * cost_control, collection, treasury, accounting — melihat SELURUH PO.
     * Halaman tetap digerbang menu (menu:list-po dll.), jadi peran tanpa menu
     * tidak otomatis bisa membukanya. 'finance' umum sengaja TIDAK ditambah.
     * Hanya hak MELIHAT — tombol aksi PO tetap mengikuti daftar perannya sendiri.
     */
    public const PO_FULL_SCOPE = [
        'superadmin', 'approver', 'finance_manager',
        'cost_control', 'collection', 'treasury', 'accounting',
    ];

    /**
     * Bagian PO_FULL_SCOPE yang WAJIB punya menu 'list-po' untuk melihat
     * detail PO lewat URL langsung (detail-json tidak digerbang menu).
     * Keputusan K4: fungsi Finance tetap dibatasi menu. Peran full scope
     * lama (superadmin/approver/finance_manager) tidak terpengaruh.
     */
    public const PO_FULL_SCOPE_MENU_GATED = ['cost_control', 'collection', 'treasury', 'accounting'];

    /**
     * Melihat kolom bernilai uang pada PO (Contract, PO Value, Payment,
     * Gross Profit, Overhead, Fixed Cost, Net Profit).
     *
     * Disamakan PERSIS dengan FinanceSummaryController::PO_VALUE_ROLES supaya
     * kedua halaman tidak lagi bisa berbeda pendapat tentang siapa yang boleh
     * melihat nilai PO. Yang di luar daftar ini melihat kolomnya tetap ada
     * (agar indeks kolom, filter dan ekspor tidak bergeser) tapi isinya
     * ditutup.
     *
     * FinanceSummaryController::PO_VALUE_ROLES kini MERUJUK konstanta ini,
     * jadi keduanya tidak bisa lagi berbeda. Fungsi Finance (K4) ditambah
     * 6 Nov 2026 — ikut berlaku di Finance Summary.
     */
    public const PO_VALUE = [
        'superadmin', 'approver', 'finance', 'finance_manager', 'head', 'guest',
        'cost_control', 'collection', 'treasury', 'accounting',
    ];

    /**
     * Melihat KOLOM uang di Daftar Purchase Order sama sekali.
     *
     * Lebih ketat daripada PO_VALUE: di sana kolomnya tetap ada tapi isinya
     * ditutup, di sini kolomnya tidak dirender sama sekali - termasuk dropdown
     * "Kolom" yang isinya memang cuma kolom-kolom itu. Peran di luar daftar ini
     * hanya melihat Budget & Variable Cost. Fungsi Finance (K4) ditambah
     * 6 Nov 2026.
     */
    public const PO_MONEY_COLUMNS = [
        'superadmin', 'approver', 'finance_manager',
        'cost_control', 'collection', 'treasury', 'accounting',
    ];

    /**
     * Boleh MENGUBAH Purchase Order pada status apa pun.
     *
     * Dipindahkan dari Project::FULL_EDIT_ROLES. Pembatasan per-status untuk
     * peran lain tetap dijaga di ProjectController::edit()/update().
     */
    public const PO_FULL_EDIT = ['superadmin', 'approver', 'finance_manager'];

    /**
     * Boleh mengisi bagian Budget pada form Purchase Order (Budget Plan yang
     * ikut dibuat bersama PO dan ikut approval PO). Keputusan 22 Okt 2026:
     * sama dengan akses form Tambah PO (route project/create). Harus SAMA
     * dengan gerbang route pembuatan PO, ProjectController::store()/update(),
     * dan kondisi bagian Budget di project/create & project/edit.
     */
    public const PO_BUDGET_BUNDLE = ['superadmin', 'head'];

    /**
     * Boleh MENGUBAH & MENGHAPUS baris Beneficiary yang sudah ada.
     *
     * Dipindahkan dari BeneficiaryController::MANAGE_ROLES. Harus SAMA PERSIS
     * dengan lapis kedua gerbang route 'beneficiaries' — komentar di
     * routes/web.php menjelaskan kenapa: kalau keduanya berbeda, tombol Edit
     * terlihat lalu menabrak 403, atau sebaliknya hilang padahal berhak.
     *
     * Sengaja sempit: Beneficiary dipakai Cost Request yang sudah disetujui,
     * jadi menyunting nomor rekening sebuah baris diam-diam mengubah arti
     * dokumen yang sudah berjalan.
     */
    public const BENEFICIARY_MANAGE = ['superadmin'];

    /**
     * Boleh MENGUBAH & MENGHAPUS baris PIC yang sudah ada (halaman gabungan
     * "PIC & Beneficiary", menu 'pic-beneficiary').
     *
     * Sejak Beneficiary & PIC digabung (2026-11-09) keduanya berada di balik
     * SATU kunci menu, jadi menu itu hanya membuka MELIHAT & MENAMBAH.
     * Mengubah/menghapus PIC dikunci lapis kedua 'role:superadmin' pada route
     * master-pic.edit-data/update/destroy — harus SAMA PERSIS dengan konstanta
     * ini (tombol Edit/Hapus PIC dirender berdasarkan konstanta ini).
     *
     * PIC menentukan pagu Cash Advance (token/nominal) dan dipakai Customer,
     * Project, Site & Homebase, jadi dibuat sesempit Beneficiary.
     */
    public const PIC_MANAGE = ['superadmin'];

    /**
     * Berperan sebagai "Head" di Project Monitoring — bisa ACC/tolak pengajuan
     * penyelesaian milestone. Dipindahkan dari
     * ProjectMonitoringController::HEAD_ROLES.
     */
    public const MONITORING_HEAD = ['superadmin', 'approver'];

    /**
     * Trouble Ticket — melihat SEMUA tiket tanpa dibatasi rantai
     * site → PIC → team (TroubleTicket::scopeVisibleTo()).
     */
    public const TROUBLE_TICKET_FULL_SCOPE = ['superadmin'];

    /**
     * Trouble Ticket — cadangan pengawas: approve/reject Closed, reassign,
     * cancel, koreksi keputusan kunjungan, SELAIN leader team teknisinya
     * (TroubleTicketWorkflow::canSupervise()). Peran ini tetap tidak boleh
     * menyetujui pengajuan Closed miliknya sendiri.
     */
    public const TROUBLE_TICKET_SUPERVISE = ['superadmin'];

    /**
     * Trouble Ticket — role pengawas tiket: dasar TroubleTicket::supervises()
     * (melihat tiket di luar tugasnya). Hak approve tidak lagi diberikan role
     * ini; diperiksa lewat hierarki di TroubleTicketWorkflow::canSupervise().
     */
    public const TROUBLE_TICKET_APPROVE = ['superadmin', 'supervisor'];

    /**
     * Trouble Ticket — kelola kategori & target SLA. Harus sama dengan
     * gerbang role: pada route trouble-ticket/settings.
     */
    public const TROUBLE_TICKET_SETTINGS = ['superadmin'];

    /**
     * Trouble Ticket — baca-saja tiket yang punya Cost Request, supaya tautan
     * dari CR bisa dibuka (keputusan 6 Nov 2026). Tidak memberi aksi apa pun;
     * menu trouble-ticket tetap diberikan lewat User & Access.
     */
    public const TROUBLE_TICKET_FINANCE_READ = ['finance', 'finance_manager', 'cost_control', 'treasury'];

    /**
     * Trouble Ticket — role yang TIDAK boleh membuat tiket. Teknisi hanya
     * mengerjakan tiket yang ditugaskan padanya, bukan membuatnya
     * (TroubleTicketController::create()/store() dan tombol "Buat Tiket").
     */
    public const TROUBLE_TICKET_CREATE_DENIED = ['teknisi'];

    /**
     * Site — hanya boleh MELIHAT: daftar, detail, dan unduh dokumen site.
     * Semua aksi tulis (tambah/ubah/hapus site, import, PIC, material,
     * accessories, dokumen, maintenance) ditolak di SiteController.
     *
     * Daftar site yang terlihat juga dipersempit ke customer miliknya sendiri
     * (SiteController::visibleCustomerIds()): customer dari site tempat ia
     * terdaftar sebagai Teknisi atau Teknisi Backup.
     */
    public const SITE_READ_ONLY = ['teknisi'];

    /**
     * Asset Management (2026-11-12) — pemegang menu 'asset-management' boleh
     * melihat dan mengajukan (registrasi, serah terima, transfer, check-out,
     * maintenance, inspeksi, opname, PR, penerimaan). Daftar ini mengelola:
     * menyetujui/menolak transfer, disposal & PR, menghapus aset, menyelesaikan
     * stock opname, serta mengubah kategori & lokasi aset.
     * Dipakai di route (role:), AssetController & kawan-kawan, dan Blade.
     */
    public const ASSET_MANAGE = ['superadmin', 'asset_admin'];
}

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
     * hanya melihat PO yang memang ada di Access Group-nya.
     */
    public const PO_FULL_SCOPE = ['superadmin', 'approver', 'finance_manager'];

    /**
     * Melihat kolom bernilai uang pada PO (Contract, PO Value, Payment,
     * Gross Profit, Overhead, Fixed Cost, Net Profit).
     *
     * Disamakan PERSIS dengan FinanceSummaryController::PO_VALUE_ROLES supaya
     * kedua halaman tidak lagi bisa berbeda pendapat tentang siapa yang boleh
     * melihat nilai PO. Yang di luar daftar ini melihat kolomnya tetap ada
     * (agar indeks kolom, filter dan ekspor tidak bergeser) tapi isinya
     * ditutup.
     */
    public const PO_VALUE = ['superadmin', 'approver', 'finance', 'finance_manager', 'head', 'guest'];

    /**
     * Melihat KOLOM uang di Daftar Purchase Order sama sekali.
     *
     * Lebih ketat daripada PO_VALUE: di sana kolomnya tetap ada tapi isinya
     * ditutup, di sini kolomnya tidak dirender sama sekali - termasuk dropdown
     * "Kolom" yang isinya memang cuma kolom-kolom itu. Peran di luar daftar ini
     * hanya melihat Budget & Variable Cost.
     */
    public const PO_MONEY_COLUMNS = ['superadmin', 'approver', 'finance_manager'];

    /**
     * Boleh MENGUBAH Purchase Order pada status apa pun.
     *
     * Dipindahkan dari Project::FULL_EDIT_ROLES. Pembatasan per-status untuk
     * peran lain tetap dijaga di ProjectController::edit()/update().
     */
    public const PO_FULL_EDIT = ['superadmin', 'approver', 'finance_manager'];

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
     * Trouble Ticket — approve/tolak penutupan tiket di tahap Progress Done
     * (keputusan 12 Okt 2026: hanya atasan). Pelaku tidak boleh menyetujui
     * penutupan yang ia kirim sendiri — diperiksa di TroubleTicketWorkflow.
     */
    public const TROUBLE_TICKET_APPROVE = ['superadmin', 'supervisor'];

    /**
     * Trouble Ticket — kelola kategori & target SLA. Harus sama dengan
     * gerbang role: pada route trouble-ticket/settings.
     */
    public const TROUBLE_TICKET_SETTINGS = ['superadmin'];

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
}

<?php

namespace App\Support;

/**
 * Status Cost Request mana yang "memakai" budget — dan untuk keperluan apa.
 *
 * Aplikasi memakai tiga pengertian "terpakai" yang memang berbeda, dan itu
 * benar; masalahnya dulu ketiganya ditulis sebagai daftar mentah di lima
 * berkas berbeda, sehingga satu status yang terlewat di salah satu tempat
 * tidak pernah ketahuan. Yang terlewat itu nyata: 'CHECKED' tidak ada di
 * daftar komitmen, jadi CR yang sudah disetujui Head dan sedang menunggu
 * verifikasi Finance dianggap tidak memakan budget sama sekali — pengendalian
 * anggaran melaporkan sisa yang lebih besar dari kenyataan, persis di tahap
 * paling ramai.
 *
 * Tiga pengertian itu:
 *
 *   COMMITTED  — sudah mengikat anggaran. Dipakai pengecekan NOB (Not On
 *                Budget) saat CR dibuat/diubah: begitu pengaju menekan Submit,
 *                dananya harus dianggap terpesan, karena ia akan cair kecuali
 *                ada yang menolaknya.
 *
 *   DISBURSED  — uangnya sudah keluar. Dipakai "Used" pada Budget Plan &
 *                sub-itemnya, yang memang mengukur realisasi, bukan rencana.
 *
 *   REPORTED   — dipakai Finance Summary & Report, yang menghitung sejak
 *                Finance memverifikasi (komitmen yang sudah lolos pemeriksaan).
 *
 * Status DRAFT, REJECTED dan CANCELLED tidak pernah memakai budget.
 */
final class BudgetConsumption
{
    /** Mengikat anggaran (pengecekan NOB). */
    public const COMMITTED = [
        'SUBMITTED',
        'CHECKED',
        'VERIFIED',
        'APPROVED',
        'QUEUED',
        'PAID',
        'REALIZED',
        'SETTLE_CHECKED',
        'SETTLE_VERIFIED',
        'SETTLE_APPROVED',
        'SECOND_APPROVED',
        'CLOSED',
        // Settlement ditolak: dananya belum balik, tetap mengikat anggaran
        // sama seperti PAID.
        'SETTLE_REJECTED',
    ];

    /**
     * Uang sudah keluar (angka "Used" pada Budget Plan). SETTLE_APPROVED dan
     * SECOND_APPROVED dulu tidak ada di sini, sehingga CR yang settlement-nya
     * punya expense claim hilang dari Used begitu masuk tahap itu. Untuk query
     * Used, pakai scope BudgetRequest::disbursed() — ia juga menangkap leg
     * expense claim yang kembali ke QUEUED.
     */
    public const DISBURSED = [
        'PAID',
        'REALIZED',
        'SETTLE_CHECKED',
        'SETTLE_VERIFIED',
        'SETTLE_APPROVED',
        'SECOND_APPROVED',
        'CLOSED',
        // Sama seperti PAID: uang sudah keluar, settlement-nya tinggal
        // direvisi dan diajukan ulang.
        'SETTLE_REJECTED',
    ];

    /**
     * Terpakai menurut laporan (sejak lolos verifikasi Finance). APPROVED
     * dulu terlewat, sehingga CR yang baru di-approve CEO hilang dari laporan
     * sampai masuk batch Treasury.
     */
    public const REPORTED = [
        'VERIFIED',
        'APPROVED',
        'QUEUED',
        'PAID',
        'REALIZED',
        'SETTLE_CHECKED',
        'SETTLE_VERIFIED',
        'SETTLE_APPROVED',
        'SECOND_APPROVED',
        'CLOSED',
        'SETTLE_REJECTED',
    ];

    /** Tidak pernah memakai budget. */
    public const NONE = ['DRAFT', 'REJECTED', 'CANCELLED'];
}

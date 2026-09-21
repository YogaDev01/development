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
        'CLOSED',
    ];

    /** Uang sudah keluar (angka "Used" pada Budget Plan). */
    public const DISBURSED = [
        'PAID',
        'REALIZED',
        'SETTLE_CHECKED',
        'SETTLE_VERIFIED',
        'CLOSED',
    ];

    /** Terpakai menurut laporan (sejak lolos verifikasi Finance). */
    public const REPORTED = [
        'VERIFIED',
        'QUEUED',
        'PAID',
        'REALIZED',
        'SETTLE_CHECKED',
        'SETTLE_VERIFIED',
        'CLOSED',
    ];

    /** Tidak pernah memakai budget. */
    public const NONE = ['DRAFT', 'REJECTED', 'CANCELLED'];

    /*
    |--------------------------------------------------------------------------
    | BERAPA RUPIAH yang terpakai oleh satu Cost Request
    |--------------------------------------------------------------------------
    | Daftar status di atas menjawab "CR mana yang memakai anggaran". Bagian ini
    | menjawab pertanyaan yang berbeda dan sama pentingnya: BERAPA nilainya.
    |
    | Aturannya:
    |     Request + Second Payment   (bila Settlement melebihi pengajuan)
    |     Request - Refund           (bila Settlement di bawah pengajuan)
    |
    | Keduanya bermuara pada angka yang sama — nilai Settlement — sehingga satu
    | rumus cukup: pakai total realisasi bila CR sudah settle, pakai nilai
    | pengajuan bila belum. Nilai pengajuan sendiri (kolom `amount`) tidak
    | pernah berubah setelah Settlement, jadi menjumlahkannya langsung akan
    | melewatkan Second Payment maupun Refund.
    |
    | Versi PHP-nya ada di BudgetRequest::realizedCostValue(). Yang di bawah ini
    | versi SQL-nya, untuk query agregat yang tidak menghidrasi model.
    */

    /** Status yang settlement-nya sudah final (cermin BudgetRequest::SETTLED_STATUSES). */
    public const SETTLED = ['SETTLE_CHECKED', 'SETTLE_VERIFIED', 'CLOSED'];

    /**
     * Ekspresi SQL nilai terpakai per baris Cost Request.
     *
     * Mengandaikan query-nya sudah di-LEFT JOIN dengan subquery realisasi yang
     * dihasilkan realizationJoinSql(), memakai alias yang sama.
     */
    public static function usedValueSql(string $crTable = 'budget_request', string $realizationAlias = 'r'): string
    {
        $settled = "'" . implode("','", self::SETTLED) . "'";

        return "CASE WHEN {$crTable}.status IN ({$settled}) AND COALESCE({$realizationAlias}.total, 0) > 0"
             . " THEN {$realizationAlias}.total ELSE {$crTable}.amount END";
    }

    /** Subquery total realisasi per Cost Request, untuk di-LEFT JOIN. */
    public static function realizationSubquerySql(): string
    {
        return '(SELECT budget_request_id, SUM(total) AS total'
             . ' FROM budget_request_realizations GROUP BY budget_request_id)';
    }
}

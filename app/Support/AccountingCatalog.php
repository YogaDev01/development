<?php

namespace App\Support;

/**
 * Katalog tetap modul Accounting: tipe akun, sumber jurnal, dan "tipe
 * transaksi" yang bisa dipetakan ke akun. Akun COA-nya sendiri TIDAK ditulis
 * di sini — itu data yang dikelola akuntan lewat Chart of Accounts.
 */
final class AccountingCatalog
{
    public const ACCOUNT_TYPES = [
        'ASSET'     => ['Aset', 'DEBIT'],
        'LIABILITY' => ['Liabilitas', 'CREDIT'],
        'EQUITY'    => ['Ekuitas', 'CREDIT'],
        'REVENUE'   => ['Pendapatan', 'CREDIT'],
        'EXPENSE'   => ['Beban', 'DEBIT'],
    ];

    /**
     * Penempatan akun di laporan keuangan, per tipe akun: [kode => label].
     * Elemen pertama tiap tipe = bawaan bila statement_section kosong.
     */
    public const SECTIONS = [
        'ASSET'     => ['CURRENT_ASSET' => 'Aset Lancar', 'NON_CURRENT_ASSET' => 'Aset Tidak Lancar'],
        'LIABILITY' => ['CURRENT_LIABILITY' => 'Liabilitas Jangka Pendek', 'NON_CURRENT_LIABILITY' => 'Liabilitas Jangka Panjang'],
        'EQUITY'    => ['CAPITAL' => 'Modal Disetor', 'RETAINED_EARNINGS' => 'Laba Ditahan', 'DISTRIBUTION' => 'Dividen / Distribusi', 'OTHER_EQUITY' => 'Ekuitas Lainnya'],
        'REVENUE'   => ['REVENUE' => 'Pendapatan', 'OTHER_INCOME' => 'Pendapatan Lain'],
        'EXPENSE'   => ['OPERATING_EXPENSE' => 'Beban Operasional', 'COST_OF_SERVICES' => 'Beban Pokok Layanan', 'OTHER_EXPENSE' => 'Beban Lain', 'TAX_EXPENSE' => 'Beban Pajak'],
    ];

    public const CASH_ACTIVITIES = ['OPERATING' => 'Operasi', 'INVESTING' => 'Investasi', 'FINANCING' => 'Pendanaan'];

    public const SOURCE_MODULES = [
        'TREASURY'     => 'Treasury',
        'CASH_RECEIPT' => 'Cash Receipt',
    ];

    /**
     * Tipe transaksi per sumber: [label, debit_is_bank, credit_is_bank].
     * Sisi "bank" diambil dari rekening transaksi (akun COA yang tertaut ke
     * source_fund); sisi lain wajib dipetakan ke satu akun.
     * TOP_UP Treasury (antar-rekening sendiri) tidak butuh mapping: kedua
     * sisinya bank, ditangani saat pembuatan jurnal.
     */
    /*
     * HANYA tipe yang benar-benar dipakai JournalGenerator. Klasifikasi beban /
     * aset tetap / biaya dibayar di muka / pelunasan utang dilakukan akuntan saat
     * meninjau jurnal (ganti akun per baris), bukan lewat tipe mapping tambahan.
     * Penerimaan dijurnal sebatas uang yang DITERIMA (tanpa PPh).
     */
    public const TRANSACTION_TYPES = [
        'TREASURY' => [
            'EXPENSE'     => ['Pembayaran Beban (akun usulan; klasifikasi aset/prepaid saat review)', false, true],
            'BANK_CHARGE' => ['Biaya Bank', false, true],
        ],
        'CASH_RECEIPT' => [
            'CUSTOMER_COLLECTION' => ['Penagihan Customer (pelunasan piutang)', true, false],
            'CUSTOMER_ADVANCE'    => ['Uang Muka Customer (belum dialokasikan)', true, false],
        ],
    ];

    public const PERIOD_STATUSES = ['OPEN' => 'Open', 'CLOSED' => 'Closed', 'LOCKED' => 'Locked'];

    public static function transactionLabel(string $module, string $type): string
    {
        return self::TRANSACTION_TYPES[$module][$type][0] ?? $type;
    }
}

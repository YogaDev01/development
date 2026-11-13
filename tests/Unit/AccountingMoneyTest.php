<?php

namespace Tests\Unit;

use App\Support\AccountingMoney;
use App\Support\CsvSafe;
use PHPUnit\Framework\TestCase;

/** Murni tanpa database / framework — aman dijalankan kapan pun. */
class AccountingMoneyTest extends TestCase
{
    public function test_parse_format_indonesia(): void
    {
        $this->assertSame(1234567, AccountingMoney::parse('12.345,67'));
        $this->assertSame(100000, AccountingMoney::parse('1000'));
        $this->assertSame(100050, AccountingMoney::parse('1.000,5'));
        $this->assertSame(1000000000, AccountingMoney::parse('Rp 10.000.000'));
        $this->assertSame(-505, AccountingMoney::parse('-5,05'));
        $this->assertSame(50, AccountingMoney::parse(',50'));
        $this->assertSame(100000, AccountingMoney::parse('1.000'));
    }

    public function test_parse_menolak_titik_desimal_dan_input_tidak_valid(): void
    {
        $this->assertNull(AccountingMoney::parse('1000.50'));   // titik bukan desimal
        $this->assertNull(AccountingMoney::parse('1.5'));
        $this->assertNull(AccountingMoney::parse('1,000.50'));
        $this->assertNull(AccountingMoney::parse('1.00'));
        $this->assertNull(AccountingMoney::parse('abc'));
        $this->assertNull(AccountingMoney::parse('1,234'));
        $this->assertNull(AccountingMoney::parse(''));
        $this->assertNull(AccountingMoney::parse(str_repeat('9', 30)));
        $this->assertNull(AccountingMoney::parse('1.2345'));
    }

    public function test_plain_untuk_csv(): void
    {
        $this->assertSame('12345.67', AccountingMoney::plain(1234567));
        $this->assertSame('0.05', AccountingMoney::plain(5));
        $this->assertSame('-100.05', AccountingMoney::plain(-10005));
    }

    public function test_format_selalu_dua_digit(): void
    {
        $this->assertSame('12.345,67', AccountingMoney::format(1234567));
        $this->assertSame('0,05', AccountingMoney::format(5));
        $this->assertSame('-100,05', AccountingMoney::format(-10005));
        $this->assertSame('10.000.000,00', AccountingMoney::format(AccountingMoney::fromRupiah(10000000)));
        $this->assertSame('-', AccountingMoney::format(0, true));
    }

    public function test_csv_safe_menetralkan_pemicu_rumus(): void
    {
        $this->assertSame("'=SUM(A1)", CsvSafe::cell('=SUM(A1)'));
        $this->assertSame("'+1", CsvSafe::cell('+1'));
        $this->assertSame("'-2", CsvSafe::cell('-2'));
        $this->assertSame("'@cmd", CsvSafe::cell('@cmd'));
        $this->assertSame("'  =1", CsvSafe::cell('  =1'));
        $this->assertSame("'\t=1", CsvSafe::cell("\t=1"));
        $this->assertSame('Biaya bank', CsvSafe::cell('Biaya bank'));
        $this->assertSame('', CsvSafe::cell(null));
    }
}

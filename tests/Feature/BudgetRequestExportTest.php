<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\DailyTransaction;
use App\Models\DailyTransactionItem;
use App\Models\SourceFund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\MakesTesysData;
use Tests\TestCase;

/**
 * Export Excel Cost Request: kolom "Transaction ID" harus tersimpan sebagai
 * teks (bukan angka — kalau tidak Excel menampilkannya sebagai notasi
 * ilmiah, mis. 2,02607E+14), dan kolom "Account" harus memuat No. Rekening
 * batch Treasury (Source Fund > no_account), bukan Chart of Account rincian.
 *
 * Lihat BudgetRequestController::export() & payingTransactionItem().
 */
class BudgetRequestExportTest extends TestCase
{
    use RefreshDatabase;
    use MakesTesysData;

    public function test_export_txid_column_is_text_and_account_column_shows_source_fund_no_account(): void
    {
        $cr = $this->makeCostRequest(['status' => 'PAID']);

        $bank = Bank::create(['name' => 'Bank Uji']);
        $sourceFund = SourceFund::create([
            'name'       => 'Rekening Operasional',
            'no_account' => '1234567890123',
            'bank_id'    => $bank->id,
            'status'     => 'ACTIVE',
        ]);

        $batch = DailyTransaction::create([
            'code'            => '202610130010001',
            'date'            => now()->toDateString(),
            'status'          => 'PAID',
            'type'            => 'CASH_OUT',
            'source_fund_id'  => $sourceFund->id,
            'created_by'      => $this->userWithRole('finance')->id,
        ]);

        DailyTransactionItem::create([
            'daily_transaction_id' => $batch->id,
            'budget_request_id'    => $cr->id,
            'status'                => 'INCLUDED',
        ]);

        $admin    = $this->userWithRole('superadmin');
        $response = $this->actingAs($admin)
            ->get(route('budget-request.export', ['ids' => [$cr->id]]));

        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'cr-export') . '.xlsx';
        file_put_contents($tmp, $response->streamedContent());

        $spreadsheet = IOFactory::load($tmp);
        $sheet       = $spreadsheet->getSheetByName('Header');

        // Header row: ... Q = Transaction ID, R = Account (lihat $activeCols).
        $this->assertSame('Transaction ID', $sheet->getCell('Q1')->getValue());
        $this->assertSame('Account', $sheet->getCell('R1')->getValue());

        $txidCell = $sheet->getCell('Q2');
        $this->assertSame(DataType::TYPE_STRING, $txidCell->getDataType());
        $this->assertSame('202610130010001', $txidCell->getValue());

        $accountCell = $sheet->getCell('R2');
        $this->assertSame(DataType::TYPE_STRING, $accountCell->getDataType());
        $this->assertSame('1234567890123', $accountCell->getValue());

        @unlink($tmp);
    }
}

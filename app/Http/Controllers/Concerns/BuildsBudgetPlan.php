<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Account;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanDetail;
use App\Models\Project;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Pembentuk Budget Plan yang dipakai bersama BudgetPlanController dan
 * ProjectController (budget yang ikut dibuat dari form PO).
 *
 * Isinya dipindahkan apa adanya dari BudgetPlanController - tidak satu aturan
 * pun diubah - supaya penomoran kode, normalisasi rupiah, dan penyimpanan
 * baris detail tidak punya dua salinan yang bisa menyimpang.
 */
trait BuildsBudgetPlan
{
    /** Kategori account yang boleh dipakai per jenis sumber dana.
     *  PENTING: nilai ini harus SAMA PERSIS dengan isi kolom accounts.category.
     *  Tipe cost center non-payroll lain (Tax/Asset/Liability/Capital/Adjustment)
     *  sengaja berbagi kategori "Overhead" yang sama — belum ada Account
     *  tersendiri untuk masing-masing tipe itu. */
    private const ACCOUNT_CATEGORY = [
        'PROJECT'    => 'Variable Cost',
        'OVERHEAD'   => 'Overhead',
        'PAYROLL'    => 'Fixed Cost',
        'TAX'        => 'Overhead',
        'ASSET'      => 'Overhead',
        'LIABILITY'  => 'Overhead',
        'CAPITAL'    => 'Overhead',
        'ADJUSTMENT' => 'Overhead',
    ];

    private function normalizeMoney(?string $value): int
    {
        if ($value === null || trim($value) === '') return 0;
        $stripped = preg_replace('/\D/', '', $value);
        return $stripped === '' ? 0 : (int) $stripped;
    }

    private function syncDetails(BudgetPlan $bp, Request $request): void
    {
        $this->syncBudgetDetails($bp, (array) $request->input('details', []));
    }

    /** Isi baris detail dari array baris (details[*]) - dipakai juga oleh form PO (budget.details[*]). */
    private function syncBudgetDetails(BudgetPlan $bp, array $rows): void
    {
        $bp->details()->delete();

        foreach ($rows as $row) {
            $amount = $this->normalizeMoney($row['amount'] ?? null);
            $qty    = (float) ($row['qty'] ?? 1);
            if ($qty <= 0) $qty = 1;

            if (!$amount) continue;

            $total = (int) round($amount * $qty);

            $bp->details()->create([
                'account_id' => $row['account_id'] ?? null,
                'category'   => 'Variable Cost',   // auto fixed
                'type'       => 'main',             // auto fixed
                'deskripsi'  => $row['deskripsi']   ?? null,
                'amount'     => $amount,
                'qty'        => $qty,
                'unit'       => $row['unit'] ?? null,
                'total'      => $total,
                'notes'      => $row['notes']       ?? null,
            ]);
        }

        $bp->recalculateAmount();
    }

    private function generateCode(Project $project): string
    {
        // Format: BD-{MM}{YY}{poCode(4)}{seq(4)}   contoh: BD-072600030002
        $poCode = str_pad($project->code ?? '0000', 4, '0', STR_PAD_LEFT);
        $prefix = 'BD-' . now()->format('m') . now()->format('y') . $poCode;

        $count = BudgetPlan::whereYear('created_at', now()->format('Y'))
            ->whereMonth('created_at', now()->format('m'))
            ->where('project_id', $project->id)
            ->count();

        return $this->buildUniqueCode($prefix, $count);
    }

    /** Tambahkan nomor urut & pastikan unik */
    private function buildUniqueCode(string $prefix, int $count): string
    {
        $seq       = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        $candidate = $prefix . $seq;

        $attempts = 0;
        while (BudgetPlan::where('code', $candidate)->exists() && $attempts < 100) {
            $count++;
            $seq       = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $seq;
            $attempts++;
        }

        return $candidate;
    }

    /** Account yang boleh dipakai, sesuai jenis sumber dana */
    private function getAccountsFor(string $sourceType)
    {
        $category = self::ACCOUNT_CATEGORY[strtoupper($sourceType)] ?? null;

        $query = Account::active()->orderBy('name');

        if ($category) {
            $query->byCategory($category);
        }

        return $query->get(['id', 'name']);
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT EXCEL — Detail Budget (dipakai Budget Plan & form PO)
    |--------------------------------------------------------------------------
    | Import TIDAK menyimpan ke database - hanya parsing & mengembalikan JSON,
    | lalu JS di form yang mengisi ulang tabel Detail Budget. Simpan tetap lewat
    | tombol "Simpan" seperti biasa. Dipindahkan apa adanya dari
    | BudgetPlanController; pemanggil bertugas memvalidasi request-nya.
    */

    private const IMPORT_HEADERS = ['Account', 'Deskripsi', 'Harga Satuan', 'Qty', 'Unit', 'Notes'];

    /** Template Excel siap isi, lengkap dengan dropdown Account & Unit. */
    private function budgetImportTemplateResponse(string $sourceType)
    {
        $sourceType = strtoupper($sourceType);
        $accounts   = $this->getAccountsFor($sourceType);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Detail Budget');

        // Header
        foreach (self::IMPORT_HEADERS as $i => $label) {
            $col = chr(65 + $i); // A, B, C, ...
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        // Contoh baris
        $sheet->setCellValue('A2', $accounts->first()->name ?? 'NAMA ACCOUNT');
        $sheet->setCellValue('B2', 'Contoh deskripsi item');
        $sheet->setCellValue('C2', 100000);
        $sheet->setCellValue('D2', 1);
        $sheet->setCellValue('E2', 'pcs');
        $sheet->setCellValue('F2', 'Catatan opsional');

        // Lebar kolom
        foreach (['A' => 30, 'B' => 30, 'C' => 16, 'D' => 8, 'E' => 14, 'F' => 30] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Sheet referensi: daftar Account & Unit yang valid (dipakai dropdown)
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi');
        $refSheet->setCellValue('A1', 'Daftar Account Valid');
        $refSheet->setCellValue('B1', 'Daftar Unit Valid');
        $refSheet->getStyle('A1:B1')->getFont()->setBold(true);

        foreach ($accounts->values() as $i => $acc) {
            $refSheet->setCellValue('A' . ($i + 2), $acc->name);
        }
        foreach (BudgetPlanDetail::UNITS as $i => $unit) {
            $refSheet->setCellValue('B' . ($i + 2), $unit);
        }
        $refSheet->getColumnDimension('A')->setWidth(30);
        $refSheet->getColumnDimension('B')->setWidth(18);

        // Dropdown Account (kolom A) & Unit (kolom E) di sheet utama, merujuk ke sheet Referensi
        $accountCount = max($accounts->count(), 1);
        $unitCount    = count(BudgetPlanDetail::UNITS);

        for ($row = 2; $row <= 200; $row++) {
            $validationAccount = $sheet->getCell('A' . $row)->getDataValidation();
            $validationAccount->setType(DataValidation::TYPE_LIST);
            $validationAccount->setErrorStyle(DataValidation::STYLE_WARNING);
            $validationAccount->setAllowBlank(true);
            $validationAccount->setShowInputMessage(true);
            $validationAccount->setShowErrorMessage(true);
            $validationAccount->setShowDropDown(false);
            $validationAccount->setPromptTitle('Pilih Account');
            $validationAccount->setPrompt('Pilih dari daftar di sheet Referensi.');
            $validationAccount->setFormula1("Referensi!\$A\$2:\$A\$" . ($accountCount + 1));

            $validationUnit = $sheet->getCell('E' . $row)->getDataValidation();
            $validationUnit->setType(DataValidation::TYPE_LIST);
            $validationUnit->setErrorStyle(DataValidation::STYLE_WARNING);
            $validationUnit->setAllowBlank(true);
            $validationUnit->setShowInputMessage(true);
            $validationUnit->setShowErrorMessage(true);
            $validationUnit->setShowDropDown(false);
            $validationUnit->setPromptTitle('Pilih Unit');
            $validationUnit->setPrompt('Pilih dari daftar di sheet Referensi.');
            $validationUnit->setFormula1("Referensi!\$B\$2:\$B\$" . ($unitCount + 1));
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'template-detail-budget-' . strtolower($sourceType) . '-' . now()->format('Ymd-His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Parsing file Excel (request 'file' sudah divalidasi pemanggil) menjadi
     * JSON { rows: [...], errors: [...] }. Tidak menyimpan apa pun ke database.
     */
    private function budgetImportPreviewResponse(Request $request, string $sourceType)
    {
        $accounts = $this->getAccountsFor($sourceType)
            ->keyBy(fn ($a) => strtoupper(trim($a->name)));

        $units = collect(BudgetPlanDetail::UNITS)->map(fn ($u) => strtoupper($u));

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getSheetByName('Detail Budget') ?? $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return response()->json([
                'rows'   => [],
                'errors' => ['File tidak dapat dibaca: ' . $e->getMessage()],
            ], 422);
        }

        $result = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            // Baris 0 = header, lewati
            if ($i === 0) continue;

            [$accName, $deskripsi, $amount, $qty, $unit, $notes] = array_pad($row, 6, null);

            // Lewati baris kosong total
            if (blank($accName) && blank($deskripsi) && blank($amount) && blank($qty)) {
                continue;
            }

            $rowNum = $i + 1; // nomor baris asli di Excel (1-based, +1 karena header)
            $accKey = strtoupper(trim((string) $accName));
            $account = $accounts->get($accKey);

            if (!$account && filled($accName)) {
                $errors[] = "Baris {$rowNum}: Account \"{$accName}\" tidak ditemukan di daftar Variable Cost/Overhead/Payroll yang sesuai.";
            }

            $unitKey    = strtoupper(trim((string) $unit));
            $unitValid  = blank($unit) || $units->contains($unitKey);
            if (!$unitValid) {
                $errors[] = "Baris {$rowNum}: Unit \"{$unit}\" tidak valid.";
            }

            $amountNum = is_numeric($amount) ? (int) round((float) $amount) : (int) $this->normalizeMoney((string) $amount);
            $qtyNum    = is_numeric($qty) ? (float) $qty : 1;
            if ($qtyNum <= 0) $qtyNum = 1;

            $result[] = [
                'row'         => $rowNum,
                'account_id'  => $account->id ?? null,
                'account_name'=> $account->name ?? $accName,
                'deskripsi'   => $deskripsi,
                'amount'      => $amountNum,
                'qty'         => $qtyNum,
                'unit'        => $unitValid && filled($unit) ? strtolower(trim((string) $unit)) : null,
                'notes'       => $notes,
                'total'       => $amountNum * $qtyNum,
            ];
        }

        if (empty($result) && empty($errors)) {
            $errors[] = 'Tidak ada baris data yang terbaca dari file. Pastikan mengisi mulai baris ke-2.';
        }

        return response()->json([
            'rows'   => $result,
            'errors' => $errors,
        ]);
    }
}

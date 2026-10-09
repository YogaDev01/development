<?php

namespace App\Http\Controllers\Concerns;

use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Import Excel untuk master data (dipakai bersama ManagesMasterRecords).
 *
 * Semua-atau-tidak: setiap baris divalidasi dengan rules() yang sama dengan
 * input manual; satu baris salah = tidak ada yang disimpan.
 *
 * Controller mendefinisikan importConfig():
 *   headers   => [kolom => contoh nilai]   (urutan = urutan kolom Excel)
 *   map       => fn (array $cells): array  -> [data, [field => pesan]]
 *   reference => fn (): array              -> [judul kolom => [nilai sah...]]
 *   unique    => [field...]                (tidak boleh kembar di dalam file)
 */
trait ImportsMasterRecords
{
    protected int $maxImportRows = 1000;

    abstract protected function importConfig(): array;

    public function importTemplate()
    {
        $m   = $this->master();
        $cfg = $this->importConfig();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        $col = 1;
        foreach ($cfg['headers'] as $label => $example) {
            $sheet->setCellValue([$col, 1], $label);
            $sheet->setCellValueExplicit([$col, 2], (string) $example, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getColumnDimensionByColumn($col)->setWidth(max(14, strlen($label) + 4));
            $col++;
        }
        $this->styleHeader($sheet, count($cfg['headers']));

        $ref = $spreadsheet->createSheet();
        $ref->setTitle('Referensi');
        $col = 1;
        foreach (($cfg['reference'])() as $title => $values) {
            $ref->setCellValue([$col, 1], $title);
            foreach (array_values($values) as $i => $value) {
                $ref->setCellValueExplicit([$col, $i + 2], (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $ref->getColumnDimensionByColumn($col)->setWidth(32);
            $col++;
        }
        $this->styleHeader($ref, $col - 1);
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'template-import-' . \Illuminate\Support\Str::slug($m['singular']) . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120'], [], ['file' => 'File Excel']);

        $m   = $this->master();
        $cfg = $this->importConfig();

        try {
            $book  = IOFactory::load($request->file('file')->getRealPath());
            $sheet = $book->getSheetByName('Data') ?? $book->getSheet(0);
            $rows  = $sheet->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'File tidak dapat dibaca. Gunakan template yang disediakan.');
        }

        $headers  = array_keys($cfg['headers']);
        $fileHead = array_map(fn ($h) => trim((string) $h), $rows[0] ?? []);
        if (array_slice($fileHead, 0, count($headers)) !== $headers) {
            return back()->with('error', 'Kolom file tidak sesuai template. Unduh ulang template terbaru.');
        }

        $labels   = array_map(fn ($f) => $f['label'], $m['fields']);
        $prepared = [];
        $errors   = [];
        $seen     = [];

        foreach (array_slice($rows, 1, null, true) as $i => $row) {
            $row = array_pad(array_slice($row, 0, count($headers)), count($headers), null);
            if (collect($row)->every(fn ($v) => blank($v))) {
                continue;
            }

            $line = $i + 1;
            if (count($prepared) >= $this->maxImportRows) {
                $errors[] = 'Maksimal ' . $this->maxImportRows . ' baris per file.';
                break;
            }

            $cells = array_combine($headers, array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row));
            [$data, $rowErrors] = ($cfg['map'])($cells);

            $validator = Validator::make(
                $data,
                array_intersect_key($this->rules(new Request($data), null), $this->formFields(null)),
                [],
                $labels
            );

            // $rowErrors dikunci per field; pesan validator untuk field yang sama dilewati.
            $messages = array_merge(
                array_values($rowErrors),
                collect($validator->errors()->messages())->except(array_keys($rowErrors))->flatten()->all()
            );
            foreach ($messages as $msg) {
                $errors[] = "Baris {$line}: {$msg}";
            }

            foreach ($cfg['unique'] ?? [] as $field) {
                $key = $field . '|' . mb_strtolower((string) ($data[$field] ?? ''));
                if (($data[$field] ?? '') !== '' && isset($seen[$key])) {
                    $errors[] = "Baris {$line}: {$labels[$field]} sama dengan baris {$seen[$key]}.";
                }
                $seen[$key] = $line;
            }

            $prepared[] = $validator->valid();
        }

        if (! $prepared && ! $errors) {
            return back()->with('error', 'Tidak ada baris data di file. Isi mulai baris ke-2.');
        }

        if ($errors) {
            return back()
                ->with('error', 'Import dibatalkan: ' . count($errors) . ' kesalahan ditemukan, tidak ada data yang disimpan.')
                ->with('import_errors', array_slice($errors, 0, 100));
        }

        DB::transaction(function () use ($m, $prepared) {
            foreach ($prepared as $data) {
                $record = new $m['model']($data);
                $this->beforeCreate($record, $data);
                $record->save();
            }
        });

        UserActivityLog::record('master_data', 'create', 'Import ' . count($prepared) . ' ' . $m['singular'] . ' dari Excel');

        return redirect($m['url'])->with('success', count($prepared) . ' ' . $m['singular'] . ' berhasil diimpor');
    }

    /** Cocokkan isian Excel ke kunci konstanta (boleh kunci atau label, tanpa beda besar-kecil). */
    protected static function matchOption(array $options, $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }

        foreach ($options as $key => $label) {
            if (mb_strtolower($key) === $value || mb_strtolower($label) === $value) {
                return (string) $key;
            }
        }

        return $value; // dibiarkan apa adanya supaya ditolak validasi Rule::in
    }

    private function styleHeader($sheet, int $cols): void
    {
        $range = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols) . '1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
    }
}

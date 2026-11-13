<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Site;
use App\Models\SiteSla;
use App\Models\SiteSlaPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Input SLA Site (menu 'site-sla'). Satu entri = satu customer + satu periode
 * (bulan); rinciannya nilai SLA per site milik customer itu. Rekap tahunan
 * (site × bulan) hanya ditampilkan di Summary Operations.
 */
class SiteSlaController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $customerId = $request->input('customer_id');

        $periods = SiteSlaPeriod::query()
            ->with(['customer:id,name,initial', 'updater:id,name'])
            ->withCount('details')
            ->withAvg('details', 'sla_percent')
            ->where('year', $year)
            ->when($customerId, fn ($q, $v) => $q->where('customer_id', $v))
            ->orderByDesc('month')->orderBy('customer_id')
            ->get();

        return view('site-sla.index', [
            'year' => $year,
            'customerId' => $customerId,
            'periods' => $periods,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Buat entri baru (customer + periode); bila sudah ada, buka entri itu. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], ['period.regex' => 'Periode harus berformat bulan (YYYY-MM).']);

        [$y, $m] = array_map('intval', explode('-', $data['period']));
        $period = SiteSlaPeriod::firstOrCreate(
            ['customer_id' => $data['customer_id'], 'year' => $y, 'month' => $m],
            ['created_by' => Auth::id(), 'updated_by' => Auth::id()],
        );

        return redirect()->route('site-sla.show', $period)
            ->with('success', $period->wasRecentlyCreated
                ? 'Periode SLA dibuat. Isi SLA per site lalu simpan.'
                : 'Periode SLA untuk customer ini sudah ada; dibuka untuk diubah.');
    }

    /** Rincian site milik customer pada periode ini, dengan isian SLA. */
    public function show(SiteSlaPeriod $period)
    {
        $period->load('customer:id,name,initial', 'updater:id,name');
        $values = $period->details()->pluck('sla_percent', 'site_id')->map(fn ($v) => (float) $v);

        $sites = $this->periodSites($period, $values->keys()->all());

        return view('site-sla.show', compact('period', 'values', 'sites'));
    }

    /**
     * Simpan rincian. Payload JSON {site_id: nilai|""} untuk seluruh baris
     * (JSON agar tidak terbentur max_input_vars PHP). Kosong = hapus nilai.
     */
    public function update(Request $request, SiteSlaPeriod $period)
    {
        $data = $request->validate(['payload' => 'required|json']);
        $payload = json_decode($data['payload'], true);
        if (! is_array($payload)) {
            return back()->with('error', 'Data SLA tidak valid.');
        }

        $allowed = Site::where('customer_id', $period->customer_id)->pluck('id')
            ->merge($period->details()->pluck('site_id'))->unique()->flip();

        $set = [];
        $clear = [];
        foreach ($payload as $siteId => $v) {
            if (! ctype_digit((string) $siteId) || ! $allowed->has((int) $siteId)) {
                return back()->with('error', 'Ada site yang bukan milik customer periode ini.');
            }
            if ($v === '' || $v === null) {
                $clear[] = (int) $siteId;
            } elseif (is_numeric($v) && $v >= 0 && $v <= 100) {
                $set[(int) $siteId] = round((float) $v, 2);
            } else {
                return back()->with('error', 'Nilai SLA harus angka 0–100.');
            }
        }

        DB::transaction(function () use ($period, $set, $clear) {
            if ($clear) {
                $period->details()->whereIn('site_id', $clear)->delete();
            }
            foreach ($set as $siteId => $val) {
                SiteSla::updateOrCreate(
                    ['site_sla_period_id' => $period->id, 'site_id' => $siteId],
                    ['sla_percent' => $val],
                );
            }
            $period->update(['updated_by' => Auth::id()]);
        });

        return back()->with('success', 'SLA ' . $period->periodLabel() . ' tersimpan (' . count($set) . ' site terisi).');
    }

    /**
     * Site yang tampil di rincian periode: site milik customer + site lain yang
     * sudah punya nilai di periode ini (mis. site yang kemudian pindah customer).
     */
    private function periodSites(SiteSlaPeriod $period, array $withValueIds)
    {
        return Site::query()
            ->with(['provinsi:id,name', 'kota:id,name'])
            ->where(fn ($q) => $q->where('customer_id', $period->customer_id)->orWhereIn('id', $withValueIds))
            ->orderBy('tid')
            ->get(['id', 'tid', 'sitename', 'status', 'provinsi_id', 'kota_id']);
    }

    /**
     * Template Excel rincian periode: satu baris per site (Site ID sebagai kunci),
     * kolom SLA terisi nilai yang sudah tersimpan.
     */
    public function template(SiteSlaPeriod $period)
    {
        $period->load('customer:id,name,initial');
        $values = $period->details()->pluck('sla_percent', 'site_id');
        $sites = $this->periodSites($period, $values->keys()->all());

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('SLA Site');
        foreach (['Site ID', 'Nama Lokasi', 'Provinsi', 'Kabupaten/Kota', 'SLA (%)'] as $i => $label) {
            $cell = chr(65 + $i) . '1';
            $sheet->setCellValue($cell, $label);
            $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
        }
        foreach ($sites->values() as $i => $s) {
            $r = $i + 2;
            $sheet->setCellValueExplicit('A' . $r, (string) $s->tid, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $r, $s->sitename);
            $sheet->setCellValue('C' . $r, $s->provinsi->name ?? '');
            $sheet->setCellValue('D' . $r, $s->kota->name ?? '');
            if (isset($values[$s->id])) {
                $sheet->setCellValue('E' . $r, (float) $values[$s->id]);
            }
        }
        foreach (['A' => 22, 'B' => 40, 'C' => 22, 'D' => 26, 'E' => 10] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        $sheet->freezePane('A2');

        $name = 'template-sla-' . str($period->customer->initial ?: $period->customer->name)->slug()
            . '-' . sprintf('%04d-%02d', $period->year, $period->month) . '.xlsx';
        $writer = new Xlsx($book);

        return response()->streamDownload(fn () => $writer->save('php://output'), $name,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Import Excel: TIDAK menyimpan apa pun — hanya membaca file dan
     * mengembalikan nilai per site untuk diisi JS ke grid (pola import rincian
     * Cost Request). Pengguna tetap menekan Simpan. Kolom SLA kosong dilewati.
     */
    public function importPreview(Request $request, SiteSlaPeriod $period)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120']);

        $sites = $this->periodSites($period, $period->details()->pluck('site_id')->all())
            ->keyBy(fn ($s) => strtoupper(trim((string) $s->tid)));

        try {
            $book = IOFactory::load($request->file('file')->getRealPath());
            $rows = ($book->getSheetByName('SLA Site') ?? $book->getActiveSheet())->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return response()->json(['rows' => [], 'errors' => ['File tidak dapat dibaca. Pastikan memakai template Excel SLA Site.']], 422);
        }

        $result = [];
        $errors = [];
        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue; // header
            }
            [$tid, , , , $sla] = array_pad($row, 5, null);
            if (blank($tid) && blank($sla)) {
                continue;
            }
            $n = $i + 1;
            $site = $sites->get(strtoupper(trim((string) $tid)));
            if (! $site) {
                $errors[] = "Baris {$n}: Site ID \"{$tid}\" bukan site customer periode ini.";
                continue;
            }
            if (blank($sla)) {
                continue;
            }
            $num = is_numeric($sla) ? (float) $sla : (is_numeric($c = str_replace(',', '.', trim((string) $sla))) ? (float) $c : null);
            if ($num === null || $num < 0 || $num > 100) {
                $errors[] = "Baris {$n}: SLA \"{$sla}\" untuk {$site->tid} harus angka 0–100.";
                continue;
            }
            $result[] = ['site_id' => $site->id, 'tid' => $site->tid, 'value' => round($num, 2)];
        }

        if (! $result && ! $errors) {
            $errors[] = 'Tidak ada nilai SLA yang terbaca. Isi kolom SLA (%) mulai baris ke-2.';
        }

        return response()->json(['rows' => $result, 'errors' => $errors]);
    }

    public function destroy(SiteSlaPeriod $period)
    {
        $label = $period->periodLabel();
        DB::transaction(fn () => $period->delete());

        return redirect()->route('site-sla.index', ['year' => $period->year])->with('success', "Periode SLA {$label} dihapus.");
    }
}

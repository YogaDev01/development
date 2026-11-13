<?php

namespace App\Http\Controllers;

use App\Models\SourceFund;
use App\Services\CashReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cash & Bank — Cash Reports (read-only). Seluruh angka dari CashReportService
 * (yang membaca sumber yang sama dengan Cash Overview/Cashflow).
 * Lihat = menu 'cash-reports'; ekspor CSV dan cetak = EXPORT_ROLES.
 */
class CashReportController extends Controller
{
    /** Cermin route role: pada ekspor/cetak dan kondisi Blade. */
    public const EXPORT_ROLES = ['superadmin', 'finance_manager', 'finance'];

    public function __construct(private CashReportService $reports)
    {
    }

    public function index()
    {
        return view('cash-reports.index', ['reports' => CashReportService::REPORTS]);
    }

    public function show(Request $request, string $report)
    {
        $f = $this->filters($request);
        $data = $this->reports->build($report, $f);
        abort_if($data === null, 404);

        if ($request->query('format') === 'csv') {
            abort_unless($this->canExport(), 403);

            return $this->csv($report, $data);
        }

        $print = $request->query('print') === '1';
        abort_if($print && ! $this->canExport(), 403);

        return view($print ? 'cash-reports.print' : 'cash-reports.show', [
            'key'       => $report,
            'data'      => $data,
            'filters'   => $f,
            'funds'     => SourceFund::orderBy('name')->get(['id', 'name']),
            'reports'   => CashReportService::REPORTS,
            'canExport' => $this->canExport(),
        ]);
    }

    /** @return array{from:string,to:string,fund:?int,days:int} */
    private function filters(Request $request): array
    {
        $valid = fn ($v) => $v && strtotime((string) $v) ? date('Y-m-d', strtotime((string) $v)) : null;

        $from = $valid($request->query('from')) ?? now()->startOfMonth()->format('Y-m-d');
        $to   = $valid($request->query('to')) ?? now()->format('Y-m-d');
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from,
            'to'   => $to,
            'fund' => $request->query('fund') ? (int) $request->query('fund') : null,
            'days' => min(365, max(1, (int) ($request->query('days') ?: 30))),
        ];
    }

    private function csv(string $key, array $data)
    {
        return response()->streamDownload(function () use ($data) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($h, array_column($data['columns'], 'label'));
            foreach ($data['rows'] as $r) {
                fputcsv($h, array_map(fn ($c) => $r[$c['key']] ?? '', $data['columns']));
            }
            if ($data['totals']) {
                fputcsv($h, array_map(fn ($c) => $data['totals'][$c['key']] ?? '', $data['columns']));
            }
            fclose($h);
        }, 'cash-report-' . $key . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function canExport(): bool
    {
        return (bool) Auth::user()?->hasRole(self::EXPORT_ROLES);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SourceFund;
use App\Services\CashFlowService;
use Illuminate\Http\Request;

/**
 * Cash & Bank — Transaction Viewer terpusat (read-only). Membaca
 * CashFlowService::accountLedger(): semua pergerakan kas per rekening,
 * termasuk dua sisi transfer internal (ditandai), dari semua modul sumber.
 */
class CashTransactionController extends Controller
{
    public function __construct(private CashFlowService $flow)
    {
    }

    public function index(Request $request)
    {
        $from = $request->query('from') ?: now()->startOfMonth()->format('Y-m-d');
        $to   = $request->query('to') ?: now()->endOfMonth()->format('Y-m-d');

        $filters = [
            'from'           => $from,
            'to'             => $to,
            'direction'      => $request->query('direction'),
            'source_fund_id' => $request->query('source_fund_id'),
            'module'         => $request->query('module'),
            'q'              => $request->query('q'),
        ];

        $rows = $this->flow->accountLedger($filters);

        $in  = (int) $rows->where('direction', 'IN')->sum('amount');
        $out = (int) $rows->where('direction', 'OUT')->sum('amount');
        // Sisi transfer internal saling meniadakan di tingkat konsolidasi.
        $tIn  = (int) $rows->where('is_transfer', true)->where('direction', 'IN')->sum('amount');
        $tOut = (int) $rows->where('is_transfer', true)->where('direction', 'OUT')->sum('amount');

        if ($request->query('export') === 'csv') {
            return $this->csv($rows);
        }

        return view('cash-transactions.index', [
            'rows'    => $rows->take(1000),
            'total'   => $rows->count(),
            'filters' => $filters,
            'funds'   => SourceFund::orderBy('name')->get(['id', 'name']),
            'modules' => [
                CashFlowService::MODULE_CASH_RECEIPT => 'Cash Receipt',
                CashFlowService::MODULE_TREASURY     => 'Treasury',
                CashFlowService::MODULE_MANUAL       => 'Entri Manual',
                CashFlowService::MODULE_TRANSFER     => 'Internal Transfer',
                CashFlowService::MODULE_ADJUSTMENT   => 'Cash Adjustment',
            ],
            'sum'     => [
                'in'  => $in - $tIn,
                'out' => $out - $tOut,
                'transfer' => $tIn,
            ],
        ]);
    }

    private function csv($rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['Tanggal', 'Referensi', 'Deskripsi', 'Sumber', 'Rekening', 'Arah', 'Jumlah', 'Transfer Internal']);
            foreach ($rows as $r) {
                fputcsv($h, [
                    $r['date']->format('Y-m-d'), $r['reference_number'], $r['description'], $r['category'],
                    $r['source_fund'], $r['direction'], $r['amount'], ! empty($r['is_transfer']) ? 'Ya' : 'Tidak',
                ]);
            }
            fclose($h);
        }, 'cash-transactions-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }
}

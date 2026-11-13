<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Services\FinancialStatementService;
use App\Support\AccountingMoney;
use App\Support\CsvSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Accounting — laporan keuangan (Laba Rugi, Neraca, Arus Kas, Perubahan Ekuitas).
 * Semua dari buku besar lewat FinancialStatementService. Lihat = menu masing-masing
 * (accounting-income-statement, -balance-sheet, -cash-flow, -equity); CSV = EXPORT_ROLES.
 */
class FinancialStatementController extends Controller
{
    public const EXPORT_ROLES = \App\Support\AccessRoles::ACCOUNTING_EXPORT;

    private const KINDS = [
        'income'  => 'Laba Rugi (Income Statement)',
        'balance' => 'Neraca (Balance Sheet)',
        'cash'    => 'Arus Kas (Cash Flow Statement)',
        'equity'  => 'Perubahan Ekuitas (Changes in Equity)',
    ];

    public function __construct(private FinancialStatementService $statements)
    {
    }

    public function income(Request $r)   { return $this->render($r, 'income'); }
    public function balance(Request $r)  { return $this->render($r, 'balance'); }
    public function cash(Request $r)     { return $this->render($r, 'cash'); }
    public function equity(Request $r)   { return $this->render($r, 'equity'); }

    private function render(Request $request, string $kind)
    {
        [$from, $to] = $this->range($request);

        $data = match ($kind) {
            'income'  => $this->statements->incomeStatement($from, $to),
            'balance' => $this->statements->balanceSheet($to),
            'cash'    => $this->statements->cashFlow($from, $to),
            'equity'  => $this->statements->equityChanges($from, $to),
        };

        if ($request->boolean('csv')) {
            abort_unless(Auth::user()?->hasRole(self::EXPORT_ROLES), 403);

            return $this->csv($kind, $data, $from, $to);
        }

        return view('accounting.statements.show', [
            'kind' => $kind, 'title' => self::KINDS[$kind], 'data' => $data, 'from' => $from, 'to' => $to,
            'asOf'      => $kind === 'balance',
            'periods'   => AccountingPeriod::orderByDesc('start_date')->get(['id', 'name']),
            'routeName' => 'accounting.statement.' . $kind,
            'canExport' => (bool) Auth::user()?->hasRole(self::EXPORT_ROLES),
        ]);
    }

    private function range(Request $request): array
    {
        if ($request->period_id && ($p = AccountingPeriod::find($request->period_id))) {
            return [$p->start_date->toDateString(), $p->end_date->toDateString()];
        }
        $from = $request->input('from') ?: now()->startOfYear()->toDateString();
        $to = $request->input('to') ?: now()->toDateString();

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private function csv(string $kind, array $data, string $from, string $to)
    {
        return response()->streamDownload(function () use ($kind, $data, $from, $to) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, [self::KINDS[$kind], $kind === 'balance' ? 'per ' . $to : $from . ' s/d ' . $to]);
            foreach ($data['lines'] as $l) {
                fputcsv($o, [CsvSafe::cell($l['label']), $l['amount'] === null ? '' : AccountingMoney::plain($l['amount'])]);
            }
            fclose($o);
        }, 'laporan-' . $kind . '-' . $to . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

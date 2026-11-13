<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\CoaAccount;
use App\Services\LedgerService;
use App\Support\AccountingCatalog;
use App\Support\AccountingMoney;
use App\Support\CsvSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Accounting — Trial Balance dari jurnal POSTED/REVERSED. Lihat = menu
 * accounting-trial-balance; CSV = EXPORT_ROLES.
 */
class TrialBalanceController extends Controller
{
    public const EXPORT_ROLES = \App\Support\AccessRoles::ACCOUNTING_EXPORT;

    public function __construct(private LedgerService $ledger)
    {
    }

    public function index(Request $request)
    {
        if ($request->period_id && ($p = AccountingPeriod::find($request->period_id))) {
            [$from, $to] = [$p->start_date->toDateString(), $p->end_date->toDateString()];
        } else {
            $from = $request->input('from') ?: now()->startOfMonth()->toDateString();
            $to = $request->input('to') ?: now()->toDateString();
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
        }

        $type = array_key_exists((string) $request->type, AccountingCatalog::ACCOUNT_TYPES) ? $request->type : null;
        $category = $request->filled('category') ? (string) $request->category : null;
        $tb = $this->ledger->trialBalance($from, $to, $type, $category, $request->boolean('zero'));

        if ($request->boolean('csv')) {
            abort_unless(Auth::user()?->hasRole(self::EXPORT_ROLES), 403);

            return $this->csv($tb, $from, $to);
        }

        return view('accounting.trial-balance.index', [
            'tb' => $tb, 'from' => $from, 'to' => $to,
            'periods'    => AccountingPeriod::orderByDesc('start_date')->get(['id', 'name']),
            'types'      => AccountingCatalog::ACCOUNT_TYPES,
            'categories' => CoaAccount::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'canExport'  => (bool) Auth::user()?->hasRole(self::EXPORT_ROLES),
        ]);
    }

    private function csv(array $tb, string $from, string $to)
    {
        return response()->streamDownload(function () use ($tb, $from, $to) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['Trial Balance', $from . ' s/d ' . $to]);
            fputcsv($o, ['Kode', 'Nama', 'Saldo Awal Debit', 'Saldo Awal Kredit', 'Mutasi Debit', 'Mutasi Kredit', 'Saldo Akhir Debit', 'Saldo Akhir Kredit']);
            foreach ($tb['rows'] as $r) {
                fputcsv($o, [CsvSafe::cell($r['account']->code), CsvSafe::cell($r['account']->name), ...array_map(fn ($k) => AccountingMoney::plain($r[$k]), ['od', 'oc', 'pd', 'pc', 'ed', 'ec'])]);
            }
            $s = $tb['sum'];
            fputcsv($o, ['', 'TOTAL', ...array_map(fn ($k) => AccountingMoney::plain($s[$k]), ['od', 'oc', 'pd', 'pc', 'ed', 'ec'])]);
            fclose($o);
        }, 'trial-balance-' . $from . '-' . $to . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

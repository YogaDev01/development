<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\CoaAccount;
use App\Models\CostCenter;
use App\Models\Project;
use App\Services\LedgerService;
use App\Support\AccountingMoney;
use App\Support\CsvSafe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Accounting — General Ledger. Hanya jurnal POSTED/REVERSED (lihat LedgerService).
 * Lihat = menu accounting-ledger; CSV dan cetak = EXPORT_ROLES.
 */
class GeneralLedgerController extends Controller
{
    public const EXPORT_ROLES = \App\Support\AccessRoles::ACCOUNTING_EXPORT;

    public function __construct(private LedgerService $ledger)
    {
    }

    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $request->validate(['account_id' => 'nullable|exists:coa_accounts,id']);

        $acc = $request->account_id ? CoaAccount::find($request->account_id) : null;
        $data = $acc ? $this->ledger->ledger($acc, $from, $to, $this->dim($request)) : null;

        if ($acc && $data && $request->boolean('csv')) {
            return $this->csv($acc, $data, $from, $to);
        }

        // Tampilan dibatasi 1.000 baris (saldo berjalan tetap dihitung dari seluruh mutasi); CSV memuat semuanya.
        $rowTotal = $data ? $data['rows']->count() : 0;
        if ($data && $rowTotal > 1000) {
            $data['rows'] = $data['rows']->take(1000)->values();
        }

        return view('accounting.ledger.index', [
            'rowTotal'  => $rowTotal,
            'accounts'  => CoaAccount::where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name']),
            'periods'   => AccountingPeriod::orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date']),
            'projects'  => Project::orderByDesc('id')->limit(500)->get(['id', 'name']),
            'centers'   => CostCenter::orderBy('code')->get(['id', 'code', 'name']),
            'acc'       => $acc, 'data' => $data, 'from' => $from, 'to' => $to,
            'canExport' => (bool) Auth::user()?->hasRole(self::EXPORT_ROLES),
        ]);
    }

    private function csv(CoaAccount $acc, array $d, string $from, string $to)
    {
        abort_unless(Auth::user()?->hasRole(self::EXPORT_ROLES), 403);

        return response()->streamDownload(function () use ($acc, $d, $from, $to) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['Buku Besar', CsvSafe::cell($acc->code . ' ' . $acc->name), $from . ' s/d ' . $to]);
            fputcsv($o, ['Tanggal', 'No. Jurnal', 'Referensi', 'Deskripsi', 'Debit', 'Kredit', 'Saldo']);
            fputcsv($o, ['', '', '', 'Saldo awal', '', '', AccountingMoney::plain($d['opening'])]);
            foreach ($d['rows'] as $r) {
                fputcsv($o, [substr((string) $r->journal_date, 0, 10), CsvSafe::cell($r->journal_number), CsvSafe::cell($r->reference_number),
                    CsvSafe::cell($r->line_description ?: $r->journal_description), AccountingMoney::plain($r->debit),
                    AccountingMoney::plain($r->credit), AccountingMoney::plain($r->balance)]);
            }
            fputcsv($o, ['', '', '', 'Total / Saldo akhir', AccountingMoney::plain($d['debit']), AccountingMoney::plain($d['credit']), AccountingMoney::plain($d['closing'])]);
            fclose($o);
        }, 'buku-besar-' . $acc->code . '-' . $from . '-' . $to . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function range(Request $request): array
    {
        if ($request->period_id && ($p = AccountingPeriod::find($request->period_id))) {
            return [$p->start_date->toDateString(), $p->end_date->toDateString()];
        }
        $from = $request->input('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->input('to') ?: now()->toDateString();

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private function dim(Request $request): array
    {
        return ['project_id' => $request->project_id, 'cost_center_id' => $request->cost_center_id];
    }
}

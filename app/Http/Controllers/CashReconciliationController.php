<?php

namespace App\Http\Controllers;

use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\CashReconciliation;
use App\Models\CashReconciliationLog;
use App\Models\CashReconciliationMatch;
use App\Models\SourceFund;
use App\Models\UserActivityLog;
use App\Services\ReconciliationService;
use App\Support\DocumentSequence;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cash & Bank — Bank Reconciliation.
 *
 * Tidak mengubah ledger. Alur: buat (DRAFT) → impor CSV → pencocokan (otomatis /
 * manual) → submit → approve (APPROVED = periode terkunci). Semua perubahan
 * data (impor, pencocokan) hanya pada status DRAFT dan berjalan dalam transaksi
 * DB dengan kunci baris rekonsiliasi, sehingga tidak bisa berubah diam-diam
 * setelah disubmit/disetujui. Pembukaan ulang hanya superadmin + alasan + log.
 */
class CashReconciliationController extends Controller
{
    /** Cermin route role: dan Blade. */
    public const WORK_ROLES    = ['superadmin', 'finance_manager', 'finance'];
    public const APPROVE_ROLES = ['superadmin', 'finance_manager'];
    public const REOPEN_ROLES  = ['superadmin'];

    public function __construct(private ReconciliationService $svc)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR & BUAT
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('cash-reconciliation.index', [
            'recs'     => CashReconciliation::with(['sourceFund:id,name', 'creator:id,username'])
                ->orderByDesc('period_end')->orderByDesc('id')->limit(300)->get(),
            'canWork'  => $this->can(self::WORK_ROLES),
        ]);
    }

    public function create()
    {
        return view('cash-reconciliation.create', [
            'funds' => SourceFund::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source_fund_id'           => 'required|exists:source_funds,id',
            'period_start'             => 'required|date',
            'period_end'               => 'required|date|after_or_equal:period_start|before_or_equal:today',
            'statement_ending_balance' => 'required|string|max:25',
        ]);

        $balance = $this->svc->parseAmount($data['statement_ending_balance']);
        if ($balance === null) {
            return back()->withInput()->with('error', 'Saldo akhir rekening koran tidak valid.');
        }
        if (! SourceFund::whereKey($data['source_fund_id'])->where('status', 'ACTIVE')->exists()) {
            return back()->withInput()->with('error', 'Rekening harus aktif.');
        }

        $overlap = CashReconciliation::where('source_fund_id', $data['source_fund_id'])
            ->whereDate('period_start', '<=', $data['period_end'])
            ->whereDate('period_end', '>=', $data['period_start'])->first();
        if ($overlap) {
            return back()->withInput()->with('error', "Periode bertumpuk dengan {$overlap->number} ({$overlap->status}).");
        }

        $rec = DB::transaction(function () use ($data, $balance) {
            $n = DocumentSequence::next('cash-reconciliation:' . now()->year, fn () => 0);

            $rec = CashReconciliation::create([
                'number'                   => sprintf('REC/%d/%04d', now()->year, $n),
                'source_fund_id'           => $data['source_fund_id'],
                'period_start'             => $data['period_start'],
                'period_end'               => $data['period_end'],
                'statement_ending_balance' => $balance,
                'status'                   => CashReconciliation::DRAFT,
                'created_by'               => Auth::id(),
            ]);
            $this->log($rec, 'CREATED');

            return $rec;
        });

        UserActivityLog::record('cash_bank', 'create', 'Membuat rekonsiliasi ' . $rec->number, $rec);

        return redirect()->route('cash-reconciliation.show', $rec->id)->with('success', 'Rekonsiliasi dibuat. Impor rekening koran untuk mulai mencocokkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | WORKSPACE
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, $id)
    {
        $rec = CashReconciliation::with(['sourceFund.bank', 'creator:id,username', 'submitter:id,username', 'approver:id,username', 'imports.importer:id,username', 'logs.user:id,username'])
            ->findOrFail($id);
        $rec->load(['lines.matches']);

        $summary = $this->svc->summary($rec);
        $pool    = $summary['unmatched_ledger'];

        $lines = $rec->lines->sortBy([['txn_date', 'asc'], ['id', 'asc']])->map(function ($l) use ($pool, $rec) {
            $l->status_label = $this->svc->lineStatus($l);
            $l->suggestions  = $l->status_label === 'MATCHED' || ! $rec->isEditable()
                ? collect()
                : $pool->where('direction', $l->direction)
                    ->sortByDesc(fn ($r) => (((int) $r['amount'] === $l->amount) ? 1000 : 0) + $this->svc->score($l, $r))
                    ->take(8)->values();

            return $l;
        })->values();

        return view('cash-reconciliation.show', [
            'rec'        => $rec,
            'summary'    => $summary,
            'lines'      => $lines,
            'preview'    => session('recon_preview_' . $rec->id),
            'tab'        => $request->query('tab', 'summary'),
            'canWork'    => $this->can(self::WORK_ROLES),
            'canApprove' => $this->can(self::APPROVE_ROLES),
            'canReopen'  => $this->can(self::REOPEN_ROLES),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | IMPOR CSV
    |--------------------------------------------------------------------------
    */

    public function template()
    {
        return response()->streamDownload(function () {
            $h = fopen('php://output', 'w');
            fputcsv($h, ReconciliationService::TEMPLATE_HEADER);
            fputcsv($h, ['2026-11-05', 'TRANSFER MASUK PT ABC', 'INV-001', '0', '10000000', '25000000']);
            fputcsv($h, ['2026-11-06', 'BIAYA ADMIN', '', '6500', '0', '24993500']);
            fclose($h);
        }, 'template-rekening-koran.csv', ['Content-Type' => 'text/csv']);
    }

    /** Langkah 1: parse + validasi + deteksi duplikat. Belum menulis apa pun ke DB. */
    public function importPreview(Request $request, $id)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $rec = CashReconciliation::findOrFail($id);
        if (! $rec->isEditable()) {
            return back()->with('error', 'Rekonsiliasi tidak berstatus DRAFT; impor ditolak.');
        }

        $result = $this->svc->parseCsv($request->file('file')->getRealPath(), $rec);
        if ($result['error']) {
            return redirect()->route('cash-reconciliation.show', [$rec->id, 'tab' => 'import'])->with('error', $result['error']);
        }

        session(['recon_preview_' . $rec->id => [
            'filename' => $request->file('file')->getClientOriginalName(),
            'rows'     => $result['rows'],
        ]]);

        return redirect()->route('cash-reconciliation.show', [$rec->id, 'tab' => 'import']);
    }

    /** Langkah 2: simpan hanya baris OK; duplikat/galat dilewati. */
    public function importConfirm($id)
    {
        $preview = session('recon_preview_' . $id);
        if (! $preview) {
            return back()->with('error', 'Pratinjau tidak ditemukan; unggah ulang file.');
        }

        $result = $this->withEditable($id, function (CashReconciliation $rec) use ($preview) {
            $ok      = collect($preview['rows'])->where('state', 'OK');
            $skipped = count($preview['rows']) - $ok->count();

            $import = BankStatementImport::create([
                'reconciliation_id' => $rec->id, 'filename' => $preview['filename'],
                'rows_imported' => 0, 'rows_skipped' => $skipped, 'imported_by' => Auth::id(),
            ]);

            $inserted = 0;
            foreach ($ok as $row) {
                $d = $row['data'];
                // Kunci unik (rekening, hash) menolak duplikat yang lolos dari pratinjau (impor bersamaan).
                $new = BankStatementLine::firstOrCreate(
                    ['source_fund_id' => $rec->source_fund_id, 'dedupe_hash' => $d['hash']],
                    [
                        'reconciliation_id' => $rec->id, 'import_id' => $import->id, 'txn_date' => $d['date'],
                        'description' => $d['description'], 'reference' => $d['reference'],
                        'debit' => $d['debit'], 'credit' => $d['credit'], 'balance' => $d['balance'],
                    ]
                );
                $new->wasRecentlyCreated ? $inserted++ : $skipped++;
            }
            $import->update(['rows_imported' => $inserted, 'rows_skipped' => $skipped]);
            $this->log($rec, 'IMPORTED', "{$preview['filename']}: {$inserted} baris, {$skipped} dilewati");

            return "{$inserted} baris diimpor, {$skipped} dilewati.";
        });

        session()->forget('recon_preview_' . $id);

        return $this->respond($result, 'cash-reconciliation.show', [$id, 'tab' => 'lines']);
    }

    public function importCancel($id)
    {
        session()->forget('recon_preview_' . $id);

        return redirect()->route('cash-reconciliation.show', [$id, 'tab' => 'import']);
    }

    /** Batalkan satu impor (DRAFT): hanya bila tidak ada barisnya yang sudah dicocokkan. */
    public function importDestroy($id, $importId)
    {
        $result = $this->withEditable($id, function (CashReconciliation $rec) use ($importId) {
            $import = BankStatementImport::where('reconciliation_id', $rec->id)->findOrFail($importId);
            $lineIds = BankStatementLine::where('import_id', $import->id)->pluck('id');

            if (CashReconciliationMatch::whereIn('bank_line_id', $lineIds)->exists()) {
                return ['error' => 'Sebagian baris impor sudah dicocokkan; lepas pencocokannya dulu.'];
            }

            BankStatementLine::whereIn('id', $lineIds)->delete();
            $this->log($rec, 'IMPORT_REMOVED', "{$import->filename} ({$lineIds->count()} baris)");
            $import->delete();

            return 'Impor dibatalkan.';
        });

        return $this->respond($result, 'cash-reconciliation.show', [$id, 'tab' => 'import']);
    }

    /*
    |--------------------------------------------------------------------------
    | PENCOCOKAN
    |--------------------------------------------------------------------------
    */

    public function autoMatch($id)
    {
        $result = $this->withEditable($id, function (CashReconciliation $rec) {
            $n = $this->svc->autoMatch($rec, Auth::id());
            $this->log($rec, 'AUTO_MATCH', "{$n} pasangan");

            return "{$n} transaksi dicocokkan otomatis (nominal sama persis, selisih maks. " . ReconciliationService::DATE_TOLERANCE_DAYS . ' hari).';
        });

        return $this->respond($result, 'cash-reconciliation.show', [$id, 'tab' => 'lines']);
    }

    public function matchLine(Request $request, $id)
    {
        $request->validate(['bank_line_id' => 'required|integer', 'ledger_keys' => 'required|array|min:1', 'ledger_keys.*' => 'string|max:191']);

        $result = $this->withEditable($id, function (CashReconciliation $rec) use ($request) {
            $line = BankStatementLine::where('reconciliation_id', $rec->id)->findOrFail($request->bank_line_id);

            return match ($this->svc->manualMatch($rec, $line, $request->ledger_keys, Auth::id())) {
                'ok'       => tap('Transaksi dicocokkan.', fn () => $this->log($rec, 'MATCH', 'baris bank #' . $line->id)),
                'conflict' => ['error' => 'Salah satu transaksi TESYS sudah dicocokkan di tempat lain.'],
                default    => ['error' => 'Pilihan tidak valid (arah berbeda atau transaksi tidak tersedia).'],
            };
        });

        return $this->respond($result, 'cash-reconciliation.show', [$id, 'tab' => 'lines']);
    }

    /** Lepas semua pencocokan satu baris bank. */
    public function unmatch($id, $lineId)
    {
        $result = $this->withEditable($id, function (CashReconciliation $rec) use ($lineId) {
            $line = BankStatementLine::where('reconciliation_id', $rec->id)->findOrFail($lineId);
            CashReconciliationMatch::where('bank_line_id', $line->id)->delete();
            $this->log($rec, 'UNMATCH', 'baris bank #' . $line->id);

            return 'Pencocokan dilepas.';
        });

        return $this->respond($result, 'cash-reconciliation.show', [$id, 'tab' => 'lines']);
    }

    /** Tandai / lepas tanda "Under Investigation" (dengan catatan). */
    public function investigate(Request $request, $id, $lineId)
    {
        $request->validate(['note' => 'nullable|string|max:255']);

        $result = $this->withEditable($id, function (CashReconciliation $rec) use ($request, $lineId) {
            $line = BankStatementLine::where('reconciliation_id', $rec->id)->findOrFail($lineId);
            $line->update(['investigating' => ! $line->investigating, 'note' => $request->note ?: $line->note]);
            $this->log($rec, $line->investigating ? 'INVESTIGATE' : 'INVESTIGATE_CLEARED', 'baris bank #' . $line->id);

            return $line->investigating ? 'Ditandai Under Investigation.' : 'Tanda investigasi dilepas.';
        });

        return $this->respond($result, 'cash-reconciliation.show', [$id, 'tab' => 'lines']);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVAL & KUNCI PERIODE
    |--------------------------------------------------------------------------
    */

    public function submit(Request $request, $id)
    {
        $request->validate(['exception_reason' => 'nullable|string|max:500']);

        $result = $this->withEditable($id, function (CashReconciliation $rec) use ($request) {
            $s = $this->svc->summary($rec);

            if ($s['needs_exception'] && blank($request->exception_reason)) {
                return ['error' => 'Masih ada selisih (' . number_format($s['difference'], 0, ',', '.') . ') atau baris bank belum cocok ('
                    . $s['unresolved_lines'] . '). Isi alasan pengecualian untuk submit, atau selesaikan dulu.'];
            }

            $rec->update([
                'status' => CashReconciliation::SUBMITTED, 'submitted_by' => Auth::id(), 'submitted_at' => now(),
                'exception_reason' => $s['needs_exception'] ? $request->exception_reason : null,
            ]);
            $this->log($rec, 'SUBMITTED', $s['needs_exception'] ? 'Dengan pengecualian: ' . $request->exception_reason : 'Selisih nol');

            return 'Rekonsiliasi disubmit untuk persetujuan.';
        });

        return $this->respond($result, 'cash-reconciliation.show', [$id]);
    }

    /** Setujui & kunci periode. Penyetuju ≠ pembuat dan ≠ pensubmit. */
    public function approve($id)
    {
        $result = $this->transition($id, CashReconciliation::SUBMITTED, function (CashReconciliation $rec) {
            if (in_array((int) Auth::id(), [(int) $rec->created_by, (int) $rec->submitted_by], true)) {
                return ['error' => 'Pembuat atau pengirim rekonsiliasi tidak boleh menyetujuinya sendiri.'];
            }

            // Hitung ulang saat approve: data tidak boleh berubah sejak submit.
            $s = $this->svc->summary($rec);
            if ($s['needs_exception'] && blank($rec->exception_reason)) {
                return ['error' => 'Selisih/baris belum cocok tanpa alasan pengecualian; kembalikan ke DRAFT.'];
            }

            $rec->update(['status' => CashReconciliation::APPROVED, 'approved_by' => Auth::id(), 'approved_at' => now()]);
            $this->log($rec, 'APPROVED', $s['needs_exception'] ? 'Pengecualian disetujui: ' . $rec->exception_reason : 'Selisih nol');
            UserActivityLog::record('cash_bank', 'approve', 'Menyetujui & mengunci rekonsiliasi ' . $rec->number, $rec);

            return 'Rekonsiliasi disetujui; periode terkunci.';
        });

        return $this->respond($result, 'cash-reconciliation.show', [$id]);
    }

    /** Kembalikan SUBMITTED → DRAFT (penyetuju menolak). */
    public function returnToDraft(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:255']);

        $result = $this->transition($id, CashReconciliation::SUBMITTED, function (CashReconciliation $rec) use ($request) {
            $rec->update(['status' => CashReconciliation::DRAFT, 'submitted_by' => null, 'submitted_at' => null]);
            $this->log($rec, 'RETURNED', $request->reason);

            return 'Dikembalikan ke DRAFT.';
        });

        return $this->respond($result, 'cash-reconciliation.show', [$id]);
    }

    /** Buka ulang periode terkunci — hanya REOPEN_ROLES, wajib alasan, tercatat di log & activity log. */
    public function reopen(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|min:5|max:255']);

        $result = $this->transition($id, CashReconciliation::APPROVED, function (CashReconciliation $rec) use ($request) {
            $rec->update(['status' => CashReconciliation::DRAFT, 'submitted_by' => null, 'submitted_at' => null, 'approved_by' => null, 'approved_at' => null]);
            $this->log($rec, 'REOPENED', $request->reason);
            UserActivityLog::record('cash_bank', 'reopen', 'Membuka ulang rekonsiliasi ' . $rec->number, $rec, $request->reason);

            return 'Rekonsiliasi dibuka ulang (DRAFT).';
        });

        return $this->respond($result, 'cash-reconciliation.show', [$id]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER
    |--------------------------------------------------------------------------
    */

    /** Jalankan $fn pada rekonsiliasi DRAFT yang dikunci barisnya. */
    private function withEditable($id, Closure $fn)
    {
        return $this->transition($id, CashReconciliation::DRAFT, $fn);
    }

    /** Transaksi DB + kunci baris + cek status yang diharapkan. */
    private function transition($id, string $expected, Closure $fn)
    {
        return DB::transaction(function () use ($id, $expected, $fn) {
            $rec = CashReconciliation::lockForUpdate()->findOrFail($id);
            if ($rec->status !== $expected) {
                return ['error' => "Aksi ditolak: status rekonsiliasi {$rec->status} (diperlukan {$expected})."];
            }

            return $fn($rec);
        });
    }

    private function respond($result, string $route, array $params)
    {
        $url = route($route, $params);

        return is_array($result) && isset($result['error'])
            ? redirect($url)->with('error', $result['error'])
            : redirect($url)->with('success', is_string($result) ? $result : 'Berhasil.');
    }

    private function log(CashReconciliation $rec, string $action, ?string $notes = null): void
    {
        CashReconciliationLog::create([
            'reconciliation_id' => $rec->id, 'action' => $action, 'notes' => $notes, 'user_id' => Auth::id(),
        ]);
    }

    private function can(array $roles): bool
    {
        return (bool) Auth::user()?->hasRole($roles);
    }
}

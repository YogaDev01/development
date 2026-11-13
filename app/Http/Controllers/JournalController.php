<?php

namespace App\Http\Controllers;

use App\Models\AccountingAuditLog;
use App\Models\AccountingPeriod;
use App\Models\CoaAccount;
use App\Models\CostCenter;
use App\Models\Journal;
use App\Models\Project;
use App\Services\JournalGenerator;
use App\Support\AccountingMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Accounting — Jurnal. Alur: DRAFT -> SUBMITTED -> POSTED.
 *   - Draft boleh diedit/dihapus (soft delete); Posted tidak pernah diubah.
 *   - Finance manager (atau superadmin) me-review dan memposting; revisi = kembali ke Draft.
 *   - Pembuat tidak boleh memposting jurnalnya sendiri (kecuali superadmin).
 *   - Jurnal manual (General/Accrual/Adjustment/Reclassification/Depreciation/Saldo Awal)
 *     mengikuti validasi, approval, posting, dan audit yang SAMA dengan jurnal dari sumber.
 *   - Koreksi jurnal Posted = jurnal pembalik (REVERSAL) lalu jurnal pengganti; tidak ada edit/hapus.
 */
class JournalController extends Controller
{
    public const CREATE_ROLES = \App\Support\AccessRoles::ACCOUNTING_MANAGE;
    public const POST_ROLES   = \App\Support\AccessRoles::ACCOUNTING_POST;

    public function __construct(private JournalGenerator $generator)
    {
    }

    public function index(Request $request)
    {
        $journals = Journal::with(['creator:id,username', 'sources'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->number, fn ($q, $n) => $q->where('journal_number', 'like', '%' . $n . '%'))
            ->when($request->from, fn ($q, $d) => $q->whereDate('journal_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('journal_date', '<=', $d))
            ->orderByDesc('journal_date')->orderByDesc('id')->limit(500)->get();

        $kpi = Journal::select('status', DB::raw('COUNT(*) c'), DB::raw('SUM(total_debit) v'))->groupBy('status')->get()->keyBy('status');
        $truncated = false;
        $request->validate(['src_from' => 'nullable|date', 'src_to' => 'nullable|date']);
        $sources = $request->tab === 'sources'
            ? $this->generator->candidates($request->src_from, $request->src_to, 400, $truncated)
            : collect();

        return view('accounting.journal.index', [
            'journals'  => $journals,
            'sources'   => $sources,
            'tab'       => $request->tab === 'sources' ? 'sources' : 'journals',
            'kpi'       => $kpi,
            'cutover'   => JournalGenerator::cutoverDate(),
            'awaiting'  => $sources->where('eligible', true)->count(),
            'truncated' => $truncated,
            'canCreate' => $this->can(self::CREATE_ROLES),
        ]);
    }

    public function generate(Request $request)
    {
        $data = $request->validate(['source_type' => 'required|string|max:40', 'source_id' => 'required|integer']);

        try {
            $journal = $this->generator->generate($data['source_type'], (int) $data['source_id']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.journal.show', $journal->id)->with('success', 'Jurnal Draft dibuat. Periksa akun dan nominal sebelum Submit.');
    }

    /** Form jurnal manual. */
    public function create()
    {
        return view('accounting.journal.create', $this->editorData() + ['types' => Journal::MANUAL_TYPES]);
    }

    public function storeManual(Request $request)
    {
        $request->validate([
            'journal_type'      => 'required|in:' . implode(',', array_keys(Journal::MANUAL_TYPES)),
            'journal_date'      => 'required|date',
            'auto_reverse_date' => 'nullable|date|after:journal_date',
            'reference_number'  => 'nullable|string|max:60',
            'description'       => 'required|string|max:500',
        ] + $this->lineRules());

        [$lines, $err] = $this->parseLines($request->lines);
        if ($err) {
            return back()->withInput()->with('error', $err);
        }

        $journal = DB::transaction(function () use ($request, $lines) {
            $dr = array_sum(array_column($lines, 'debit'));
            $cr = array_sum(array_column($lines, 'credit'));
            $j = Journal::create([
                'journal_number' => Journal::nextNumber(), 'journal_date' => $request->journal_date,
                // Pembalik otomatis hanya berarti untuk akrual.
                'auto_reverse_date' => $request->journal_type === 'ACCRUAL' ? $request->auto_reverse_date : null,
                'accounting_period_id' => AccountingPeriod::forDate($request->journal_date)?->id,
                'journal_type' => $request->journal_type, 'status' => Journal::DRAFT,
                'reference_number' => $request->reference_number, 'description' => $request->description,
                'total_debit' => $dr, 'total_credit' => $cr, 'created_by' => Auth::id(),
            ]);
            foreach ($lines as $l) {
                $j->lines()->create($l);
            }
            AccountingAuditLog::record('journal', $j->id, 'create_manual', null,
                ['type' => $j->journal_type, 'total_debit' => $dr], null, null, Journal::DRAFT);

            return $j;
        });

        return redirect()->route('accounting.journal.show', $journal->id)->with('success', 'Jurnal manual Draft dibuat.');
    }

    public function show($id)
    {
        $journal = Journal::withTrashed()->with(['lines.account:id,code,name', 'sources', 'creator:id,username', 'period:id,name', 'reversesJournal:id,journal_number', 'reversals:id,journal_number,reverses_journal_id,status'])->findOrFail($id);
        $logs = AccountingAuditLog::with('user:id,username')->where('entity_type', 'journal')->where('entity_id', $id)->orderBy('id')->get();
        $activeReversal = $journal->reversals->first(fn ($r) => true);

        $lines = $journal->lines;

        return view('accounting.journal.show', $this->editorData(
            $lines->pluck('coa_account_id')->all(), $lines->pluck('cost_center_id')->filter()->all(), $lines->pluck('project_id')->filter()->all()
        ) + [
            'j'        => $journal,
            'logs'     => $logs,
            'drift'    => $journal->status === Journal::POSTED || $journal->trashed() ? null : $this->generator->sourceDrift($journal),
            'blocker'  => $journal->status === Journal::POSTED ? null : AccountingPeriod::postingBlocker($journal->journal_date),
            'canEdit'  => $this->can(self::CREATE_ROLES) && $journal->isEditable() && ! $journal->trashed() && $journal->journal_type !== 'REVERSAL',
            'canPost'  => $this->can(self::POST_ROLES),
            'sodBlocked' => $this->sodBlocked($journal),
            'canSubmit' => $this->can(self::CREATE_ROLES),
            'canReverse' => $this->can(self::CREATE_ROLES) && $journal->status === Journal::POSTED
                && $journal->journal_type !== 'REVERSAL' && ! $activeReversal,
            'activeReversal' => $activeReversal,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'journal_date'     => 'required|date',
            'reference_number' => 'nullable|string|max:60',
            'description'      => 'nullable|string|max:500',
        ] + $this->lineRules());

        [$lines, $err] = $this->parseLines($request->lines);
        if ($err) {
            return back()->withInput()->with('error', $err);
        }

        $err = DB::transaction(function () use ($request, $id, $lines) {
            $j = Journal::lockForUpdate()->findOrFail($id);
            if (! $j->isEditable()) {
                return 'Hanya jurnal Draft yang bisa diedit.';
            }
            if ($j->journal_type === 'REVERSAL') {
                return 'Jurnal pembalik harus mencerminkan jurnal asal dan tidak bisa diedit; hapus Draft lalu buat ulang.';
            }

            $old = $j->lines()->get()->map(fn ($l) => $l->only(['coa_account_id', 'suggested_account_id', 'description', 'debit', 'credit']))->all();
            $suggested = $j->lines()->get()->pluck('suggested_account_id', 'line_no');

            $j->lines()->delete();
            foreach ($lines as $l) {
                $j->lines()->create($l + ['suggested_account_id' => $suggested[$l['line_no']] ?? null]);
            }
            $dr = array_sum(array_column($lines, 'debit'));
            $cr = array_sum(array_column($lines, 'credit'));
            $period = AccountingPeriod::forDate($request->journal_date);

            $j->update([
                'journal_date' => $request->journal_date, 'accounting_period_id' => $period?->id,
                'reference_number' => $request->reference_number, 'description' => $request->description,
                'total_debit' => $dr, 'total_credit' => $cr, 'needs_review' => false, 'review_reason' => null,
                'last_edited_by' => Auth::id(),
            ]);

            AccountingAuditLog::record('journal', $j->id, 'edit', ['lines' => $old],
                ['lines' => collect($lines)->map(fn ($l) => collect($l)->only(['coa_account_id', 'description', 'debit', 'credit'])->all())->all()],
                null, Journal::DRAFT, Journal::DRAFT);

            return null;
        });

        return $err ? back()->withInput()->with('error', $err) : back()->with('success', 'Draft disimpan.');
    }

    public function submit($id)
    {
        if ($msg = $this->flagIfDrifted((int) $id)) {
            return back()->with('error', $msg);
        }

        $err = DB::transaction(function () use ($id) {
            $j = Journal::with('lines')->lockForUpdate()->findOrFail($id);
            if ($j->status !== Journal::DRAFT) {
                return 'Hanya jurnal Draft yang bisa di-submit.';
            }
            if ($e = $this->validateForWorkflow($j)) {
                return $e;
            }
            $j->update(['status' => Journal::SUBMITTED, 'submitted_by' => Auth::id(), 'submitted_at' => now(), 'return_reason' => null]);
            AccountingAuditLog::record('journal', $j->id, 'submit', null, null, null, Journal::DRAFT, Journal::SUBMITTED);

            return null;
        });

        return $err ? back()->with('error', $err) : back()->with('success', 'Jurnal di-submit ke Finance Manager.');
    }

    public function returnToDraft(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|min:5|max:255']);

        $err = DB::transaction(function () use ($request, $id) {
            $j = Journal::lockForUpdate()->findOrFail($id);
            if ($j->status !== Journal::SUBMITTED) {
                return 'Hanya jurnal Submitted yang bisa dikembalikan ke Draft.';
            }
            $j->update(['status' => Journal::DRAFT, 'return_reason' => $request->reason, 'submitted_by' => null, 'submitted_at' => null]);
            AccountingAuditLog::record('journal', $j->id, 'return', null, null, $request->reason, Journal::SUBMITTED, Journal::DRAFT);

            return null;
        });

        return $err ? back()->with('error', $err) : back()->with('success', 'Jurnal dikembalikan ke Draft.');
    }

    public function post($id)
    {
        if ($msg = $this->flagIfDrifted((int) $id)) {
            return back()->with('error', $msg);
        }

        $err = DB::transaction(function () use ($id) {
            $j = Journal::with('lines')->lockForUpdate()->findOrFail($id);
            if ($j->status !== Journal::SUBMITTED) {
                return 'Hanya jurnal Submitted yang bisa diposting.';
            }
            if ($this->sodBlocked($j)) {
                return 'Pembuat atau editor terakhir jurnal tidak boleh memposting jurnal ini. Minta pengguna lain (atau superadmin) yang memposting.';
            }
            if ($e = $this->validateForWorkflow($j)) {
                return $e;
            }
            if ($e = AccountingPeriod::postingBlocker($j->journal_date)) {
                return $e;
            }
            if ($e = $this->generator->sourceDrift($j)) {
                return $e;
            }

            // Pembalik: jurnal asal harus masih POSTED (kunci barisnya dulu agar dua pembalik tidak lolos bersamaan).
            $original = null;
            if ($j->journal_type === 'REVERSAL' && $j->reverses_journal_id) {
                $original = Journal::lockForUpdate()->find($j->reverses_journal_id);
                if (! $original || $original->status !== Journal::POSTED) {
                    return 'Jurnal yang dibalik tidak lagi berstatus Posted.';
                }
            }

            $period = AccountingPeriod::forDate($j->journal_date);
            $j->update(['status' => Journal::POSTED, 'posted_by' => Auth::id(), 'posted_at' => now(), 'accounting_period_id' => $period->id]);
            AccountingAuditLog::record('journal', $j->id, 'post', null, ['total_debit' => $j->total_debit], null, Journal::SUBMITTED, Journal::POSTED);

            if ($original) {
                $original->update(['status' => Journal::REVERSED]);
                // Sumber dilepas: transaksi asal boleh dijurnal ulang (jurnal pengganti); netto buku tetap nol.
                $original->sources()->update(['active_key' => null]);
                AccountingAuditLog::record('journal', $original->id, 'reversed', null, ['by' => $j->journal_number], null, Journal::POSTED, Journal::REVERSED);
            }

            // Akrual: siapkan Draft pembalik otomatis (tetap harus di-submit & diposting).
            if ($j->auto_reverse_date && $j->journal_type === 'ACCRUAL') {
                $rev = $this->makeReversalDraft($j, $j->auto_reverse_date->toDateString(), 'Pembalik otomatis akrual ' . $j->journal_number, true);
                AccountingAuditLog::record('journal', $j->id, 'auto_reversal_created', null, ['draft' => $rev->journal_number]);
            }

            return null;
        });

        return $err ? back()->with('error', $err) : back()->with('success', 'Jurnal diposting ke General Ledger.');
    }

    /** Buat Draft jurnal pembalik untuk jurnal Posted. */
    public function reverse(Request $request, $id)
    {
        $request->validate(['journal_date' => 'required|date', 'reason' => 'required|string|min:5|max:255']);

        $result = DB::transaction(function () use ($request, $id) {
            $orig = Journal::with('lines')->lockForUpdate()->findOrFail($id);
            if ($orig->status !== Journal::POSTED || $orig->journal_type === 'REVERSAL') {
                return 'Hanya jurnal Posted (bukan jurnal pembalik) yang bisa dibalik.';
            }
            if ($orig->reversals()->exists()) {
                return 'Jurnal ini sudah punya jurnal pembalik.';
            }
            $rev = $this->makeReversalDraft($orig, $request->journal_date, 'Pembalik ' . $orig->journal_number . ': ' . $request->reason);
            AccountingAuditLog::record('journal', $orig->id, 'reverse_requested', null, ['draft' => $rev->journal_number], $request->reason);

            return $rev;
        });

        return is_string($result) ? back()->with('error', $result)
            : redirect()->route('accounting.journal.show', $result->id)->with('success', 'Draft jurnal pembalik dibuat. Submit lalu minta Finance Manager memposting.');
    }

    public function destroy(Request $request, $id)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        $err = DB::transaction(function () use ($request, $id) {
            $j = Journal::lockForUpdate()->findOrFail($id);
            if (! $j->isEditable()) {
                return 'Hanya jurnal Draft yang bisa dihapus. Jurnal yang sudah diposting dikoreksi dengan jurnal pembalik.';
            }
            // Lepas kunci anti-dobel: sumber boleh dijurnal ulang.
            $j->sources()->update(['active_key' => null]);
            $j->update(['deleted_by' => Auth::id(), 'delete_reason' => $request->reason]);
            $j->delete();
            AccountingAuditLog::record('journal', $j->id, 'delete', null, null, $request->reason, Journal::DRAFT, null);

            return null;
        });

        return $err ? back()->with('error', $err)
            : redirect()->route('accounting.journal.index')->with('success', 'Draft dihapus; sumbernya bisa dijurnal ulang.');
    }

    /** Pemisahan tugas: pemosting tidak boleh pembuat maupun editor terakhir (superadmin dikecualikan). */
    private function sodBlocked(Journal $j): bool
    {
        $me = (int) Auth::id();
        if (Auth::user()?->hasRole(['superadmin'])) {
            return false;
        }

        return ($j->created_by && (int) $j->created_by === $me) || ($j->last_edited_by && (int) $j->last_edited_by === $me);
    }

    /**
     * Bila sumber berubah sejak jurnal dibuat: tandai needs_review (persisten, di luar transaksi
     * aksi) dan kembalikan pesan; null = sumber masih cocok.
     */
    private function flagIfDrifted(int $id): ?string
    {
        $j = Journal::with('sources')->find($id);
        if (! $j || ! in_array($j->status, [Journal::DRAFT, Journal::SUBMITTED], true)) {
            return null;
        }
        $msg = $this->generator->sourceDrift($j);
        if ($msg && ! $j->needs_review) {
            $j->update(['needs_review' => true, 'review_reason' => mb_substr($msg, 0, 250)]);
            AccountingAuditLog::record('journal', $j->id, 'needs_review', null, null, $msg);
        }

        return $msg;
    }

    /** Draft pembalik: debit dan kredit ditukar, tertaut ke jurnal asal. Dipanggil di dalam transaksi. */
    private function makeReversalDraft(Journal $orig, string $date, string $description, bool $system = false): Journal
    {
        $rev = Journal::create([
            'journal_number' => Journal::nextNumber(), 'journal_date' => $date,
            'accounting_period_id' => AccountingPeriod::forDate($date)?->id,
            'journal_type' => 'REVERSAL', 'status' => Journal::DRAFT, 'reference_number' => $orig->journal_number,
            'description' => mb_substr($description, 0, 500), 'reverses_journal_id' => $orig->id,
            'total_debit' => $orig->total_credit, 'total_credit' => $orig->total_debit,
            // Pembalik otomatis akrual dibuat sistem (tanpa pembuat) agar pemosting akrual tidak terkunci oleh SoD.
            'created_by' => $system ? null : Auth::id(),
        ]);
        foreach ($orig->lines()->get() as $l) {
            $rev->lines()->create([
                'line_no' => $l->line_no, 'coa_account_id' => $l->coa_account_id, 'description' => $l->description,
                'debit' => $l->credit, 'credit' => $l->debit, 'cost_center_id' => $l->cost_center_id, 'project_id' => $l->project_id,
            ]);
        }
        AccountingAuditLog::record('journal', $rev->id, 'create_reversal', null, ['reverses' => $orig->journal_number],
            null, null, Journal::DRAFT, $orig->journal_number);

        return $rev;
    }

    /**
     * Data dropdown editor. $keep* = nilai yang SUDAH tersimpan di baris (akun/cost center/project
     * yang kini nonaktif atau di luar 500 project terbaru) — tetap dimasukkan supaya browser tidak
     * mengganti pilihannya diam-diam ke opsi pertama saat Draft disimpan. Akun nonaktif tetap
     * ditolak server saat simpan/submit dengan pesan yang jelas.
     */
    private function editorData(array $keepAccounts = [], array $keepCenters = [], array $keepProjects = []): array
    {
        return [
            'accounts' => CoaAccount::where(fn ($q) => $q->where(fn ($w) => $w->where('status', 'ACTIVE')->where('is_postable', true))
                    ->orWhereIn('id', $keepAccounts))
                ->orderBy('code')->get(['id', 'code', 'name', 'status', 'is_postable']),
            'centers'  => CostCenter::where(fn ($q) => $q->where('status', 'ACTIVE')->orWhereIn('id', $keepCenters))
                ->orderBy('code')->get(['id', 'code', 'name']),
            'projects' => Project::where(fn ($q) => $q->whereIn('id', Project::orderByDesc('id')->limit(500)->pluck('id'))->orWhereIn('id', $keepProjects))
                ->orderByDesc('id')->get(['id', 'name']),
        ];
    }

    private function lineRules(): array
    {
        return [
            'lines'                  => 'required|array|min:2|max:200',
            'lines.*.coa_account_id' => 'required|exists:coa_accounts,id',
            'lines.*.description'    => 'nullable|string|max:255',
            'lines.*.debit'          => 'nullable|string|max:30',
            'lines.*.credit'         => 'nullable|string|max:30',
            'lines.*.cost_center_id' => 'nullable|exists:cost_centers,id',
            'lines.*.project_id'     => 'nullable|exists:projects,id',
        ];
    }

    /** Teks input -> baris jurnal dalam sen. @return array{0:array,1:?string} [baris, pesan galat] */
    private function parseLines(array $input): array
    {
        $lines = [];
        foreach (array_values($input) as $i => $l) {
            $n = $i + 1;
            $rawDr = trim((string) ($l['debit'] ?? ''));
            $rawCr = trim((string) ($l['credit'] ?? ''));
            $dr = $rawDr === '' ? 0 : AccountingMoney::parse($rawDr);
            $cr = $rawCr === '' ? 0 : AccountingMoney::parse($rawCr);
            if ($dr === null || $cr === null) {
                return [[], "Baris {$n}: nominal tidak valid. Gunakan koma untuk desimal dan titik hanya untuk ribuan (contoh 1.234.567,89)."];
            }
            if ($dr < 0 || $cr < 0) {
                return [[], "Baris {$n}: nominal tidak boleh negatif."];
            }
            if ($dr > 0 && $cr > 0) {
                return [[], "Baris {$n}: isi debit ATAU kredit, bukan keduanya."];
            }
            if ($dr === 0 && $cr === 0) {
                return [[], "Baris {$n}: nominal debit/kredit wajib diisi."];
            }
            $lines[] = [
                'line_no' => $n, 'coa_account_id' => (int) $l['coa_account_id'], 'description' => $l['description'] ?? null,
                'debit' => $dr, 'credit' => $cr,
                'cost_center_id' => ($l['cost_center_id'] ?? null) ?: null, 'project_id' => ($l['project_id'] ?? null) ?: null,
            ];
        }

        $bad = CoaAccount::whereIn('id', array_column($lines, 'coa_account_id'))
            ->where(fn ($q) => $q->where('status', '!=', 'ACTIVE')->orWhere('is_postable', false))->exists();
        if ($bad) {
            return [[], 'Ada akun nonaktif atau akun induk (tidak bisa diposting) di baris jurnal.'];
        }

        return [$lines, null];
    }

    /** Validasi wajib sebelum Submit / Post (dihitung ulang dari baris, bukan dari header). */
    private function validateForWorkflow(Journal $j): ?string
    {
        $lines = $j->lines;
        if ($lines->count() < 2) {
            return 'Jurnal minimal punya dua baris.';
        }
        $dr = (int) $lines->sum('debit');
        $cr = (int) $lines->sum('credit');
        if ($dr <= 0 || $dr !== $cr) {
            return 'Debit (' . AccountingMoney::format($dr) . ') dan kredit (' . AccountingMoney::format($cr) . ') tidak seimbang.';
        }
        if ($lines->contains(fn ($l) => ($l->debit > 0) === ($l->credit > 0))) {
            return 'Setiap baris harus berisi debit atau kredit (salah satu, lebih dari nol).';
        }
        $bad = CoaAccount::whereIn('id', $lines->pluck('coa_account_id'))->where(fn ($q) => $q->where('status', '!=', 'ACTIVE')->orWhere('is_postable', false))->exists();
        if ($bad) {
            return 'Ada akun nonaktif atau akun induk di baris jurnal.';
        }
        if ($j->total_debit !== $dr || $j->total_credit !== $cr) {
            $j->update(['total_debit' => $dr, 'total_credit' => $cr]);
        }

        return null;
    }

    private function can(array $roles): bool
    {
        return (bool) Auth::user()?->hasRole($roles);
    }
}

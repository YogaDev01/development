<?php

namespace App\Http\Controllers;

use App\Models\AccountingAuditLog;
use App\Models\AccountMapping;
use App\Models\CoaAccount;
use App\Models\SourceFund;
use App\Support\AccountingCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Accounting — Chart of Accounts. Akun tidak dihapus bila sudah dipakai jurnal,
 * dan tipe/saldo normalnya terkunci setelah dipakai (menjaga saldo historis).
 */
class CoaController extends Controller
{
    public const MANAGE_ROLES = \App\Support\AccessRoles::ACCOUNTING_MANAGE;

    public function index()
    {
        $accounts = CoaAccount::with(['parent:id,code,name', 'sourceFund:id,name'])
            ->withCount(['children', 'journalLines'])
            ->orderBy('code')->get();

        // Kedalaman hierarki untuk indentasi (dihitung di memori, tanpa N+1).
        $byId = $accounts->keyBy('id');
        $depth = function ($a) use ($byId) {
            $d = 0;
            for ($p = $a->parent_id; $p && isset($byId[$p]) && $d < 10; $p = $byId[$p]->parent_id) {
                $d++;
            }

            return $d;
        };
        $accounts->each(fn ($a) => $a->depth = $depth($a));

        return view('accounting.coa.index', [
            'accounts'  => $accounts,
            'types'     => AccountingCatalog::ACCOUNT_TYPES,
            'sections'  => AccountingCatalog::SECTIONS,
            'activities' => AccountingCatalog::CASH_ACTIVITIES,
            'funds'     => SourceFund::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']),
            'usedFunds' => $accounts->pluck('source_fund_id')->filter()->all(),
            'canManage' => $this->can(self::MANAGE_ROLES),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = Auth::id();

        if ($err = $this->parentError($data)) {
            return back()->withInput()->with('error', $err);
        }

        $acc = CoaAccount::create($data);
        AccountingAuditLog::record('coa_account', $acc->id, 'create', null, $acc->only(array_keys($data)));

        return back()->with('success', 'Akun ' . $acc->code . ' dibuat.');
    }

    public function update(Request $request, $id)
    {
        $acc = CoaAccount::findOrFail($id);
        $data = $this->validated($request, $acc);

        if ($err = $this->parentError($data, $acc)) {
            return back()->withInput()->with('error', $err);
        }

        $used = $acc->hasJournalLines();
        if ($used && ($data['account_type'] !== $acc->account_type || $data['normal_balance'] !== $acc->normal_balance)) {
            return back()->withInput()->with('error', 'Tipe akun dan saldo normal tidak bisa diubah: akun sudah dipakai jurnal.');
        }
        if ($used && ! $data['is_postable']) {
            return back()->withInput()->with('error', 'Akun sudah dipakai jurnal, tidak bisa dijadikan akun induk (non-posting).');
        }
        // Report lock: klasifikasi laporan terkunci bila akun punya mutasi di periode yang sudah ditutup.
        $locked = ['category', 'statement_section', 'cash_flow_activity', 'source_fund_id', 'parent_id'];
        $changed = array_filter($locked, fn ($f) => (string) ($data[$f] ?? '') !== (string) ($acc->{$f} ?? ''));
        if ($changed && $acc->hasClosedPeriodActivity()) {
            return back()->withInput()->with('error', 'Klasifikasi laporan (kategori, penempatan, aktivitas kas, rekening bank, induk) terkunci: akun punya mutasi di periode yang sudah ditutup. Buka kembali periodenya dulu (Finance Manager) bila perubahan memang perlu.');
        }
        if ($data['status'] === 'INACTIVE' && $acc->children()->where('status', 'ACTIVE')->exists()) {
            return back()->withInput()->with('error', 'Nonaktifkan dulu akun turunannya.');
        }

        $old = $acc->only(array_keys($data));
        $acc->update($data);
        AccountingAuditLog::record('coa_account', $acc->id, 'update', $old, $acc->only(array_keys($data)));

        return back()->with('success', 'Akun ' . $acc->code . ' diperbarui.');
    }

    public function destroy($id)
    {
        $result = DB::transaction(function () use ($id) {
            $acc = CoaAccount::lockForUpdate()->findOrFail($id);
            if ($acc->hasJournalLines()) {
                return 'Akun sudah dipakai jurnal — nonaktifkan saja, tidak bisa dihapus.';
            }
            if ($acc->children()->exists()) {
                return 'Akun masih punya akun turunan.';
            }
            if (AccountMapping::where('debit_account_id', $id)->orWhere('credit_account_id', $id)->exists()) {
                return 'Akun masih dipakai di Account Mapping.';
            }
            AccountingAuditLog::record('coa_account', $acc->id, 'delete', $acc->toArray());
            $acc->delete();

            return null;
        });

        return $result ? back()->with('error', $result) : back()->with('success', 'Akun dihapus.');
    }

    private function validated(Request $request, ?CoaAccount $acc = null): array
    {
        $data = $request->validate([
            'code'           => ['required', 'string', 'max:20', Rule::unique('coa_accounts', 'code')->ignore($acc?->id)],
            'name'           => 'required|string|max:255',
            'account_type'   => ['required', Rule::in(array_keys(AccountingCatalog::ACCOUNT_TYPES))],
            'category'       => 'nullable|string|max:100',
            'parent_id'      => 'nullable|exists:coa_accounts,id',
            'normal_balance' => ['nullable', Rule::in(['DEBIT', 'CREDIT'])],
            'description'    => 'nullable|string|max:1000',
            'is_postable'    => 'nullable|boolean',
            'source_fund_id' => ['nullable', 'exists:source_funds,id', Rule::unique('coa_accounts', 'source_fund_id')->ignore($acc?->id)],
            'status'         => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
            'statement_section'  => 'nullable|string|max:30',
            'cash_flow_activity' => ['nullable', Rule::in(array_keys(AccountingCatalog::CASH_ACTIVITIES))],
        ]);

        // Penempatan laporan harus cocok dengan tipe akun; kosong = bawaan tipe.
        $sections = AccountingCatalog::SECTIONS[$data['account_type']] ?? [];
        $data['statement_section'] = isset($sections[$data['statement_section'] ?? '']) ? $data['statement_section'] : null;
        $data['cash_flow_activity'] = $data['cash_flow_activity'] ?? null;

        $data['normal_balance'] = $data['normal_balance'] ?? AccountingCatalog::ACCOUNT_TYPES[$data['account_type']][1];
        $data['is_postable']    = (bool) ($data['is_postable'] ?? false);
        $data['status']         = $data['status'] ?? 'ACTIVE';

        return $data;
    }

    /** Induk harus satu tipe, bukan diri sendiri / turunannya sendiri (cegah siklus). */
    private function parentError(array $data, ?CoaAccount $self = null): ?string
    {
        if (empty($data['parent_id'])) {
            return null;
        }
        $parent = CoaAccount::find($data['parent_id']);
        if ($parent->account_type !== $data['account_type']) {
            return 'Akun induk harus bertipe sama (' . $data['account_type'] . ').';
        }
        for ($p = $parent, $i = 0; $p && $i < 20; $p = $p->parent, $i++) {
            if ($self && $p->id === $self->id) {
                return 'Akun induk tidak boleh diri sendiri atau turunannya.';
            }
        }

        return null;
    }

    private function can(array $roles): bool
    {
        return (bool) Auth::user()?->hasRole($roles);
    }
}

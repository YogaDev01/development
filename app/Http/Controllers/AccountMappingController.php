<?php

namespace App\Http\Controllers;

use App\Models\AccountingAuditLog;
use App\Models\AccountMapping;
use App\Models\CoaAccount;
use App\Support\AccountingCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Accounting — Account Mapping. Menentukan akun usulan saat jurnal dibuat dari
 * Treasury / Cash Receipt. Tidak ada kode akun di logika aplikasi; semuanya
 * dari tabel ini. Sisi "bank" memakai akun yang tertaut ke rekening transaksi.
 */
class AccountMappingController extends Controller
{
    public const MANAGE_ROLES = \App\Support\AccessRoles::ACCOUNTING_MANAGE;

    public function index()
    {
        return view('accounting.mapping.index', [
            'mappings'  => AccountMapping::with(['debitAccount:id,code,name', 'creditAccount:id,code,name'])
                ->orderBy('source_module')->orderBy('transaction_type')->orderByDesc('effective_from')->get(),
            'accounts'  => CoaAccount::postable()->orderBy('code')->get(['id', 'code', 'name']),
            'catalog'   => AccountingCatalog::TRANSACTION_TYPES,
            'modules'   => AccountingCatalog::SOURCE_MODULES,
            'canManage' => $this->can(self::MANAGE_ROLES),
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new AccountMapping());
    }

    public function update(Request $request, $id)
    {
        return $this->save($request, AccountMapping::findOrFail($id));
    }

    public function destroy($id)
    {
        $m = AccountMapping::findOrFail($id);
        AccountingAuditLog::record('account_mapping', $m->id, 'delete', $m->toArray());
        $m->delete();

        return back()->with('success', 'Mapping dihapus. Jurnal yang sudah dibuat tidak terpengaruh.');
    }

    private function save(Request $request, AccountMapping $m)
    {
        $data = $request->validate([
            'source_module'     => ['required', Rule::in(array_keys(AccountingCatalog::SOURCE_MODULES))],
            'transaction_type'  => 'required|string|max:40',
            'match_key'         => 'nullable|string|max:60',
            'debit_account_id'  => 'nullable|exists:coa_accounts,id',
            'credit_account_id' => 'nullable|exists:coa_accounts,id',
            'effective_from'    => 'required|date',
            'effective_to'      => 'nullable|date|after_or_equal:effective_from',
            'status'            => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
            'description'       => 'nullable|string|max:255',
        ]);

        $def = AccountingCatalog::TRANSACTION_TYPES[$data['source_module']][$data['transaction_type']] ?? null;
        if (! $def) {
            return back()->withInput()->with('error', 'Tipe transaksi tidak dikenal untuk sumber tersebut.');
        }

        // Sisi bank dikunci dari katalog; sisi lain wajib akun aktif yang bisa diposting.
        $data['debit_is_bank']  = $def[1];
        $data['credit_is_bank'] = $def[2];
        $data['match_key']      = ($data['match_key'] ?? '') === '' ? null : $data['match_key'];
        $data['status']         = $data['status'] ?? 'ACTIVE';

        foreach (['debit' => 'Debit', 'credit' => 'Kredit'] as $side => $label) {
            $isBank = $data[$side . '_is_bank'];
            $accId = $data[$side . '_account_id'] ?? null;
            if ($isBank) {
                $data[$side . '_account_id'] = null; // diambil dari rekening transaksi saat jurnal dibuat
                continue;
            }
            if (! $accId) {
                return back()->withInput()->with('error', "Akun sisi {$label} wajib dipilih.");
            }
            if (! CoaAccount::postable()->whereKey($accId)->exists()) {
                return back()->withInput()->with('error', "Akun sisi {$label} harus aktif dan bisa diposting.");
            }
        }

        $m->fill($data);
        if ($m->conflictingQuery()->exists()) {
            return back()->withInput()->with('error', 'Konflik: sudah ada mapping aktif untuk kombinasi yang sama pada rentang tanggal tersebut.');
        }

        $old = $m->exists ? $m->only(array_keys($data)) : null;
        $m->created_by = $m->created_by ?? Auth::id();
        $m->save();
        AccountingAuditLog::record('account_mapping', $m->id, $old ? 'update' : 'create', $old, $m->only(array_keys($data)));

        return back()->with('success', 'Mapping disimpan.');
    }

    private function can(array $roles): bool
    {
        return (bool) Auth::user()?->hasRole($roles);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AccountingAuditLog;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Accounting — Journal Audit Trail (hanya baca). Menampilkan jejak seluruh perubahan
 * akuntansi: jurnal (buat, generate, edit, submit, kembalikan, posting, pembalik, hapus),
 * mapping, akun COA, dan periode. Log tidak pernah diubah/dihapus oleh aplikasi.
 */
class AccountingAuditController extends Controller
{
    private const ENTITIES = ['journal' => 'Jurnal', 'account_mapping' => 'Account Mapping', 'coa_account' => 'Akun COA', 'period' => 'Periode'];

    public function index(Request $request)
    {
        $logs = AccountingAuditLog::with('user:id,username')
            ->when($request->entity, fn ($q, $v) => $q->where('entity_type', $v))
            ->when($request->action, fn ($q, $v) => $q->where('action', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->number, function ($q, $n) {
                $ids = Journal::withTrashed()->where('journal_number', 'like', '%' . $n . '%')->pluck('id');
                $q->where('entity_type', 'journal')->whereIn('entity_id', $ids);
            })
            ->orderByDesc('id')->limit(500)->get();

        $numbers = Journal::withTrashed()->whereIn('id', $logs->where('entity_type', 'journal')->pluck('entity_id'))
            ->pluck('journal_number', 'id');

        return view('accounting.audit.index', [
            'logs' => $logs, 'numbers' => $numbers, 'entities' => self::ENTITIES,
            'actions' => AccountingAuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'users' => User::whereIn('id', AccountingAuditLog::select('user_id')->distinct())->orderBy('username')->get(['id', 'username']),
        ]);
    }
}

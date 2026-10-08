<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\UserActivityLog;

class AccountController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $accounts = Account::orderBy('category')->orderBy('name')->get();

        $summary = [
            'total'         => $accounts->count(),
            'variable_cost' => $accounts->where('category', 'Variable Cost')->count(),
            'fixed_cost'    => $accounts->where('category', 'Fixed Cost')->count(),
            'overhead'      => $accounts->where('category', 'Overhead')->count(),
        ];

        return view('account.index', compact('accounts', 'summary'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', Account::CATEGORIES),
        ]);

        $account = Account::create([
            'name'     => $request->name,
            'category' => $request->category,
            'status'   => 'ACTIVE',
        ]);

        UserActivityLog::record('master_data', 'create', 'Menambah Account ' . $account->name, $account);

        return redirect('/account')
            ->with('success', 'Account berhasil ditambahkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | GET EDIT DATA — AJAX
    |--------------------------------------------------------------------------
    */

    public function getEditData($id)
    {
        $account = Account::findOrFail($id);

        return response()->json([
            'id'       => $account->id,
            'name'     => $account->name,
            'category' => $account->category,
            'status'   => $account->status,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'category' => 'required|in:' . implode(',', Account::CATEGORIES),
            'status'   => 'required|in:ACTIVE,INACTIVE',
        ]);

        $account = Account::findOrFail($id);

        $account->update([
            'name'     => $request->name,
            'category' => $request->category,
            'status'   => $request->status,
        ]);

        UserActivityLog::record('master_data', 'update', 'Mengubah Account ' . $account->name, $account);

        return redirect('/account')
            ->with('success', 'Account berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $account = Account::findOrFail($id);

        // Cek apakah account masih dipakai di budget plan detail
        if ($account->budgetPlanDetails()->exists()) {
            return redirect('/account')
                ->with('error', 'Account tidak dapat dihapus karena masih digunakan di Budget Plan.');
        }

        $accountName = $account->name;
        $account->delete();

        UserActivityLog::record('master_data', 'delete', 'Menghapus Account ' . $accountName);

        return redirect('/account')
            ->with('success', 'Account berhasil dihapus.');
    }
}
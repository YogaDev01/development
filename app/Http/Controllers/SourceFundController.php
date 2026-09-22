<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\SourceFund;
use App\Models\Bank;

class SourceFundController extends Controller
{
    public function index()
    {
        $sourceFunds = SourceFund::with('bank')->latest()->get();
        $banks       = Bank::orderBy('name')->get();

        $summary = [
            'total'  => $sourceFunds->count(),
            'active' => $sourceFunds->where('status', 'ACTIVE')->count(),
        ];

        return view('source-fund.index', compact('sourceFunds', 'banks', 'summary'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'no_account'      => 'nullable|string|max:100',
            'bank_id'         => 'nullable|exists:banks,id',
            'status'          => 'required|in:ACTIVE,INACTIVE',
            'is_main_account' => 'nullable|boolean',
        ]);

        $isMain = $request->boolean('is_main_account');

        if ($isMain) {
            SourceFund::where('is_main_account', true)->update(['is_main_account' => false]);
        }

        SourceFund::create([
            'name'            => strtoupper($request->name),
            'no_account'      => $request->no_account,
            'bank_id'         => $request->bank_id,
            'status'          => $request->status,
            'is_main_account' => $isMain,
            'input_by'        => Auth::id(),
        ]);

        return redirect('/source-fund')->with('success', 'Source Fund berhasil ditambahkan');
    }

    public function getEditData($id)
    {
        $sf = SourceFund::findOrFail($id);

        return response()->json([
            'id'              => $sf->id,
            'name'            => $sf->name,
            'no_account'      => $sf->no_account,
            'bank_id'         => $sf->bank_id,
            'status'          => $sf->status,
            'is_main_account' => $sf->is_main_account,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'no_account'      => 'nullable|string|max:100',
            'bank_id'         => 'nullable|exists:banks,id',
            'status'          => 'required|in:ACTIVE,INACTIVE',
            'is_main_account' => 'nullable|boolean',
        ]);

        $sf = SourceFund::findOrFail($id);
        $isMain = $request->boolean('is_main_account');

        if ($isMain) {
            SourceFund::where('is_main_account', true)->where('id', '!=', $sf->id)->update(['is_main_account' => false]);
        }

        $sf->update([
            'name'            => strtoupper($request->name),
            'no_account'      => $request->no_account,
            'bank_id'         => $request->bank_id,
            'status'          => $request->status,
            'is_main_account' => $isMain,
        ]);

        return redirect('/source-fund')->with('success', 'Source Fund berhasil diupdate');
    }

    public function destroy($id)
    {
        $sf = SourceFund::findOrFail($id);

        if (\App\Models\DailyTransaction::where('source_fund_id', $sf->id)->exists()) {
            return back()->with('error', 'Source Fund tidak bisa dihapus karena sudah dipakai pada Batch Treasury.');
        }
        // withTrashed(): Invoice & Cash Receipt memakai SoftDeletes. FK
        // source_fund_id di sini ON DELETE SET NULL, jadi yang hilang bukan
        // barisnya melainkan sumber dananya — dokumen yang dipulihkan dari
        // recycle bin akan kembali tanpa Source Fund. Tetap ditahan.
        if (\App\Models\CashReceipt::withTrashed()->where('source_fund_id', $sf->id)->exists()) {
            return back()->with('error', 'Source Fund tidak bisa dihapus karena sudah dipakai pada Cash Receipt.');
        }
        if (\App\Models\Invoice::withTrashed()->where('source_fund_id', $sf->id)->exists()) {
            return back()->with('error', 'Source Fund tidak bisa dihapus karena sudah dipakai pada Invoice.');
        }

        $sf->delete();

        return redirect('/source-fund')->with('success', 'Source Fund berhasil dihapus');
    }

    /**
     * AJAX — dropdown picker (dipakai form Buat Batch Treasury)
     */
    public function optionsJson()
    {
        $sourceFunds = SourceFund::with('bank')
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name', 'no_account', 'bank_id', 'is_main_account']);

        return response()->json($sourceFunds);
    }
}
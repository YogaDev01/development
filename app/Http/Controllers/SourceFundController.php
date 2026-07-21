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
            'name'       => 'required|string|max:255',
            'no_account' => 'nullable|string|max:100',
            'bank_id'    => 'nullable|exists:banks,id',
            'status'     => 'required|in:ACTIVE,INACTIVE',
        ]);

        SourceFund::create([
            'name'       => strtoupper($request->name),
            'no_account' => $request->no_account,
            'bank_id'    => $request->bank_id,
            'status'     => $request->status,
            'input_by'   => Auth::id(),
        ]);

        return redirect('/source-fund')->with('success', 'Source Fund berhasil ditambahkan');
    }

    public function getEditData($id)
    {
        $sf = SourceFund::findOrFail($id);

        return response()->json([
            'id'         => $sf->id,
            'name'       => $sf->name,
            'no_account' => $sf->no_account,
            'bank_id'    => $sf->bank_id,
            'status'     => $sf->status,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'no_account' => 'nullable|string|max:100',
            'bank_id'    => 'nullable|exists:banks,id',
            'status'     => 'required|in:ACTIVE,INACTIVE',
        ]);

        $sf = SourceFund::findOrFail($id);

        $sf->update([
            'name'       => strtoupper($request->name),
            'no_account' => $request->no_account,
            'bank_id'    => $request->bank_id,
            'status'     => $request->status,
        ]);

        return redirect('/source-fund')->with('success', 'Source Fund berhasil diupdate');
    }

    public function destroy($id)
    {
        $sf = SourceFund::findOrFail($id);
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
            ->get(['id', 'name', 'no_account', 'bank_id']);

        return response()->json($sourceFunds);
    }
}
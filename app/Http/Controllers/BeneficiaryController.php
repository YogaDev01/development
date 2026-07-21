<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Bank;
use App\Models\Beneficiary;

class BeneficiaryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $beneficiaries = Beneficiary::with(['bank', 'user'])
            ->latest()
            ->get();

        $banks = Bank::orderBy('name')->get();

        return view('beneficiaries.index', compact('beneficiaries', 'banks'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $banks = Bank::orderBy('name')->get();
        return view('beneficiaries.create', compact('banks'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nickname'       => 'required',
            'complete_name'  => 'required',
            'contact'        => 'nullable',
            'bank_id'        => 'nullable|exists:banks,id',
            'account_number' => 'nullable',
            'status'         => 'required|in:ACTIVE,INACTIVE',
        ]);

        Beneficiary::create([
            'nickname'       => strtoupper($request->nickname),
            'complete_name'  => strtoupper($request->complete_name),
            'contact'        => $request->contact,
            'bank_id'        => $request->bank_id,
            'account_number' => $request->account_number,
            'status'         => $request->status,
            'input_by'       => Auth::id(),
        ]);

        return redirect('/beneficiaries')
            ->with('success', 'Beneficiary berhasil ditambahkan');
    }

    /**
     * --------------------------------------------------------------------------
     * AJAX — Get beneficiary data for edit modal
     * GET /beneficiaries/{id}/edit-data
     * --------------------------------------------------------------------------
     */
    public function getEditData($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);

        return response()->json([
            'id'             => $beneficiary->id,
            'nickname'       => $beneficiary->nickname,
            'complete_name'  => $beneficiary->complete_name,
            'bank_id'        => $beneficiary->bank_id,
            'account_number' => $beneficiary->account_number,
            'contact'        => $beneficiary->contact,
            'status'         => $beneficiary->status,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $banks = Bank::orderBy('name')->get();
        return view('beneficiaries.edit', compact('beneficiary', 'banks'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nickname'       => 'required',
            'complete_name'  => 'required',
            'contact'        => 'nullable',
            'bank_id'        => 'nullable|exists:banks,id',
            'account_number' => 'nullable',
            'status'         => 'required|in:ACTIVE,INACTIVE',
        ]);

        $beneficiary = Beneficiary::findOrFail($id);

        $beneficiary->update([
            'nickname'       => strtoupper($request->nickname),
            'complete_name'  => strtoupper($request->complete_name),
            'contact'        => $request->contact,
            'bank_id'        => $request->bank_id,
            'account_number' => $request->account_number,
            'status'         => $request->status,
        ]);

        return redirect('/beneficiaries')
            ->with('success', 'Beneficiary berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $beneficiary = Beneficiary::findOrFail($id);
        $beneficiary->delete();

        return redirect('/beneficiaries')
            ->with('success', 'Beneficiary berhasil dihapus');
    }
}
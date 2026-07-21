<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Beneficiary;
use Illuminate\Http\Request;

class BankController extends Controller
{
    /**
     * DISPLAY LIST
     */
    public function index()
    {
        $banks = Bank::latest()->get();

        return view('bank.index', compact(
            'banks'
        ));
    }

    /**
     * CREATE PAGE
     */
    public function create()
    {
        return view('bank.create');
    }

    /**
     * STORE DATA
     */
    public function store(Request $request)
    {
        $request->validate([

            'name'           => 'required|unique:banks,name',
            'code'           => 'nullable|string|max:50',
            'default_method' => 'nullable|in:' . implode(',', Bank::METHODS),

        ]);

        Bank::create([

            'name'           => strtoupper($request->name),
            'code'           => $request->code ? strtoupper($request->code) : null,
            'default_method' => $request->default_method,

        ]);

        return redirect('/bank')
            ->with('success', 'Bank berhasil ditambahkan');
    }

    /**
     * EDIT PAGE
     */
    public function edit($id)
    {
        $bank = Bank::findOrFail($id);

        return view('bank.edit', compact(
            'bank'
        ));
    }

    /**
     * UPDATE DATA
     */
    public function update(Request $request, $id)
    {
        $request->validate([

            'name'           => 'required|unique:banks,name,' . $id,
            'code'           => 'nullable|string|max:50',
            'default_method' => 'nullable|in:' . implode(',', Bank::METHODS),

        ]);

        $bank = Bank::findOrFail($id);

        $bank->update([

            'name'           => strtoupper($request->name),
            'code'           => $request->code ? strtoupper($request->code) : null,
            'default_method' => $request->default_method,

        ]);

        return redirect('/bank')
            ->with('success', 'Bank berhasil diupdate');
    }

    /**
     * DELETE DATA
     */
    public function destroy($id)
    {
        $bank = Bank::findOrFail($id);

        $bank->delete();

        return redirect('/bank')
            ->with('success', 'Bank berhasil dihapus');
    }

    public function show($id)
    {
        /*
        |--------------------------------------------------------------------------
        | BANK
        |--------------------------------------------------------------------------
        */

        $bank = Bank::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | BENEFICIARIES
        |--------------------------------------------------------------------------
        */

        $beneficiaries = Beneficiary::where(

                'bank_id',
                $bank->id

            )
            ->orderBy('complete_name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('bank.show', compact(

            'bank',
            'beneficiaries'

        ));
    }
}
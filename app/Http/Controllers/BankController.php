<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Beneficiary;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;

class BankController extends Controller
{
    /**
     * DISPLAY LIST
     */
    public function index()
    {
        $banks = Bank::withCount('beneficiaries')->latest()->get();

        $summary = [
            'total'      => $banks->count(),
            'with_method' => $banks->whereNotNull('default_method')->count(),
            'in_use'     => $banks->where('beneficiaries_count', '>', 0)->count(),
        ];

        return view('bank.index', compact(
            'banks', 'summary'
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

        $bank = Bank::create([

            'name'           => strtoupper($request->name),
            'code'           => $request->code ? strtoupper($request->code) : null,
            'default_method' => $request->default_method,

        ]);

        UserActivityLog::record('master_data', 'create', 'Menambah Bank ' . $bank->name, $bank);

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

        UserActivityLog::record('master_data', 'update', 'Mengubah Bank ' . $bank->name, $bank);

        return redirect('/bank')
            ->with('success', 'Bank berhasil diupdate');
    }

    /**
     * DELETE DATA
     */
    public function destroy($id)
    {
        $bank = Bank::findOrFail($id);

        if ($bank->beneficiaries()->exists()) {
            return back()->with('error', 'Bank tidak bisa dihapus karena sudah dipakai pada Beneficiary.');
        }
        if (\App\Models\SourceFund::where('bank_id', $bank->id)->exists()) {
            return back()->with('error', 'Bank tidak bisa dihapus karena sudah dipakai pada Source Fund.');
        }
        if (\App\Models\Employee::where('bank_id', $bank->id)->exists()) {
            return back()->with('error', 'Bank tidak bisa dihapus karena sudah dipakai pada data Employee.');
        }
        if (\App\Models\PayrollRunLine::where('bank_id', $bank->id)->exists()) {
            return back()->with('error', 'Bank tidak bisa dihapus karena sudah dipakai pada Payroll.');
        }

        $bankName = $bank->name;
        $bank->delete();

        UserActivityLog::record('master_data', 'delete', 'Menghapus Bank ' . $bankName);

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
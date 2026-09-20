<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Bank;
use App\Models\Beneficiary;
use App\Models\MasterPic;

class BeneficiaryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Filter server-side (MN-02) — kategori & status ikut menentukan baris
        // yang diambil, bukan cuma disembunyikan di DataTable, supaya angka
        // summary di kartu atas selalu sama dengan isi tabel di bawahnya.
        $category = $request->query('category');
        $status   = $request->query('status');
        $search   = trim((string) $request->query('q', ''));

        $beneficiaries = Beneficiary::with(['bank', 'user', 'pic'])
            ->when(in_array($category, Beneficiary::CATEGORIES, true),
                fn ($q) => $q->where('category', $category))
            ->when(in_array($status, ['ACTIVE', 'INACTIVE'], true),
                fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('nickname', 'like', $like)
                      ->orWhere('complete_name', 'like', $like)
                      ->orWhere('account_number', 'like', $like)
                      ->orWhere('contact', 'like', $like)
                      // Cari juga lewat relasi PIC & Bank.
                      ->orWhereHas('pic', fn ($r) => $r->where('pic', 'like', $like))
                      ->orWhereHas('bank', fn ($r) => $r->where('name', 'like', $like));
                });
            })
            ->latest()
            ->get();

        $banks = Bank::orderBy('name')->get();
        $pics  = MasterPic::where('status', 'ACTIVE')->orderBy('pic')->get();

        $summary = [
            'total'    => $beneficiaries->count(),
            'active'   => $beneficiaries->where('status', 'ACTIVE')->count(),
            'inactive' => $beneficiaries->where('status', 'INACTIVE')->count(),
            'internal' => $beneficiaries->where('category', 'INTERNAL')->count(),
            'external' => $beneficiaries->where('category', 'EXTERNAL')->count(),
            'billing'  => $beneficiaries->where('category', 'BILLING')->count(),
        ];

        $filters = ['category' => $category, 'status' => $status, 'q' => $search];

        return view('beneficiaries.index', compact('beneficiaries', 'banks', 'pics', 'summary', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $banks = Bank::orderBy('name')->get();
        $pics  = MasterPic::where('status', 'ACTIVE')->orderBy('pic')->get();
        return view('beneficiaries.create', compact('banks', 'pics'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nickname'       => 'required',
            'complete_name'  => 'required',
            'category'       => 'required|in:INTERNAL,EXTERNAL,BILLING',
            'pic_id'         => 'nullable|exists:master_pics,id',
            'contact'        => 'nullable',
            'bank_id'        => 'nullable|exists:banks,id',
            'account_number' => 'nullable',
            'status'         => 'required|in:ACTIVE,INACTIVE',
        ]);

        Beneficiary::create([
            'nickname'       => strtoupper($request->nickname),
            'complete_name'  => strtoupper($request->complete_name),
            'category'       => $request->category,
            'pic_id'         => $request->pic_id ?: null,
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
        $beneficiary = Beneficiary::with('pic')->findOrFail($id);

        return response()->json([
            'id'             => $beneficiary->id,
            'nickname'       => $beneficiary->nickname,
            'complete_name'  => $beneficiary->complete_name,
            'category'       => $beneficiary->category,
            'pic_id'         => $beneficiary->pic_id,
            'pic_name'       => $beneficiary->pic?->pic,
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
        $pics  = MasterPic::where('status', 'ACTIVE')->orderBy('pic')->get();
        return view('beneficiaries.edit', compact('beneficiary', 'banks', 'pics'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nickname'       => 'required',
            'complete_name'  => 'required',
            'category'       => 'required|in:INTERNAL,EXTERNAL,BILLING',
            'pic_id'         => 'nullable|exists:master_pics,id',
            'contact'        => 'nullable',
            'bank_id'        => 'nullable|exists:banks,id',
            'account_number' => 'nullable',
            'status'         => 'required|in:ACTIVE,INACTIVE',
        ]);

        $beneficiary = Beneficiary::findOrFail($id);

        $beneficiary->update([
            'nickname'       => strtoupper($request->nickname),
            'complete_name'  => strtoupper($request->complete_name),
            'category'       => $request->category,
            'pic_id'         => $request->pic_id ?: null,
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

        // withTrashed(): BudgetRequest memakai SoftDeletes, tapi FK
        // budget_request.beneficiary_id ON DELETE CASCADE bekerja di level MySQL dan
        // tidak mengenal soft delete. Tanpa withTrashed() baris yang "ada di
        // recycle bin" tidak terlihat oleh penjaga ini, penghapusan lolos, dan
        // MySQL menghapusnya permanen beserta seluruh log statusnya.
        if (\App\Models\BudgetRequest::withTrashed()->where('beneficiary_id', $beneficiary->id)->exists()) {
            return back()->with('error', 'Beneficiary tidak bisa dihapus karena sudah dipakai pada Cost Request.');
        }

        $beneficiary->delete();

        return redirect('/beneficiaries')
            ->with('success', 'Beneficiary berhasil dihapus');
    }
}
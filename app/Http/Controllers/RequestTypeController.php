<?php

namespace App\Http\Controllers;

use App\Models\RequestType;
use Illuminate\Http\Request;

class RequestTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $requestTypes = RequestType::latest()->get();

        return view('requesttype.index', compact(
            'requestTypes'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('requesttype.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([

            'name' => 'required|unique:request_type,name'

        ]);

        RequestType::create([

            'name' => $request->name

        ]);

        return redirect('/request-type')
            ->with('success', 'Tipe pengajuan berhasil ditambahkan');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $requestType = RequestType::findOrFail($id);

        return view('requesttype.show', compact(
            'requestType'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $requestType = RequestType::findOrFail($id);

        return view('requesttype.edit', compact(
            'requestType'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $requestType = RequestType::findOrFail($id);

        $request->validate([

            'name' => 'required|unique:request_type,name,' . $id

        ]);

        $requestType->update([

            'name' => $request->name

        ]);

        return redirect('/request-type')
            ->with('success', 'Tipe pengajuan berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $requestType = RequestType::findOrFail($id);

        // withTrashed(): BudgetRequest memakai SoftDeletes, tapi FK
        // budget_request.type_id ON DELETE CASCADE bekerja di level MySQL dan
        // tidak mengenal soft delete. Tanpa withTrashed() baris yang "ada di
        // recycle bin" tidak terlihat oleh penjaga ini, penghapusan lolos, dan
        // MySQL menghapusnya permanen beserta seluruh log statusnya.
        if (\App\Models\BudgetRequest::withTrashed()->where('type_id', $requestType->id)->exists()) {
            return back()->with('error', 'Tipe pengajuan tidak bisa dihapus karena sudah dipakai pada Cost Request.');
        }

        $requestType->delete();

        return redirect('/request-type')
            ->with('success', 'Tipe pengajuan berhasil dihapus');
    }
}
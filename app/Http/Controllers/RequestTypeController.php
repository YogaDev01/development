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

        $requestType->delete();

        return redirect('/request-type')
            ->with('success', 'Tipe pengajuan berhasil dihapus');
    }
}
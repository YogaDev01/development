<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\MasterPic;

class MasterPicController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $pics = MasterPic::latest()->get();

        $summary = [
            'total'    => $pics->count(),
            'customer' => $pics->where('type', 'customer')->count(),
            'internal' => $pics->where('type', 'internal')->count(),
            'active'   => $pics->where('status', 'ACTIVE')->count(),
        ];

        return view('master-pic.index', compact('pics', 'summary'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type'     => 'required|in:customer,internal',
            'pic'      => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'phone'    => 'nullable|string|max:50',
            'email'    => 'nullable|email|max:255',
            'status'   => 'required|in:ACTIVE,INACTIVE',
        ]);

        MasterPic::create([
            'type'     => $request->type,
            'pic'      => strtoupper($request->pic),
            'position' => $request->position,
            'phone'    => $request->phone,
            'email'    => $request->email,
            'status'   => $request->status,
            'input_by' => Auth::id(),
        ]);

        return redirect('/master-pic')
            ->with('success', 'PIC berhasil ditambahkan');
    }

    /**
     * --------------------------------------------------------------------------
     * AJAX — Get PIC data for edit modal
     * GET /master-pic/{id}/edit-data
     * --------------------------------------------------------------------------
     */
    public function getEditData($id)
    {
        $pic = MasterPic::findOrFail($id);

        return response()->json([
            'id'       => $pic->id,
            'type'     => $pic->type,
            'pic'      => $pic->pic,
            'position' => $pic->position,
            'phone'    => $pic->phone,
            'email'    => $pic->email,
            'status'   => $pic->status,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'type'     => 'required|in:customer,internal',
            'pic'      => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'phone'    => 'nullable|string|max:50',
            'email'    => 'nullable|email|max:255',
            'status'   => 'required|in:ACTIVE,INACTIVE',
        ]);

        $pic = MasterPic::findOrFail($id);

        $pic->update([
            'type'     => $request->type,
            'pic'      => strtoupper($request->pic),
            'position' => $request->position,
            'phone'    => $request->phone,
            'email'    => $request->email,
            'status'   => $request->status,
        ]);

        return redirect('/master-pic')
            ->with('success', 'PIC berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $pic = MasterPic::findOrFail($id);
        $pic->delete();

        return redirect('/master-pic')
            ->with('success', 'PIC berhasil dihapus');
    }

    /**
     * --------------------------------------------------------------------------
     * AJAX — Options untuk dropdown picker (dipakai PO, Project, dsb nantinya)
     * GET /master-pic/options-json?type=customer|internal
     * --------------------------------------------------------------------------
     */
    public function optionsJson(Request $request)
    {
        $query = MasterPic::where('status', 'ACTIVE');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $pics = $query->orderBy('pic')->get(['id', 'type', 'pic', 'position', 'phone', 'email']);

        return response()->json($pics);
    }
}

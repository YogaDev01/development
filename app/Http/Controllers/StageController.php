<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StageController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GENERATE NEXT CODE
    | Ambil code dari record paling akhir diinput (latest created_at),
    | lalu +1. Jika ada konflik, terus increment sampai bebas.
    |--------------------------------------------------------------------------
    */
    private function generateNextCode(): string
    {
        $last = Stage::orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->value('code');

        if ($last && ctype_digit($last) && strlen($last) <= 4) {
            $next = (int) $last + 1;
        } else {
            $next = 1;
        }

        $attempts = 0;
        while ($attempts < 9999) {
            $candidate = str_pad($next, 4, '0', STR_PAD_LEFT);
            if (!Stage::where('code', $candidate)->exists()) {
                return $candidate;
            }
            $next++;
            $attempts++;
        }

        return str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * --------------------------------------------------------------------------
     * AJAX — Get next auto code (for modal)
     * GET /stage/next-code
     * --------------------------------------------------------------------------
     */
    public function getNextCode()
    {
        return response()->json([
            'code' => $this->generateNextCode()
        ]);
    }

    /**
     * --------------------------------------------------------------------------
     * AJAX — Get stage data for edit modal
     * GET /stage/{id}/edit-data
     * --------------------------------------------------------------------------
     */
    public function getEditData($id)
    {
        $stage = Stage::findOrFail($id);

        return response()->json([
            'id'     => $stage->id,
            'code'   => $stage->code,
            'name'   => $stage->name,
            'status' => $stage->status,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $stages = Stage::with('user')
            ->orderBy('name')
            ->get();

        $summary = [
            'total'    => $stages->count(),
            'active'   => $stages->where('status', 'ACTIVE')->count(),
            'inactive' => $stages->where('status', 'INACTIVE')->count(),
        ];

        return view('stage.index', compact('stages', 'summary'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $nextCode = $this->generateNextCode();
        return view('stage.create', compact('nextCode'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Code di-generate ulang di server — tidak dari form input
        $code = $this->generateNextCode();

        $request->validate([
            'name'   => 'required|max:100',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        Stage::create([
            'code'       => $code,
            'name'       => $request->name,
            'status'     => $request->status,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('stage.index')
            ->with('success', 'Stage berhasil ditambahkan dengan kode ' . $code);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $stage = Stage::with('user')->findOrFail($id);
        return view('stage.show', compact('stage'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $stage = Stage::findOrFail($id);
        return view('stage.edit', compact('stage'));
    }

    /**
     * Update the specified resource in storage.
     * Code tidak diubah saat edit — immutable setelah dibuat.
     */
    public function update(Request $request, $id)
    {
        $stage = Stage::findOrFail($id);

        $request->validate([
            'name'   => 'required|max:100',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $stage->update([
            // code sengaja tidak diupdate
            'name'   => $request->name,
            'status' => $request->status,
        ]);

        return redirect()
            ->route('stage.index')
            ->with('success', 'Stage berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $stage = Stage::findOrFail($id);

        if (\App\Models\ProjectDetail::where('stage_id', $stage->id)->exists()) {
            return back()->with('error', 'Stage tidak bisa dihapus karena sudah dipakai pada Project Detail.');
        }

        $stage->delete();

        return redirect()
            ->route('stage.index')
            ->with('success', 'Stage berhasil dihapus.');
    }
}
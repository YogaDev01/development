<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Cluster;
use App\Models\ClusterGroup;

class ClusterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GENERATE NEXT CODE
    | Ambil code dari record paling akhir diinput (latest created_at),
    | lalu +1. Jika ada konflik (sudah dipakai), terus increment sampai bebas.
    |--------------------------------------------------------------------------
    */
    private function generateNextCode(): string
    {
        // Ambil record terakhir berdasarkan waktu input
        $last = Cluster::orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->value('code');

        // Tentukan starting number
        // Jika last code adalah angka 4-digit, +1. Jika tidak ada data atau bukan angka, mulai dari 1.
        if ($last && ctype_digit($last) && strlen($last) <= 4) {
            $next = (int) $last + 1;
        } else {
            $next = 1;
        }

        // Cari angka yang belum dipakai (handle gap jika ada duplikat/skip)
        $maxAttempts = 9999;
        $attempts    = 0;

        while ($attempts < $maxAttempts) {
            $candidate = str_pad($next, 4, '0', STR_PAD_LEFT);

            if (!Cluster::where('code', $candidate)->exists()) {
                return $candidate;
            }

            $next++;
            $attempts++;
        }

        // Fallback — seharusnya tidak pernah tercapai
        return str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * --------------------------------------------------------------------------
     * DISPLAY LISTING
     * --------------------------------------------------------------------------
     */
    public function index()
    {
        $clusters = Cluster::with(['group', 'user'])
            ->latest()
            ->get();

        $groups = ClusterGroup::orderBy('name')->get();

        $summary = [
            'total'    => $clusters->count(),
            'active'   => $clusters->where('status', 'ACTIVE')->count(),
            'inactive' => $clusters->where('status', 'INACTIVE')->count(),
        ];

        return view('cluster.index', compact('clusters', 'groups', 'summary'));
    }

    /**
     * --------------------------------------------------------------------------
     * AJAX — Get next auto code (for modal)
     * GET /cluster/next-code
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
     * AJAX — Get cluster data for edit modal
     * GET /cluster/{id}/edit-data
     * --------------------------------------------------------------------------
     */
    public function getEditData($id)
    {
        $cluster = Cluster::findOrFail($id);

        return response()->json([
            'id'       => $cluster->id,
            'code'     => $cluster->code,
            'name'     => $cluster->name,
            'group_id' => $cluster->group_id,
            'status'   => $cluster->status,
        ]);
    }

    /**
     * --------------------------------------------------------------------------
     * SHOW CREATE FORM
     * --------------------------------------------------------------------------
     */
    public function create()
    {
        $groups = ClusterGroup::orderBy('name')->get();

        // Generate next code dan kirim ke view
        $nextCode = $this->generateNextCode();

        return view('cluster.create', compact('groups', 'nextCode'));
    }

    /**
     * --------------------------------------------------------------------------
     * STORE DATA
     * --------------------------------------------------------------------------
     */
    public function store(Request $request)
    {
        /*
        |----------------------------------------------------------------------
        | Re-generate code di server saat store untuk mencegah race condition.
        | Code dari form diabaikan — selalu generate ulang yang fresh.
        |----------------------------------------------------------------------
        */
        $code = $this->generateNextCode();

        $request->validate([
            'name'     => 'required|string|max:255',
            'group_id' => 'nullable|exists:cluster_groups,id',
            'status'   => 'required|in:ACTIVE,INACTIVE',
        ]);

        Cluster::create([
            'code'     => $code,
            'name'     => strtoupper(trim($request->name)),
            'group_id' => $request->group_id,
            'status'   => $request->status,
            'input_by' => Auth::id(),
        ]);

        return redirect('/cluster')
            ->with('success', 'Project berhasil ditambahkan dengan kode ' . $code);
    }

    /**
     * --------------------------------------------------------------------------
     * SHOW DETAIL
     * --------------------------------------------------------------------------
     */
    public function show($id)
    {
        $cluster = Cluster::with(['group', 'user'])->findOrFail($id);

        return view('cluster.show', compact('cluster'));
    }

    /**
     * --------------------------------------------------------------------------
     * SHOW EDIT FORM
     * --------------------------------------------------------------------------
     */
    public function edit($id)
    {
        $cluster = Cluster::findOrFail($id);
        $groups  = ClusterGroup::orderBy('name')->get();

        return view('cluster.edit', compact('cluster', 'groups'));
    }

    /**
     * --------------------------------------------------------------------------
     * UPDATE DATA
     * --------------------------------------------------------------------------
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            // Code tidak boleh diubah saat edit — dikecualikan dari validasi
            'name'     => 'required|string|max:255',
            'group_id' => 'nullable|exists:cluster_groups,id',
            'status'   => 'required|in:ACTIVE,INACTIVE',
        ]);

        $cluster = Cluster::findOrFail($id);

        $cluster->update([
            // Code sengaja tidak diupdate — immutable setelah dibuat
            'name'     => strtoupper(trim($request->name)),
            'group_id' => $request->group_id,
            'status'   => $request->status,
        ]);

        return redirect('/cluster')
            ->with('success', 'Project berhasil diupdate');
    }

    /**
     * --------------------------------------------------------------------------
     * DELETE DATA
     * --------------------------------------------------------------------------
     */
    public function destroy($id)
    {
        $cluster = Cluster::findOrFail($id);

        if ($cluster->details()->exists()) {
            return back()->with('error', 'Cluster tidak bisa dihapus karena sudah memiliki milestone/project detail.');
        }
        // withTrashed(): BudgetRequest memakai SoftDeletes, tapi FK
        // budget_request.cluster_id ON DELETE CASCADE bekerja di level MySQL dan
        // tidak mengenal soft delete. Tanpa withTrashed() baris yang "ada di
        // recycle bin" tidak terlihat oleh penjaga ini, penghapusan lolos, dan
        // MySQL menghapusnya permanen beserta seluruh log statusnya.
        if (\App\Models\BudgetRequest::withTrashed()->where('cluster_id', $cluster->id)->exists()) {
            return back()->with('error', 'Cluster tidak bisa dihapus karena sudah dipakai pada Cost Request.');
        }

        $cluster->delete();

        return redirect('/cluster')
            ->with('success', 'Project berhasil dihapus');
    }
}
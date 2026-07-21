<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\ClusterGroup;
use App\Models\Cluster;

class ClusterGroupController extends Controller
{
    /**
     * DISPLAY LIST
     */
    public function index()
    {
        $groups = ClusterGroup::with('user')
            ->latest()
            ->get();

        return view('groups.index', compact(
            'groups'
        ));
    }

    /**
     * CREATE PAGE
     */
    public function create()
    {
        return view('groups.create');
    }

    /**
     * STORE DATA
     */
    public function store(Request $request)
    {
        $request->validate([

            'code' => 'required|unique:cluster_groups,code',

            'name' => 'required',

        ]);

        ClusterGroup::create([

            'code' => strtoupper($request->code),

            'name' => strtoupper($request->name),

            'input_by' => Auth::id(),

        ]);

        return redirect('/groups')
            ->with('success', 'Group berhasil ditambahkan');
    }

    /**
     * SHOW DETAIL
     */
    public function show($id)
    {
        /*
        |--------------------------------------------------------------------------
        | BUSINESS GROUP
        |--------------------------------------------------------------------------
        */

        $group = ClusterGroup::with([

            'user'

        ])->findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | CLUSTERS
        |--------------------------------------------------------------------------
        */

        $clusters = Cluster::where(

                'group_id',
                $group->id

            )
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('groups.show', compact(

            'group',
            'clusters'

        ));
    }

    /**
     * EDIT PAGE
     */
    public function edit($id)
    {
        $group = ClusterGroup::findOrFail($id);

        return view('groups.edit', compact(
            'group'
        ));
    }

    /**
     * UPDATE DATA
     */
    public function update(Request $request, $id)
    {
        $request->validate([

            'code' => 'required|unique:cluster_groups,code,' . $id,

            'name' => 'required',

        ]);

        $group = ClusterGroup::findOrFail($id);

        $group->update([

            'code' => strtoupper($request->code),

            'name' => strtoupper($request->name),

        ]);

        return redirect('/groups')
            ->with('success', 'Group berhasil diupdate');
    }

    /**
     * DELETE DATA
     */
    public function destroy($id)
    {
        $group = ClusterGroup::findOrFail($id);

        $group->delete();

        return redirect('/groups')
            ->with('success', 'Group berhasil dihapus');
    }

    public function getEditData($id)
    {
        $group = ClusterGroup::findOrFail($id);
 
        return response()->json([
            'id'     => $group->id,
            'code'   => $group->code,
            'name'   => $group->name,
            'status' => $group->status,
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\AccessGroup;
use App\Models\Project;
use App\Models\CostCenter;

class AccessGroupController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $accessGroups = AccessGroup::with([

            'details.project'

        ])
        ->latest()
        ->get();

        return view('accessgroup.index', compact(

            'accessGroups'

        ));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $projects = Project::query()

            ->select('id', 'name', 'code', 'po_number', 'done_by')

            ->orderBy('name')

            ->get();

        // Hanya cost center OVERHEAD yang boleh ditugaskan lewat access group.
        // (Payroll bersifat rahasia & di-scope terpisah lewat role.)
        $costCenters = CostCenter::where('type', 'OVERHEAD')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('accessgroup.create', compact(

            'projects',
            'costCenters'

        ));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'name' => [

                'required',
                'max:255',
                'unique:access_groups,name'

            ],

            'project_ids' => [

                'nullable',
                'array'

            ],

            'project_ids.*' => [

                'distinct',
                'exists:projects,id'

            ],

            'cost_center_ids' => [

                'nullable',
                'array'

            ],

            'cost_center_ids.*' => [

                'distinct',
                'exists:cost_centers,id'

            ]

        ], [], [
            'project_ids'     => 'project access',
            'cost_center_ids' => 'cost center access',
        ]);

        // Minimal salah satu sumber akses harus dipilih
        $hasProject    = !empty($validated['project_ids']);
        $hasCostCenter = !empty($validated['cost_center_ids']);

        if (!$hasProject && !$hasCostCenter) {
            return back()->withInput()->withErrors([
                'project_ids' => 'Pilih minimal satu project atau cost center.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | DB TRANSACTION
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use ($validated) {

            /*
            |--------------------------------------------------------------------------
            | CREATE ACCESS GROUP
            |--------------------------------------------------------------------------
            */

            $accessGroup = AccessGroup::create([

                'name' => strtoupper(
                    trim($validated['name'])
                )

            ]);

            /*
            |--------------------------------------------------------------------------
            | PREPARE DETAILS (project + cost center)
            |--------------------------------------------------------------------------
            */

            $details = [];

            foreach (collect($validated['project_ids'] ?? [])->unique() as $projectId) {
                $details[] = [
                    'project_id'     => (int) $projectId,
                    'cost_center_id' => null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            foreach (collect($validated['cost_center_ids'] ?? [])->unique() as $ccId) {
                $details[] = [
                    'project_id'     => null,
                    'cost_center_id' => (int) $ccId,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | INSERT DETAILS
            |--------------------------------------------------------------------------
            */

            $accessGroup->details()
                ->createMany($details);

        });

        /*
        |--------------------------------------------------------------------------
        | REDIRECT
        |--------------------------------------------------------------------------
        */

        return redirect('/accessgroup')
            ->with(

                'success',
                'Access group berhasil dibuat'

            );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $accessGroup = AccessGroup::with([

            'details.project',
            'users'

        ])->findOrFail($id);

        return view('accessgroup.show', compact(

            'accessGroup'

        ));
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        /*
        |--------------------------------------------------------------------------
        | ACCESS GROUP
        |--------------------------------------------------------------------------
        */

        $accessGroup = AccessGroup::with([

            'details.project'

        ])->findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | PROJECTS
        |--------------------------------------------------------------------------
        */

        $projects = Project::query()

            ->select('id', 'name', 'code', 'po_number', 'done_by')

            ->orderBy('name')

            ->get();

        $costCenters = CostCenter::where('type', 'OVERHEAD')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        /*
        |--------------------------------------------------------------------------
        | SELECTED PROJECTS & COST CENTERS
        |--------------------------------------------------------------------------
        */

        $selectedProjects = $accessGroup->details

            ->pluck('project_id')

            ->filter()

            ->map(fn ($id) => (int) $id)

            ->unique()

            ->values()

            ->toArray();

        $selectedCostCenters = $accessGroup->details

            ->pluck('cost_center_id')

            ->filter()

            ->map(fn ($id) => (int) $id)

            ->unique()

            ->values()

            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('accessgroup.edit', compact(

            'accessGroup',
            'projects',
            'costCenters',
            'selectedProjects',
            'selectedCostCenters'

        ));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        $id
    ) {

        /*
        |--------------------------------------------------------------------------
        | FIND ACCESS GROUP
        |--------------------------------------------------------------------------
        */

        $accessGroup = AccessGroup::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'name' => [

                'required',
                'max:255',
                'unique:access_groups,name,' . $accessGroup->id

            ],

            'project_ids' => [

                'nullable',
                'array'

            ],

            'project_ids.*' => [

                'distinct',
                'exists:projects,id'

            ],

            'cost_center_ids' => [

                'nullable',
                'array'

            ],

            'cost_center_ids.*' => [

                'distinct',
                'exists:cost_centers,id'

            ]

        ]);

        // Minimal salah satu sumber akses
        if (empty($validated['project_ids']) && empty($validated['cost_center_ids'])) {
            return back()->withInput()->withErrors([
                'project_ids' => 'Pilih minimal satu project atau cost center.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | DB TRANSACTION
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (

            $validated,
            $accessGroup

        ) {

            /*
            |--------------------------------------------------------------------------
            | UPDATE ACCESS GROUP
            |--------------------------------------------------------------------------
            */

            $accessGroup->update([

                'name' => strtoupper(
                    trim($validated['name'])
                )

            ]);

            /*
            |--------------------------------------------------------------------------
            | DELETE OLD DETAILS
            |--------------------------------------------------------------------------
            */

            $accessGroup->details()
                ->delete();

            /*
            |--------------------------------------------------------------------------
            | PREPARE NEW DETAILS (project + cost center)
            |--------------------------------------------------------------------------
            */

            $details = [];

            foreach (collect($validated['project_ids'] ?? [])->unique() as $projectId) {
                $details[] = [
                    'project_id'     => (int) $projectId,
                    'cost_center_id' => null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            foreach (collect($validated['cost_center_ids'] ?? [])->unique() as $ccId) {
                $details[] = [
                    'project_id'     => null,
                    'cost_center_id' => (int) $ccId,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | INSERT NEW DETAILS
            |--------------------------------------------------------------------------
            */

            $accessGroup->details()
                ->createMany($details);

        });

        /*
        |--------------------------------------------------------------------------
        | REDIRECT
        |--------------------------------------------------------------------------
        */

        return redirect('/accessgroup')
            ->with(

                'success',
                'Access group berhasil diupdate'

            );
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        /*
        |--------------------------------------------------------------------------
        | FIND ACCESS GROUP
        |--------------------------------------------------------------------------
        */

        $accessGroup = AccessGroup::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | DB TRANSACTION
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (

            $accessGroup

        ) {

            /*
            |--------------------------------------------------------------------------
            | DELETE DETAILS
            |--------------------------------------------------------------------------
            */

            $accessGroup->details()
                ->delete();

            /*
            |--------------------------------------------------------------------------
            | DELETE ACCESS GROUP
            |--------------------------------------------------------------------------
            */

            $accessGroup->delete();

        });

        /*
        |--------------------------------------------------------------------------
        | REDIRECT
        |--------------------------------------------------------------------------
        */

        return redirect('/accessgroup')
            ->with(

                'success',
                'Access group berhasil dihapus'

            );
    }

    public function showJson($id)
    {
        $accessGroup = AccessGroup::with([
            'details.project',
            'users.role',
        ])->findOrFail($id);
 
        return response()->json([
            'id'            => $accessGroup->id,
            'name'          => $accessGroup->name,
            'project_count' => $accessGroup->details->count(),
            'user_count'    => $accessGroup->users->count(),
            'created_at'    => $accessGroup->created_at->format('d M Y H:i'),
 
            'projects' => $accessGroup->details->map(fn($d) => [
                'name' => $d->project->name ?? '-',
                'id'   => $d->project_id,
            ])->values(),
 
            'users' => $accessGroup->users->map(fn($u) => [
                'name'  => $u->name,
                'email' => $u->email,
                'role'  => $u->role->name ?? '-',
            ])->values(),
        ]);
    }
}
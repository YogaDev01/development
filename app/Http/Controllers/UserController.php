<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use App\Models\User;
use App\Models\Role;
use App\Models\AccessGroup;

class UserController extends Controller
{
    /**
     * --------------------------------------------------------------------------
     * DISPLAY LIST
     * --------------------------------------------------------------------------
     */
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        $users = User::with([

            'role',
            'accessGroup'

        ])
        ->latest()
        ->get();

        /*
        |--------------------------------------------------------------------------
        | ROLES
        |--------------------------------------------------------------------------
        */

        $roles = Role::orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | ACCESS GROUPS
        |--------------------------------------------------------------------------
        */

        $accessGroups = AccessGroup::orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('user.index', compact(

            'users',
            'roles',
            'accessGroups'

        ));
    }

    /**
     * --------------------------------------------------------------------------
     * CREATE PAGE
     * --------------------------------------------------------------------------
     */
    public function create()
    {
        /*
        |--------------------------------------------------------------------------
        | ROLES
        |--------------------------------------------------------------------------
        */

        $roles = Role::orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | ACCESS GROUPS
        |--------------------------------------------------------------------------
        */

        $accessGroups = AccessGroup::orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('user.create', compact(

            'roles',
            'accessGroups'

        ));
    }

    /**
     * --------------------------------------------------------------------------
     * STORE DATA
     * --------------------------------------------------------------------------
     */
    public function store(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $request->validate([

            'name' => 'required',

            'username' => 'required|unique:users,username',

            'email' => 'required|email|unique:users,email',

            'password' => 'required|min:6',

            'role_id' => 'required|exists:roles,id',

            'access_group_id' => 'required|exists:access_groups,id',

            'status' => 'required|in:ACTIVE,INACTIVE',

        ]);

        /*
        |--------------------------------------------------------------------------
        | CREATE USER
        |--------------------------------------------------------------------------
        */

        User::create([

            'name' => strtoupper($request->name),

            'username' => strtolower($request->username),

            'email' => strtolower($request->email),

            'password' => Hash::make($request->password),

            'role_id' => $request->role_id,

            'access_group_id' => $request->access_group_id,

            'status' => $request->status,

        ]);

        /*
        |--------------------------------------------------------------------------
        | REDIRECT
        |--------------------------------------------------------------------------
        */

        return redirect('/user')
            ->with('success', 'User berhasil ditambahkan');
    }

    /**
     * --------------------------------------------------------------------------
     * SHOW DETAIL
     * --------------------------------------------------------------------------
     */
    public function show($id)
    {
        /*
        |--------------------------------------------------------------------------
        | USER
        |--------------------------------------------------------------------------
        */

        $user = User::with([

            'role',
            'accessGroup'

        ])->findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('user.show', compact(

            'user'

        ));
    }

    /**
     * --------------------------------------------------------------------------
     * EDIT PAGE
     * --------------------------------------------------------------------------
     */
    public function edit($id)
    {
        /*
        |--------------------------------------------------------------------------
        | USER
        |--------------------------------------------------------------------------
        */

        $user = User::with('accessGroup')
            ->findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | ROLES
        |--------------------------------------------------------------------------
        */

        $roles = Role::orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | ACCESS GROUPS
        |--------------------------------------------------------------------------
        */

        $accessGroups = AccessGroup::orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('user.edit', compact(

            'user',
            'roles',
            'accessGroups'

        ));
    }

    /**
     * --------------------------------------------------------------------------
     * UPDATE DATA
     * --------------------------------------------------------------------------
     */
    public function update(Request $request, $id)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $request->validate([

            'name' => 'required',

            'username' => 'required|unique:users,username,' . $id,

            'email' => 'required|email|unique:users,email,' . $id,

            'role_id' => 'required|exists:roles,id',

            'access_group_id' => 'required|exists:access_groups,id',

            'status' => 'required|in:ACTIVE,INACTIVE',

        ]);

        /*
        |--------------------------------------------------------------------------
        | USER
        |--------------------------------------------------------------------------
        */

        $user = User::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | DATA
        |--------------------------------------------------------------------------
        */

        $data = [

            'name' => strtoupper($request->name),

            'username' => strtolower($request->username),

            'email' => strtolower($request->email),

            'role_id' => $request->role_id,

            'access_group_id' => $request->access_group_id,

            'status' => $request->status,

        ];

        /*
        |--------------------------------------------------------------------------
        | UPDATE PASSWORD (OPTIONAL)
        |--------------------------------------------------------------------------
        */

        if ($request->filled('password')) {

            $request->validate([

                'password' => 'min:6',

            ]);

            $data['password'] = Hash::make(

                $request->password

            );

        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE USER
        |--------------------------------------------------------------------------
        */

        $user->update($data);

        /*
        |--------------------------------------------------------------------------
        | REDIRECT
        |--------------------------------------------------------------------------
        */

        return redirect('/user')
            ->with('success', 'User berhasil diupdate');
    }

    /**
     * --------------------------------------------------------------------------
     * DELETE DATA
     * --------------------------------------------------------------------------
     */
    public function destroy($id)
    {
        /*
        |--------------------------------------------------------------------------
        | USER
        |--------------------------------------------------------------------------
        */

        $user = User::findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | DELETE USER
        |--------------------------------------------------------------------------
        */

        $user->delete();

        /*
        |--------------------------------------------------------------------------
        | REDIRECT
        |--------------------------------------------------------------------------
        */

        return redirect('/user')
            ->with('success', 'User berhasil dihapus');
    }

     public function showJson($id)
    {
        $user = User::with([
            'role',
            'accessGroup.details.project',
        ])->findOrFail($id);
 
        $projects = [];
        if ($user->accessGroup && $user->accessGroup->details) {
            foreach ($user->accessGroup->details as $detail) {
                if ($detail->project) {
                    $projects[] = [
                        'name' => $detail->project->name,
                        'code' => $detail->project->code ?? null,
                    ];
                }
            }
        }
 
        return response()->json([
            'id'            => $user->id,
            'name'          => $user->name,
            'username'      => $user->username,
            'email'         => $user->email,
            'role'          => $user->role->name ?? null,
            'access_group'  => $user->accessGroup->name ?? null,
            'status'        => $user->status,
            'project_count' => count($projects),
            'projects'      => $projects,
            'created_at'    => $user->created_at->format('d M Y'),
            'created_at_full' => $user->created_at->format('d M Y H:i'),
        ]);
    }
}
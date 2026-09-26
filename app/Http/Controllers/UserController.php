<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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

        $summary = [
            'total'    => $users->count(),
            'active'   => $users->where('status', 'ACTIVE')->count(),
            'inactive' => $users->where('status', 'INACTIVE')->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('user.index', compact(

            'users',
            'roles',
            'accessGroups',
            'summary'

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

            'phone' => 'nullable|string|max:20',

            /*
            | Kebijakan password: min 10 karakter, wajib huruf DAN angka.
            |
            | Sebelumnya 'min:6' saja, sehingga "123456" lolos untuk akun yang
            | bisa menyetujui pembayaran. Batas laju login memperlambat serangan
            | daring, tapi tidak menolong sama sekali terhadap password yang
            | memang mudah ditebak pada percobaan pertama.
            |
            | Password yang SUDAH ada tidak dipaksa berubah — aturan ini hanya
            | berlaku untuk yang dibuat/diganti setelah ini.
            */
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],

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

            'phone' => $request->phone,

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

            'phone' => 'nullable|string|max:20',

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

            'phone' => $request->phone,

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

                // Sama dengan aturan saat pembuatan akun (lihat store()).
                'password' => ['confirmed', Password::min(10)->letters()->numbers()],

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
        | PROTEKSI DELETE — user masih dipakai di data transaksi
        |--------------------------------------------------------------------------
        */

        if (\App\Models\Project::where('manager_id', $user->id)->exists()) {
            return redirect('/user')
                ->with('error', 'User tidak dapat dihapus karena masih menjadi Project Manager pada PO. Nonaktifkan saja.');
        }

        // withTrashed(): BudgetRequest memakai SoftDeletes, tapi FK
        // budget_request.applicant_id ON DELETE CASCADE bekerja di level MySQL dan
        // tidak mengenal soft delete. Tanpa withTrashed() baris yang "ada di
        // recycle bin" tidak terlihat oleh penjaga ini, penghapusan lolos, dan
        // MySQL menghapusnya permanen beserta seluruh log statusnya.
        if (\App\Models\BudgetRequest::withTrashed()->where('applicant_id', $user->id)->exists()) {
            return redirect('/user')
                ->with('error', 'User tidak dapat dihapus karena sudah memiliki Cost Request. Nonaktifkan saja.');
        }

        /*
        |--------------------------------------------------------------------------
        | Tiga FK lain yang juga ON DELETE CASCADE ke users
        |--------------------------------------------------------------------------
        | Dua penjaga di atas menutup Project & Cost Request, tapi users masih
        | dirujuk cascade oleh tiga tabel lain:
        |
        |   daily_transactions.created_by   -> batch pembayaran Treasury
        |   feedback_submissions.user_id    -> riwayat kritik/saran ke CEO
        |   team_members.user_id            -> keanggotaan Team
        |
        | Yang paling berat konsekuensinya daily_transactions: tabel itu TIDAK
        | memakai SoftDeletes, dan daily_transaction_items ikut cascade di
        | belakangnya. Menghapus satu user berarti batch pembayaran yang sudah
        | APPROVED/PAID lenyap permanen beserta seluruh itemnya - di level
        | MySQL, tanpa lewat Eloquent, tanpa recycle bin, tanpa jejak. Setelah
        | itu rekonsiliasi kas tidak lagi bisa dibuktikan.
        |
        | Migrasi 2026_09_26_000002 mengubah created_by menjadi RESTRICT supaya
        | database menolak sendiri; penjaga di sini yang memberi pesan yang
        | bisa dimengerti alih-alih error constraint mentah.
        */
        if (\App\Models\DailyTransaction::where('created_by', $user->id)->exists()) {
            return redirect('/user')
                ->with('error', 'User tidak dapat dihapus karena sudah pernah membuat batch Treasury. Nonaktifkan saja.');
        }

        if (\App\Models\FeedbackSubmission::where('user_id', $user->id)->exists()) {
            return redirect('/user')
                ->with('error', 'User tidak dapat dihapus karena memiliki riwayat General Feedback. Nonaktifkan saja.');
        }

        if (\App\Models\TeamMember::where('user_id', $user->id)->exists()) {
            return redirect('/user')
                ->with('error', 'User tidak dapat dihapus karena masih tercatat sebagai anggota Team. Keluarkan dari Team lebih dulu, atau nonaktifkan saja.');
        }

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
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Department;
use App\Models\User;
use App\Models\UserActivityLog;

class TeamController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        /*
        | M-02 — filter, pencarian, dan pagination dibangun dari SATU query
        | dasar. Pencarian sengaja menembus relasi (leader, department, dan
        | nama anggota), karena yang dicari orang biasanya nama orangnya, bukan
        | nama team-nya.
        */
        $baseQuery = Team::with(['department:id,name', 'leader:id,username,name', 'activeMembers.user:id,username,name'])
            ->withCount('activeMembers')
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhereHas('department', fn ($r) => $r->where('name', 'like', $like))
                    ->orWhereHas('leader', fn ($r) => $r->where('username', 'like', $like)->orWhere('name', 'like', $like))
                    ->orWhereHas('activeMembers.user', fn ($r) => $r->where('username', 'like', $like)->orWhere('name', 'like', $like)));
            });

        // Ringkasan dihitung dari himpunan yang SAMA dengan daftar (sebelum
        // dipotong halaman), bukan dari query lain — supaya angka kartu dan
        // isi tabel tidak pernah bercerita berbeda.
        $scoped = (clone $baseQuery)->get();

        $summary = [
            'total'   => $scoped->count(),
            'active'  => $scoped->where('status', 'ACTIVE')->count(),
            'members' => $scoped->sum('active_members_count'),
        ];

        $teams = $baseQuery->orderBy('name')->paginate(15)->withQueryString();

        $departments = Department::where('status', 'ACTIVE')->orderBy('name')->get();

        $filters = [
            'department_id' => $request->query('department_id'),
            'status'        => $request->query('status'),
            'q'             => $search,
        ];

        return view('team.index', compact('teams', 'summary', 'departments', 'filters'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $departments = Department::where('status', 'ACTIVE')->orderBy('name')->get();
        $users = User::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'username']);

        return view('team.create', compact('departments', 'users'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $this->validateTeam($request);

        DB::transaction(function () use ($validated) {
            $team = Team::create([
                'code'          => Team::generateNextCode(),
                'name'          => strtoupper(trim($validated['name'])),
                'department_id' => $validated['department_id'] ?? null,
                'leader_id'     => $validated['leader_id'],
                'status'        => 'ACTIVE',
            ]);

            $this->syncMembers($team, $validated['members']);

            UserActivityLog::record('master_data', 'create', 'Menambah Team ' . $team->name, $team);
        });

        return redirect('/team')->with('success', 'Team berhasil dibuat.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $team = Team::with(['department', 'leader', 'members.user'])->findOrFail($id);
        $orgChart = $team->orgChart();

        return view('team.show', compact('team', 'orgChart'));
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $team = Team::with(['members.user'])->findOrFail($id);
        $this->authorizeEdit($team);

        $departments = Department::where('status', 'ACTIVE')->orderBy('name')->get();
        $users = User::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'username']);

        return view('team.edit', compact('team', 'departments', 'users'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $team = Team::findOrFail($id);
        $this->authorizeEdit($team);

        $validated = $this->validateTeam($request, $team->id);

        DB::transaction(function () use ($team, $validated) {
            $team->update([
                'name'          => strtoupper(trim($validated['name'])),
                'department_id' => $validated['department_id'] ?? null,
                'leader_id'     => $validated['leader_id'],
                'status'        => $validated['status'],
            ]);

            $this->syncMembers($team, $validated['members']);
        });

        UserActivityLog::record('master_data', 'update', 'Mengubah Team ' . $team->name, $team);

        return redirect('/team')->with('success', 'Team berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $team = Team::withCount('accessGroupLinks')->findOrFail($id);

        if ($team->accessGroupLinks()->exists()) {
            return redirect('/team')->with(
                'error',
                'Team tidak dapat dihapus karena masih dipakai sebagai Team Access pada Access Group. Nonaktifkan saja.'
            );
        }

        $teamName = $team->name;
        $team->delete();

        UserActivityLog::record('master_data', 'delete', 'Menghapus Team ' . $teamName);

        return redirect('/team')->with('success', 'Team berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Edit/Update Team boleh diakses superadmin, atau Team Leader untuk
     * team-nya sendiri (leader_id). Create/Delete tetap superadmin only
     * (digate di route, lihat routes/web.php).
     */
    private function authorizeEdit(Team $team): void
    {
        $user = Auth::user();

        if (strtolower($user->role->name) === 'superadmin') {
            return;
        }

        if ((int) $team->leader_id === (int) $user->id) {
            return;
        }

        abort(403, 'Hanya Superadmin atau Leader team ini yang boleh mengubah data.');
    }

    private function validateTeam(Request $request, ?int $teamId = null): array
    {
        $rules = [
            'name'                  => 'required|string|max:255',
            'department_id'         => 'nullable|exists:departments,id',
            'leader_id'              => 'required|exists:users,id',
            'members'                => 'required|array|min:1',
            'members.*.user_id'      => 'required|exists:users,id|distinct',
            'members.*.position'     => 'nullable|string|max:100',
            'members.*.level'        => 'required|integer|min:0',
            'members.*.ca_limit'     => 'nullable',
            'members.*.join_at'      => 'nullable|date',
            'members.*.left_at'      => 'nullable|date',
            'members.*.status'       => 'required|in:ACTIVE,INACTIVE',
        ];

        if ($teamId) {
            $rules['status'] = 'required|in:ACTIVE,INACTIVE';
        }

        $validated = $request->validate($rules);

        // Leader wajib juga tercatat sebagai member ACTIVE — jangan simpan
        // data leader terpisah dari membership (lihat dokumen Major A).
        $leaderIsActiveMember = collect($validated['members'])
            ->contains(fn ($m) => (int) $m['user_id'] === (int) $validated['leader_id'] && $m['status'] === 'ACTIVE');

        if (!$leaderIsActiveMember) {
            abort(422, 'Leader wajib tercatat sebagai member ACTIVE pada tabel Team Members.');
        }

        // Satu user maksimal satu membership ACTIVE — cegah duplikat.
        $activeUserIds = collect($validated['members'])
            ->where('status', 'ACTIVE')
            ->pluck('user_id');
        if ($activeUserIds->count() !== $activeUserIds->unique()->count()) {
            abort(422, 'Satu user tidak boleh punya lebih dari satu membership ACTIVE dalam team yang sama.');
        }

        return $validated;
    }

    /** Delete-all-then-recreate dibungkus DB transaction (pola child-row edit existing). */
    private function syncMembers(Team $team, array $members): void
    {
        $team->members()->delete();

        foreach ($members as $m) {
            $team->members()->create([
                'user_id'  => $m['user_id'],
                'position' => $m['position'] ?? null,
                'level'    => (int) $m['level'],
                'ca_limit' => isset($m['ca_limit']) ? (int) preg_replace('/\D/', '', (string) $m['ca_limit']) : null,
                'join_at'  => $m['join_at'] ?? null,
                'left_at'  => $m['left_at'] ?? null,
                'status'   => $m['status'],
            ]);
        }
    }
}

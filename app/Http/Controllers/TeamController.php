<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Models\AccessGroup;
use App\Models\AccessGroupDetail;
use App\Models\Customer;
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
        $relasi = ['department:id,name', 'leader:id,username,name', 'activeMembers.user:id,username,name'];
        if (Team::supportsCustomer()) {
            $relasi[] = 'customer:id,name';
        }
        if (Team::supportsStructure()) {
            $relasi[] = 'parent:id,name';
        }

        $baseQuery = Team::with($relasi)
            ->withCount(['activeMembers', 'accessGroupLinks'])
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

        // Tab "Organisasi" (struktur keseluruhan) dirender di halaman yang sama.
        return view('team.index', compact('teams', 'summary', 'departments', 'filters') + $this->organizationData());
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
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $parentOptions = $this->parentOptions();
        $accessGroups = $this->accessGroupOptions();
        $selectedAccessGroupIds = [];
        $canEditAccess = $this->canEditAccess();

        return view('team.create', compact('departments', 'users', 'customers', 'parentOptions', 'accessGroups', 'selectedAccessGroupIds', 'canEditAccess'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $this->validateTeam($request);

        $team = DB::transaction(function () use ($validated) {
            $team = new Team($this->customerAttribute($validated) + $this->parentAttribute($validated) + [
                'code'          => Team::generateNextCode(),
                'name'          => strtoupper(trim($validated['name'])),
                'department_id' => $validated['department_id'] ?? null,
                'leader_id'     => $validated['leader_id'],
                'status'        => 'ACTIVE',
            ]);

            $this->assertParentValid($team, $team->parent_team_id);
            $team->save();

            $memberEvents = $this->syncMembers($team, $validated['members']);

            $team->addLog('created', [
                'name'      => $team->name,
                'leader'    => $this->usernameOf($team->leader_id),
                'parent'    => $this->teamNameOf($team->parent_team_id),
                'members'   => collect($memberEvents)->pluck('user')->implode(', '),
            ]);

            if ($this->canEditAccess() && ! empty($validated['access_group_sync'])) {
                $this->syncAccessGroups($team, $validated['access_group_ids'] ?? []);
            }

            UserActivityLog::record('master_data', 'create', 'Menambah Team ' . $team->name, $team);

            return $team;
        });

        return redirect('/team')->with('success', 'Team ' . $team->name . ' berhasil dibuat.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $relasi = [
            'department', 'leader', 'members.user',
            'accessGroupLinks.accessGroup' => fn ($q) => $q->withCount(['details as po_count' => fn ($d) => $d->whereNotNull('project_id')]),
        ];
        if (Team::supportsStructure()) {
            $relasi[] = 'parent:id,code,name,status';
            $relasi[] = 'children:id,code,name,status,parent_team_id';
            $relasi['logs'] = fn ($q) => $q->limit(200);
        }

        $team = Team::with($relasi)->findOrFail($id);
        $orgChart = $team->orgChart();

        return view('team.show', compact('team', 'orgChart'));
    }

    /*
    |--------------------------------------------------------------------------
    | STRUKTUR ORGANISASI
    |--------------------------------------------------------------------------
    | Pohon team ACTIVE dari teams.parent_team_id + leader + anggota ACTIVE.
    | HANYA tampilan: relasi induk/leader tidak memberi akses data apa pun
    | (blueprint 2026-11-06, K3). Dibangun dari SATU set query (team + relasi
    | di-eager-load), lalu disusun jadi pohon di PHP — tabel team kecil.
    */

    /**
     * Halaman lama /team/structure kini menjadi tab "Organisasi" di daftar
     * Team. URL lama tetap berlaku dan diarahkan ke tab tersebut.
     */
    public function structure()
    {
        return redirect()->to(url('/team') . '#organisasi');
    }

    /**
     * Data tab "Organisasi" (pohon struktur + ringkasan) untuk team.index.
     *
     * @return array{roots:\Illuminate\Support\Collection, children:\Illuminate\Support\Collection, ledBy:\Illuminate\Support\Collection, people:array, meId:int, myTeams:\Illuminate\Support\Collection, orgSummary:array}
     */
    private function organizationData(): array
    {
        $relasi = [
            'department:id,name',
            'leader:id,name,username,avatar',
            'activeMembers.user:id,name,username,avatar,role_id',
            'activeMembers.user.role:id,name',
        ];
        if (Team::supportsStructure()) {
            $relasi[] = 'parent:id,name,status';
        }

        $teams = Team::where('status', 'ACTIVE')->with($relasi)->orderBy('name')->get();
        $meId  = (int) Auth::id();

        // Bagan organisasi berbasis orang (lihat buildOrgChart()).
        $orgChart = $this->buildOrgChart($teams, $meId);

        // Profil ringkas per user untuk drawer (tanpa data kontak).
        $people = [];
        foreach ($teams as $t) {
            foreach ($t->activeMembers as $m) {
                if (! $m->user) {
                    continue;
                }
                $uid = (int) $m->user_id;
                $people[$uid] ??= [
                    'id'       => $uid,
                    'name'     => $m->user->name,
                    'username' => strtoupper((string) $m->user->username),
                    'avatar'   => $m->user->avatar_url,
                    'role'     => $m->user->role->name ?? '-',
                    'teams'    => [],
                ];
                $people[$uid]['teams'][] = [
                    'id'         => $t->id,
                    'code'       => $t->code,
                    'name'       => $t->name,
                    'department' => $t->department->name ?? null,
                    'position'   => $m->position,
                    'level'      => (int) $m->level,
                    'is_primary' => (bool) ($m->is_primary ?? false),
                    'is_leader'  => (int) $t->leader_id === $uid,
                ];
            }
        }

        $myTeams = collect($people[$meId]['teams'] ?? []);

        /*
        | Ringkasan organisasi (team ACTIVE): angka keseluruhan + per
        | department. Orang dihitung UNIK — satu orang di beberapa team
        | dihitung sekali per department dan sekali di total.
        */
        $perDept = $teams->groupBy(fn ($t) => $t->department->name ?? 'Tanpa Department')
            ->map(function ($g, $nama) {
                $orang = $g->flatMap(fn ($t) => $t->activeMembers->pluck('user_id'))->map(fn ($i) => (int) $i)->unique();

                return [
                    'name'    => $nama,
                    'teams'   => $g->count(),
                    'people'  => $orang->count(),
                    'leaders' => $g->pluck('leader_id')->filter()->unique()->count(),
                ];
            })
            ->sortByDesc('people')
            ->values();

        $orgSummary = [
            'teams'       => $teams->count(),
            'people'      => count($people),
            'leaders'     => $teams->pluck('leader_id')->filter()->unique()->count(),
            'multi_team'  => collect($people)->filter(fn ($p) => count($p['teams']) > 1)->count(),
            'departments' => $perDept,
            'max_people'  => max(1, (int) $perDept->max('people')),
        ];

        return compact('orgChart', 'people', 'meId', 'myTeams', 'orgSummary');
    }

    /**
     * Bagan organisasi berbasis ORANG (atas → bawah), dari team ACTIVE.
     *
     * Aturan penyambungan team (hanya tampilan — tidak memberi akses, K3):
     *  1. Leader team T yang juga ANGGOTA (bukan leader) team B → team T
     *     digantung di bawah kartu orang itu di team B. Contoh: Ketut
     *     memimpin B, Yoga anggota B & leader A → Ketut → Yoga → anggota A.
     *     Bila ia anggota beberapa team, dipilih membership primary, lalu
     *     level terkecil, lalu nama team.
     *  2. Bila tidak ada (1), Induk Team (parent_team_id) → team T beserta
     *     kartu leader-nya digantung di bawah leader team induk.
     *  3. Selain itu team menjadi akar. Leader yang memimpin beberapa team
     *     akar tampil SEKALI dengan seluruh team-nya di bawahnya.
     * Penyambungan yang membentuk lingkaran diputus (team menjadi akar).
     *
     * Di dalam satu team, anggota disusun per baris menurut level
     * (team_members.level, kecil = lebih tinggi). Orang yang tetap muncul
     * di lebih dari satu tempat (anggota biasa di dua team) ditandai dan
     * dihubungkan garis putus-putus di layar (data-user sama).
     *
     * @return array<int, array>  kartu akar (rekursif: card → blocks → rows → card)
     */
    private function buildOrgChart(\Illuminate\Support\Collection $teams, int $meId): array
    {
        $byId = $teams->keyBy('id');

        // Membership non-leader per user: [user_id => [[team, member], ...]]
        $memberOf = [];
        foreach ($teams as $t) {
            foreach ($t->activeMembers as $m) {
                if ($m->user && (int) $m->user_id !== (int) $t->leader_id) {
                    $memberOf[(int) $m->user_id][] = ['team' => $t, 'member' => $m];
                }
            }
        }

        // 1) Tentukan tempat tiap team: member / parent / root.
        $attach = [];
        foreach ($teams as $t) {
            $leaderId = (int) $t->leader_id;
            $kandidat = collect($memberOf[$leaderId] ?? [])->sortBy(fn ($x) => [
                ($x['member']->is_primary ?? false) ? 0 : 1, (int) $x['member']->level, $x['team']->name,
            ])->first();

            if ($leaderId && $kandidat) {
                $attach[$t->id] = ['type' => 'member', 'team' => (int) $kandidat['team']->id];
            } elseif ($t->parent_team_id && $byId->has($t->parent_team_id)) {
                $attach[$t->id] = ['type' => 'parent', 'team' => (int) $t->parent_team_id];
            } else {
                $attach[$t->id] = ['type' => 'root'];
            }
        }

        // Putus lingkaran: telusuri rantai penyambungan; bila kembali ke
        // dirinya, team itu dijadikan akar.
        foreach ($teams as $t) {
            $lihat = [];
            $cur = $t->id;
            while (($attach[$cur]['type'] ?? 'root') !== 'root') {
                if (isset($lihat[$cur])) {
                    $attach[$t->id] = ['type' => 'root'];
                    break;
                }
                $lihat[$cur] = true;
                $cur = $attach[$cur]['team'];
            }
        }

        $leaderRow = fn (Team $t) => $t->activeMembers->first(fn ($m) => (int) $m->user_id === (int) $t->leader_id);
        $dipakai   = []; // team yang sudah dirender (penjaga data ganda)
        $kunci     = 0;

        $card = function ($user, Team $team, string $role, ?string $position, int $level, array $children) use ($meId, &$kunci) {
            return [
                'key'       => 'oc' . (++$kunci),
                'user_id'   => (int) $user->id,
                'name'      => $user->name,
                'avatar'    => $user->avatar_url,
                'role'      => $role,               // 'leader' | 'member'
                'position'  => $position,
                'level'     => $level,
                'team_id'   => (int) $team->id,
                'team_code' => $team->code,
                'team_name' => $team->name,
                'is_me'     => (int) $user->id === $meId,
                'children'  => $children,           // blok di bawah kartu ini
            ];
        };

        // Blok "anggota team T" (tanpa kartu leader) + team yang diinduk ke T.
        $blocksOf = null;
        $tiers = function (Team $t) use (&$blocksOf, $card, $attach, $teams) {
            $rows = $t->activeMembers
                ->filter(fn ($m) => $m->user && (int) $m->user_id !== (int) $t->leader_id)
                ->sortBy(fn ($m) => [(int) $m->level, $m->user->name])
                ->groupBy(fn ($m) => (int) $m->level)
                ->map(fn ($g) => $g->map(function ($m) use ($t, &$blocksOf, $card, $attach, $teams) {
                    // Team yang dipimpin orang ini & disambung ke membership-nya di T.
                    $anak = [];
                    foreach ($teams as $x) {
                        if ((int) $x->leader_id === (int) $m->user_id
                            && ($attach[$x->id]['type'] ?? null) === 'member'
                            && $attach[$x->id]['team'] === (int) $t->id) {
                            $anak = array_merge($anak, $blocksOf($x));
                        }
                    }

                    return $card($m->user, $t, 'member', $m->position, (int) $m->level, $anak);
                })->values()->all())
                ->values()
                ->all();

            return [
                'kind'  => 'tiers',
                'team'  => [
                    'id' => (int) $t->id, 'code' => $t->code, 'name' => $t->name,
                    'department' => $t->department->name ?? null,
                    'count' => $t->activeMembers->count(),
                ],
                'rows'  => $rows,
            ];
        };

        // Semua blok milik team T: barisan anggotanya + team yang diinduk ke T
        // (lengkap dengan kartu leader masing-masing).
        $blocksOf = function (Team $t) use (&$blocksOf, &$dipakai, $tiers, $card, $attach, $teams, $leaderRow) {
            if (isset($dipakai[$t->id])) {
                return [];
            }
            $dipakai[$t->id] = true;

            $blok = [$tiers($t)];
            foreach ($teams as $x) {
                if (($attach[$x->id]['type'] ?? null) === 'parent' && $attach[$x->id]['team'] === (int) $t->id
                    && ! isset($dipakai[$x->id]) && $x->leader) {
                    $lr = $leaderRow($x);
                    $blok[] = [
                        'kind' => 'leader',
                        'card' => $card($x->leader, $x, 'leader', $lr?->position, (int) ($lr?->level ?? 0), $blocksOf($x)),
                    ];
                }
            }

            return $blok;
        };

        // 3) Akar: dikelompokkan per leader.
        $akar = [];
        foreach ($teams->filter(fn ($t) => ($attach[$t->id]['type'] ?? null) === 'root')->groupBy(fn ($t) => (int) $t->leader_id) as $leaderId => $grup) {
            $pertama = $grup->first();
            $blok = [];
            foreach ($grup as $t) {
                $blok = array_merge($blok, $blocksOf($t));
            }
            if (! $pertama->leader) {
                // Team tanpa leader: tampilkan barisannya saja sebagai akar tersendiri.
                $akar[] = ['orphan' => true, 'blocks' => $blok];
                continue;
            }
            $lr = $leaderRow($pertama);
            $akar[] = $card($pertama->leader, $pertama, 'leader', $lr?->position, (int) ($lr?->level ?? 0), $blok);
        }

        return $akar;
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        // Form hanya memuat membership ACTIVE. Membership INACTIVE adalah
        // riwayat — tetap tersimpan dan tampil di halaman Detail, tidak
        // ikut disunting di sini.
        $team = Team::with(['activeMembers.user'])->findOrFail($id);
        $this->authorizeEdit($team);

        $departments = Department::where('status', 'ACTIVE')->orderBy('name')->get();
        $users = User::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'username']);
        $customers = Customer::orderBy('name')->get(['id', 'name']);
        $parentOptions = $this->parentOptions($team->id, Team::supportsStructure() ? $team->parent_team_id : null);
        $canEditParent = $this->canEditStructure();
        $accessGroups = $this->accessGroupOptions();
        $selectedAccessGroupIds = $team->accessGroupLinks()->pluck('access_group_id')->map(fn ($i) => (int) $i)->unique()->values()->all();
        $canEditAccess = $this->canEditAccess();
        $isSuperadmin = Auth::user()->hasRole('superadmin');
        // Keanggotaan team ber-Akses PO hanya diubah superadmin (review HIGH-2).
        $membersLocked = ! $isSuperadmin && ! empty($selectedAccessGroupIds);

        return view('team.edit', compact('team', 'departments', 'users', 'customers', 'parentOptions', 'canEditParent', 'accessGroups', 'selectedAccessGroupIds', 'canEditAccess', 'isSuperadmin', 'membersLocked'));
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

        $team = DB::transaction(function () use ($team, $validated) {
            // Kunci baris team: dua penyunting bersamaan tidak boleh saling
            // menimpa validasi leader/anggota/induk.
            $team = Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            $before = $team->only(['name', 'department_id', 'customer_id', 'parent_team_id', 'leader_id', 'status']);

            // Induk team hanya boleh diubah superadmin; pengubah lain (Leader)
            // tidak mengirim/mengubah nilainya.
            $parent = $this->canEditStructure() ? $this->parentAttribute($validated) : [];

            $superadmin = Auth::user()->hasRole('superadmin');

            $team->fill($this->customerAttribute($validated) + $parent + [
                'name'          => strtoupper(trim($validated['name'])),
                'department_id' => $validated['department_id'] ?? null,
                'leader_id'     => $validated['leader_id'],
                // Status team ikut menentukan akses (team INACTIVE tidak
                // memberi akses), jadi hanya superadmin yang boleh mengubahnya
                // — dari sini maupun lewat PATCH team/{id}/status (K9).
                'status'        => $superadmin ? $validated['status'] : $team->status,
            ]);

            $this->assertParentValid($team, $team->parent_team_id);

            $aktifLama = $team->activeMembers()->pluck('user_id')->map(fn ($i) => (int) $i)->unique()->sort()->values();
            $aktifBaru = collect($validated['members'])->where('status', 'ACTIVE')
                ->pluck('user_id')->map(fn ($i) => (int) $i)->unique()->sort()->values();

            // Team INACTIVE tidak menerima anggota baru (termasuk saat sedang
            // dinonaktifkan pada penyimpanan yang sama).
            if ($team->status !== 'ACTIVE' && $aktifBaru->diff($aktifLama)->isNotEmpty()) {
                throw ValidationException::withMessages(['members' => 'Team INACTIVE tidak dapat menerima anggota baru.']);
            }

            /*
            | Keanggotaan team yang memiliki Akses PO (Access Group) = otorisasi
            | data: menambah anggota berarti memberi akses PO/cost center Access
            | Group itu. Keputusan pemilik 2026-11-06 (review HIGH-2): hanya
            | superadmin. Leader tetap boleh mengubah posisi/level/primary.
            */
            if (! $superadmin && $aktifBaru->all() !== $aktifLama->all() && $team->accessGroupLinks()->exists()) {
                throw ValidationException::withMessages([
                    'members' => 'Team ini memiliki Akses PO (Access Group). Menambah atau mengeluarkan anggota hanya dapat dilakukan Superadmin.',
                ]);
            }

            $team->save();

            $this->logTeamChanges($team, $before);

            foreach ($this->syncMembers($team, $validated['members']) as $event) {
                $team->addLog($event['action'], ['user' => $event['user']] + ($event['fields'] ? ['fields' => $event['fields']] : []));
            }

            // Akses PO team hanya diubah superadmin — Leader yang menyunting
            // team-nya sendiri tidak boleh memberi dirinya akses baru.
            if ($this->canEditAccess() && ! empty($validated['access_group_sync'])) {
                $this->syncAccessGroups($team, $validated['access_group_ids'] ?? []);
            }

            return $team;
        });

        UserActivityLog::record('master_data', 'update', 'Mengubah Team ' . $team->name, $team);

        return redirect('/team')->with('success', 'Team berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | AKTIF / NONAKTIF (pengganti hapus)
    |--------------------------------------------------------------------------
    | Team tidak pernah dihapus permanen (keputusan 2026-11-06): menghapusnya
    | ikut menghapus seluruh riwayat keanggotaan (FK cascade team_members).
    | Nonaktif = team berhenti dipakai, anggotanya tetap tercatat.
    */

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:ACTIVE,INACTIVE',
            'notes'  => 'nullable|string|max:500',
        ]);

        $team = DB::transaction(function () use ($id, $validated) {
            $team = Team::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($team->status === $validated['status']) {
                return $team;
            }

            $from = $team->status;
            $team->update(['status' => $validated['status']]);
            $team->addLog(
                $validated['status'] === 'ACTIVE' ? 'activated' : 'deactivated',
                ['status' => [$from, $team->status]],
                $validated['notes'] ?? null
            );

            return $team;
        });

        UserActivityLog::record(
            'master_data',
            'update',
            ($team->status === 'ACTIVE' ? 'Mengaktifkan' : 'Menonaktifkan') . ' Team ' . $team->name,
            $team,
            $validated['notes'] ?? null
        );

        $pesan = $team->status === 'ACTIVE'
            ? 'Team ' . $team->name . ' diaktifkan.'
            : 'Team ' . $team->name . ' dinonaktifkan. Anggota & riwayatnya tetap tersimpan.';

        return redirect('/team')->with('success', $pesan);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Edit/Update Team boleh diakses superadmin, atau Team Leader untuk
     * team-nya sendiri (leader_id). Create/Aktif-Nonaktif tetap superadmin
     * only (digate di route, lihat routes/web.php).
     */
    private function authorizeEdit(Team $team): void
    {
        $user = Auth::user();

        // hasRole() = role efektif (ikut View As), sama dengan route role:
        // dan pemeriksaan superadmin lain di controller ini — preview View As
        // berperilaku persis seperti role yang ditiru.
        if ($user->hasRole('superadmin')) {
            return;
        }

        if ((int) $team->leader_id === (int) $user->id) {
            return;
        }

        abort(403, 'Hanya Superadmin atau Leader team ini yang boleh mengubah data.');
    }

    /** Induk team (struktur organisasi) hanya diatur superadmin. */
    private function canEditStructure(): bool
    {
        return Team::supportsStructure() && Auth::user()->hasRole('superadmin');
    }

    /**
     * Akses PO (Access Group) team = otorisasi data, jadi hanya superadmin —
     * sama dengan pengelolaan Access Group & akses individu user.
     */
    private function canEditAccess(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    /** Pilihan Access Group untuk bagian Akses PO, dengan jumlah PO-nya. */
    private function accessGroupOptions()
    {
        return AccessGroup::orderBy('name')
            ->withCount(['details as po_count' => fn ($q) => $q->whereNotNull('project_id')])
            ->get(['id', 'name']);
    }

    /**
     * Sinkron Access Group yang dipasang ke team ini (baris
     * access_group_details dengan team_id). Hanya menambah/menghapus baris
     * milik team ini — baris project/cost center Access Group tidak disentuh,
     * pola sama dengan ProjectController::syncAccessGroups(). Perubahannya
     * dicatat di team_logs (tabel yang sama dengan halaman Access Group,
     * sehingga kedua pintu selalu menampilkan keadaan yang sama).
     */
    private function syncAccessGroups(Team $team, array $selectedIds): void
    {
        $selected = collect($selectedIds)->map(fn ($i) => (int) $i)->unique()->values();
        $current  = $team->accessGroupLinks()->lockForUpdate()->pluck('access_group_id')->map(fn ($i) => (int) $i)->unique();

        $toAdd    = $selected->diff($current)->values();
        $toRemove = $current->diff($selected)->values();

        if ($toAdd->isEmpty() && $toRemove->isEmpty()) {
            return;
        }

        foreach ($toAdd as $agId) {
            AccessGroupDetail::create([
                'access_group_id' => $agId,
                'project_id'      => null,
                'cost_center_id'  => null,
                'team_id'         => $team->id,
            ]);
        }

        if ($toRemove->isNotEmpty()) {
            $team->accessGroupLinks()->whereIn('access_group_id', $toRemove)->delete();
        }

        $nama = AccessGroup::whereIn('id', $toAdd->concat($toRemove))->pluck('name', 'id');
        $team->addLog('access', array_filter([
            'ditambah' => $toAdd->map(fn ($id) => $nama[$id] ?? '#' . $id)->implode(', '),
            'dicabut'  => $toRemove->map(fn ($id) => $nama[$id] ?? '#' . $id)->implode(', '),
        ]));
    }

    /**
     * Kandidat induk: team ACTIVE selain team ini sendiri, DITAMBAH induk
     * yang sedang terpasang walau sudah INACTIVE — supaya menyimpan form
     * tidak diam-diam melepas induknya. Siklus dicek di server.
     */
    private function parentOptions(?int $exceptId = null, ?int $currentParentId = null)
    {
        if (! Team::supportsStructure()) {
            return collect();
        }

        return Team::where(fn ($q) => $q->where('status', 'ACTIVE')
                ->when($currentParentId, fn ($w) => $w->orWhereKey($currentParentId)))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);
    }

    /**
     * ['customer_id' => …] hanya bila kolomnya sudah ada (migrasi
     * 2026_10_19_000300). Basis data yang belum dimigrasi menyimpan team
     * seperti sebelumnya, tanpa galat "Unknown column".
     */
    private function customerAttribute(array $validated): array
    {
        return Team::supportsCustomer()
            ? ['customer_id' => $validated['customer_id'] ?? null]
            : [];
    }

    /** ['parent_team_id' => …] hanya bila migrasi struktur sudah jalan. */
    private function parentAttribute(array $validated): array
    {
        return Team::supportsStructure()
            ? ['parent_team_id' => isset($validated['parent_team_id']) ? (int) $validated['parent_team_id'] : null]
            : [];
    }

    /** Induk tidak boleh diri sendiri, team INACTIVE, atau membentuk siklus. */
    private function assertParentValid(Team $team, $parentId): void
    {
        if (! Team::supportsStructure() || $parentId === null) {
            return;
        }

        if ($team->id && (int) $parentId === (int) $team->id) {
            throw ValidationException::withMessages(['parent_team_id' => 'Team tidak boleh menjadi induk dirinya sendiri.']);
        }

        // Hanya dicek bila induknya baru dipilih: induk lama yang kemudian
        // dinonaktifkan tidak memblokir penyuntingan data lain team ini.
        if ($team->isDirty('parent_team_id') || ! $team->exists) {
            $aktif = Team::whereKey($parentId)->where('status', 'ACTIVE')->exists();
            if (! $aktif) {
                throw ValidationException::withMessages(['parent_team_id' => 'Induk team harus team yang ACTIVE.']);
            }
        }

        if ($team->id && $team->wouldCreateCycle((int) $parentId)) {
            throw ValidationException::withMessages(['parent_team_id' => 'Induk team ini membentuk struktur melingkar (team menjadi induk bagi induknya sendiri).']);
        }
    }

    private function validateTeam(Request $request, ?int $teamId = null): array
    {
        $rules = [
            'name'                  => 'required|string|max:255',
            'department_id'         => 'nullable|exists:departments,id',
            // Customer yang dilayani team — dipakai Trouble Ticket (opsional).
            'customer_id'           => 'nullable|exists:customers,id',
            'parent_team_id'        => 'nullable|integer|exists:teams,id',
            // Akses PO (Access Group) team — hanya dipakai bila superadmin.
            // Penanda bahwa form memang memuat bagian Akses PO: tanpa ini,
            // permintaan yang tidak membawa access_group_ids TIDAK boleh
            // diartikan "cabut semua akses".
            'access_group_sync'     => 'nullable|boolean',
            'access_group_ids'      => 'nullable|array',
            'access_group_ids.*'    => 'integer|distinct|exists:access_groups,id',
            // Leader harus akun ACTIVE.
            'leader_id'             => ['required', Rule::exists('users', 'id')->where('status', 'ACTIVE')],
            'members'               => 'required|array|min:1',
            'members.*.id'          => 'nullable|integer',
            'members.*.user_id'     => 'required|exists:users,id|distinct',
            'members.*.position'    => 'nullable|string|max:100',
            'members.*.level'       => 'required|integer|min:0',
            'members.*.is_primary'  => 'nullable|boolean',
            'members.*.status'      => 'required|in:ACTIVE,INACTIVE',
        ];

        if ($teamId) {
            $rules['status'] = 'required|in:ACTIVE,INACTIVE';
        }

        $validated = $request->validate($rules, [
            'leader_id.exists' => 'Leader harus user yang berstatus ACTIVE.',
        ]);

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

        // Membership ACTIVE hanya untuk akun ACTIVE.
        $nonaktif = User::whereIn('id', $activeUserIds)->where('status', '!=', 'ACTIVE')->pluck('username');
        if ($nonaktif->isNotEmpty()) {
            $daftar = $nonaktif->map(fn ($u) => strtoupper($u))->implode(', ');
            $team = $teamId ? Team::find($teamId) : null;
            $terkunci = $team && ! Auth::user()->hasRole('superadmin') && $team->accessGroupLinks()->exists();

            throw ValidationException::withMessages([
                'members' => 'Akun berikut sudah tidak ACTIVE sehingga tidak bisa tetap menjadi anggota aktif: ' . $daftar . '. '
                    . ($terkunci
                        ? 'Team ini memiliki Akses PO — minta Superadmin mengeluarkannya dari team, lalu simpan kembali.'
                        : 'Keluarkan dari team (hapus baris atau pilih INACTIVE), lalu simpan kembali.'),
            ]);
        }

        return $validated;
    }

    /**
     * Upsert anggota — baris membership TIDAK PERNAH dihapus.
     *
     * Versi lama menghapus semua anggota lalu membuat ulang setiap kali form
     * disimpan, sehingga id dan riwayat keanggotaan hilang. Sekarang:
     *  - baris yang membawa id milik team ini diupdate di tempat;
     *  - baris tanpa id memakai membership ACTIVE user yang sama bila ada,
     *    kalau tidak dibuat baris baru (bergabung lagi = baris baru);
     *  - membership ACTIVE yang tidak dikirim lagi = keluar dari team →
     *    status INACTIVE (barisnya tetap ada sebagai riwayat).
     *
     * @return array<int, array{action:string, user:string, fields:array}>  kejadian untuk team_logs
     */
    private function syncMembers(Team $team, array $members): array
    {
        $structure = Team::supportsStructure();
        $existing  = $team->members()->lockForUpdate()->get()->keyBy('id');
        $claimed   = [];
        $events    = [];

        $usernames = User::whereIn('id', collect($members)->pluck('user_id')->merge($existing->pluck('user_id'))->unique())
            ->pluck('username', 'id');
        $nama = fn ($userId) => strtoupper($usernames[$userId] ?? ('#' . $userId));

        foreach ($members as $m) {
            $userId  = (int) $m['user_id'];
            $status  = $m['status'];
            $payload = [
                'user_id'  => $userId,
                'position' => $m['position'] ?? null,
                'level'    => (int) $m['level'],
                'status'   => $status,
            ];
            if ($structure) {
                // Primary hanya bermakna untuk membership ACTIVE.
                $payload['is_primary'] = $status === 'ACTIVE' && ! empty($m['is_primary']);
            }

            /*
            | id hanya diakui bila menunjuk membership ACTIVE milik user yang
            | SAMA. Mengganti user pada sebuah baris tidak boleh menimpa riwayat
            | user lama (baris lama → INACTIVE, user baru → baris baru), dan id
            | baris INACTIVE tidak menghidupkannya kembali (bergabung lagi =
            | baris baru).
            */
            $rowId  = (int) ($m['id'] ?? 0);
            $member = ($rowId && $existing->has($rowId) && ! isset($claimed[$rowId])
                && (int) $existing->get($rowId)->user_id === $userId
                && $existing->get($rowId)->status === 'ACTIVE')
                ? $existing->get($rowId) : null;

            if (! $member) {
                $member = $existing->first(fn ($e) => ! isset($claimed[$e->id])
                    && (int) $e->user_id === $userId
                    && $e->status === 'ACTIVE');
            }

            if ($member) {
                $claimed[$member->id] = true;
                $member->fill($payload);
                $fields = [];
                foreach ($member->getDirty() as $field => $baru) {
                    $fields[$field] = [$member->getOriginal($field), $baru];
                }
                if ($fields) {
                    $member->save();
                    $events[] = [
                        'action' => $status === 'INACTIVE' && isset($fields['status']) ? 'member_removed' : 'member_updated',
                        'user'   => $nama($userId),
                        'fields' => $fields,
                    ];
                }
            } else {
                $member = $team->members()->create($payload);
                $claimed[$member->id] = true;
                $events[] = ['action' => 'member_added', 'user' => $nama($userId), 'fields' => []];
            }
        }

        // Membership ACTIVE yang tidak dikirim lagi = keluar dari team.
        foreach ($existing as $e) {
            if (isset($claimed[$e->id]) || $e->status !== 'ACTIVE') {
                continue;
            }
            $e->status = 'INACTIVE';
            if ($structure) {
                $e->is_primary = false;
            }
            $e->save();
            $events[] = ['action' => 'member_removed', 'user' => $nama((int) $e->user_id), 'fields' => ['status' => ['ACTIVE', 'INACTIVE']]];
        }

        if ($structure) {
            $this->assertSinglePrimary($team);
        }

        return $events;
    }

    /**
     * Maksimal SATU membership ACTIVE primary per user lintas seluruh team.
     * Dicek setelah anggota tersimpan (masih di dalam transaksi): baris
     * membership user terkait dikunci supaya dua penyunting team berbeda
     * tidak sama-sama lolos.
     */
    private function assertSinglePrimary(Team $team): void
    {
        $userIds = $team->members()
            ->where('status', 'ACTIVE')
            ->where('is_primary', true)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        $ganda = TeamMember::whereIn('user_id', $userIds)
            ->where('status', 'ACTIVE')
            ->where('is_primary', true)
            ->lockForUpdate()
            ->get(['user_id', 'team_id'])
            ->groupBy('user_id')
            ->filter(fn ($rows) => $rows->count() > 1)
            ->keys();

        if ($ganda->isNotEmpty()) {
            $nama = User::whereIn('id', $ganda)->pluck('username')->map(fn ($u) => strtoupper($u))->implode(', ');
            throw ValidationException::withMessages([
                'members' => 'User berikut sudah punya Team Primary di team lain: ' . $nama . '. Lepas primary di team lama lebih dulu.',
            ]);
        }
    }

    /** Catat perubahan data header team (leader, induk, status, data umum). */
    private function logTeamChanges(Team $team, array $before): void
    {
        $after = $team->only(array_keys($before));

        if ((int) $before['leader_id'] !== (int) $after['leader_id']) {
            $team->addLog('leader', ['leader' => [$this->usernameOf($before['leader_id']), $this->usernameOf($after['leader_id'])]]);
        }

        if (Team::supportsStructure() && (int) ($before['parent_team_id'] ?? 0) !== (int) ($after['parent_team_id'] ?? 0)) {
            $team->addLog('parent', ['parent' => [$this->teamNameOf($before['parent_team_id'] ?? null), $this->teamNameOf($after['parent_team_id'] ?? null)]]);
        }

        if ($before['status'] !== $after['status']) {
            $team->addLog($after['status'] === 'ACTIVE' ? 'activated' : 'deactivated', ['status' => [$before['status'], $after['status']]]);
        }

        $umum = [];
        foreach (['name', 'department_id', 'customer_id'] as $f) {
            if (array_key_exists($f, $before) && (string) $before[$f] !== (string) $after[$f]) {
                $umum[$f] = [$before[$f], $after[$f]];
            }
        }
        if (isset($umum['department_id'])) {
            $umum['department_id'] = array_map(fn ($id) => $id ? (Department::whereKey($id)->value('name') ?? $id) : null, $umum['department_id']);
        }
        if ($umum) {
            $team->addLog('updated', $umum);
        }
    }

    private function usernameOf($userId): ?string
    {
        return $userId ? strtoupper((string) User::whereKey($userId)->value('username')) : null;
    }

    private function teamNameOf($teamId): ?string
    {
        return $teamId ? Team::whereKey($teamId)->value('name') : null;
    }
}

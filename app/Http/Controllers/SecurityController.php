<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\Authorizer;
use App\Support\DataScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Konsol Keamanan — Fase 1 Security & Access Control
 * (docs/plans/2026-11-06-security-access-control.md).
 *
 * Khusus superadmin (route role:superadmin). Tiga tab:
 *   audit    : seluruh user_activity_logs (filter user/module/aksi/tanggal/IP)
 *   login    : riwayat autentikasi (module auth: login, logout, gagal, diblokir, cabut sesi)
 *   sessions : sesi aktif dari tabel sessions + cabut sesi / logout semua perangkat
 *
 * Halaman ini TIDAK mengubah alur aplikasi lain. Aksi tulisnya hanya mencabut
 * sesi; data bisnis tidak disentuh.
 */
class SecurityController extends Controller
{
    private const TABS = ['audit', 'login', 'sessions', 'shadow', 'preview', 'findings'];

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'audit';

        $summary  = $this->summary();
        $users    = User::orderBy('name')->get(['id', 'name', 'username']);
        $filters  = $request->only(['user_id', 'module', 'action', 'date_from', 'date_to', 'q', 'ip']);
        $logs     = null;
        $sessions = collect();
        $shadow   = null;
        $preview  = null;
        $findings = [];

        if ($tab === 'login') {
            $filters['module'] = 'auth';
        }

        if ($tab === 'sessions') {
            $sessions = $this->activeSessions();
        } elseif ($tab === 'shadow') {
            $shadow = $this->shadowReport();
        } elseif ($tab === 'preview') {
            $preview = $this->accessPreview((int) $request->query('user_id'), (string) $request->query('permission'));
        } elseif ($tab === 'findings') {
            $findings = $this->findings();
        } else {
            $logs = UserActivityLog::feedAll($filters)->appends($filters + ['tab' => $tab]);
        }

        return view('security.index', [
            'tab'             => $tab,
            'summary'         => $summary,
            'users'           => $users,
            'filters'         => $filters,
            'logs'            => $logs,
            'sessions'        => $sessions,
            'shadow'          => $shadow,
            'preview'         => $preview,
            'findings'        => $findings,
            'sessionsEnabled' => $this->usesDatabaseSessions(),
            'currentHash'     => hash('sha256', $request->session()->getId()),
            'hasDeviceColumn' => UserActivityLog::supportsSecurityColumns(),
        ]);
    }

    /**
     * Cabut SATU sesi. Dikenali lewat sha256 id sesi — id mentah tidak pernah
     * dikirim ke peramban.
     *
     * remember_token user ikut dirotasi: tanpa itu perangkat yang memakai
     * "Ingat saya" langsung login kembali lewat cookie remember dan pencabutan
     * tidak berarti apa-apa. Efek sampingnya, perangkat lain user tersebut
     * yang memakai "Ingat saya" harus login ulang setelah sesinya habis —
     * sesi yang sedang berjalan di perangkat lain tidak diputus.
     */
    public function revokeSession(Request $request)
    {
        $data = $request->validate([
            'session_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        if (! $this->usesDatabaseSessions()) {
            return back()->with('error', 'Driver sesi bukan database — sesi tidak dapat dicabut dari aplikasi.');
        }

        if (hash_equals(hash('sha256', $request->session()->getId()), $data['session_hash'])) {
            return back()->with('error', 'Sesi yang sedang Anda pakai tidak bisa dicabut dari sini. Gunakan Logout.');
        }

        // Dibatasi ke sesi login yang masih berlaku (sama dengan daftar di tab
        // Sesi Aktif) supaya SHA2() tidak dihitung untuk seluruh sesi tamu.
        $row = $this->sessionTable()
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp())
            ->whereRaw('SHA2(id, 256) = ?', [$data['session_hash']])
            ->first(['id', 'user_id', 'ip_address', 'user_agent']);

        if (! $row) {
            return back()->with('error', 'Sesi tidak ditemukan atau sudah berakhir.');
        }

        $target = $row->user_id ? User::find($row->user_id) : null;

        DB::transaction(function () use ($row, $target) {
            $this->sessionTable()->where('id', $row->id)->delete();

            if ($target) {
                $this->rotateRememberToken($target);
            }
        });

        UserActivityLog::record(
            'auth',
            'revoke_session',
            'Mencabut satu sesi ' . ($target ? 'milik @' . $target->username : 'tanpa user'),
            $target,
            null,
            $target ? '@' . $target->username : null,
            [
                ['label' => 'IP', 'from' => $row->ip_address ?: '-', 'to' => '-'],
                ['label' => 'Perangkat', 'from' => Str::limit((string) $row->user_agent, 120) ?: '-', 'to' => '-'],
            ]
        );

        return back()->with('success', 'Sesi berhasil dicabut.');
    }

    /** Putus SEMUA sesi user lain + rotasi remember_token. */
    public function logoutAllDevices(Request $request, $id)
    {
        $target = User::findOrFail($id);

        if ((int) $target->id === (int) Auth::id()) {
            return back()->with('error', 'Tidak dapat memutus semua sesi akun Anda sendiri dari sini. Gunakan Logout.');
        }

        if (! $this->usesDatabaseSessions()) {
            return back()->with('error', 'Driver sesi bukan database — sesi tidak dapat dicabut dari aplikasi.');
        }

        $jumlah = DB::transaction(function () use ($target) {
            $n = $this->sessionTable()->where('user_id', $target->id)->delete();
            $this->rotateRememberToken($target);

            return $n;
        });

        UserActivityLog::record(
            'auth',
            'revoke_session',
            'Memutus semua sesi milik @' . $target->username . ' (' . $jumlah . ' sesi)',
            $target,
            null,
            '@' . $target->username
        );

        return back()->with('success', 'Semua sesi @' . $target->username . ' diputus (' . $jumlah . ' sesi).');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function usesDatabaseSessions(): bool
    {
        return config('session.driver') === 'database';
    }

    private function sessionTable()
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
    }

    /**
     * Lewat query builder: updated_at user tidak ikut berubah dan event model
     * tidak terpicu (rotasi token bukan perubahan data akun).
     */
    private function rotateRememberToken(User $user): void
    {
        DB::table('users')->where('id', $user->id)->update(['remember_token' => Str::random(60)]);
    }

    /** Sesi login yang belum kedaluwarsa, terbaru di atas. */
    private function activeSessions()
    {
        if (! $this->usesDatabaseSessions()) {
            return collect();
        }

        $batas = now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();

        $rows = $this->sessionTable()
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $batas)
            ->orderByDesc('last_activity')
            ->limit(500)
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);

        $users = User::with('role')->whereIn('id', $rows->pluck('user_id')->unique())->get()->keyBy('id');

        return $rows->map(fn ($r) => (object) [
            'hash'          => hash('sha256', $r->id),
            'user'          => $users->get($r->user_id),
            'ip_address'    => $r->ip_address,
            'user_agent'    => $r->user_agent,
            // Zona waktu eksplisit: createFromTimestamp() default UTC.
            'last_activity' => \Carbon\Carbon::createFromTimestamp($r->last_activity, config('app.timezone')),
        ]);
    }

    /**
     * Ringkasan mode shadow mesin permission (Fase 2): selisih 14 hari
     * terakhir dikelompokkan per permission + role + arah selisih.
     * Tabelnya bisa belum ada (migrasi belum dijalankan) — ditampilkan apa adanya.
     */
    private function shadowReport(): array
    {
        $siap = \Illuminate\Support\Facades\Schema::hasTable('permission_shadow_logs')
            && \Illuminate\Support\Facades\Schema::hasTable('role_permissions');

        $hasil = [
            'mode'        => (string) config('security.permission_mode', 'off'),
            'ready'       => $siap,
            'registered'  => count(\App\Support\Authorizer::registry()),
            'grant_rows'  => 0,
            'rows'        => collect(),
            'total_14d'   => 0,
        ];

        if (! $siap) {
            return $hasil;
        }

        $sejak = now()->subDays(14);

        $hasil['grant_rows'] = DB::table('role_permissions')->count();
        $hasil['total_14d']  = DB::table('permission_shadow_logs')->where('created_at', '>=', $sejak)->count();
        $hasil['rows']       = DB::table('permission_shadow_logs')
            ->where('created_at', '>=', $sejak)
            ->selectRaw('permission_key, role_name, legacy_allowed, engine_allowed, COUNT(*) as jumlah, COUNT(DISTINCT user_id) as user_unik, MAX(created_at) as terakhir')
            ->groupBy('permission_key', 'role_name', 'legacy_allowed', 'engine_allowed')
            ->orderByDesc('terakhir')
            ->limit(200)
            ->get();

        return $hasil;
    }

    /**
     * Access Preview (baca-saja): menelusuri satu user (+ opsional satu permission)
     * lapis demi lapis. Memakai role ASLI user (bukan View As) dan TIDAK mengubah apa pun.
     *
     * Keputusan yang berlaku saat ini tetap gerbang `role:` lama; mesin permission
     * ditampilkan sebagai pembanding (mode shadow), bukan penentu.
     */
    private function accessPreview(int $userId, string $permission): ?array
    {
        $user = $userId ? User::with('role.menuItems')->find($userId) : null;
        if (! $user) {
            return null;
        }

        $role     = $user->role;
        $roleName = $role ? strtolower(trim($role->name)) : null;
        $siap     = Schema::hasTable('role_permissions');
        $registry = Authorizer::registry();
        $dipegang = ($role && $siap) ? array_flip(Authorizer::keysForRole((int) $role->getKey())) : [];

        $out = [
            'user'   => $user,
            'role'   => $role,
            'ready'  => $siap,
            'menus'  => $role ? $role->menuItems->pluck('key')->all() : [],
            'groups' => ($role && $siap) ? Authorizer::groupStatusForRole((int) $role->getKey()) : null,
            'scopes' => $role ? DataScope::statusForRole((int) $role->getKey()) : null,
            'perm'   => null,
        ];

        if ($permission === '') {
            return $out;
        }

        $steps = [];
        $ok    = true;
        $add   = function (string $title, string $detail, bool $pass) use (&$steps, &$ok) {
            $steps[] = ['title' => $title, 'detail' => $detail, 'state' => ! $ok ? 'skip' : ($pass ? 'ok' : 'no')];
            if ($ok && ! $pass) {
                $ok = false;
            }
        };

        $add('Status akun', $user->status === 'ACTIVE' ? 'Akun aktif.' : 'Akun ' . $user->status . ' — semua akses ditolak.', $user->status === 'ACTIVE');
        $add('Role', $role ? 'Role: ' . $role->name . '.' : 'User belum punya role — ditolak (default deny).', (bool) $role);
        $add('Permission terdaftar', isset($registry[$permission]) ? 'Terdaftar: ' . $registry[$permission]['label'] . '.' : 'Permission tidak ada di registry — ditolak.', isset($registry[$permission]));

        $engine = isset($dipegang[$permission]);
        $add('Mesin permission (role_permissions)', $engine ? 'Role memegang permission ini.' : 'Role tidak memegang permission ini.', $engine);

        // Gerbang lama untuk route yang sama (pembanding parity).
        $legacy = null;
        $route  = Route::getRoutes()->getByName($permission);
        if ($route && $roleName) {
            foreach ($route->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'role:')) {
                    $legacy = in_array($roleName, array_map(fn ($r) => strtolower(trim($r)), explode(',', substr($m, 5))), true);
                    break;
                }
            }
        }

        $out['perm'] = [
            'key'      => $permission,
            'label'    => $registry[$permission]['label'] ?? $permission,
            'steps'    => $steps,
            'engine'   => $ok,
            'legacy'   => $legacy,
            'mismatch' => $legacy !== null && $user->status === 'ACTIVE' && $legacy !== $engine,
        ];

        return $out;
    }

    /** Temuan postur keamanan (baca-saja); tiap temuan menautkan ke user terkait. */
    private function findings(): array
    {
        $out = [];

        foreach (User::where('status', 'ACTIVE')->whereNull('role_id')->get(['id', 'name', 'username']) as $u) {
            $out[] = ['level' => 'warning', 'title' => 'User aktif tanpa role', 'detail' => $u->name . ' (@' . $u->username . ') tidak punya role — tidak bisa mengakses modul apa pun.', 'user_id' => $u->id];
        }

        $sa = User::where('status', 'ACTIVE')
            ->whereHas('role', fn ($q) => $q->whereRaw('LOWER(name) = ?', ['superadmin']))
            ->get(['id', 'name', 'username']);
        foreach ($sa as $u) {
            $out[] = ['level' => 'info', 'title' => 'Akun superadmin aktif', 'detail' => $u->name . ' (@' . $u->username . ') memegang akses penuh. Tinjau berkala.', 'user_id' => $u->id];
        }

        $lama = User::where('status', 'ACTIVE')
            ->where(fn ($q) => $q->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(90)))
            ->orderBy('name')->limit(50)->get(['id', 'name', 'username', 'last_login_at']);
        foreach ($lama as $u) {
            $out[] = ['level' => 'info', 'title' => 'Akun aktif tanpa login 90 hari', 'detail' => $u->name . ' (@' . $u->username . ') ' . ($u->last_login_at ? 'terakhir login ' . $u->last_login_at->format('d M Y') : 'belum tercatat login') . '.', 'user_id' => $u->id];
        }

        $urut = ['danger' => 0, 'warning' => 1, 'info' => 2];
        usort($out, fn ($a, $b) => $urut[$a['level']] <=> $urut[$b['level']]);

        return $out;
    }

    /** Angka ringkas di atas konsol. */
    private function summary(): array
    {
        $sejak24 = now()->subDay();

        $auth = UserActivityLog::where('module', 'auth')
            ->where('created_at', '>=', $sejak24)
            ->whereIn('action', ['login_failed', 'lockout'])
            ->selectRaw('action, COUNT(*) as jumlah')
            ->groupBy('action')
            ->pluck('jumlah', 'action');

        $aktif = 0;
        if ($this->usesDatabaseSessions()) {
            $aktif = $this->sessionTable()
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp())
                ->distinct()
                ->count('user_id');
        }

        return [
            'active_users'   => $aktif,
            'failed_24h'     => (int) ($auth['login_failed'] ?? 0),
            'lockout_24h'    => (int) ($auth['lockout'] ?? 0),
            'access_changes' => UserActivityLog::where('module', 'user_access')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    private const TABS = ['audit', 'login', 'sessions'];

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'audit';

        $summary  = $this->summary();
        $users    = User::orderBy('name')->get(['id', 'name', 'username']);
        $filters  = $request->only(['user_id', 'module', 'action', 'date_from', 'date_to', 'q', 'ip']);
        $logs     = null;
        $sessions = collect();

        if ($tab === 'login') {
            $filters['module'] = 'auth';
        }

        if ($tab === 'sessions') {
            $sessions = $this->activeSessions();
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

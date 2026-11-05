<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Role;
use App\Models\Team;
use App\Models\ProjectMonitoringLog;
use App\Models\RequestStatusLog;
use App\Models\UserActivityLog;

class ProfileController extends Controller
{
    private const LOG_LIMIT = 50;

    public function show(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $user->load('role', 'accessGroup');

        // PO yang bisa diakses: Access Group individu ∪ Access Group team
        // ACTIVE — sumber yang sama dengan daftar PO (User::accessibleProjectIds()).
        $accessibleProjects = \App\Models\Project::with('customer')
            ->whereIn('id', $user->accessibleProjectIds()->all() ?: [0])
            ->orderBy('name')
            ->get();

        $projectLogs = ProjectMonitoringLog::where('user_id', $user->id)
            ->with('project')
            ->latest()
            ->limit(self::LOG_LIMIT)
            ->get();

        $requestLogs = RequestStatusLog::where('acted_by', $user->id)
            ->with('budgetRequest')
            ->latest('created_at')
            ->limit(self::LOG_LIMIT)
            ->get();

        // Activity Log — audit trail lintas module, paginasi server-side.
        $activityFilters = $request->only(['module', 'action', 'date_from', 'date_to', 'q']);
        $activityLogs = UserActivityLog::feedFor($user->id, $activityFilters)
            ->appends($activityFilters);

        // Ini profil sendiri → selalu boleh melihat rincian perubahan nilai.
        $canSeeChanges = true;

        $team = $user->access_group_id
            ? User::where('access_group_id', $user->access_group_id)
                ->where('id', '!=', $user->id)
                ->with('role')
                ->orderBy('name')
                ->get()
            : collect();

        /*
        | TEAM — Organization Chart. Diambil dari Team tempat user ini jadi
        | member AKTIF (lintas access group), disusun per level lewat
        | Team::orgChart(). Satu user bisa ada di beberapa team.
        */
        $teamIds = $user->teamMemberships()->where('status', 'ACTIVE')->pluck('team_id');
        $myTeams = Team::whereIn('id', $teamIds)
            ->with(['department', 'leader', 'activeMembers.user'])
            ->orderBy('name')
            ->get();

        /*
        | RINGKASAN — total lintas data & log untuk kartu statistik di tab
        | Informasi. count() ringan dan halaman ini bukan jalur panas.
        */
        $summary = [
            'projects'    => $accessibleProjects->count(),
            'project_log' => ProjectMonitoringLog::where('user_id', $user->id)->count(),
            'request_log' => RequestStatusLog::where('acted_by', $user->id)->count(),
            'activity'    => $activityLogs->total(),
            'teams'       => $teamIds->count(),
            'unread'      => $user->unreadNotifications()->count(),
        ];

        // Keamanan akun — sesi login aktif user ini (tabel sessions di MySQL).
        $sessionInfo = [
            'active' => DB::table('sessions')->where('user_id', $user->id)->count(),
            'last'   => DB::table('sessions')->where('user_id', $user->id)->max('last_activity'),
        ];

        // Changelog aplikasi (sumber tunggal config/changelog.php).
        $changelog        = config('changelog.releases', []);
        $changelogCurrent = config('changelog.current');

        // View As — hanya untuk superadmin sungguhan.
        $viewAsRoles  = $user->isRealSuperadmin()
            ? Role::where('status', 'ACTIVE')
                ->whereRaw('LOWER(name) != ?', ['superadmin'])
                ->orderBy('name')
                ->get()
            : collect();
        $viewAsActive = session('view_as_role_id');

        // Ringkasan Todo & Summary Task dimuat LAZY (AJAX) — tidak dihitung di
        // sini karena worklist mahal. Tersedia untuk semua pengguna login.

        // Daftar aplikasi untuk dropdown "App Default" di Settings.
        $apps = config('apps.items', []);

        return view('profile.show', compact(
            'user', 'accessibleProjects', 'projectLogs', 'requestLogs', 'team',
            'activityLogs', 'activityFilters', 'canSeeChanges', 'myTeams', 'summary', 'sessionInfo',
            'changelog', 'changelogCurrent', 'viewAsRoles', 'viewAsActive', 'apps'
        ));
    }

    /**
     * Pemetaan key antrean (TodoListController::actionQueues) ke 3 modul tabel
     * yang ditampilkan di Profil: Cost Request, Budgeting, Purchase Order.
     * Tahap Treasury/Settlement bagian dari alur Cost Request; Invoice &
     * Collection bagian dari alur Purchase Order.
     */
    private const TODO_MODULE_MAP = [
        'Cost Request' => [
            'head', 'cost_control_checked', 'ceo', 'reject',
            'batch-create', 'batch-process', 'batch-pay', 'second-payment',
            'settlement', 'settle-check', 'settle-verify', 'settle-2nd', 'settle-close',
        ],
        'Budgeting' => [
            'bp-head', 'bp-verify', 'bp-ceo', 'bp-closing-due', 'bp-rejected',
        ],
        'Purchase Order' => [
            'po-submitted', 'po-verified', 'collection', 'invoice-verify', 'invoice-approve',
        ],
    ];

    /**
     * GET /profile/todo-summary — ringkasan worklist per-user (lazy, JSON),
     * dikelompokkan jadi 3 tabel modul. Memakai
     * TodoListController::actionQueues() sebagai satu sumber kebenaran, sengaja
     * TIDAK dipanggil saat render halaman profil (query berat).
     */
    public function todoSummary()
    {
        /** @var User $user */
        $user = Auth::user();

        $data   = app(TodoListController::class)->actionQueues($user);
        $byKey  = collect($data['queues'] ?? [])->keyBy('key');

        $tables = [];
        foreach (self::TODO_MODULE_MAP as $module => $keys) {
            $rows     = [];
            $subtotal = 0;
            foreach ($keys as $key) {
                $q = $byKey->get($key);
                if (!$q || ($q['count'] ?? 0) <= 0) {
                    continue;
                }
                $rows[] = [
                    'label' => $q['label'],
                    'count' => $q['count'],
                    'url'   => $q['url'] ?? null,
                ];
                $subtotal += $q['count'];
            }
            $tables[] = [
                'module'   => $module,
                'rows'     => $rows,
                'subtotal' => $subtotal,
            ];
        }

        return response()->json([
            'tables'         => $tables,
            'total'          => $data['total'] ?? 0,
            'completedToday' => $data['completedToday'] ?? 0,
            'gagal'          => $data['gagal'] ?? false,
        ]);
    }

    /**
     * GET /profile/todo-matrix — matrix Summary Task (agregat jumlah) sebagai
     * HTML partial, untuk modal di Profil. Auth-only (tersedia untuk semua
     * pengguna login) karena hanya berisi hitungan, bukan nilai uang/record;
     * halaman Todo List sendiri tetap digerbang menu:todo-list.
     */
    public function todoMatrix()
    {
        return app(TodoListController::class)->summaryPartial();
    }

    /**
     * POST /profile/notifications — simpan preferensi notifikasi.
     *
     * Aditif & aman: menyimpan struktur eksplisit true/false. Lihat
     * App\Models\User::wantsNotification() untuk pembacaannya.
     */
    public function updateNotifications(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'reminder_mode'     => 'nullable|in:fixed,interval',
            'reminder_interval' => 'nullable|integer|min:1|max:24',
            'reminder_hours'    => 'nullable|array',
            'reminder_hours.*'  => 'integer|min:0|max:23',
        ]);

        $hours = array_values(array_unique(array_map('intval', (array) $request->input('reminder_hours', []))));
        sort($hours);

        // Pertahankan kunci lain yang mungkin sudah ada (mis. 'categories' lama)
        // agar perubahan bersifat aditif.
        $prefs = is_array($user->notification_prefs) ? $user->notification_prefs : [];

        $prefs['wa_enabled']    = $request->boolean('wa_enabled');
        $prefs['todo_reminder'] = [
            'enabled'        => $request->boolean('reminder_enabled'),
            'mode'           => $request->input('reminder_mode') === 'interval' ? 'interval' : 'fixed',
            'hours'          => $hours ?: User::TODO_REMINDER_DEFAULTS['hours'],
            'interval_hours' => (int) ($request->input('reminder_interval') ?: User::TODO_REMINDER_DEFAULTS['interval_hours']),
            'whatsapp'       => $request->boolean('reminder_whatsapp'),
        ];

        $user->update(['notification_prefs' => $prefs]);

        UserActivityLog::record('profile', 'update', 'Memperbarui preferensi notifikasi');

        return back()->with('success', 'Preferensi notifikasi berhasil disimpan.');
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Password TIDAK lagi di sini — diganti lewat alur verifikasi token
        // email (requestPasswordToken / changePassword) agar ada bukti
        // kepemilikan email sebelum kredensial diubah.
        $appKeys = array_keys(config('apps.items', []));
        $request->validate([
            'name'        => 'required|string|max:255',
            'phone'       => 'nullable|string|max:30',
            'default_app' => ['nullable', 'in:' . implode(',', $appKeys)],
        ]);

        $user->update([
            'name'        => $request->name,
            'phone'       => $request->phone,
            'default_app' => $request->default_app ?: null,
        ]);

        UserActivityLog::record('profile', 'update', 'Memperbarui informasi profil');

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    /*
    |--------------------------------------------------------------------------
    | GANTI PASSWORD — verifikasi lewat kode yang dikirim ke email.
    |
    | Alur: user minta kode -> kode 6 digit dikirim ke email terdaftar &
    | disimpan (hash) di cache 15 menit -> user memasukkan kode + password
    | baru -> kode diverifikasi lalu password diperbarui. Email menjadi bukti
    | kepemilikan, menggantikan "password saat ini".
    |--------------------------------------------------------------------------
    */

    private const PWD_TOKEN_TTL = 900;          // 15 menit
    private function pwdCacheKey(int $userId): string
    {
        return "profile:pwd-token:{$userId}";
    }

    /** POST /profile/password/token — kirim kode verifikasi ke email user. */
    public function requestPasswordToken(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Maksimal 5 permintaan kode per 10 menit per user, mencegah spam email.
        $limiterKey = 'pwd-token:' . $user->id;
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            $sec = RateLimiter::availableIn($limiterKey);
            return response()->json([
                'ok'      => false,
                'message' => "Terlalu banyak permintaan kode. Coba lagi dalam {$sec} detik.",
            ], 429);
        }
        RateLimiter::hit($limiterKey, 600);

        $code = (string) random_int(100000, 999999);

        Cache::put($this->pwdCacheKey($user->id), [
            'hash'     => Hash::make($code),
            'attempts' => 0,
        ], self::PWD_TOKEN_TTL);

        try {
            Mail::raw(
                "Kode verifikasi ganti password TESYS Anda: {$code}\n\n"
                . "Kode berlaku 15 menit. Abaikan email ini bila Anda tidak meminta penggantian password.",
                function ($m) use ($user) {
                    $m->to($user->email)->subject('Kode Verifikasi Ganti Password - TESYS');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Gagal kirim kode ganti password', ['user_id' => $user->id, 'err' => $e->getMessage()]);
            Cache::forget($this->pwdCacheKey($user->id));
            return response()->json([
                'ok'      => false,
                'message' => 'Gagal mengirim email kode verifikasi. Hubungi Superadmin.',
            ], 500);
        }

        // Jangan bocorkan email lengkap; tampilkan tersamar.
        return response()->json([
            'ok'      => true,
            'message' => 'Kode verifikasi telah dikirim ke ' . $this->maskEmail($user->email) . '.',
        ]);
    }

    /** POST /profile/password — verifikasi kode lalu ganti password. */
    public function changePassword(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'code'     => 'required|string',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $cached = Cache::get($this->pwdCacheKey($user->id));
        if (!is_array($cached)) {
            throw ValidationException::withMessages([
                'code' => 'Kode kedaluwarsa atau belum diminta. Minta kode baru.',
            ]);
        }

        // Batas 5 percobaan kode salah sebelum kode dibatalkan.
        if (($cached['attempts'] ?? 0) >= 5) {
            Cache::forget($this->pwdCacheKey($user->id));
            throw ValidationException::withMessages([
                'code' => 'Terlalu banyak percobaan. Minta kode baru.',
            ]);
        }

        if (!Hash::check(trim($request->code), $cached['hash'])) {
            $cached['attempts'] = ($cached['attempts'] ?? 0) + 1;
            Cache::put($this->pwdCacheKey($user->id), $cached, self::PWD_TOKEN_TTL);
            throw ValidationException::withMessages(['code' => 'Kode verifikasi salah.']);
        }

        $user->update(['password' => Hash::make($request->password)]);
        Cache::forget($this->pwdCacheKey($user->id));

        UserActivityLog::record('profile', 'update', 'Mengganti password (verifikasi email)', null, 'Password diubah');

        return back()->with('success', 'Password berhasil diganti.');
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $maskedName = mb_strlen($name) <= 2
            ? mb_substr($name, 0, 1) . '*'
            : mb_substr($name, 0, 2) . str_repeat('*', max(1, mb_strlen($name) - 2));

        return $maskedName . '@' . $domain;
    }

    /**
     * POST /profile/avatar — foto disimpan per user id, nama file lama
     * (ekstensi apapun) dihapus dulu supaya tidak menumpuk file yatim.
     */
    public function uploadAvatar(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'avatar' => 'required|image|max:2048|mimes:jpg,jpeg,png,webp',
        ]);

        if ($user->avatar) {
            Storage::disk('secure')->delete($user->avatar);
        }

        /*
        | Nama berkas acak, bukan "user_{id}.{ext}". Nama berpola bisa ditebak
        | berurutan — dulu itu berarti foto siapa pun bisa diambil hanya dengan
        | menghitung id. Sekarang aksesnya sudah lewat route ber-otentikasi,
        | tapi nama acak menghilangkan pola tebakan sekaligus.
        */
        $file = $request->file('avatar');
        $path = $file->store('avatars', 'secure');

        $user->update(['avatar' => $path]);

        UserActivityLog::record('profile', 'upload', 'Memperbarui foto profil');

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    /** DELETE /profile/avatar — hapus foto profil. */
    public function deleteAvatar()
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->avatar) {
            Storage::disk('secure')->delete($user->avatar);
            $user->update(['avatar' => null]);
            UserActivityLog::record('profile', 'update', 'Menghapus foto profil');
        }

        return back()->with('success', 'Foto profil dihapus.');
    }

    /** POST /profile/cover — unggah foto sampul (pola sama dengan avatar). */
    public function uploadCover(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'cover' => 'required|image|max:4096|mimes:jpg,jpeg,png,webp',
        ]);

        if ($user->cover) {
            Storage::disk('secure')->delete($user->cover);
        }

        $path = $request->file('cover')->store('covers', 'secure');
        $user->update(['cover' => $path]);

        UserActivityLog::record('profile', 'upload', 'Memperbarui foto sampul');

        return back()->with('success', 'Foto sampul berhasil diperbarui.');
    }

    /** DELETE /profile/cover — hapus foto sampul. */
    public function deleteCover()
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->cover) {
            Storage::disk('secure')->delete($user->cover);
            $user->update(['cover' => null]);
            UserActivityLog::record('profile', 'update', 'Menghapus foto sampul');
        }

        return back()->with('success', 'Foto sampul dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW AS — superadmin mencoba UI/menu sebagai role lain (preview).
    | Lihat App\Models\User::hasRole()/effectiveRole() untuk mekanismenya.
    |--------------------------------------------------------------------------
    */

    /** POST /view-as — aktifkan preview sebagai role tertentu. */
    public function viewAs(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isRealSuperadmin()) {
            abort(403, 'Hanya superadmin yang bisa memakai View As.');
        }

        $request->validate(['role_id' => 'required|exists:roles,id']);

        $role = Role::findOrFail($request->role_id);

        // View As sebagai 'superadmin' sendiri = sama saja dengan reset.
        if (strtolower(trim($role->name)) === 'superadmin') {
            session()->forget('view_as_role_id');
        } else {
            session(['view_as_role_id' => $role->id]);
        }

        return back()->with('success', 'Sekarang melihat sebagai role: ' . $role->name . '.');
    }

    /** POST /view-as/reset — kembali ke superadmin sungguhan. */
    public function resetViewAs()
    {
        session()->forget('view_as_role_id');

        return back()->with('success', 'Kembali ke tampilan Superadmin.');
    }
}

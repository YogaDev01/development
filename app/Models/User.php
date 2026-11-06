<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Role;
use App\Models\AccessGroup;
use App\Models\Cluster;

/**
 * @method bool hasRole(array|string $roles)
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany notifications()
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany unreadNotifications()
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'avatar',
        'cover',
        'password',
        'role_id',
        'access_group_id',
        'status',
        'dismissed_changelog_version',
        'notification_prefs',
        'default_app',
    ];

    /**
     * Aplikasi default (kunci config/apps.php) yang aktif saat user login.
     * Jatuh ke config('apps.default') bila belum disetel atau tidak valid lagi.
     */
    public function defaultApp(): string
    {
        $apps = config('apps.items', []);

        // Aplikasi yang mencantumkan role ini ('roles' di config/apps.php)
        // MENANG atas pilihan App Default user (keputusan 19 Okt 2026): role
        // lapangan seperti teknisi harus selalu mendarat di Operations, bukan
        // di Finance yang tidak ada pekerjaannya.
        foreach ($apps as $key => $app) {
            if (!empty($app['roles']) && $this->hasRole($app['roles'])) {
                return (string) $key;
            }
        }

        if (in_array($this->default_app, array_keys($apps), true)) {
            return $this->default_app;
        }

        return (string) config('apps.default', 'finance');
    }

    /** Alamat muka aplikasi default user (dipakai sesudah login). */
    public function defaultAppHome(): string
    {
        $app = $this->defaultApp();

        return (string) (config("apps.items.$app.home") ?: '/');
    }

    /**
     * Halaman yang benar-benar dibuka sesudah login: 'landing' modul bila
     * aplikasinya punya ('operation' → /trouble-ticket), selain itu 'home'.
     * Tanpa ini teknisi mampir dulu di /app/operation lalu dialihkan.
     */
    public function defaultAppLanding(): string
    {
        $app = $this->defaultApp();

        return (string) (config("apps.items.$app.landing") ?: $this->defaultAppHome());
    }

    /**
     * Kategori notifikasi yang bisa diatur user di Profil > Settings.
     * Kunci = nilai $category pada NotifyService::send(); label untuk UI.
     * 'general' sengaja TIDAK dicantumkan: ia notifikasi sistem umum yang
     * tidak boleh dimatikan dari sini.
     */
    public const NOTIFICATION_CATEGORIES = [
        'workflow'  => 'Alur Cost Request (pengajuan, approval, settlement)',
        'milestone' => 'Project Monitoring (milestone, update)',
        'mention'   => 'Mention / sebutan pada komentar',
        'assignment' => 'Assignment (tugas yang diberikan/ditandai selesai)',
    ];

    /**
     * Default pengaturan Reminder To-Do List. Dipakai saat user belum pernah
     * menyetel preferensi (notification_prefs null / tanpa kunci todo_reminder).
     *
     * SAFE BY DEFAULT: lonceng in-app aktif pada jam kerja, WhatsApp MATI
     * (user harus opt-in) agar tidak terjadi blast WA massal.
     *
     * mode 'fixed'    => reminder dikirim pada setiap jam di 'hours' (0-23).
     * mode 'interval' => reminder dikirim tiap 'interval_hours' jam (now->hour % N == 0).
     */
    public const TODO_REMINDER_DEFAULTS = [
        'enabled'        => true,
        'mode'           => 'fixed',
        'hours'          => [8, 13, 16],
        'interval_hours' => 3,
        'whatsapp'       => false,
    ];

    /**
     * Pengaturan Reminder To-Do List yang sudah dinormalisasi (selalu lengkap).
     * Dipakai oleh command todo:remind dan view Settings.
     */
    public function todoReminderPrefs(): array
    {
        $prefs = $this->notification_prefs;
        $saved = (is_array($prefs) && is_array($prefs['todo_reminder'] ?? null))
            ? $prefs['todo_reminder']
            : [];

        $out = array_merge(self::TODO_REMINDER_DEFAULTS, $saved);

        $out['enabled']  = (bool) $out['enabled'];
        $out['whatsapp'] = (bool) $out['whatsapp'];
        $out['mode']     = $out['mode'] === 'interval' ? 'interval' : 'fixed';

        $out['interval_hours'] = min(24, max(1, (int) $out['interval_hours']));

        $hours = is_array($out['hours'] ?? null) ? $out['hours'] : [];
        $hours = array_values(array_unique(array_filter(
            array_map('intval', $hours),
            fn ($h) => $h >= 0 && $h <= 23
        )));
        sort($hours);
        $out['hours'] = $hours;

        return $out;
    }

    /**
     * Apakah reminder To-Do List jatuh tempo untuk JAM saat ini (zona waktu app)?
     * Murni berdasarkan jadwal; belum mengecek ada/tidaknya item To-Do.
     */
    public function todoReminderDueNow(?\Carbon\Carbon $now = null): bool
    {
        $r = $this->todoReminderPrefs();
        if (! $r['enabled']) {
            return false;
        }

        $hour = ($now ?? now())->hour;

        return $r['mode'] === 'interval'
            ? ($hour % $r['interval_hours'] === 0)
            : in_array($hour, $r['hours'], true);
    }

    /**
     * Nomor tujuan notifikasi WhatsApp (dipakai channel Fonnte).
     */
    public function routeNotificationForFonnte(): ?string
    {
        return $this->phone;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    /**
     * Peta id user => URL foto profil, satu query untuk seluruh log. User tanpa
     * foto tidak masuk peta (layar jatuh ke avatar inisial).
     *
     * @param  iterable<int|null>  $ids
     * @return array<int, string>
     */
    public static function avatarUrlMap(iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return static::whereIn('id', $ids)->whereNotNull('avatar')->get(['id', 'avatar'])
            ->mapWithKeys(fn ($u) => [$u->id => $u->avatar_url])
            ->all();
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover ? asset('storage/' . $this->cover) : null;
    }

    protected $hidden = [
        'password',
        'remember_token',
        'last_login_ip',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'notification_prefs' => 'array',
            'last_login_at'      => 'datetime',
        ];
    }

    /**
     * Apakah user mau menerima notifikasi kategori ini lewat channel tertentu?
     *
     * SAFE BY DEFAULT: selama preferensi belum pernah disetel (null) atau
     * kunci kategori/channel belum ada, hasilnya true — jadi perilaku untuk
     * semua user lama tidak berubah sama sekali. User harus secara eksplisit
     * mematikan sesuatu agar bernilai false.
     *
     * @param  string  $category  Nilai $category dari NotifyService::send().
     * @param  string  $channel   'database' (lonceng in-app) atau 'whatsapp'.
     */
    public function wantsNotification(string $category, string $channel = 'database'): bool
    {
        // Reminder To-Do List punya pengaturan tersendiri (todo_reminder) yang
        // selalu dinormalisasi lewat todoReminderPrefs(), jadi ditangani lebih
        // dulu — termasuk saat user belum pernah menyetel preferensi sama sekali
        // (default: in-app ON, WhatsApp OFF) agar tidak ada blast WA tak terduga.
        if ($category === 'todo') {
            $r = $this->todoReminderPrefs();
            if (! $r['enabled']) {
                return false;
            }
            if ($channel === 'whatsapp') {
                $prefs = $this->notification_prefs;
                $waGlobalOff = is_array($prefs) && ($prefs['wa_enabled'] ?? true) === false;

                return $r['whatsapp'] && ! $waGlobalOff;
            }

            return true;
        }

        $prefs = $this->notification_prefs;
        if (!is_array($prefs)) {
            return true;
        }

        // WhatsApp global switch — mematikan ini membungkam SELURUH WhatsApp,
        // lonceng in-app tetap jalan.
        if ($channel === 'whatsapp' && array_key_exists('wa_enabled', $prefs)
            && $prefs['wa_enabled'] === false) {
            return false;
        }

        // Kategori di luar daftar yang bisa diatur (mis. 'general') tidak
        // pernah dibungkam.
        if (!array_key_exists($category, self::NOTIFICATION_CATEGORIES)) {
            return true;
        }

        $cat = $prefs['categories'][$category] ?? null;
        if (!is_array($cat)) {
            return true;
        }

        return ($cat[$channel] ?? true) !== false;
    }

    /**
     * Get the role that belongs to the user
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if user has one of the given roles
     * Support both uppercase and lowercase matching
     *
     * Dihitung dari effectiveRole() (bukan langsung $this->role) supaya
     * fitur "View As" (superadmin mencoba UI sebagai role lain) otomatis
     * konsisten di setiap tempat yang memakai hasRole() — termasuk middleware
     * role:..., seluruh gate di controller, dan kondisi @if di Blade.
     *
     * @param array|string $roles
     * @return bool
     */
    public function hasRole($roles)
    {
        // Convert single role to array
        if (is_string($roles)) {
            $roles = [$roles];
        }

        $effectiveRole = $this->effectiveRole();

        if (!$effectiveRole) {
            return false;
        }

        // Get effective role name and normalize to lowercase
        $userRoleName = strtolower(trim($effectiveRole->name));

        // Check if any of the provided roles match (case-insensitive)
        foreach ($roles as $role) {
            $normalizedRole = strtolower(trim($role));
            if ($userRoleName === $normalizedRole) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW AS — superadmin mencoba UI/menu sebagai role lain (preview saja,
    | tidak mengubah role_id sungguhan). Disimpan di session, per-tab browser
    | tidak ikut (session server-side), reset otomatis saat logout.
    |--------------------------------------------------------------------------
    */

    /**
     * Role SUNGGUHAN user ini (mengabaikan View As) — dipakai untuk
     * menentukan apakah tombol "View As"/"Kembali ke Superadmin" boleh
     * ditampilkan, supaya superadmin tidak pernah terkunci dari kontrol itu
     * sendiri walau sedang meng-impersonate role lain.
     */
    public function isRealSuperadmin(): bool
    {
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && strtolower(trim($this->role->name)) === 'superadmin';
    }

    /** Sedang aktif "View As" role lain? */
    public function isViewingAs(): bool
    {
        return $this->isRealSuperadmin() && session()->has('view_as_role_id');
    }

    /**
     * Role EFEKTIF yang dipakai hasRole()/menu sidebar: role target "View As"
     * kalau sedang aktif (dan hanya untuk superadmin sungguhan), selain itu
     * role asli user.
     */
    public function effectiveRole(): ?Role
    {
        if ($this->isRealSuperadmin() && session()->has('view_as_role_id')) {
            $viewAsRole = Role::find(session('view_as_role_id'));
            if ($viewAsRole) {
                return $viewAsRole;
            }
        }

        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role;
    }

    /**
     * Get the access group that belongs to the user
     */
    public function accessGroup()
    {
        return $this->belongsTo(AccessGroup::class);
    }

    public function teamMemberships()
    {
        return $this->hasMany(TeamMember::class, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | AKSES DATA — satu sumber kebenaran (blueprint 2026-11-06, K2/K3/K5)
    |--------------------------------------------------------------------------
    | Akses = Access Group individu (users.access_group_id)
    |       ∪ Access Group yang dipasang ke Team ACTIVE tempat user menjadi
    |         anggota ACTIVE (access_group_details.team_id).
    |
    | Struktur organisasi (leader, induk team) TIDAK ikut menentukan akses:
    | memimpin team, atau menjadi induk team lain, tidak memberi apa pun.
    |
    | Hasilnya di-memo per instance (review MEDIUM-5): satu permintaan bisa
    | memanggil scope ini belasan kali (scopeByRole, Todo List, bulk per CR).
    | Memo dikunci pada access_group_id saat dihitung dan dibuang oleh
    | refresh() / forgetAccessCache() — perubahan keanggotaan team di tengah
    | permintaan yang sama tidak terlihat sampai memo dibuang.
    */

    /** @var array<string, \Illuminate\Support\Collection> */
    protected array $accessMemo = [];

    protected ?int $accessMemoGroupId = null;

    /** Buang memo akses (dipakai bila akses diubah di instance yang sama). */
    public function forgetAccessCache(): static
    {
        $this->accessMemo = [];

        return $this;
    }

    public function refresh()
    {
        $this->forgetAccessCache();

        return parent::refresh();
    }

    private function accessMemo(string $key, \Closure $hitung): \Illuminate\Support\Collection
    {
        if ($this->accessMemoGroupId !== ($this->access_group_id === null ? null : (int) $this->access_group_id)) {
            $this->accessMemo = [];
            $this->accessMemoGroupId = $this->access_group_id === null ? null : (int) $this->access_group_id;
        }

        return $this->accessMemo[$key] ??= $hitung();
    }

    /** ID Access Group yang berlaku untuk user ini (individu + via team). */
    public function accessibleAccessGroupIds(): \Illuminate\Support\Collection
    {
        return $this->accessMemo('groups', fn () => $this->computeAccessibleAccessGroupIds());
    }

    private function computeAccessibleAccessGroupIds(): \Illuminate\Support\Collection
    {
        $viaTeams = AccessGroupDetail::query()
            ->whereNotNull('team_id')
            ->whereIn('team_id', TeamMember::query()
                ->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->where('team_members.user_id', $this->id)
                ->where('team_members.status', 'ACTIVE')
                ->where('teams.status', 'ACTIVE')
                ->select('team_members.team_id'))
            ->pluck('access_group_id');

        return collect([$this->access_group_id])
            ->concat($viaTeams)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Semua project id (PO) yang bisa diakses user ini lewat Access Group
     * individu ATAU Access Group team ACTIVE-nya. Team INACTIVE (atau
     * membership INACTIVE) tidak lagi memberi akses.
     */
    public function accessibleProjectIds(): \Illuminate\Support\Collection
    {
        return $this->accessGroupDetailIds('project_id');
    }

    /**
     * Boleh melihat PO $projectId (detail JSON & lampiran)?
     *
     *  - Role PO_FULL_SCOPE: semua PO. Fungsi Finance yang ditambahkan
     *    6 Nov 2026 (PO_FULL_SCOPE_MENU_GATED) hanya bila punya menu list-po;
     *    tanpa menu mereka diperlakukan seperti role lain.
     *  - Selainnya: PO di Access Group individu ∪ team ACTIVE.
     *
     * Jalur baca-saja "pernah ditindak" (Project::wasActedOnBy) ditambahkan
     * oleh pemanggilnya, bukan di sini.
     */
    public function canSeeProject(int $projectId): bool
    {
        if ($this->hasRole(\App\Support\AccessRoles::PO_FULL_SCOPE)) {
            $tanpaMenu = $this->hasRole(\App\Support\AccessRoles::PO_FULL_SCOPE_MENU_GATED)
                && ! $this->effectiveRole()?->hasMenu('list-po');

            if (! $tanpaMenu) {
                return true;
            }
        }

        return $this->accessibleProjectIds()->contains($projectId);
    }

    /**
     * Cost center (overhead dll.) yang bisa diakses user ini — pasangan
     * accessibleProjectIds(), dengan jalur yang sama (individu ∪ team).
     */
    public function accessibleCostCenterIds(): \Illuminate\Support\Collection
    {
        return $this->accessGroupDetailIds('cost_center_id');
    }

    private function accessGroupDetailIds(string $column): \Illuminate\Support\Collection
    {
        return $this->accessMemo($column, fn () => $this->computeAccessGroupDetailIds($column));
    }

    private function computeAccessGroupDetailIds(string $column): \Illuminate\Support\Collection
    {
        $groupIds = $this->accessibleAccessGroupIds();
        if ($groupIds->isEmpty()) {
            return collect();
        }

        return AccessGroupDetail::query()
            ->whereIn('access_group_id', $groupIds)
            ->whereNotNull($column)
            ->pluck($column)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | KONTEKS ORGANISASI — dibekukan di baris log status (blueprint K6)
    |--------------------------------------------------------------------------
    */

    /**
     * Team primary, department, posisi & level user SAAT INI, plus kode semua
     * team ACTIVE-nya. Dipanggil addLog() saat aksi terjadi; hasilnya
     * disimpan di kolom actor_org_context sehingga perubahan organisasi
     * berikutnya tidak mengubah histori. Primary tidak ditebak: bila belum
     * ditetapkan, primary_team = null.
     */
    public function orgContext(): array
    {
        $rows = TeamMember::query()
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->leftJoin('departments', 'departments.id', '=', 'teams.department_id')
            ->where('team_members.user_id', $this->id)
            ->where('team_members.status', 'ACTIVE')
            ->where('teams.status', 'ACTIVE')
            ->orderBy('teams.code')
            ->get([
                'teams.id as team_id', 'teams.code', 'teams.name',
                'departments.id as department_id', 'departments.name as department_name',
                'team_members.position', 'team_members.level', 'team_members.is_primary',
            ]);

        $primary = $rows->first(fn ($r) => (bool) $r->is_primary);

        return [
            'primary_team' => $primary ? ['id' => (int) $primary->team_id, 'code' => $primary->code, 'name' => $primary->name] : null,
            'department'   => $primary && $primary->department_id
                ? ['id' => (int) $primary->department_id, 'name' => $primary->department_name]
                : null,
            'position'     => $primary?->position,
            'level'        => $primary ? (int) $primary->level : null,
            'teams'        => $rows->pluck('code')->values()->all(),
        ];
    }

    /**
     * ['actor_org_context' => …] untuk baris log $table, atau [] bila kolomnya
     * / struktur team belum dimigrasi (kode di-upload sebelum migrate) atau
     * tidak ada pelaku. Gagal-aman: konteks yang gagal dihitung tidak boleh
     * menggagalkan transisi status dokumen — kolomnya dibiarkan NULL.
     */
    public static function orgContextAttribute(?self $user, string $table): array
    {
        static $kolomAda = [];

        if (! $user) {
            return [];
        }

        $kolomAda[$table] ??= Team::supportsStructure()
            && \Illuminate\Support\Facades\Schema::hasColumn($table, 'actor_org_context');

        if (! $kolomAda[$table]) {
            return [];
        }

        try {
            return ['actor_org_context' => $user->orgContext()];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal menghitung actor_org_context', [
                'table' => $table, 'user_id' => $user->id, 'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Kebalikan accessibleProjectIds(): user yang punya akses ke PO $projectId
     * lewat Access Group individu ATAU team ACTIVE-nya. Role ber-scope penuh
     * TIDAK termasuk — pemanggil menambahkannya sendiri bila perlu.
     */
    public function scopeWithProjectAccess($query, int $projectId)
    {
        $groupIds = AccessGroupDetail::query()->where('project_id', $projectId)->select('access_group_id');

        return $query->where(fn ($q) => $q
            ->whereIn('users.access_group_id', $groupIds)
            ->orWhereIn('users.id', TeamMember::query()
                ->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->where('team_members.status', 'ACTIVE')
                ->where('teams.status', 'ACTIVE')
                ->whereIn('team_members.team_id', AccessGroupDetail::query()
                    ->whereNotNull('team_id')
                    ->whereIn('access_group_id', AccessGroupDetail::query()->where('project_id', $projectId)->select('access_group_id'))
                    ->select('team_id'))
                ->select('team_members.user_id')));
    }

    /**
     * Get all clusters that the user belongs to
     */
    public function clusters()
    {
        return $this->belongsToMany(
            Cluster::class,
            'user_groups'
        );
    }

}
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
        $apps = array_keys(config('apps.items', []));

        return in_array($this->default_app, $apps, true)
            ? $this->default_app
            : (string) config('apps.default', 'finance');
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

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover ? asset('storage/' . $this->cover) : null;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'notification_prefs' => 'array',
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

    /**
     * Semua project id (PO) yang bisa diakses user ini: langsung dari access
     * group-nya sendiri (project_id di access_group_details), DITAMBAH
     * project id yang bisa diakses lewat access group manapun yang di-assign
     * ke Team tempat user ini jadi member ACTIVE (team_id di
     * access_group_details) — lihat dokumen Major A "Team -> Access Group ->
     * PO". Union, BUKAN pengganti — assignment access group langsung tetap
     * berlaku seperti sekarang, ini cuma menambah jalur baru.
     */
    public function accessibleProjectIds(): \Illuminate\Support\Collection
    {
        $direct = $this->accessGroup ? $this->accessGroup->projectIds() : collect();

        $teamIds = $this->teamMemberships()->where('status', 'ACTIVE')->pluck('team_id');
        if ($teamIds->isEmpty()) {
            return $direct;
        }

        $viaTeams = AccessGroup::whereHas('details', fn ($q) => $q->whereIn('team_id', $teamIds))
            ->get()
            ->flatMap(fn ($ag) => $ag->projectIds());

        return $direct->concat($viaTeams)->unique()->values();
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
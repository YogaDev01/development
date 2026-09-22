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
        'password',
        'role_id',
        'access_group_id',
        'status',
        'dismissed_changelog_version',
    ];

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

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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
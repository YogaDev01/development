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
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_id',
        'access_group_id',
        'status',
    ];

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
     * @param array|string $roles
     * @return bool
     */
    public function hasRole($roles)
    {
        // Convert single role to array
        if (is_string($roles)) {
            $roles = [$roles];
        }

        // Ensure role is loaded
        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        // Check if user has a role assigned
        if (!$this->role) {
            return false;
        }

        // Get user role name and normalize to lowercase
        $userRoleName = strtolower(trim($this->role->name));

        // Check if any of the provided roles match (case-insensitive)
        foreach ($roles as $role) {
            $normalizedRole = strtolower(trim($role));
            if ($userRoleName === $normalizedRole) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the access group that belongs to the user
     */
    public function accessGroup()
    {
        return $this->belongsTo(AccessGroup::class);
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
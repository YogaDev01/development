<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'status',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function menuItems()
    {
        return $this->belongsToMany(
            MenuItem::class,
            'role_menu_items',
            'role_id',
            'menu_item_id'
        )->orderBy('sort_order');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this role has access to a menu item by key.
     * Superadmin always returns true.
     */
    public function hasMenu(string $key): bool
    {
        if (strtolower($this->name) === 'superadmin') {
            return true;
        }

        // Use cached collection to avoid N+1
        return $this->menuItems
            ->pluck('key')
            ->contains($key);
    }

    /**
     * Get all accessible menu keys for this role.
     */
    public function menuKeys(): array
    {
        if (strtolower($this->name) === 'superadmin') {
            return MenuItem::pluck('key')->toArray();
        }

        return $this->menuItems->pluck('key')->toArray();
    }
}
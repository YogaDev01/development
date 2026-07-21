<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = [
        'key',
        'label',
        'icon',
        'route',
        'section',
        'sort_order',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_menu_items',
            'menu_item_id',
            'role_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopeBySection($query, string $section)
    {
        return $query->where('section', $section);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Group all menu items by their section, ordered.
     */
    public static function groupedBySection(): array
    {
        return static::ordered()
            ->get()
            ->groupBy('section')
            ->toArray();
    }
}
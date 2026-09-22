<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleMenuItem extends Model
{
    protected $table = 'role_menu_items';

    protected $fillable = [
        'role_id',
        'menu_item_id',
    ];
}
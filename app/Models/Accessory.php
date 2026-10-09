<?php

namespace App\Models;

use App\Support\MasterItemCode;
use Illuminate\Database\Eloquent\Model;

class Accessory extends Model
{
    protected $table = 'accessories';

    public const STATUSES = MasterMaterial::STATUSES;

    // accessories_code sengaja tidak fillable: hanya diisi lewat generateCode().
    protected $fillable = ['accessories_name', 'customer_id', 'category_id', 'status'];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function category()
    {
        return $this->belongsTo(CategoryItem::class, 'category_id');
    }

    public function siteLinks()
    {
        return $this->hasMany(SiteAccessory::class, 'accessories_id');
    }

    public static function generateCode(Customer $customer): string
    {
        return MasterItemCode::next('A', $customer, 'accessories', 'accessories_code');
    }
}

<?php

namespace App\Models;

use App\Support\MasterItemCode;
use Illuminate\Database\Eloquent\Model;

class MasterMaterial extends Model
{
    protected $table = 'master_material';

    public const STATUSES = [
        'WAREHOUSE'  => 'Warehouse',
        'DELIVERY'   => 'Delivery',
        'HOMEBASE'   => 'Homebase',
        'TECHNICIAN' => 'Technician',
        'INSTALLED'  => 'Installed',
        'FAULTY'     => 'Faulty',
        'RETURNED'   => 'Returned',
        'UNKNOWN'    => 'Unknown',
    ];

    // product_code sengaja tidak fillable: hanya diisi lewat generateCode().
    protected $fillable = ['customer_id', 'category_id', 'serial_number', 'status'];

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
        return $this->hasMany(SiteMaterial::class, 'material_id');
    }

    public function sites()
    {
        return $this->belongsToMany(Site::class, 'site_material', 'material_id', 'site_id')->withTimestamps();
    }

    public static function generateCode(Customer $customer): string
    {
        return MasterItemCode::next('P', $customer, 'master_material', 'product_code');
    }
}

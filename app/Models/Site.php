<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $table = 'sites';

    public const TYPES = [
        'PEMERINTAH' => 'Pemerintah',
        'PENDIDIKAN' => 'Pendidikan',
        'TOWER'      => 'Tower',
    ];

    public const SOURCE_POWERS = [
        'PLN'    => 'PLN',
        'SPS'    => 'SPS',
        'HYBRID' => 'Hybrid',
    ];

    public const PROGRAMS = [
        'AKSES_INTERNET' => 'Akses Internet',
        'TOWER_POWER'    => 'Tower & Power',
    ];

    public const STATUSES = [
        'ACTIVE'   => 'Active',
        'INACTIVE' => 'Inactive',
    ];

    protected $fillable = [
        'tid', 'sitename', 'type', 'source_power', 'program', 'status',
        'latitude', 'longitude', 'address', 'customer_id', 'network_id',
        'desa_id', 'kecamatan_id', 'kota_id', 'provinsi_id', 'pulau_id',
    ];

    protected $casts = [
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function network()
    {
        return $this->belongsTo(Network::class, 'network_id');
    }

    public function pulau()
    {
        return $this->belongsTo(Pulau::class, 'pulau_id');
    }

    public function provinsi()
    {
        return $this->belongsTo(Provinsi::class, 'provinsi_id');
    }

    public function kota()
    {
        return $this->belongsTo(Kota::class, 'kota_id');
    }

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'kecamatan_id');
    }

    public function desa()
    {
        return $this->belongsTo(Desa::class, 'desa_id');
    }

    public function materialLinks()
    {
        return $this->hasMany(SiteMaterial::class, 'site_id');
    }

    public function accessoryLinks()
    {
        return $this->hasMany(SiteAccessory::class, 'site_id');
    }

    public function picLinks()
    {
        return $this->hasMany(SitePic::class, 'site_id');
    }

    public function pics()
    {
        return $this->belongsToMany(MasterPic::class, 'site_pics', 'site_id', 'pic_id')->withTimestamps();
    }

    public function troubleTickets()
    {
        return $this->hasMany(TroubleTicket::class, 'site_id');
    }

    public function materials()
    {
        return $this->belongsToMany(MasterMaterial::class, 'site_material', 'site_id', 'material_id')->withTimestamps();
    }

    public function accessories()
    {
        return $this->belongsToMany(Accessory::class, 'site_accessories', 'site_id', 'accessories_id')
            ->withPivot('qty')
            ->withTimestamps();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $table = 'sites';

    public const TYPES = [
        'SEKOLAH' => 'Sekolah',
        'KANTOR' => 'Kantor',
        'KEAMANAN' => 'Keamanan',
        'LAYANAN_KESEHATAN' => 'Layanan Kesehatan',
        'TEMPAT_IBADAH' => 'Tempat Ibadah',
        'TOWER' => 'Tower',
    ];

    public const SOURCE_POWERS = [
        'PLN' => 'PLN',
        'SPS' => 'SPS',
        'HYBRID' => 'Hybrid',
    ];

    public const PROGRAMS = [
        'AKSES_INTERNET' => 'Akses Internet',
        'TOWER_POWER' => 'Tower & Power',
    ];

    public const STATUSES = [
        'ACTIVE' => 'Active',
        'INACTIVE' => 'Inactive',
    ];

    /** Polarisasi antena, mengikuti referensi SLAmeet. */
    public const POLARIZATIONS = [
        'R' => 'R',
        'L' => 'L',
    ];

    /** Jabatan PIC yang dibaca sebagai Supervisor / PIC Site (cocok sebagian). */
    public const SUPERVISOR_POSITION = 'supervisor';

    public const TECHNICIAN_POSITION = 'teknisi';

    protected $fillable = [
        'tid', 'sitename', 'type', 'source_power', 'program', 'status',
        'latitude', 'longitude', 'address', 'customer_id', 'network_id',
        'desa_id', 'kecamatan_id', 'kota_id', 'provinsi_id', 'pulau_id',
        'hb_id', 'region_id',
        // Data jaringan per site (migrasi 2026_10_10_000100) — menggantikan
        // dropdown Network di form; `network_id` tinggal untuk site lama.
        'gateway', 'outroute_id', 'hub', 'spotbeam', 'polarization',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function homebase()
    {
        return $this->belongsTo(Homebase::class, 'hb_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
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

    /** Dokumen site (disk `secure`), terbaru lebih dulu. */
    public function documents()
    {
        return $this->hasMany(SiteDocument::class, 'site_id')->orderByDesc('id');
    }

    /** Catatan Preventive Maintenance site, terbaru lebih dulu. */
    public function maintenances()
    {
        return $this->hasMany(SiteMaintenance::class, 'site_id')->orderByDesc('performed_at')->orderByDesc('id');
    }

    /** Penjaga site (nama + no HP), diisi bebas di form site. */
    public function keepers()
    {
        return $this->hasMany(SiteKeeper::class, 'site_id');
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

    /*
    |--------------------------------------------------------------------------
    | PIC VIRTUAL (spv_id / pic_id)
    |--------------------------------------------------------------------------
    | Bukan kolom `sites`: nilainya baris di `site_pics`, dibedakan dari
    | jabatan PIC-nya. Disimpan balik lewat SiteController::syncSitePics().
    */

    /** Supervisor site: PIC internal terpasang yang jabatannya "Supervisor". */
    public function supervisorPic(): ?MasterPic
    {
        return $this->picByPosition(self::SUPERVISOR_POSITION);
    }

    /** PIC Site: PIC internal terpasang yang jabatannya "Teknisi". */
    public function technicianPic(): ?MasterPic
    {
        return $this->picByPosition(self::TECHNICIAN_POSITION);
    }

    protected function picByPosition(string $position): ?MasterPic
    {
        return $this->picLinks
            ->pluck('pic')
            ->first(fn (?MasterPic $p) => $p
                && $p->type === 'internal'
                && str_contains(strtolower((string) $p->position), $position));
    }

    public function getSpvIdAttribute(): ?int
    {
        return $this->supervisorPic()?->id;
    }

    public function getPicIdAttribute(): ?int
    {
        return $this->technicianPic()?->id;
    }
}

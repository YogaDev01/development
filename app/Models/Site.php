<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $table = 'sites';

    /*
    | Pilihan Tipe / Source Power / Program diambil dari master (dulu konstanta
    | TYPES / SOURCE_POWERS / PROGRAMS). Format [code => name]; `code` yang
    | disimpan di kolom sites. Di-cache per request lewat once().
    */
    public static function typeOptions(): array
    {
        return once(fn () => SiteType::orderBy('name')->pluck('name', 'code')->all());
    }

    public static function sourcePowerOptions(): array
    {
        return once(fn () => SiteSourcePower::orderBy('name')->pluck('name', 'code')->all());
    }

    public static function programOptions(): array
    {
        return once(fn () => SiteProgram::orderBy('name')->pluck('name', 'code')->all());
    }

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

    /** Role akun (roles.name) yang boleh dipilih sebagai Teknisi / Teknisi Backup site. */
    public const TECHNICIAN_ROLE = 'technician';

    /** Jenis PIC (MasterPic::TYPES) yang mengisi pilihan Site Keeper. */
    public const KEEPER_PIC_TYPE = 'site_keeper';

    protected $fillable = [
        'tid', 'sitename', 'type', 'source_power', 'program', 'status',
        'latitude', 'longitude', 'address', 'customer_id', 'network_id',
        'desa_id', 'kecamatan_id', 'kota_id', 'provinsi_id', 'pulau_id',
        'hb_id', 'region_id',
        // Data jaringan per site (migrasi 2026_10_10_000100) — menggantikan
        // dropdown Network di form; `network_id` tinggal untuk site lama.
        'gateway', 'outroute_id', 'hub', 'spotbeam', 'polarization',
        // Teknisi site = akun user (migrasi 2026_11_09_000300); site_pics
        // tetap dibaca sebagai teknisi lama (technicianPic()).
        'technician_user_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** Teknisi site: akun user ber-role technician (field Teknisi di form). */
    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    /**
     * Kolom sites.technician_user_id sudah ada (migrasi 2026_11_09_000300)?
     *
     * Kode di-upload SEBELUM migrate; selama jeda itu teknisi tetap dibaca
     * dari site_pics saja dan field Teknisi tidak ditampilkan. Dihitung sekali
     * per permintaan.
     */
    public static function supportsTechnicianUser(): bool
    {
        static $ada = null;

        return $ada ??= \Illuminate\Support\Facades\Schema::hasColumn('sites', 'technician_user_id');
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

    /** Teknisi backup site: cadangan untuk pilihan Change Teknisi. */
    public function backupTechnicians()
    {
        return $this->hasMany(SiteBackupTechnician::class, 'site_id');
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

    /**
     * Teknisi LAMA: PIC internal terpasang yang jabatannya "Teknisi". Sejak
     * 9 Nov 2026 teknisi utama ada di technician(); ini tinggal dibaca untuk
     * site yang teknisinya belum dipindah ke akun user.
     */
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

    /** Nama teknisi untuk tampilan: akun user, atau teknisi lama dari site_pics. */
    public function technicianName(): string
    {
        return $this->technician?->name ?? $this->technicianPic()?->pic ?? '';
    }
}

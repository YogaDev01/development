<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'code',
        'name',
        'department_id',
        // Customer (project) yang dilayani team ini — dipakai form Buat
        // Trouble Ticket untuk mengisi customer & menyaring site otomatis.
        'customer_id',
        'leader_id',
        'status',
    ];

    public const STATUSES = ['ACTIVE', 'INACTIVE'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Kolom teams.customer_id sudah ada (migrasi 2026_10_19_000300 dijalankan)?
     *
     * Dipakai jalur yang membaca/menulis kolom itu — Buat Trouble Ticket dan
     * form Team — supaya basis data yang belum dimigrasi tidak memunculkan
     * "Unknown column", melainkan kembali ke perilaku lama (customer dipilih
     * sendiri). Jawabannya dihitung sekali per permintaan.
     */
    public static function supportsCustomer(): bool
    {
        static $ada = null;

        return $ada ??= \Illuminate\Support\Facades\Schema::hasColumn('teams', 'customer_id');
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members()
    {
        return $this->hasMany(TeamMember::class)->orderBy('level');
    }

    public function activeMembers()
    {
        return $this->members()->where('status', 'ACTIVE');
    }

    public function accessGroupLinks()
    {
        return $this->hasMany(AccessGroupDetail::class, 'team_id');
    }

    public static function generateNextCode(): string
    {
        $last = static::orderBy('id', 'desc')->value('id');
        return 'TEAM-' . str_pad(($last ?? 0) + 1, 3, '0', STR_PAD_LEFT);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    public function getStatusColorAttribute(): string
    {
        return $this->status === 'ACTIVE' ? 'success' : 'secondary';
    }

    /**
     * Organization chart dinamis dari team_members.level — TIDAK hard-coded,
     * mengikuti perubahan level kapan saja. Level makin kecil = makin tinggi
     * hierarkinya. Dikelompokkan per level (bukan pohon parent/child, karena
     * data mentahnya cuma punya level datar, bukan relasi atasan-langsung).
     */
    public function orgChart(): \Illuminate\Support\Collection
    {
        return $this->activeMembers()
            ->with('user')
            ->get()
            ->groupBy('level')
            ->sortKeys()
            ->map(fn ($members, $level) => [
                'level'   => $level,
                'members' => $members->values(),
            ])
            ->values();
    }
}

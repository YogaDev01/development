<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'code',
        'name',
        'department_id',
        // Customer pertama team ini. Daftar lengkapnya ada di customers();
        // kolom ini hanya dibaca selama tabel team_customers belum dimigrasi.
        'customer_id',
        // Induk team — HANYA untuk tampilan struktur organisasi. Relasi ini
        // tidak pernah memberi/menurunkan akses data (blueprint 2026-11-06 K3).
        'parent_team_id',
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
     * Customer yang dilayani team ini — dipakai form Buat Trouble Ticket untuk
     * mengisi customer & menyaring site otomatis.
     */
    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'team_customers');
    }

    /**
     * Tabel team_customers sudah ada (migrasi 2026_11_06_000500 dijalankan)?
     *
     * Kode di-upload SEBELUM migrate; selama jeda itu team tetap memakai satu
     * customer di teams.customer_id. Dihitung sekali per permintaan.
     */
    public static function supportsCustomerList(): bool
    {
        static $ada = null;

        return $ada ??= \Illuminate\Support\Facades\Schema::hasTable('team_customers');
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

    /**
     * Migrasi struktur team (2026_11_06_000100 & 000200) sudah dijalankan?
     *
     * Kode di-upload SEBELUM migrate; selama jeda itu form Team tetap jalan
     * seperti sebelumnya (tanpa induk, primary, dan riwayat) alih-alih galat
     * "Unknown column". Dihitung sekali per permintaan.
     */
    public static function supportsStructure(): bool
    {
        static $ada = null;

        return $ada ??= \Illuminate\Support\Facades\Schema::hasColumn('teams', 'parent_team_id')
            && \Illuminate\Support\Facades\Schema::hasColumn('team_members', 'is_primary')
            && \Illuminate\Support\Facades\Schema::hasTable('team_logs');
    }

    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function parent()
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }

    public function children()
    {
        return $this->hasMany(Team::class, 'parent_team_id')->orderBy('name');
    }

    public function logs()
    {
        return $this->hasMany(TeamLog::class)->orderByDesc('id');
    }

    /**
     * Catat satu kejadian perubahan Team. Dipanggil DI DALAM transaksi
     * perubahannya (tidak gagal-aman), supaya perubahan struktur tidak pernah
     * tersimpan tanpa jejak. No-op bila tabel team_logs belum dimigrasi.
     */
    public function addLog(string $action, ?array $changes = null, ?string $notes = null): void
    {
        if (! static::supportsStructure()) {
            return;
        }

        $user = \Illuminate\Support\Facades\Auth::user();

        TeamLog::create([
            'team_id'   => $this->id,
            'action'    => $action,
            'changes'   => $changes ?: null,
            'notes'     => $notes,
            'user_id'   => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'role'      => $user?->role?->name,
        ]);
    }

    /**
     * Apakah menjadikan $parentId induk team ini membentuk siklus?
     * Menelusuri rantai induk dari $parentId ke atas; siklus bila bertemu
     * team ini sendiri. Batas langkah menjaga dari data yang sudah rusak.
     */
    public function wouldCreateCycle(?int $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        $parents = static::query()->pluck('parent_team_id', 'id');
        $cursor  = $parentId;

        for ($i = 0; $cursor !== null && $i <= $parents->count(); $i++) {
            if ((int) $cursor === (int) $this->id) {
                return true;
            }
            $cursor = $parents->get($cursor);
        }

        return $cursor !== null; // batas langkah habis = data sudah melingkar
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterPic extends Model
{
    protected $fillable = [
        'type',
        // M-02 — penghubung ke orang yang sebenarnya, kalau PIC ini orang
        // dalam. Nullable: PIC customer memang tidak punya akun/kepegawaian.
        'user_id',
        'employee_id',
        'pic',
        'position',
        // Rekening milik PIC-nya sendiri — bukan rekening beneficiary yang ia
        // tangani. Lihat migrasi 2026_09_22_000020.
        'bank_id',
        'account_number',
        'phone',
        'email',
        'status',
        'input_by',
    ];

    public const TYPES = [
        'customer' => 'Customer',
        'internal' => 'Internal',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /** Bank rekening milik PIC ini. */
    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    /** Akun aplikasi milik PIC ini (M-02). */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Data kepegawaian PIC ini (M-02). */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** Beneficiary yang menunjuk PIC ini sebagai penanggung jawab (MN-02). */
    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class, 'pic_id');
    }

    /**
     * Team yang diikuti PIC ini — lewat akun User-nya (M-02).
     *
     * Sengaja relasi TIDAK LANGSUNG: keanggotaan team melekat pada User, bukan
     * pada baris Master PIC. Membuat relasi langsung Team<->MasterPic berarti
     * satu orang bisa punya dua keanggotaan berbeda yang saling bertentangan.
     */
    public function teamMemberships()
    {
        return $this->hasMany(TeamMember::class, 'user_id', 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}

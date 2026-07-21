<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employees';

    protected $fillable = [
        'employee_number',
        'name',
        'birth_place',
        'birth_date',
        'nik',
        'gender',
        'address',
        'domicile',
        'phone',
        'email',
        'npwp',
        'ptkp_status',
        'bank_id',
        'account_name',
        'account_number',
        'default_salary',
        'status',
        'notes',
        'input_by',
    ];

    protected $casts = [
        'birth_date'     => 'date',
        'default_salary' => 'integer',
    ];

    public const STATUSES = ['ACTIVE', 'INACTIVE'];

    public const GENDERS = ['L', 'P'];

    /** Status PTKP standar (PMK-101/PMK-010) */
    public const PTKP_STATUSES = [
        'TK/0', 'TK/1', 'TK/2', 'TK/3',
        'K/0',  'K/1',  'K/2',  'K/3',
        'K/I/0', 'K/I/1', 'K/I/2', 'K/I/3',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function payrollLines()
    {
        return $this->hasMany(PayrollRunLine::class, 'employee_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES & HELPERS
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function isActive(): bool
    {
        return strtoupper($this->status) === 'ACTIVE';
    }

    public function getStatusColorAttribute(): string
    {
        return strtoupper($this->status) === 'ACTIVE' ? 'success' : 'secondary';
    }

    public function getGenderLabelAttribute(): string
    {
        return match (strtoupper((string) $this->gender)) {
            'L'     => 'Laki-laki',
            'P'     => 'Perempuan',
            default => '-',
        };
    }

    public function getDefaultSalaryFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->default_salary ?? 0, 0, ',', '.');
    }
}
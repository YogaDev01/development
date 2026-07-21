<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRunLine extends Model
{
    protected $table = 'payroll_run_lines';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'employee_name',
        'position',
        'bank_id',
        'account_name',
        'account_number',
        'net_amount',
        'charge_type',
        'charge_project_id',
        'notes',
    ];

    protected $casts = [
        'net_amount' => 'integer',
    ];

    public const CHARGE_TYPES = ['OVERHEAD', 'PROJECT'];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    /** Proyek yang dibebani (hanya bila charge_type = PROJECT) */
    public function chargeProject()
    {
        return $this->belongsTo(Project::class, 'charge_project_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isChargedToProject(): bool
    {
        return strtoupper($this->charge_type) === 'PROJECT';
    }

    public function getNetFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->net_amount ?? 0, 0, ',', '.');
    }

    public function getChargeColorAttribute(): string
    {
        return $this->isChargedToProject() ? 'info' : 'secondary';
    }
}
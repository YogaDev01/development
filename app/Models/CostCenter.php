<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    protected $table = 'cost_centers';

    protected $fillable = [
        'code',
        'name',
        'type',
        'status',
        'notes',
        'input_by',
    ];

    public const TYPES = [
        'OVERHEAD',
        'PAYROLL',
        'TAX',
        'ASSET',
        'LIABILITY',
        'CAPITAL',
        'ADJUSTMENT',
    ];

    /** Prefix kode otomatis per tipe (OH-001, PR-001, TX-001, dst). */
    public const CODE_PREFIXES = [
        'OVERHEAD'   => 'OH',
        'PAYROLL'    => 'PR',
        'TAX'        => 'TX',
        'ASSET'      => 'AS',
        'LIABILITY'  => 'LB',
        'CAPITAL'    => 'CP',
        'ADJUSTMENT' => 'AJ',
    ];

    public const STATUSES = [
        'ACTIVE',
        'INACTIVE',
    ];

    /**
     * Semua tipe cost center SELAIN Payroll — diperlakukan seragam di seluruh
     * alur Budget Plan/Cost Request (visibility, akses group, kategori Account
     * "Overhead") persis seperti Overhead selama ini. Payroll sengaja dikecualikan
     * karena datanya rahasia (role/akses sendiri, lihat PAYROLL_ROLES di
     * BudgetPlanController/BuildsCrWorklist).
     */
    public const OVERHEAD_LIKE_TYPES = [
        'OVERHEAD', 'TAX', 'ASSET', 'LIABILITY', 'CAPITAL', 'ADJUSTMENT',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function budgetPlans()
    {
        return $this->hasMany(BudgetPlan::class, 'cost_center_id');
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeOverhead($query)
    {
        return $query->where('type', 'OVERHEAD');
    }

    public function scopePayroll($query)
    {
        return $query->where('type', 'PAYROLL');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isOverhead(): bool { return strtoupper($this->type) === 'OVERHEAD'; }
    public function isPayroll(): bool  { return strtoupper($this->type) === 'PAYROLL'; }
    public function isActive(): bool   { return strtoupper($this->status) === 'ACTIVE'; }

    public function getTypeColorAttribute(): string
    {
        return match (strtoupper($this->type)) {
            'OVERHEAD'   => 'info',
            'PAYROLL'    => 'warning',
            'TAX'        => 'danger',
            'ASSET'      => 'success',
            'LIABILITY'  => 'dark',
            'CAPITAL'    => 'primary',
            'ADJUSTMENT' => 'secondary',
            default      => 'secondary',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return strtoupper($this->status) === 'ACTIVE' ? 'success' : 'secondary';
    }

    /**
     * Generate kode unik: OH-001 (overhead) / PR-001 (payroll)
     */
    public static function generateNextCode(string $type): string
    {
        $prefix = self::CODE_PREFIXES[strtoupper($type)] ?? 'OH';

        $last = static::where('type', strtoupper($type))
            ->where('code', 'like', $prefix . '-%')
            ->orderBy('code', 'desc')
            ->value('code');

        $next = 1;
        if ($last) {
            $next = ((int) substr($last, strlen($prefix) + 1)) + 1;
        }

        return $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
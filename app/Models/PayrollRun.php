<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    protected $table = 'payroll_runs';

    protected $fillable = [
        'code',
        'period',
        'description',
        'budget_plan_id',
        'total',
        'status',
        'daily_transaction_id',
        'notes',
        'input_by',
        'submitted_by',
        'verified_by',
        'approved_by',
        'submitted_at',
        'verified_at',
        'approved_at',
        'reject_notes',
    ];

    protected $casts = [
        'total'        => 'integer',
        'submitted_at' => 'datetime',
        'verified_at'  => 'datetime',
        'approved_at'  => 'datetime',
    ];

    public const STATUSES = [
        'DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'PAID', 'CLOSED', 'REJECTED',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function lines()
    {
        return $this->hasMany(PayrollRunLine::class, 'payroll_run_id');
    }

    public function budgetPlan()
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    public function dailyTransaction()
    {
        return $this->belongsTo(DailyTransaction::class, 'daily_transaction_id');
    }

    public function inputBy()     { return $this->belongsTo(User::class, 'input_by'); }
    public function submittedBy() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function verifiedBy()  { return $this->belongsTo(User::class, 'verified_by'); }
    public function approvedBy()  { return $this->belongsTo(User::class, 'approved_by'); }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isEditable(): bool
    {
        return strtoupper($this->status) === 'DRAFT';
    }

    public function recalcTotal(): void
    {
        $this->total = $this->lines()->sum('net_amount');
        $this->save();
    }

    public function getTotalFormattedAttribute(): string
    {
        return number_format($this->total ?? 0, 0, ',', '.');
    }

    public function getPeriodLabelAttribute(): string
    {
        // '2026-07' -> 'Juli 2026'
        if (!$this->period || !str_contains($this->period, '-')) {
            return $this->period ?? '-';
        }

        [$y, $m] = explode('-', $this->period);
        $bulan = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];
        $mm = str_pad($m, 2, '0', STR_PAD_LEFT);

        return ($bulan[$mm] ?? $m) . ' ' . $y;
    }

    public function getStatusColorAttribute(): string
    {
        return match (strtoupper($this->status)) {
            'DRAFT'     => 'secondary',
            'SUBMITTED' => 'warning',
            'VERIFIED'  => 'primary',
            'APPROVED'  => 'info',
            'PAID'      => 'success',
            'CLOSED'    => 'dark',
            'REJECTED'  => 'danger',
            default     => 'secondary',
        };
    }

    /**
     * Generate kode: PRR-{MM}{YY}{seq(5)}
     */
    public static function generateCode(): string
    {
        $prefix = 'PRR-' . now()->format('m') . now()->format('y');

        $count = static::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $seq       = str_pad($count + 1, 5, '0', STR_PAD_LEFT);
        $candidate = $prefix . $seq;

        $attempts = 0;
        while (static::where('code', $candidate)->exists() && $attempts < 100) {
            $count++;
            $seq       = str_pad($count + 1, 5, '0', STR_PAD_LEFT);
            $candidate = $prefix . $seq;
            $attempts++;
        }

        return $candidate;
    }
}
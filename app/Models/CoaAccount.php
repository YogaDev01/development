<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoaAccount extends Model
{
    protected $fillable = [
        'code', 'name', 'account_type', 'category', 'parent_id', 'normal_balance',
        'description', 'is_postable', 'source_fund_id', 'status', 'created_by',
        'statement_section', 'cash_flow_activity',
    ];

    protected $casts = ['is_postable' => 'boolean'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function sourceFund()
    {
        return $this->belongsTo(SourceFund::class);
    }

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'ACTIVE');
    }

    public function scopePostable($q)
    {
        return $q->where('status', 'ACTIVE')->where('is_postable', true);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /** Sudah dipakai jurnal (baris maupun sebagai akun usulan) — akun tidak boleh dihapus / diubah tipenya. */
    public function hasJournalLines(): bool
    {
        return JournalLine::where('coa_account_id', $this->id)->orWhere('suggested_account_id', $this->id)->exists();
    }

    /**
     * Report lock: akun punya mutasi (jurnal POSTED/REVERSED) pada periode yang sudah CLOSED/LOCKED.
     * Mengubah klasifikasi laporannya akan menggeser laporan periode yang sudah ditutup.
     */
    public function hasClosedPeriodActivity(): bool
    {
        return \Illuminate\Support\Facades\DB::table('journal_lines as l')
            ->join('journals as j', 'j.id', '=', 'l.journal_id')
            ->join('accounting_periods as p', function ($q) {
                $q->on('j.journal_date', '>=', 'p.start_date')->on('j.journal_date', '<=', 'p.end_date');
            })
            ->whereNull('j.deleted_at')->whereIn('j.status', ['POSTED', 'REVERSED'])
            ->where('p.status', '!=', 'OPEN')->where('l.coa_account_id', $this->id)->exists();
    }

    /** Penempatan di laporan; kosong = bawaan tipe akun (elemen pertama SECTIONS). */
    public function effectiveSection(): string
    {
        $sections = \App\Support\AccountingCatalog::SECTIONS[$this->account_type] ?? [];

        return isset($sections[$this->statement_section]) ? $this->statement_section : (string) array_key_first($sections);
    }

    /** Aktivitas Arus Kas; kosong = diturunkan dari penempatan akun. */
    public function effectiveCashActivity(): string
    {
        if (isset(\App\Support\AccountingCatalog::CASH_ACTIVITIES[$this->cash_flow_activity])) {
            return $this->cash_flow_activity;
        }

        return match ($this->effectiveSection()) {
            'NON_CURRENT_ASSET' => 'INVESTING',
            'NON_CURRENT_LIABILITY', 'CAPITAL', 'DISTRIBUTION', 'OTHER_EQUITY', 'RETAINED_EARNINGS' => 'FINANCING',
            default => 'OPERATING',
        };
    }

    /** "1101 — Bank Mandiri" untuk dropdown. */
    public function getLabelAttribute(): string
    {
        return $this->code . ' — ' . $this->name;
    }
}

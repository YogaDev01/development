<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target SLA per severity (opsional per problem), dalam menit.
 *
 * Tidak ada nilai bawaan: target kontraktual diisi perusahaan lewat halaman
 * Pengaturan. Targetnya DISALIN ke tiket saat dibuat (snapshot), jadi
 * mengubah/menonaktifkan kebijakan tidak mengubah tiket yang sudah ada.
 */
class TroubleTicketSlaPolicy extends Model
{
    protected $table = 'trouble_ticket_sla_policies';

    /*
     | slm_target_value / slm_target_unit: disiapkan 13 Okt 2026, BELUM dipakai
     | — tidak dibaca kode mana pun dan tidak ditampilkan di layar. Kosongkan
     | sampai aturan SLM-nya ditetapkan.
     */
    protected $fillable = [
        'name', 'severity', 'problem_id', 'response_minutes', 'travel_minutes',
        'resolution_minutes', 'slm_target_value', 'slm_target_unit',
        'effective_from', 'status', 'created_by',
    ];

    protected $casts = [
        'effective_from'     => 'date',
        'response_minutes'   => 'integer',
        'travel_minutes'     => 'integer',
        'resolution_minutes' => 'integer',
        'slm_target_value'   => 'integer',
    ];

    /** Satuan target SLM yang dikenal. Belum dipakai di layar. */
    public const SLM_UNITS = ['HOUR' => 'Jam', 'DAY' => 'Hari'];

    /**
     * Nama yang ditampilkan di halaman Pengaturan. Baris lama belum punya
     * nama, jadi dijatuhkan ke label turunannya supaya barisnya tetap bisa
     * dikenali.
     */
    public function getDisplayNameAttribute(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        $sev = $this->severityRef?->name ?? $this->severity;
        $prob = $this->problem?->name;

        return collect([$sev, $prob])->filter()->implode(' · ') ?: 'Semua tiket';
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketProblem::class, 'problem_id');
    }

    public function severityRef(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketSeverity::class, 'severity', 'code');
    }

    /**
     * Kebijakan yang berlaku untuk tiket baru: ACTIVE, sudah efektif, untuk
     * severity itu. Yang spesifik problem menang atas yang umum; di antara
     * yang setara, yang paling baru efektif.
     */
    public static function applicableFor(string $severity, ?int $problemId): ?self
    {
        return static::query()
            ->where('status', 'ACTIVE')
            ->where('severity', $severity)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', now()->toDateString()))
            ->where(fn ($q) => $q->whereNull('problem_id')->when($problemId, fn ($q) => $q->orWhere('problem_id', $problemId)))
            ->orderByRaw('problem_id IS NULL')
            ->orderByRaw('effective_from IS NULL, effective_from DESC')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Kebijakan untuk tiket BARU (sejak 12 Okt 2026). Severity tidak lagi
     * diisi saat tiket dibuat — ia dihitung dari aging — jadi pencarian
     * kebijakan hanya memakai Problem: yang spesifik problem menang atas yang
     * umum; di antara yang setara, yang paling baru efektif.
     */
    public static function applicableForProblem(?int $problemId): ?self
    {
        return static::query()
            ->where('status', 'ACTIVE')
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', now()->toDateString()))
            ->where(fn ($q) => $q->whereNull('problem_id')->when($problemId, fn ($q) => $q->orWhere('problem_id', $problemId)))
            ->orderByRaw('problem_id IS NULL')
            ->orderByRaw('effective_from IS NULL, effective_from DESC')
            ->orderByDesc('id')
            ->first();
    }
}

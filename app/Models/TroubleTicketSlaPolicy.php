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

    protected $fillable = [
        'severity', 'problem_id', 'response_minutes', 'travel_minutes',
        'resolution_minutes', 'effective_from', 'status', 'created_by',
    ];

    protected $casts = [
        'effective_from'     => 'date',
        'response_minutes'   => 'integer',
        'travel_minutes'     => 'integer',
        'resolution_minutes' => 'integer',
    ];

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
}

<?php

namespace App\Models;

use App\Support\AccessRoles;
use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Trouble Ticket — gangguan teknis di sebuah site.
 *
 * Alur status (transisi HANYA lewat App\Services\TroubleTicketWorkflow):
 *
 *   OPEN ─(teknisi membuka tiket)→ AWAITING_PLAN ─(Visit/Non-Visit)→ PLANNED
 *   PLANNED ─Visit→ TRAVELING ─→ ON_SITE ─→ IN_PROGRESS
 *   PLANNED ─Non-Visit──────────────────→ IN_PROGRESS
 *   IN_PROGRESS ─(Ajukan Closed)→ PENDING_CLOSURE ─approve→ CLOSED
 *                                             └─reject─→ IN_PROGRESS
 *   status terbuka (kecuali TRAVELING/PENDING_CLOSURE) ─cancel→ CANCELLED
 *
 * SLA (lihat docs/modules/trouble-ticket.md):
 *   Response   = acknowledged_at − created_at (reported_at hanya informasi; keputusan 9 Okt 2026)
 *   Travel     = arrived_at − departed_at (per sesi kunjungan)
 *   Resolution = closed_at − progress_started_at (termasuk waktu tunggu approval)
 */
class TroubleTicket extends Model
{
    protected $table = 'trouble_tickets';

    protected $fillable = [
        'ticket_number', 'external_ref', 'site_id', 'customer_id', 'problem_id',
        'issue', 'device', 'severity', 'flag_alarm', 'description', 'reporter_name',
        'reported_at', 'status',
        'created_by', 'assigned_to', 'pic_id', 'assigned_at',
        'acknowledged_at', 'acknowledged_by', 'response_seconds',
        'visit_decision', 'visit_decided_by', 'visit_decided_at', 'visit_decision_reason',
        'visit_site', 'target_online',
        'progress_started_at', 'resolved_at', 'rca', 'sub_rca', 'action', 'approval',
        'resolution_summary', 'closed_at', 'closed_by', 'resolution_seconds',
        'cancelled_at', 'cancelled_by', 'cancel_reason',
        'sla_policy_id', 'sla_response_minutes', 'sla_travel_minutes', 'sla_resolution_minutes',
    ];

    protected $casts = [
        'assigned_at'          => 'datetime',
        'reported_at'          => 'datetime',
        'acknowledged_at'      => 'datetime',
        'visit_decided_at'     => 'datetime',
        'visit_site'           => 'date',
        'target_online'        => 'date',
        'progress_started_at'  => 'datetime',
        'resolved_at'          => 'datetime',
        'closed_at'            => 'datetime',
        'cancelled_at'         => 'datetime',
        'response_seconds'     => 'integer',
        'resolution_seconds'   => 'integer',
        'sla_response_minutes'   => 'integer',
        'sla_travel_minutes'     => 'integer',
        'sla_resolution_minutes' => 'integer',
    ];

    public const STATUSES = [
        'OPEN', 'AWAITING_PLAN', 'PLANNED', 'TRAVELING', 'ON_SITE',
        'IN_PROGRESS', 'PENDING_CLOSURE', 'CLOSED', 'CANCELLED',
    ];

    /** Status akhir — tidak ada transisi keluar pada MVP. */
    public const FINAL_STATUSES = ['CLOSED', 'CANCELLED'];

    /** Status pengajuan Closed terakhir (kolom `approval`). */
    public const APPROVALS = ['PENDING', 'APPROVED', 'REJECTED'];

    public const VISIT = 'VISIT';
    public const NON_VISIT = 'NON_VISIT';

    public const VISIT_DECISIONS = [
        self::VISIT     => 'Plan Visit',
        self::NON_VISIT => 'Non-Visit',
    ];

    /** Batas peringatan SLA: >= 75% target = warning, >= 100% = critical. */
    public const SLA_WARNING_RATIO = 0.75;

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketProblem::class, 'problem_id');
    }

    /** Severity disimpan sebagai kode (seperti skema referensi). */
    public function severityRef(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketSeverity::class, 'severity', 'code');
    }

    /** Baris master PIC teknisi (PIC internal site). */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(MasterPic::class, 'pic_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visit_decided_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TroubleTicketMessage::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TroubleTicketAttachment::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(TroubleTicketVisit::class)->orderBy('id');
    }

    /** Sesi kunjungan terakhir (MVP: paling banyak satu). */
    public function latestVisit(): HasOne
    {
        return $this->hasOne(TroubleTicketVisit::class)->latestOfMany();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TroubleTicketAssignment::class)->orderBy('id');
    }

    public function closureReviews(): HasMany
    {
        return $this->hasMany(TroubleTicketClosureReview::class)->orderBy('attempt_no');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Tiket yang boleh dilihat seorang user (keputusan bisnis 9 Okt 2026):
     *
     *   site → site_pics → master_pics → user → team
     *
     * Siapa pun yang SATU TEAM (anggota ACTIVE, atau leader team ACTIVE) dengan
     * salah satu PIC site melihat tiket site tersebut. Ditambah pembuat tiket,
     * teknisi yang ditugaskan, dan AccessRoles::TROUBLE_TICKET_FULL_SCOPE.
     *
     * Dipakai daftar, halaman detail, setiap aksi, DAN FileAccess — satu
     * aturan, supaya lampiran tidak pernah lebih terbuka dari tiketnya.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(AccessRoles::TROUBLE_TICKET_FULL_SCOPE)) {
            return $query;
        }

        $teamIds = self::teamIdsOf($user);

        return $query->where(function (Builder $w) use ($user, $teamIds) {
            $w->where('trouble_tickets.created_by', $user->id)
              ->orWhere('trouble_tickets.assigned_to', $user->id);

            if ($teamIds->isNotEmpty()) {
                $w->orWhereIn('trouble_tickets.site_id', function ($s) use ($teamIds) {
                    $s->select('site_pics.site_id')
                      ->from('site_pics')
                      ->join('master_pics', 'master_pics.id', '=', 'site_pics.pic_id')
                      ->join('team_members', 'team_members.user_id', '=', 'master_pics.user_id')
                      ->where('master_pics.status', 'ACTIVE')
                      ->where('team_members.status', 'ACTIVE')
                      ->whereIn('team_members.team_id', $teamIds);
                });
            }
        });
    }

    /** Team ACTIVE tempat user menjadi anggota ACTIVE atau leader. */
    public static function teamIdsOf(User $user): Collection
    {
        return Team::query()
            ->where('status', 'ACTIVE')
            ->where(function ($q) use ($user) {
                $q->where('leader_id', $user->id)
                  ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('status', 'ACTIVE'));
            })
            ->pluck('id');
    }

    /** Status yang belum final. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('trouble_tickets.status', self::FINAL_STATUSES);
    }

    /**
     * Tiket terbuka yang SEDANG melewati salah satu target SLA-nya. Tiket tanpa
     * target tidak pernah masuk sini — "belum diatur" bukan "terlambat".
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->where(function (Builder $w) {
            $w->where(function ($q) {
                $q->whereNull('acknowledged_at')
                  ->whereNotNull('sla_response_minutes')
                  ->whereRaw('TIMESTAMPDIFF(MINUTE, trouble_tickets.created_at, NOW()) >= sla_response_minutes');
            })->orWhere(function ($q) {
                $q->whereNotNull('progress_started_at')
                  ->whereNotNull('sla_resolution_minutes')
                  ->whereRaw('TIMESTAMPDIFF(MINUTE, progress_started_at, NOW()) >= sla_resolution_minutes');
            })->orWhere(function ($q) {
                $q->where('trouble_tickets.status', 'TRAVELING')
                  ->whereNotNull('sla_travel_minutes')
                  ->whereExists(function ($v) {
                      $v->selectRaw('1')->from('trouble_ticket_visits')
                        ->whereColumn('trouble_ticket_visits.trouble_ticket_id', 'trouble_tickets.id')
                        ->whereNull('trouble_ticket_visits.arrived_at')
                        ->whereRaw('TIMESTAMPDIFF(MINUTE, trouble_ticket_visits.departed_at, NOW()) >= trouble_tickets.sla_travel_minutes');
                  });
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | PENOMORAN
    |--------------------------------------------------------------------------
    */

    /** TT-0001/10/26 — urut per tahun, pola sama dengan Delivery Note. */
    public static function generateNumber(?Carbon $date = null): string
    {
        $date ??= now();
        $year = $date->format('Y');

        $no = DocumentSequence::next(
            "trouble-ticket:{$year}",
            fn () => static::whereYear('created_at', $year)->count(),
        );

        return 'TT-' . str_pad((string) $no, 4, '0', STR_PAD_LEFT)
            . '/' . $date->format('m') . '/' . $date->format('y');
    }

    /*
    |--------------------------------------------------------------------------
    | SLA
    |--------------------------------------------------------------------------
    */

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    /**
     * Keadaan satu ukuran SLA, siap ditampilkan.
     *
     * @return array{state:string, seconds:?int, since:?Carbon, target:?int, level:string}
     *   state : 'done' (durasi final) | 'running' (timer berjalan) | 'na' (belum/tidak berlaku)
     *   level : 'none' (tanpa target) | 'ok' | 'warning' | 'critical'
     */
    public function slaMetric(string $metric, ?Carbon $now = null): array
    {
        $now ??= now();

        [$start, $end, $final, $target] = match ($metric) {
            'response'   => [$this->created_at, $this->acknowledged_at, $this->response_seconds, $this->sla_response_minutes],
            'resolution' => [$this->progress_started_at, $this->closed_at, $this->resolution_seconds, $this->sla_resolution_minutes],
            'travel'     => [$this->latestVisit?->departed_at, $this->latestVisit?->arrived_at, $this->latestVisit?->travel_seconds, $this->sla_travel_minutes],
        };

        if ($start === null) {
            return ['state' => 'na', 'seconds' => null, 'since' => null, 'target' => $target, 'level' => 'none'];
        }

        if ($end !== null) {
            $seconds = $final ?? max(0, $end->getTimestamp() - $start->getTimestamp());
            $state = 'done';
        } elseif ($this->isFinal()) {
            // Tiket dibatalkan sebelum ukuran ini selesai: tidak ada durasi final.
            return ['state' => 'na', 'seconds' => null, 'since' => null, 'target' => $target, 'level' => 'none'];
        } else {
            $seconds = max(0, $now->getTimestamp() - $start->getTimestamp());
            $state = 'running';
        }

        return [
            'state'   => $state,
            'seconds' => $seconds,
            'since'   => $state === 'running' ? $start : null,
            'target'  => $target,
            'level'   => self::slaLevel($seconds, $target, $state),
        ];
    }

    public static function slaLevel(int $seconds, ?int $targetMinutes, string $state): string
    {
        if (! $targetMinutes) {
            return 'none';
        }

        $ratio = $seconds / ($targetMinutes * 60);

        if ($ratio >= 1) {
            return 'critical';
        }

        return $state === 'running' && $ratio >= self::SLA_WARNING_RATIO ? 'warning' : 'ok';
    }

    /** Lama menunggu approval closed (pengajuan terakhir → keputusan/sekarang). */
    public function approvalWaitSeconds(?Carbon $now = null): ?int
    {
        if (! $this->resolved_at) {
            return null;
        }

        $end = $this->closed_at ?? ($this->status === 'PENDING_CLOSURE' ? ($now ?? now()) : null);

        return $end ? max(0, $end->getTimestamp() - $this->resolved_at->getTimestamp()) : null;
    }

    /** "1j 05m", "2h 3j", "45dtk" (hari/jam/menit/detik) — format ringkas durasi. */
    public static function formatDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);

        if ($d > 0) {
            return "{$d}h {$h}j";
        }
        if ($h > 0) {
            return sprintf('%dj %02dm', $h, $m);
        }
        if ($m > 0) {
            return "{$m}m";
        }

        return $seconds . 'dtk';
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS TAMPILAN
    |--------------------------------------------------------------------------
    */

    /** Nama severity dari tabelnya; jatuh ke kodenya bila baris master tidak ada. */
    public function getSeverityLabelAttribute(): string
    {
        return $this->severityRef?->name ?? (string) $this->severity;
    }

    /** Level severity (1 = paling parah) untuk kelas warna tt-sev-*. */
    public function getSeverityLevelAttribute(): int
    {
        return (int) ($this->severityRef?->level ?? 9);
    }

    public function getVisitDecisionLabelAttribute(): ?string
    {
        return $this->visit_decision ? (self::VISIT_DECISIONS[$this->visit_decision] ?? $this->visit_decision) : null;
    }
}

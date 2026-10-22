<?php

namespace App\Models;

use App\Support\AccessRoles;
use App\Support\DocumentSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Trouble Ticket — gangguan teknis di sebuah site.
 *
 * Alur status — 7 tahap (keputusan 12 Okt 2026). Transisi HANYA lewat
 * App\Services\TroubleTicketWorkflow:
 *
 *   OPEN ─(teknisi pilih Remote)──────────────────→ REMOTE
 *        └─(teknisi buat Plan Visit)──────────────→ PLAN
 *   PLAN ─(tanggal plan visit tiba, otomatis)─────→ TRIP
 *   TRIP ─(mulai perjalanan → on site → mulai progres)→ IN_PROGRESS
 *   REMOTE / IN_PROGRESS ─(form close dikirim)────→ PROGRESS_DONE
 *   PROGRESS_DONE ─(supervisor approve)───────────→ CLOSED
 *                 └─(supervisor tolak)────────────→ REMOTE / IN_PROGRESS
 *
 * Tidak ada approval plan (dihapus 12 Okt 2026); satu-satunya approval ada
 * di penutupan tiket, dan hanya supervisor yang boleh.
 *
 * SLA (lihat docs/modules/trouble-ticket.md):
 *   Response   = acknowledged_at − created_at (berhenti saat form close dikirim)
 *   Travel     = travel_arrived_at − travel_started_at (geser "mulai perjalanan"
 *                sampai konfirmasi On Site; dibatalkan = jejaknya dikosongkan)
 *   Resolution = closed_at − progress_started_at (berhenti saat supervisor approve)
 */
class TroubleTicket extends Model
{
    protected $table = 'trouble_tickets';

    protected $fillable = [
        'ticket_number', 'external_ref', 'site_id', 'customer_id', 'problem_id',
        'issue_id', 'issue', 'device_id', 'device', 'severity', 'flag_alarm',
        'description', 'reporter_name', 'reported_at', 'status',
        'created_by', 'assigned_to', 'pic_id', 'assigned_at',
        'acknowledged_at', 'acknowledged_by', 'response_seconds',
        'visit_decision', 'visit_decided_by', 'visit_decided_at', 'visit_decision_reason',
        'visit_site', 'target_online', 'plan_approved_at', 'plan_approved_by',
        // Create Plan — sumber dana, estimasi biaya & approval supervisor
        // yang membuahkan Cost Request draft (20 Okt 2026).
        'budget_plan_id', 'plan_cost_total',
        'plan_budget_status', 'plan_budget_notes', 'budget_request_id',
        'travel_started_at', 'travel_arrived_at', 'travel_seconds',
        'travel_cancelled_at', 'travel_cancel_reason',
        'progress_started_at', 'resolved_at', 'progress_done_at',
        'approved_at', 'approved_by', 'rca_id', 'sub_rca_id',
        'rca', 'sub_rca', 'action', 'approval',
        'resolution_summary', 'closed_at', 'closed_by', 'resolution_seconds',
        'chat_locked_at', 'chat_locked_by',
        'sla_policy_id', 'sla_response_minutes', 'sla_travel_minutes', 'sla_resolution_minutes',
    ];

    protected $casts = [
        'assigned_at'          => 'datetime',
        'reported_at'          => 'datetime',
        'acknowledged_at'      => 'datetime',
        'visit_decided_at'     => 'datetime',
        'visit_site'           => 'date',
        'target_online'        => 'date',
        'plan_approved_at'     => 'datetime',
        'travel_started_at'    => 'datetime',
        'travel_arrived_at'    => 'datetime',
        'travel_cancelled_at'  => 'datetime',
        'travel_seconds'       => 'integer',
        'progress_started_at'  => 'datetime',
        'resolved_at'          => 'datetime',
        'progress_done_at'     => 'datetime',
        'approved_at'          => 'datetime',
        'closed_at'            => 'datetime',
        'chat_locked_at'       => 'datetime',
        'response_seconds'     => 'integer',
        'plan_cost_total'      => 'integer',
        'resolution_seconds'   => 'integer',
        'sla_response_minutes'   => 'integer',
        'sla_travel_minutes'     => 'integer',
        'sla_resolution_minutes' => 'integer',
    ];

    /** Tujuh tahap alur tiket. */
    public const STATUSES = ['OPEN', 'REMOTE', 'PLAN', 'TRIP', 'IN_PROGRESS', 'PROGRESS_DONE', 'CLOSED'];

    /**
     * Tahap yang boleh mengirim form close. PLAN ikut (keputusan 12 Okt 2026):
     * gangguan kadang beres sebelum tanggal kunjungan tiba. TRIP ikut
     * (keputusan 15 Okt 2026): gangguan bisa beres saat teknisi masih di
     * jalan — perjalanan yang sedang berjalan dibatalkan otomatis.
     */
    public const CLOSABLE = ['OPEN', 'REMOTE', 'PLAN', 'TRIP', 'IN_PROGRESS'];

    /* Status approval plan & biaya oleh supervisor (20 Okt 2026). null =
       plan lama tanpa biaya; perilakunya seperti sebelum aturan ini ada. */
    public const PLAN_BUDGET_PENDING  = 'PENDING';
    public const PLAN_BUDGET_APPROVED = 'APPROVED';
    public const PLAN_BUDGET_REJECTED = 'REJECTED';

    /** Plan & biaya sudah diajukan tapi belum diputus supervisor. */
    public function planBudgetPending(): bool
    {
        return $this->plan_budget_status === self::PLAN_BUDGET_PENDING;
    }

    /** Status akhir — tidak ada transisi keluar. */
    public const FINAL_STATUSES = ['CLOSED'];

    /** Keadaan approval PENUTUPAN terakhir (kolom `approval`). */
    public const APPROVALS = ['PENDING', 'APPROVED', 'REJECTED'];

    public const VISIT = 'VISIT';

    public const NON_VISIT = 'NON_VISIT';

    public const VISIT_DECISIONS = [
        self::VISIT     => 'Plan Visit',
        self::NON_VISIT => 'Non-Visit',
    ];

    /** Batas peringatan SLA: >= 75% target = warning, >= 100% = critical. */
    public const SLA_WARNING_RATIO = 0.75;

    /**
     * Severity dihitung dari AGING tiket, bukan diisi pelapor (keputusan
     * 12 Okt 2026). Aging = hari penuh sejak Date Open sampai tiket ditutup
     * (atau sampai sekarang bila masih terbuka).
     *
     *   [hari minimum, label, level]  — level 1 = paling parah (warna tt-sev-*)
     */
    public const SEVERITY_AGING = [
        [8, 'Kritis', 1],
        [3, 'Major',  2],
        [1, 'Minor',  3],
        [0, 'Rendah', 4],
    ];

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

    /** Initial Problem — gejala awal yang dilaporkan. */
    public function issueRef(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketIssue::class, 'issue_id');
    }

    public function deviceRef(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketDevice::class, 'device_id');
    }

    public function rcaRef(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketRca::class, 'rca_id');
    }

    public function subRcaRef(): BelongsTo
    {
        return $this->belongsTo(TroubleTicketSubRca::class, 'sub_rca_id');
    }

    /**
     * Severity lama (kode master). Tiket baru tidak mengisinya lagi — severity
     * kini dihitung dari aging. Relasi dipertahankan untuk baris lama.
     */
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

    /** Sumber dana estimasi biaya plan visit (Create Plan, 20 Okt 2026). */
    public function budgetPlan(): BelongsTo
    {
        return $this->belongsTo(BudgetPlan::class);
    }

    /** Cost Request draft yang terbentuk saat supervisor menyetujui plan. */
    public function budgetRequest(): BelongsTo
    {
        return $this->belongsTo(BudgetRequest::class);
    }

    /** Baris estimasi biaya plan visit — sub-item budget, keterangan, harga, qty. */
    public function planCosts(): HasMany
    {
        return $this->hasMany(TroubleTicketPlanCost::class);
    }

    /**
     * Pengajuan biaya (pra-Cost Request) tiket ini — banyak per tiket. Terlepas
     * dari Plan Visit; trouble_tickets.budget_request_id / plan_costs hanya data lama.
     */
    public function requests(): HasMany
    {
        return $this->hasMany(TroubleTicketRequest::class)->orderBy('id');
    }

    /**
     * Tiket yang melahirkan Cost Request tertentu: lewat pengajuan (baru) atau
     * kolom budget_request_id di tiket (data lama sebelum Pengajuan Biaya).
     */
    public function scopeForCostRequest($query, int $costRequestId)
    {
        // Tabel pengajuan belum tentu ada (migrasi menyusul di host): tanpa itu hanya jalur lama yang berlaku.
        static $adaPengajuan = null;
        $adaPengajuan ??= \Illuminate\Support\Facades\Schema::hasTable('trouble_ticket_requests');

        return $query->where(function ($q) use ($costRequestId, $adaPengajuan) {
            $q->where('budget_request_id', $costRequestId);

            if ($adaPengajuan) {
                $q->orWhereHas('requests', fn ($r) => $r->where('budget_request_id', $costRequestId));
            }
        });
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

    /** Leader team yang menyetujui plan (alur lama, sebelum 12 Okt 2026). */
    public function planApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'plan_approved_by');
    }

    /** Supervisor yang menyetujui penutupan tiket. */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* Kolom chat_locked_at / chat_locked_by peninggalan fitur Stop Chat yang
       dihapus 19 Okt 2026. Kolomnya dibiarkan (migrasi tidak pernah diubah),
       tapi tidak ada lagi kode yang mengisi atau membacanya. */

    public function messages(): HasMany
    {
        return $this->hasMany(TroubleTicketMessage::class);
    }

    /**
     * Tanggal paling awal yang boleh dipilih sebagai plan visit
     * (permintaan 19 Okt 2026).
     *
     * Plan pertama tidak boleh ke tanggal lampau: minimal hari ini. Saat
     * tanggalnya diubah atau di-reschedule, tanggal lampau boleh dipilih —
     * tapi tidak boleh lebih awal dari hari plan itu dibuat, jadi jadwal
     * tidak bisa digeser ke sebelum plannya ada.
     */
    public function planMinDate(): Carbon
    {
        if (! in_array($this->status, ['PLAN', 'TRIP'], true)) {
            return Carbon::today();
        }

        $dibuat = $this->messages()
            ->where('type', TroubleTicketMessage::TYPE_EVENT)
            ->where('event', 'plan_created')
            ->orderBy('id')
            ->value('created_at');

        return Carbon::parse($dibuat ?? $this->created_at)->startOfDay();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TroubleTicketAttachment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TroubleTicketAssignment::class)->orderBy('id');
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
     * KECUALI user biasa (bukan pengawas, keputusan 15 Okt 2026): hanya tiket
     * yang DITUGASKAN ke dirinya. Tiket milik teknisi lain tidak pernah muncul,
     * termasuk tiket yang ia buat sendiri lalu di-change ke teknisi lain —
     * begitu di-change, tiket pindah ke daftar teknisi baru.
     *
     * Pengawas (leader team ACTIVE, AccessRoles::TROUBLE_TICKET_SUPERVISE, atau
     * TROUBLE_TICKET_APPROVE) tetap memakai rantai site → PIC → team supaya
     * approval penutupan dan reassign tidak macet.
     *
     * Dipakai daftar, halaman detail, setiap aksi, DAN FileAccess — satu
     * aturan, supaya lampiran tidak pernah lebih terbuka dari tiketnya.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(AccessRoles::TROUBLE_TICKET_FULL_SCOPE)) {
            return $query;
        }

        if (! self::supervises($user)) {
            return $query->where('trouble_tickets.assigned_to', $user->id);
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

    /**
     * Pengawas tiket: leader team ACTIVE, atau pemegang role pengawas /
     * penyetuju penutupan. Hanya mereka yang melihat tiket di luar tiket yang
     * ditugaskan ke dirinya sendiri (keputusan 15 Okt 2026).
     */
    public static function supervises(User $user): bool
    {
        return $user->hasRole(AccessRoles::TROUBLE_TICKET_SUPERVISE)
            || $user->hasRole(AccessRoles::TROUBLE_TICKET_APPROVE)
            || Team::query()
                ->where('status', 'ACTIVE')
                ->where('leader_id', $user->id)
                ->exists();
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
                // Remote (Non-Visit) tidak diukur Resolution-nya, jadi tidak
                // pernah dihitung "Lewat SLA" karena ukuran itu.
                $q->whereNotNull('progress_started_at')
                  ->where(fn ($r) => $r->whereNull('visit_decision')->orWhere('visit_decision', '!=', self::NON_VISIT))
                  ->whereNotNull('sla_resolution_minutes')
                  ->whereRaw('TIMESTAMPDIFF(MINUTE, progress_started_at, NOW()) >= sla_resolution_minutes');
            })->orWhere(function ($q) {
                // Travel: dari geser "mulai perjalanan" sampai konfirmasi On Site.
                $q->whereNull('travel_arrived_at')
                  ->whereNotNull('travel_started_at')
                  ->whereNotNull('sla_travel_minutes')
                  ->whereRaw('TIMESTAMPDIFF(MINUTE, travel_started_at, NOW()) >= sla_travel_minutes');
            });
        });
    }

    /**
     * Tiket PLAN yang tanggal plan visit-nya sudah tiba — teknisinya boleh
     * menggeser "Swipe for travel" (TroubleTicketWorkflow::startTravel()),
     * yang sekaligus memulai perjalanan (keputusan 20 Okt 2026). Tiket tidak
     * naik sendiri ke tahap Trip (keputusan 19 Okt 2026).
     */
    public function scopeDueTrip(Builder $query): Builder
    {
        return $query->where('trouble_tickets.status', 'PLAN')
            ->whereNotNull('visit_site')
            ->whereDate('visit_site', '<=', now()->toDateString());
    }

    /*
    |--------------------------------------------------------------------------
    | PENOMORAN
    |--------------------------------------------------------------------------
    */

    /**
     * Nomor tiket: `T.1026008` — T, bulan (mm), tahun (yy), lalu urutan
     * (minimal 3 digit, melebar sendiri setelah 999). Format ini berlaku sejak
     * 15 Okt 2026; sebelumnya `T.0000011026` (urutan di depan bulan/tahun).
     *
     * Urutannya **berjalan terus**, tidak di-reset tiap bulan atau tahun:
     * angkanya adalah nomor tiket ke berapa, sesuai permintaan 14 Okt 2026.
     * Karena itu rangkaiannya satu (`trouble-ticket`), disemai dari jumlah
     * tiket yang sudah ada supaya penomoran melanjutkan, bukan mengulang.
     *
     * Tiket lama memakai format `TT-0001/10/26`; keduanya tidak akan pernah
     * bertabrakan karena bentuknya berbeda, dan nomor lama dibiarkan apa adanya.
     */
    public static function generateNumber(?Carbon $date = null): string
    {
        $date ??= now();

        $no = DocumentSequence::next('trouble-ticket', fn () => static::count());

        // T.<bulan><tahun><urutan> — mis. T.1026008 (15 Okt 2026). Urutannya tetap
        // BERJALAN TERUS (tidak reset per bulan/tahun, keputusan 14 Okt 2026) dan
        // lebarnya minimal 3 digit, bertambah sendiri begitu melewati 999.
        return 'T.' . $date->format('m') . $date->format('y')
            . str_pad((string) $no, 3, '0', STR_PAD_LEFT);
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
            'travel'     => [$this->travel_started_at, $this->travel_arrived_at, $this->travel_seconds, $this->sla_travel_minutes],
        };

        // Jalur Remote (Non-Visit) tidak diukur Resolution-nya: penanganan
        // jarak jauh tidak melewati tahap pengerjaan di site, jadi durasinya
        // tidak sebanding dengan tiket visit (keputusan 16 Okt 2026).
        if ($start === null || ($metric === 'resolution' && $this->visit_decision === self::NON_VISIT)) {
            return ['state' => 'na', 'seconds' => null, 'since' => null, 'target' => $target, 'level' => 'none'];
        }

        if ($end !== null) {
            $seconds = $final ?? max(0, $end->getTimestamp() - $start->getTimestamp());
            $state = 'done';
        } elseif ($this->isFinal()) {
            // Tiket ditutup sebelum ukuran ini selesai: tidak ada durasi final.
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

    /** Lama menunggu approval plan (pengajuan plan → keputusan leader/sekarang). */
    public function approvalWaitSeconds(?Carbon $now = null): ?int
    {
        if (! $this->visit_decided_at) {
            return null;
        }

        $end = $this->plan_approved_at ?? ($this->status === 'APPROVED' ? ($now ?? now()) : null);

        return $end ? max(0, $end->getTimestamp() - $this->visit_decided_at->getTimestamp()) : null;
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

    /**
     * Aging tiket dalam hari penuh: Date Open → penutupan (atau sekarang).
     * Date Open = reported_at bila diisi, selain itu created_at.
     */
    public function getAgingDaysAttribute(): int
    {
        $mulai = $this->reported_at ?? $this->created_at;

        if (! $mulai) {
            return 0;
        }

        $akhir = $this->closed_at ?? now();

        return max(0, (int) $mulai->copy()->startOfDay()->diffInDays($akhir->copy()->startOfDay(), false));
    }

    /** [label, level] severity dari jumlah hari aging. */
    public static function severityFromAging(int $hari): array
    {
        // Tangga diambil dari master severity bila sudah diatur di Pengaturan;
        // kalau belum, SEVERITY_AGING di atas yang dipakai.
        foreach (TroubleTicketSeverity::agingSteps() as [$minimal, $label, $level]) {
            if ($hari >= $minimal) {
                return [$label, $level];
            }
        }

        return ['Rendah', 4];
    }

    /** Nama severity — dihitung dari aging tiket. */
    public function getSeverityLabelAttribute(): string
    {
        return self::severityFromAging($this->aging_days)[0];
    }

    /** Level severity (1 = paling parah) untuk kelas warna tt-sev-*. */
    public function getSeverityLevelAttribute(): int
    {
        return self::severityFromAging($this->aging_days)[1];
    }

    /** Teks Initial Problem: master bila ada, selain itu isian bebas lama. */
    public function getIssueLabelAttribute(): ?string
    {
        return $this->issueRef?->name ?? $this->issue;
    }

    /** Teks Device: master bila ada, selain itu isian bebas lama. */
    public function getDeviceLabelAttribute(): ?string
    {
        return $this->deviceRef?->name ?? $this->device;
    }

    public function getRcaLabelAttribute(): ?string
    {
        return $this->rcaRef?->name ?? $this->rca;
    }

    public function getSubRcaLabelAttribute(): ?string
    {
        return $this->subRcaRef?->name ?? $this->sub_rca;
    }

    public function getVisitDecisionLabelAttribute(): ?string
    {
        return $this->visit_decision ? (self::VISIT_DECISIONS[$this->visit_decision] ?? $this->visit_decision) : null;
    }
}

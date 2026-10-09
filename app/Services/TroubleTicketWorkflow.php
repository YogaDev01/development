<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SitePic;
use App\Models\Team;
use App\Models\TroubleTicket;
use App\Models\TroubleTicketAssignment;
use App\Models\TroubleTicketAttachment;
use App\Models\TroubleTicketProblem;
use App\Models\TroubleTicketSeverity;
use App\Models\TroubleTicketClosureReview;
use App\Models\TroubleTicketMessage;
use App\Models\TroubleTicketSlaPolicy;
use App\Models\TroubleTicketVisit;
use App\Models\User;
use App\Support\AccessRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya tempat status Trouble Ticket berpindah.
 *
 * Controller tidak pernah mengubah status/timestamp workflow sendiri; ia
 * memanggil method di sini. Setiap transisi:
 *
 *   1. mengunci baris tiket (SELECT … FOR UPDATE) di dalam transaksi,
 *   2. memeriksa ulang hak pelaku & status asal pada baris yang terkunci
 *      (bukan pada salinan yang dibaca sebelum menunggu kunci),
 *   3. menulis timestamp dari jam SERVER, sekali — permintaan ulang gagal di
 *      pemeriksaan status, jadi tidak pernah menimpa timestamp yang ada,
 *   4. menulis kejadian timeline di transaksi yang sama.
 *
 * Kesalahan keadaan dilempar sebagai ValidationException (kunci 'workflow'):
 * form biasa kembali dengan pesan, permintaan AJAX menerima 422. Hak akses
 * yang tidak terpenuhi dilempar sebagai AuthorizationException (403).
 */
class TroubleTicketWorkflow
{
    /*
    |--------------------------------------------------------------------------
    | HAK AKSES
    |--------------------------------------------------------------------------
    */

    public function canView(User $user, TroubleTicket $ticket): bool
    {
        return TroubleTicket::visibleTo($user)->whereKey($ticket->getKey())->exists();
    }

    /** Aksi operasional (respon, kunjungan, progress, ajukan closed) — hanya teknisi aktif. */
    public function isTechnician(User $user, TroubleTicket $ticket): bool
    {
        return (int) $user->id === (int) $ticket->assigned_to;
    }

    /**
     * Pengawas tiket: leader team ACTIVE tempat teknisi menjadi anggota ACTIVE
     * (keputusan 9 Okt 2026), ditambah AccessRoles::TROUBLE_TICKET_SUPERVISE.
     */
    public function canSupervise(User $user, TroubleTicket $ticket): bool
    {
        return $user->hasRole(AccessRoles::TROUBLE_TICKET_SUPERVISE)
            || $this->supervisorIdsFor((int) $ticket->assigned_to)->contains($user->id);
    }

    /** Approve/reject Closed: pengawas, dan BUKAN pengaju pengajuan itu sendiri. */
    public function canReview(User $user, TroubleTicket $ticket, ?TroubleTicketClosureReview $pending = null): bool
    {
        $pending ??= $ticket->closureReviews()->where('review_status', 'PENDING')->first();

        return $pending !== null
            && (int) $pending->requested_by !== (int) $user->id
            && $this->canSupervise($user, $ticket);
    }

    public function canMessage(User $user, TroubleTicket $ticket): bool
    {
        return ! $ticket->isFinal() && $this->canView($user, $ticket);
    }

    /** Teknisi wajib memilih Visit/Non-Visit sebelum bisa melakukan apa pun. */
    public function mustDecide(User $user, TroubleTicket $ticket): bool
    {
        return $this->isTechnician($user, $ticket)
            && $ticket->status === 'AWAITING_PLAN'
            && $ticket->visit_decision === null;
    }

    /** Leader team ACTIVE dari teknisi (user ACTIVE). */
    public function supervisorsFor(int $technicianId): Collection
    {
        return User::query()
            ->where('status', 'ACTIVE')
            ->whereIn('id', $this->leaderQuery($technicianId))
            ->orderBy('name')
            ->get();
    }

    public function supervisorIdsFor(int $technicianId): Collection
    {
        return $this->leaderQuery($technicianId)->pluck('leader_id')->map(fn ($id) => (int) $id);
    }

    private function leaderQuery(int $technicianId)
    {
        return Team::query()
            ->select('leader_id')
            ->where('status', 'ACTIVE')
            ->whereNotNull('leader_id')
            ->whereHas('members', fn ($m) => $m->where('user_id', $technicianId)->where('status', 'ACTIVE'));
    }

    /**
     * Teknisi yang boleh ditugaskan di sebuah site (keputusan 9 Okt 2026):
     * akun ACTIVE milik PIC internal ACTIVE yang terdaftar di site_pics site itu.
     */
    public function eligibleTechnicians(int $siteId): Collection
    {
        return User::query()
            ->where('status', 'ACTIVE')
            ->whereIn('id', SitePic::query()
                ->join('master_pics', 'master_pics.id', '=', 'site_pics.pic_id')
                ->where('site_pics.site_id', $siteId)
                ->where('master_pics.type', 'internal')
                ->where('master_pics.status', 'ACTIVE')
                ->whereNotNull('master_pics.user_id')
                ->select('master_pics.user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function isEligibleTechnician(int $siteId, int $userId): bool
    {
        return $this->eligibleTechnicians($siteId)->contains('id', $userId);
    }

    /** Baris master_pics (PIC internal ACTIVE site itu) milik akun teknisi — untuk kolom pic_id. */
    public function picIdFor(int $siteId, int $userId): ?int
    {
        $id = SitePic::query()
            ->join('master_pics', 'master_pics.id', '=', 'site_pics.pic_id')
            ->where('site_pics.site_id', $siteId)
            ->where('master_pics.type', 'internal')
            ->where('master_pics.status', 'ACTIVE')
            ->where('master_pics.user_id', $userId)
            ->orderBy('master_pics.id')
            ->value('master_pics.id');

        return $id ? (int) $id : null;
    }

    /*
    |--------------------------------------------------------------------------
    | PEMBUATAN
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array  $data  hasil validasi controller: site_id, assigned_to, problem_id,
     *                       severity, issue, description, external_ref?, device?,
     *                       flag_alarm?, reported_at?, reporter_name?
     * @param  UploadedFile[]  $files
     */
    public function create(User $actor, array $data, array $files = []): TroubleTicket
    {
        // Referensi dari klien tidak dipercaya: site, problem, severity, dan
        // teknisi diperiksa ulang terhadap master dan relasinya.
        $site = Site::where('status', 'ACTIVE')->find($data['site_id']);
        if (! $site) {
            $this->fail('Site tidak ditemukan atau tidak aktif.', 'site_id');
        }

        $problemId = (int) $data['problem_id'];
        if (! TroubleTicketProblem::where('status', 'ACTIVE')->whereKey($problemId)->exists()) {
            $this->fail('Problem tidak ditemukan atau tidak aktif.', 'problem_id');
        }

        if (! TroubleTicketSeverity::where('status', 'ACTIVE')->where('code', $data['severity'])->exists()) {
            $this->fail('Severity tidak ditemukan atau tidak aktif.', 'severity');
        }

        $picId = $this->picIdFor($site->id, (int) $data['assigned_to']);
        if (! $picId || ! $this->isEligibleTechnician($site->id, (int) $data['assigned_to'])) {
            $this->fail('Teknisi harus PIC internal aktif yang terdaftar di site ini.', 'assigned_to');
        }

        $policy = TroubleTicketSlaPolicy::applicableFor($data['severity'], $problemId);
        $stored = $this->storeFiles($files);

        $ticket = $this->withStoredFiles($stored, function () use ($actor, $data, $site, $problemId, $picId, $policy, $stored) {
            $now = now();

            $ticket = TroubleTicket::create([
                'ticket_number'          => TroubleTicket::generateNumber($now),
                'external_ref'           => $data['external_ref'] ?? null,
                'site_id'                => $site->id,
                'customer_id'            => $site->customer_id,
                'problem_id'             => $problemId,
                'severity'               => $data['severity'],
                'issue'                  => $data['issue'],
                'device'                 => $data['device'] ?? null,
                'flag_alarm'             => $data['flag_alarm'] ?? null,
                'description'            => $data['description'],
                'reporter_name'          => $data['reporter_name'] ?? null,
                'reported_at'            => $data['reported_at'] ?? $now,
                'status'                 => 'OPEN',
                'created_by'             => $actor->id,
                'assigned_to'            => (int) $data['assigned_to'],
                'pic_id'                 => $picId,
                'assigned_at'            => $now,
                'sla_policy_id'          => $policy?->id,
                'sla_response_minutes'   => $policy?->response_minutes,
                'sla_travel_minutes'     => $policy?->travel_minutes,
                'sla_resolution_minutes' => $policy?->resolution_minutes,
            ]);

            TroubleTicketAssignment::create([
                'trouble_ticket_id' => $ticket->id,
                'assigned_to'       => $ticket->assigned_to,
                'assigned_by'       => $actor->id,
                'assigned_at'       => $now,
            ]);

            $created = $this->event($ticket, $actor, 'created', $ticket->issue, [
                'severity' => $ticket->severity,
                'site'     => $site->tid,
            ]);
            $this->attach($ticket, $created, $actor, $stored);

            $this->event($ticket, $actor, 'assigned', null, [
                'to_id'   => $ticket->assigned_to,
                'to_name' => User::whereKey($ticket->assigned_to)->value('name'),
            ]);

            return $ticket;
        });

        $this->notify(
            [$ticket->technician],
            $ticket,
            'Trouble Ticket baru untuk Anda',
            "{$ticket->ticket_number} — {$site->tid} {$site->sitename}: {$ticket->issue}"
        );

        return $ticket;
    }

    /*
    |--------------------------------------------------------------------------
    | RESPON & KEPUTUSAN KUNJUNGAN
    |--------------------------------------------------------------------------
    */

    /**
     * Dipanggil setiap kali tiket dibuka. Hanya akses TEKNISI AKTIF yang
     * dihitung; user lain tidak pernah mengubah apa pun. Ditulis sekali:
     * acknowledged_at tiket (sekali seumur tiket) dan responded_at
     * penugasan aktif (sekali per penugasan). Mengembalikan true bila ada
     * yang tercatat pada panggilan ini.
     */
    public function recordAccess(TroubleTicket $ticket, User $user): bool
    {
        if (! $this->isTechnician($user, $ticket) || $ticket->isFinal()) {
            return false;
        }

        $assignment = $ticket->assignments()->whereNull('unassigned_at')->first();
        if ($ticket->acknowledged_at !== null && ($assignment === null || $assignment->responded_at !== null)) {
            return false; // jalur cepat: sudah tercatat, tanpa kunci
        }

        return DB::transaction(function () use ($ticket, $user) {
            $t = $this->lock($ticket);

            if (! $this->isTechnician($user, $t) || $t->isFinal()) {
                return false;
            }

            $now = now();
            $recorded = false;

            $assignment = TroubleTicketAssignment::where('trouble_ticket_id', $t->id)
                ->whereNull('unassigned_at')
                ->lockForUpdate()
                ->first();

            if ($assignment && $assignment->responded_at === null && (int) $assignment->assigned_to === (int) $user->id) {
                $assignment->update(['responded_at' => $now]);
                $recorded = true;
            }

            $changes = [];
            if ($t->acknowledged_at === null) {
                $changes += [
                    'acknowledged_at' => $now,
                    'acknowledged_by' => $user->id,
                    'response_seconds'   => max(0, $now->getTimestamp() - $t->created_at->getTimestamp()),
                ];
                $recorded = true;
            }
            if ($t->status === 'OPEN') {
                $changes['status'] = 'AWAITING_PLAN';
            }

            if ($changes) {
                $t->update($changes);
            }

            if ($recorded) {
                $this->event($t, $user, 'responded', null, [
                    'first' => isset($changes['acknowledged_at']),
                ]);
            }

            $ticket->setRawAttributes($t->getAttributes(), true);

            return $recorded;
        });
    }

    /**
     * @param  ?string  $visitSite     tanggal rencana kunjungan (Y-m-d) — wajib untuk Plan Visit
     * @param  ?string  $targetOnline  perkiraan site kembali online (Y-m-d), opsional
     */
    public function decideVisit(TroubleTicket $ticket, User $user, string $decision, ?string $reason, ?string $visitSite = null, ?string $targetOnline = null): void
    {
        $this->assertDecision($decision, $reason, false, $visitSite);

        DB::transaction(function () use ($ticket, $user, $decision, $reason, $visitSite, $targetOnline) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->isTechnician($user, $t), 'Hanya teknisi yang ditugaskan yang dapat memilih Visit / Non-Visit.');

            if ($t->visit_decision !== null) {
                $this->fail('Keputusan kunjungan sudah dicatat dan terkunci. Hubungi leader team untuk koreksi.');
            }
            $this->expect($t, ['AWAITING_PLAN'], 'Tiket tidak sedang menunggu keputusan kunjungan.');

            $t->update([
                'visit_decision'        => $decision,
                'visit_decided_by'      => $user->id,
                'visit_decided_at'      => now(),
                'visit_decision_reason' => $reason,
                'visit_site'            => $decision === TroubleTicket::VISIT ? $visitSite : null,
                'target_online'         => $targetOnline,
                'status'                => 'PLANNED',
            ]);

            $this->event($t, $user, 'visit_decided', $reason, [
                'decision'      => $decision,
                'visit_site'    => $t->visit_site?->toDateString(),
                'target_online' => $t->target_online?->toDateString(),
            ]);
        });
    }

    /**
     * Koreksi keputusan oleh pengawas — sebelum ada kunjungan/progress.
     * Keputusan lama tidak hilang: tersimpan di metadata kejadiannya dan di
     * kejadian 'visit_decided' semula.
     */
    public function correctDecision(TroubleTicket $ticket, User $user, string $decision, string $reason, ?string $visitSite = null): void
    {
        $this->assertDecision($decision, $reason, true, $visitSite);

        DB::transaction(function () use ($ticket, $user, $decision, $reason, $visitSite) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->canSupervise($user, $t), 'Hanya leader team teknisi yang dapat mengoreksi keputusan kunjungan.');
            $this->expect($t, ['PLANNED'], 'Keputusan hanya bisa dikoreksi sebelum teknisi berangkat atau memulai progress.');

            if ($t->visit_decision === $decision) {
                $this->fail('Keputusan baru sama dengan keputusan saat ini.', 'decision');
            }

            $old = [
                'decision'   => $t->visit_decision,
                'decided_by' => $t->visit_decided_by,
                'decided_at' => $t->visit_decided_at?->toIso8601String(),
                'reason'     => $t->visit_decision_reason,
                'visit_site' => $t->visit_site?->toDateString(),
            ];

            $t->update([
                'visit_decision'        => $decision,
                'visit_decided_by'      => $user->id,
                'visit_decided_at'      => now(),
                'visit_decision_reason' => $reason,
                'visit_site'            => $decision === TroubleTicket::VISIT ? $visitSite : null,
            ]);

            $this->event($t, $user, 'visit_corrected', $reason, ['decision' => $decision, 'previous' => $old]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | KUNJUNGAN & PROGRESS
    |--------------------------------------------------------------------------
    */

    public function depart(TroubleTicket $ticket, User $user): void
    {
        DB::transaction(function () use ($ticket, $user) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->isTechnician($user, $t), 'Hanya teknisi yang ditugaskan yang dapat berangkat ke site.');

            if ($t->visit_decision !== TroubleTicket::VISIT) {
                $this->fail('Berangkat ke site hanya untuk tiket dengan keputusan Plan Visit.');
            }
            $this->expect($t, ['PLANNED'], 'Tiket tidak dalam keadaan siap berangkat.');

            // MVP: satu sesi kunjungan per tiket.
            if (TroubleTicketVisit::where('trouble_ticket_id', $t->id)->exists()) {
                $this->fail('Kunjungan untuk tiket ini sudah tercatat.');
            }

            $visit = TroubleTicketVisit::create([
                'trouble_ticket_id' => $t->id,
                'technician_id'     => $user->id,
                'departed_at'       => now(),
            ]);

            $t->update(['status' => 'TRAVELING']);
            $this->event($t, $user, 'departed', null, ['visit_id' => $visit->id]);
        });
    }

    public function arrive(TroubleTicket $ticket, User $user): void
    {
        DB::transaction(function () use ($ticket, $user) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->isTechnician($user, $t), 'Hanya teknisi yang ditugaskan yang dapat mencatat tiba di site.');
            $this->expect($t, ['TRAVELING'], 'Belum ada keberangkatan yang tercatat.');

            $visit = TroubleTicketVisit::where('trouble_ticket_id', $t->id)
                ->whereNull('arrived_at')
                ->lockForUpdate()
                ->first();

            if (! $visit) {
                $this->fail('Belum ada keberangkatan yang tercatat.');
            }

            $now = now();
            if ($now->lt($visit->departed_at)) {
                $this->fail('Waktu tiba tidak boleh sebelum waktu berangkat.');
            }

            $travel = $now->getTimestamp() - $visit->departed_at->getTimestamp();
            $visit->update(['arrived_at' => $now, 'travel_seconds' => $travel]);

            $t->update(['status' => 'ON_SITE']);
            $this->event($t, $user, 'arrived', null, ['visit_id' => $visit->id, 'travel_seconds' => $travel]);
        });
    }

    public function startProgress(TroubleTicket $ticket, User $user): void
    {
        DB::transaction(function () use ($ticket, $user) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->isTechnician($user, $t), 'Hanya teknisi yang ditugaskan yang dapat memulai progress.');

            $siap = ($t->visit_decision === TroubleTicket::VISIT && $t->status === 'ON_SITE')
                || ($t->visit_decision === TroubleTicket::NON_VISIT && $t->status === 'PLANNED');

            if (! $siap) {
                $this->fail($t->visit_decision === TroubleTicket::VISIT
                    ? 'Start Progress tersedia setelah teknisi tiba di site.'
                    : 'Tiket tidak dalam keadaan siap memulai progress.');
            }

            // Timestamp troubleshooting tidak pernah ditimpa.
            $t->update([
                'status'              => 'IN_PROGRESS',
                'progress_started_at' => $t->progress_started_at ?? now(),
            ]);

            $this->event($t, $user, 'progress_started');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSED
    |--------------------------------------------------------------------------
    */

    /** @param  UploadedFile[]  $files */
    /**
     * @param  array  $data  rca (wajib), sub_rca?, action (wajib), resolution_summary?
     * @param  UploadedFile[]  $files
     */
    public function requestClosure(TroubleTicket $ticket, User $user, array $data, array $files = []): void
    {
        $stored = $this->storeFiles($files);

        $this->withStoredFiles($stored, function () use ($ticket, $user, $data, $stored) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->isTechnician($user, $t), 'Hanya teknisi yang ditugaskan yang dapat mengajukan Closed.');
            $this->expect($t, ['IN_PROGRESS'], 'Pengajuan Closed hanya bisa dari status In Progress.');

            if ($t->problem?->requires_evidence && empty($stored)) {
                $this->fail('Problem ini mewajibkan lampiran bukti penyelesaian.', 'attachments');
            }

            $now = now();
            $attempt = (int) TroubleTicketClosureReview::where('trouble_ticket_id', $t->id)->max('attempt_no') + 1;
            $hasil = [
                'rca'                => $data['rca'],
                'sub_rca'            => $data['sub_rca'] ?? null,
                'action'             => $data['action'],
                'resolution_summary' => $data['resolution_summary'] ?? null,
            ];

            TroubleTicketClosureReview::create($hasil + [
                'trouble_ticket_id' => $t->id,
                'attempt_no'        => $attempt,
                'requested_by'      => $user->id,
                'requested_at'      => $now,
                'review_status'     => 'PENDING',
            ]);

            $t->update($hasil + [
                'status'      => 'PENDING_CLOSURE',
                'resolved_at' => $now,
                'approval'    => 'PENDING',
            ]);

            $message = $this->event($t, $user, 'closure_requested', $hasil['resolution_summary'], [
                'attempt' => $attempt,
                'rca'     => $hasil['rca'],
                'sub_rca' => $hasil['sub_rca'],
                'action'  => $hasil['action'],
            ]);
            $this->attach($t, $message, $user, $stored);
        });

        $ticket->refresh();
        $penerima = $this->supervisorsFor((int) $ticket->assigned_to);
        if ($penerima->isEmpty()) {
            $penerima = app(RoleDirectory::class)->usersWithRoles(AccessRoles::TROUBLE_TICKET_SUPERVISE);
        }

        $this->notify(
            $penerima->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Pengajuan Closed Trouble Ticket',
            "{$user->name} mengajukan Closed {$ticket->ticket_number}: {$ticket->issue}"
        );
    }

    public function approveClosure(TroubleTicket $ticket, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($ticket, $user, $notes) {
            $t = $this->lock($ticket);
            $this->expect($t, ['PENDING_CLOSURE'], 'Tiket tidak sedang menunggu approval Closed.');

            $review = $this->pendingReview($t);
            $this->authorizeIf($this->canReview($user, $t, $review), 'Anda tidak berwenang menyetujui pengajuan Closed ini.');

            $now = now();
            $review->update([
                'review_status' => 'APPROVED',
                'reviewed_by'   => $user->id,
                'reviewed_at'   => $now,
                'review_notes'  => $notes,
            ]);

            $t->update([
                'status'             => 'CLOSED',
                'approval'           => 'APPROVED',
                'closed_at'          => $now,
                'closed_by'          => $user->id,
                // Resolusi difinalkan HANYA di sini.
                'resolution_seconds' => max(0, $now->getTimestamp() - $t->progress_started_at->getTimestamp()),
            ]);

            $this->event($t, $user, 'closure_approved', $notes, ['attempt' => $review->attempt_no]);
        });

        $ticket->refresh();
        $this->notify(
            [$ticket->technician, $ticket->creator],
            $ticket,
            'Trouble Ticket Closed',
            "{$ticket->ticket_number} disetujui Closed oleh {$user->name}."
        );
    }

    public function rejectClosure(TroubleTicket $ticket, User $user, string $notes): void
    {
        DB::transaction(function () use ($ticket, $user, $notes) {
            $t = $this->lock($ticket);
            $this->expect($t, ['PENDING_CLOSURE'], 'Tiket tidak sedang menunggu approval Closed.');

            $review = $this->pendingReview($t);
            $this->authorizeIf($this->canReview($user, $t, $review), 'Anda tidak berwenang menolak pengajuan Closed ini.');

            $review->update([
                'review_status' => 'REJECTED',
                'reviewed_by'   => $user->id,
                'reviewed_at'   => now(),
                'review_notes'  => $notes,
            ]);

            // Kembali ke In Progress; progress_started_at TIDAK diubah.
            $t->update(['status' => 'IN_PROGRESS', 'approval' => 'REJECTED']);

            $this->event($t, $user, 'closure_rejected', $notes, ['attempt' => $review->attempt_no]);
        });

        $ticket->refresh();
        $this->notify(
            [$ticket->technician],
            $ticket,
            'Pengajuan Closed ditolak',
            "{$ticket->ticket_number} ditolak oleh {$user->name}: {$notes}"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REASSIGN & CANCEL
    |--------------------------------------------------------------------------
    */

    /** Status yang boleh dialihkan teknisinya. */
    public const REASSIGNABLE = ['OPEN', 'AWAITING_PLAN', 'PLANNED', 'ON_SITE', 'IN_PROGRESS'];

    /** Status yang boleh dibatalkan (TRAVELING & PENDING_CLOSURE diselesaikan dulu). */
    public const CANCELLABLE = ['OPEN', 'AWAITING_PLAN', 'PLANNED', 'ON_SITE', 'IN_PROGRESS'];

    public function reassign(TroubleTicket $ticket, User $user, int $newTechnicianId, string $reason): void
    {
        $lama = null;

        DB::transaction(function () use ($ticket, $user, $newTechnicianId, $reason, &$lama) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->canSupervise($user, $t), 'Hanya leader team teknisi yang dapat mengalihkan tiket.');
            $this->expect($t, self::REASSIGNABLE, 'Tiket pada status ini tidak bisa dialihkan.');

            if ((int) $t->assigned_to === $newTechnicianId) {
                $this->fail('Teknisi baru sama dengan teknisi saat ini.', 'assigned_to');
            }
            if (! $this->isEligibleTechnician($t->site_id, $newTechnicianId)) {
                $this->fail('Teknisi harus PIC internal aktif yang terdaftar di site ini.', 'assigned_to');
            }

            $now = now();
            $lama = (int) $t->assigned_to;

            TroubleTicketAssignment::where('trouble_ticket_id', $t->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => $now]);

            TroubleTicketAssignment::create([
                'trouble_ticket_id' => $t->id,
                'assigned_to'       => $newTechnicianId,
                'assigned_by'       => $user->id,
                'assigned_at'       => $now,
                'reason'            => $reason,
            ]);

            $changes = [
                'assigned_to' => $newTechnicianId,
                'pic_id'      => $this->picIdFor($t->site_id, $newTechnicianId),
                'assigned_at' => $now,
            ];
            // Belum ada keputusan kunjungan: teknisi baru yang merespons & memutuskan.
            if ($t->visit_decision === null) {
                $changes['status'] = 'OPEN';
            }
            $t->update($changes);

            $this->event($t, $user, 'reassigned', $reason, [
                'from_id'   => $lama,
                'from_name' => User::whereKey($lama)->value('name'),
                'to_id'     => $newTechnicianId,
                'to_name'   => User::whereKey($newTechnicianId)->value('name'),
            ]);
        });

        $ticket->refresh();
        $this->notify([$ticket->technician], $ticket, 'Trouble Ticket dialihkan kepada Anda',
            "{$ticket->ticket_number}: {$ticket->issue}");
        $this->notify([User::find($lama)], $ticket, 'Trouble Ticket dialihkan',
            "{$ticket->ticket_number} tidak lagi ditugaskan kepada Anda.");
    }

    public function cancel(TroubleTicket $ticket, User $user, string $reason): void
    {
        DB::transaction(function () use ($ticket, $user, $reason) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->canSupervise($user, $t), 'Hanya leader team teknisi yang dapat membatalkan tiket.');
            $this->expect($t, self::CANCELLABLE, 'Tiket pada status ini tidak bisa dibatalkan.');

            $t->update([
                'status'        => 'CANCELLED',
                'cancelled_at'  => now(),
                'cancelled_by'  => $user->id,
                'cancel_reason' => $reason,
            ]);

            $this->event($t, $user, 'cancelled', $reason);
        });

        $ticket->refresh();
        $this->notify([$ticket->technician, $ticket->creator], $ticket, 'Trouble Ticket dibatalkan',
            "{$ticket->ticket_number} dibatalkan oleh {$user->name}.");
    }

    /*
    |--------------------------------------------------------------------------
    | PESAN
    |--------------------------------------------------------------------------
    */

    /**
     * Kirim pesan chat (teks dan/atau lampiran). Idempoten per client_token:
     * kiriman ulang dengan token yang sama mengembalikan pesan yang sudah ada.
     *
     * @param  UploadedFile[]  $files
     */
    public function postMessage(TroubleTicket $ticket, User $user, ?string $body, array $files, ?string $clientToken): TroubleTicketMessage
    {
        $this->authorizeIf($this->canView($user, $ticket), 'Anda tidak memiliki akses ke tiket ini.');

        if ($clientToken && ($ada = $this->messageByToken($ticket, $clientToken))) {
            return $ada;
        }

        if ($ticket->isFinal()) {
            $this->fail('Tiket sudah ' . strtolower($ticket->status) . '; pesan baru tidak bisa ditambahkan.');
        }
        if ($this->mustDecide($user, $ticket)) {
            $this->fail('Pilih Visit / Non-Visit terlebih dahulu.');
        }

        $body = $body !== null ? trim($body) : null;
        if (($body === null || $body === '') && empty($files)) {
            $this->fail('Pesan atau lampiran wajib diisi.', 'body');
        }

        $stored = $this->storeFiles($files);

        try {
            return $this->withStoredFiles($stored, function () use ($ticket, $user, $body, $stored, $clientToken) {
                $message = TroubleTicketMessage::create([
                    'trouble_ticket_id' => $ticket->id,
                    'user_id'           => $user->id,
                    'actor_name'        => $user->name,
                    'actor_role'        => $user->role?->name,
                    'type'              => TroubleTicketMessage::TYPE_MESSAGE,
                    'body'              => $body !== '' ? $body : null,
                    'client_token'      => $clientToken,
                ]);

                $this->attach($ticket, $message, $user, $stored);
                $ticket->touch();

                return $message;
            });
        } catch (QueryException $e) {
            // Dua kiriman bersamaan dengan token sama: yang kalah mengambil milik pemenang.
            if ($clientToken && ($ada = $this->messageByToken($ticket, $clientToken))) {
                return $ada;
            }
            throw $e;
        }
    }

    private function messageByToken(TroubleTicket $ticket, string $token): ?TroubleTicketMessage
    {
        return TroubleTicketMessage::where('trouble_ticket_id', $ticket->id)
            ->where('client_token', $token)
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL
    |--------------------------------------------------------------------------
    */

    private function lock(TroubleTicket $ticket): TroubleTicket
    {
        return TroubleTicket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
    }

    private function pendingReview(TroubleTicket $t): TroubleTicketClosureReview
    {
        $review = TroubleTicketClosureReview::where('trouble_ticket_id', $t->id)
            ->where('review_status', 'PENDING')
            ->lockForUpdate()
            ->first();

        if (! $review) {
            $this->fail('Tidak ada pengajuan Closed yang menunggu keputusan.');
        }

        return $review;
    }

    private function expect(TroubleTicket $t, array $statuses, string $message): void
    {
        if (! in_array($t->status, $statuses, true)) {
            $this->fail($message);
        }
    }

    private function assertDecision(string $decision, ?string $reason, bool $reasonRequired = false, ?string $visitSite = null): void
    {
        if (! array_key_exists($decision, TroubleTicket::VISIT_DECISIONS)) {
            $this->fail('Pilihan kunjungan tidak dikenal.', 'decision');
        }
        if (($reasonRequired || $decision === TroubleTicket::NON_VISIT) && trim((string) $reason) === '') {
            $this->fail($reasonRequired ? 'Alasan koreksi wajib diisi.' : 'Alasan Non-Visit wajib diisi.', 'reason');
        }
        if ($decision === TroubleTicket::VISIT && blank($visitSite)) {
            $this->fail('Tanggal rencana kunjungan (Visit Site) wajib diisi untuk Plan Visit.', 'visit_site');
        }
    }

    private function fail(string $message, string $key = 'workflow'): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }

    private function authorizeIf(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw new AuthorizationException($message);
        }
    }

    /** Catat kejadian timeline. Sengaja TIDAK gagal-aman: gagal = transaksi batal. */
    private function event(TroubleTicket $ticket, User $user, string $code, ?string $body = null, array $meta = []): TroubleTicketMessage
    {
        return TroubleTicketMessage::create([
            'trouble_ticket_id' => $ticket->id,
            'user_id'           => $user->id,
            'actor_name'        => $user->name,
            'actor_role'        => $user->role?->name,
            'type'              => TroubleTicketMessage::TYPE_EVENT,
            'event'             => $code,
            'body'              => $body,
            'metadata'          => $meta ?: null,
        ]);
    }

    /**
     * Simpan berkas ke disk secure dengan nama acak SEBELUM transaksi.
     * MIME diambil dari deteksi isi (finfo), bukan dari browser.
     *
     * @param  UploadedFile[]  $files
     */
    private function storeFiles(array $files): array
    {
        $files = array_filter($files, fn ($f) => $f instanceof UploadedFile && $f->isValid());
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimes = [];

        // Semua diperiksa dulu, baru disimpan: satu berkas ditolak = tidak ada yang tersimpan.
        foreach ($files as $i => $file) {
            $mimes[$i] = $finfo->file($file->getRealPath()) ?: 'application/octet-stream';

            if (! in_array($mimes[$i], TroubleTicketAttachment::DETECTED_MIME, true)) {
                $this->fail('Isi berkas "' . $file->getClientOriginalName() . '" tidak sesuai jenis yang diizinkan.', 'attachments');
            }
        }

        $stored = [];

        foreach ($files as $i => $file) {
            $stored[] = [
                'path' => $file->store(TroubleTicketAttachment::FOLDER, 'secure'),
                // Nama asli hanya untuk tampilan/unduhan; dibersihkan dari pemisah path.
                'name' => mb_substr(str_replace(['/', '\\', "\0"], '_', $file->getClientOriginalName()), 0, 255),
                'mime' => $mimes[$i],
                'size' => (int) $file->getSize(),
                'hash' => hash_file('sha256', $file->getRealPath()) ?: null,
            ];
        }

        return $stored;
    }

    /** Jalankan transaksi; bila gagal, berkas yang sudah tersimpan dihapus lagi. */
    private function withStoredFiles(array $stored, \Closure $fn): mixed
    {
        try {
            return DB::transaction($fn);
        } catch (\Throwable $e) {
            foreach ($stored as $s) {
                Storage::disk('secure')->delete($s['path']);
            }
            throw $e;
        }
    }

    private function attach(TroubleTicket $ticket, TroubleTicketMessage $message, User $user, array $stored): void
    {
        foreach ($stored as $s) {
            TroubleTicketAttachment::create([
                'trouble_ticket_id' => $ticket->id,
                'message_id'        => $message->id,
                'uploaded_by'       => $user->id,
                'original_name'     => $s['name'],
                'storage_path'      => $s['path'],
                'mime_type'         => $s['mime'],
                'size_bytes'        => $s['size'],
                'file_hash'         => $s['hash'],
            ]);
        }
    }

    /**
     * Notifikasi dikirim SETELAH transaksi selesai, dan hanya oleh permintaan
     * yang benar-benar menjalankan transisi — permintaan ulang gagal di
     * pemeriksaan status lebih dulu, jadi tidak ada notifikasi ganda.
     */
    private function notify(iterable $recipients, TroubleTicket $ticket, string $title, string $message): void
    {
        NotifyService::send($recipients, $title, $message, route('trouble-ticket.show', $ticket), 'trouble-ticket');
    }
}

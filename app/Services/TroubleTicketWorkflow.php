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
use App\Models\TroubleTicketMessage;
use App\Models\TroubleTicketSlaPolicy;
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

    /** Aksi operasional (respon, ajukan plan, progress, tutup tiket) — hanya teknisi aktif. */
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

    /**
     * Approve/reject PLAN: pengawas, dan BUKAN teknisi yang mengajukan plan
     * itu sendiri. Hanya saat tiket berstatus APPROVED (menunggu approval).
     */
    public function canReviewPlan(User $user, TroubleTicket $ticket): bool
    {
        return $ticket->status === 'APPROVED'
            && (int) $ticket->visit_decided_by !== (int) $user->id
            && $this->canSupervise($user, $ticket);
    }

    public function canMessage(User $user, TroubleTicket $ticket): bool
    {
        return ! $ticket->isFinal() && $this->canView($user, $ticket);
    }

    /** Teknisi wajib mengajukan plan Visit/Non-Visit sebelum bisa melakukan apa pun. */
    public function mustDecide(User $user, TroubleTicket $ticket): bool
    {
        return $this->isTechnician($user, $ticket)
            && $ticket->status === 'OPEN';
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
     * Teknisi mengajukan plan (Visit / Non-Visit). Tiket masuk APPROVED —
     * MENUNGGU approval leader team; belum boleh dikerjakan.
     *
     * Plan boleh diajukan ulang setelah ditolak leader: penolakan
     * mengosongkan keputusan lama, jadi tidak ada kunci permanen di sini.
     *
     * @param  ?string  $visitSite     tanggal rencana kunjungan (Y-m-d) — wajib untuk Plan Visit
     * @param  ?string  $targetOnline  perkiraan site kembali online (Y-m-d), opsional
     */
    public function submitPlan(TroubleTicket $ticket, User $user, string $decision, ?string $reason, ?string $visitSite = null, ?string $targetOnline = null): void
    {
        $this->assertDecision($decision, $reason, false, $visitSite);

        DB::transaction(function () use ($ticket, $user, $decision, $reason, $visitSite, $targetOnline) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->isTechnician($user, $t), 'Hanya teknisi yang ditugaskan yang dapat mengajukan plan.');
            $this->expect($t, ['OPEN'], 'Plan hanya bisa diajukan saat tiket berstatus Open.');

            $t->update([
                'visit_decision'        => $decision,
                'visit_decided_by'      => $user->id,
                'visit_decided_at'      => now(),
                'visit_decision_reason' => $reason,
                'visit_site'            => $decision === TroubleTicket::VISIT ? $visitSite : null,
                'target_online'         => $targetOnline,
                'approval'              => 'PENDING',
                'plan_approved_at'      => null,
                'plan_approved_by'      => null,
                'status'                => 'APPROVED',
            ]);

            $this->event($t, $user, 'plan_submitted', $reason, [
                'decision'      => $decision,
                'visit_site'    => $t->visit_site?->toDateString(),
                'target_online' => $t->target_online?->toDateString(),
            ]);
        });

        $ticket->refresh();
        $penerima = $this->supervisorsFor((int) $ticket->assigned_to);
        if ($penerima->isEmpty()) {
            $penerima = app(RoleDirectory::class)->usersWithRoles(AccessRoles::TROUBLE_TICKET_SUPERVISE);
        }

        $this->notify(
            $penerima->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Plan Trouble Ticket menunggu approval',
            "{$user->name} mengajukan plan {$ticket->ticket_number}: {$ticket->issue}"
        );
    }

    /** Leader team menyetujui plan — tiket boleh dikerjakan. */
    public function approvePlan(TroubleTicket $ticket, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($ticket, $user, $notes) {
            $t = $this->lock($ticket);
            $this->expect($t, ['APPROVED'], 'Tiket tidak sedang menunggu approval plan.');
            $this->authorizeIf($this->canReviewPlan($user, $t), 'Anda tidak berwenang menyetujui plan ini.');

            $t->update([
                'status'           => 'ON_PLAN',
                'approval'         => 'APPROVED',
                'plan_approved_at' => now(),
                'plan_approved_by' => $user->id,
            ]);

            $this->event($t, $user, 'plan_approved', $notes);
        });

        $ticket->refresh();
        $this->notify(
            [$ticket->technician],
            $ticket,
            'Plan Trouble Ticket disetujui',
            "Plan {$ticket->ticket_number} disetujui {$user->name}. Tiket siap dikerjakan."
        );
    }

    /**
     * Leader team menolak plan — tiket kembali OPEN dan keputusan kunjungan
     * dikosongkan supaya teknisi bisa mengajukan plan baru.
     */
    public function rejectPlan(TroubleTicket $ticket, User $user, string $notes): void
    {
        DB::transaction(function () use ($ticket, $user, $notes) {
            $t = $this->lock($ticket);
            $this->expect($t, ['APPROVED'], 'Tiket tidak sedang menunggu approval plan.');
            $this->authorizeIf($this->canReviewPlan($user, $t), 'Anda tidak berwenang menolak plan ini.');

            $lama = [
                'decision'   => $t->visit_decision,
                'visit_site' => $t->visit_site?->toDateString(),
                'reason'     => $t->visit_decision_reason,
            ];

            $t->update([
                'status'                => 'OPEN',
                'approval'              => 'REJECTED',
                'visit_decision'        => null,
                'visit_decided_by'      => null,
                'visit_decided_at'      => null,
                'visit_decision_reason' => null,
                'visit_site'            => null,
                'target_online'         => null,
            ]);

            $this->event($t, $user, 'plan_rejected', $notes, ['previous' => $lama]);
        });

        $ticket->refresh();
        $this->notify(
            [$ticket->technician],
            $ticket,
            'Plan Trouble Ticket ditolak',
            "Plan {$ticket->ticket_number} ditolak {$user->name}: {$notes}"
        );
    }

    /**
     * Koreksi plan oleh pengawas — sebelum pengerjaan dimulai. Plan lama
     * tidak hilang: tersimpan di metadata kejadiannya dan di kejadian
     * 'plan_submitted' semula.
     */
    public function correctDecision(TroubleTicket $ticket, User $user, string $decision, string $reason, ?string $visitSite = null): void
    {
        $this->assertDecision($decision, $reason, true, $visitSite);

        DB::transaction(function () use ($ticket, $user, $decision, $reason, $visitSite) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->canSupervise($user, $t), 'Hanya leader team teknisi yang dapat mengoreksi plan.');
            $this->expect($t, ['APPROVED', 'ON_PLAN'], 'Plan hanya bisa dikoreksi sebelum pengerjaan dimulai.');

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

            $this->event($t, $user, 'plan_corrected', $reason, ['decision' => $decision, 'previous' => $old]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | PROGRESS
    |--------------------------------------------------------------------------
    */

    public function startProgress(TroubleTicket $ticket, User $user): void
    {
        DB::transaction(function () use ($ticket, $user) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->isTechnician($user, $t), 'Hanya teknisi yang ditugaskan yang dapat memulai progress.');
            $this->expect($t, ['ON_PLAN'], 'Start Progress tersedia setelah plan disetujui leader team.');

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

    /**
     * Teknisi menutup tiket. Tidak ada approval Closed (keputusan 10 Okt
     * 2026): hasil troubleshooting langsung final, jadi resolution_seconds
     * dihitung di sini dan — karena CLOSED status akhir — tidak pernah
     * ditimpa.
     *
     * @param  array  $data  rca (wajib), sub_rca?, action (wajib), resolution_summary?
     * @param  UploadedFile[]  $files
     */
    public function close(TroubleTicket $ticket, User $user, array $data, array $files = []): void
    {
        $stored = $this->storeFiles($files);

        $this->withStoredFiles($stored, function () use ($ticket, $user, $data, $stored) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->isTechnician($user, $t), 'Hanya teknisi yang ditugaskan yang dapat menutup tiket.');
            $this->expect($t, ['IN_PROGRESS'], 'Tiket hanya bisa ditutup dari status In Progress.');

            if ($t->problem?->requires_evidence && empty($stored)) {
                $this->fail('Problem ini mewajibkan lampiran bukti penyelesaian.', 'attachments');
            }

            $now = now();
            $hasil = [
                'rca'                => $data['rca'],
                'sub_rca'            => $data['sub_rca'] ?? null,
                'action'             => $data['action'],
                'resolution_summary' => $data['resolution_summary'] ?? null,
            ];

            $t->update($hasil + [
                'status'             => 'CLOSED',
                'resolved_at'        => $now,
                'closed_at'          => $now,
                'closed_by'          => $user->id,
                'resolution_seconds' => max(0, $now->getTimestamp() - $t->progress_started_at->getTimestamp()),
            ]);

            $message = $this->event($t, $user, 'closed', $hasil['resolution_summary'], [
                'rca'     => $hasil['rca'],
                'sub_rca' => $hasil['sub_rca'],
                'action'  => $hasil['action'],
            ]);
            $this->attach($t, $message, $user, $stored);
        });

        $ticket->refresh();
        $penerima = $this->supervisorsFor((int) $ticket->assigned_to)
            ->push($ticket->creator)
            ->filter()
            ->reject(fn ($u) => (int) $u->id === (int) $user->id);

        $this->notify(
            $penerima,
            $ticket,
            'Trouble Ticket Closed',
            "{$ticket->ticket_number} ditutup oleh {$user->name}."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REASSIGN
    |--------------------------------------------------------------------------
    */

    /** Status yang boleh dialihkan teknisinya — semua yang belum final. */
    public const REASSIGNABLE = ['OPEN', 'APPROVED', 'ON_PLAN', 'IN_PROGRESS'];

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
            $this->fail('Ajukan plan Visit / Non-Visit terlebih dahulu.');
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

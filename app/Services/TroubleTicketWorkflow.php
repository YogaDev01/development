<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanDetail;
use App\Models\BudgetRequest;
use App\Models\RequestType;
use App\Models\Site;
use App\Models\SiteBackupTechnician;
use App\Models\SitePic;
use App\Models\Team;
use App\Models\TroubleTicket;
use App\Models\TroubleTicketAssignment;
use App\Models\TroubleTicketAttachment;
use App\Models\TroubleTicketDevice;
use App\Models\TroubleTicketIssue;
use App\Models\TroubleTicketMessage;
use App\Models\TroubleTicketProblem;
use App\Models\TroubleTicketRequest;
use App\Models\TroubleTicketRequestAttachment;
use App\Models\TroubleTicketRequestItem;
use App\Models\TroubleTicketRca;
use App\Models\TroubleTicketSlaPolicy;
use App\Models\TroubleTicketSubRca;
use App\Models\User;
use App\Services\TicketDetailImage;
use App\Support\AccessRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /** Teknisi yang BENAR-BENAR ditugaskan — dipakai pencatatan respons pertama. */
    public function isTechnician(User $user, TroubleTicket $ticket): bool
    {
        return (int) $user->id === (int) $ticket->assigned_to;
    }

    /**
     * Boleh menjalankan aksi teknisi (Remote, Create Plan, perjalanan, On Site,
     * mulai progres, form close): teknisi yang ditugaskan — ditambah
     * AccessRoles::TROUBLE_TICKET_FULL_SCOPE (superadmin), keputusan 12 Okt
     * 2026, supaya tiket tidak macet saat teknisi berhalangan. Pelakunya tetap
     * tercatat apa adanya di log tiket.
     */
    public function actsAsTechnician(User $user, TroubleTicket $ticket): bool
    {
        return $this->isTechnician($user, $ticket)
            || $user->hasRole(AccessRoles::TROUBLE_TICKET_FULL_SCOPE);
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
     * Boleh mengirim FORM CLOSE (keputusan 20 Okt 2026): SEMUA peran yang
     * boleh melihat tiket — bukan lagi hanya teknisi yang ditugaskan — di
     * tahap Open, Remote, Plan, dan In Progress. Dua kekecualian tetap:
     *
     *   - tahap TRIP tertutup bagi TEKNISI tiket itu (superadmin & peran lain
     *     tetap bisa), lanjutan aturan 15 Okt 2026 soal perjalanan;
     *   - sesudah On Site TEKNISI tetap harus memulai progres dulu (19 Okt
     *     2026); peran lain boleh menutup dari tahap itu (20 Okt 2026).
     */
    public function canClose(User $user, TroubleTicket $ticket): bool
    {
        if (! in_array($ticket->status, TroubleTicket::CLOSABLE, true)) {
            return false;
        }

        if ($ticket->status === 'TRIP'
            && $this->isTechnician($user, $ticket)
            && ! $user->hasRole(AccessRoles::TROUBLE_TICKET_FULL_SCOPE)) {
            return false;
        }

        return $this->canView($user, $ticket);
    }

    /**
     * Boleh mengalihkan teknisi: pengawas tiket ini, ditambah peran penyetuju
     * penutupan — supervisor (keputusan 20 Okt 2026).
     */
    public function canReassignBy(User $user, TroubleTicket $ticket): bool
    {
        return $this->canSupervise($user, $ticket)
            || $user->hasRole(AccessRoles::TROUBLE_TICKET_APPROVE);
    }

    /**
     * Membuat Plan Visit PERTAMA (dari tahap Remote): teknisi yang ditugaskan
     * (atau superadmin lewat TROUBLE_TICKET_FULL_SCOPE) dan supervisor tiket
     * ini — leader team-nya, cadangan pengawas, atau role penyetuju penutupan
     * (keputusan 15 Okt 2026). Peran lain tidak boleh membuat plan.
     */
    public function canCreatePlan(User $user, TroubleTicket $ticket): bool
    {
        return $this->actsAsTechnician($user, $ticket)
            || $this->canSupervise($user, $ticket)
            || $user->hasRole(AccessRoles::TROUBLE_TICKET_APPROVE);
    }

    /**
     * MENGUBAH tanggal plan yang sudah ada (tahap Plan / Trip): hanya
     * supervisor (keputusan 19 Okt 2026) — leader team teknisinya, cadangan
     * pengawas, atau role penyetuju penutupan. Teknisi tidak lagi menggeser
     * sendiri jadwal kunjungannya.
     */
    public function canReschedulePlan(User $user, TroubleTicket $ticket): bool
    {
        return $this->canSupervise($user, $ticket)
            || $user->hasRole(AccessRoles::TROUBLE_TICKET_APPROVE);
    }

    /** Hak membuat/mengubah plan sesuai tahap tiket sekarang — dipakai tombol & server. */
    public function canPlan(User $user, TroubleTicket $ticket): bool
    {
        return in_array($ticket->status, ['PLAN', 'TRIP'], true)
            ? $this->canReschedulePlan($user, $ticket)
            : $this->canCreatePlan($user, $ticket);
    }

    /**
     * Approve/tolak PENUTUPAN tiket: hanya role supervisor (keputusan 12 Okt
     * 2026) dan BUKAN teknisi yang mengerjakan tiket itu sendiri. Hanya saat
     * tiket berstatus PROGRESS_DONE.
     */
    public function canApproveClose(User $user, TroubleTicket $ticket): bool
    {
        return $ticket->status === 'PROGRESS_DONE'
            && (int) $ticket->assigned_to !== (int) $user->id
            && $user->hasRole(AccessRoles::TROUBLE_TICKET_APPROVE);
    }

    /**
     * Boleh mengirim pesan: siapa pun yang bisa melihat tiket. Tiket CLOSED
     * tidak menutup diskusi (keputusan 19 Okt 2026), dan fitur Stop Chat
     * dihapus (keputusan 19 Okt 2026) — tidak ada lagi yang menutup chat.
     */
    public function canMessage(User $user, TroubleTicket $ticket): bool
    {
        return $this->canView($user, $ticket);
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
            // Leader team bukan teknisi: ia yang menyetujui plan, jadi tidak
            // boleh ditugaskan ke tiket walau terdaftar sebagai PIC site.
            ->whereNotIn('id', Team::query()
                ->where('status', 'ACTIVE')
                ->whereNotNull('leader_id')
                ->select('leader_id'))
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

    /**
     * Kandidat Change Teknisi: Teknisi Backup site ini (keputusan 16 Okt 2026).
     *
     * Daftarnya diisi manusia di Master Data > Site (tab Teknisi Backup), jadi
     * tidak ada lagi penebakan lewat homebase/cluster terdekat. Yang disaring
     * di sini hanya yang tidak mungkin ditugaskan: akun non-ACTIVE, leader team
     * (ia yang menyetujui plan), dan teknisi tiket ini sendiri.
     *
     * @return Collection<int, object{id:int, name:string, group:string}>
     */
    public function reassignCandidates(TroubleTicket $ticket): Collection
    {
        return User::query()
            ->where('users.status', 'ACTIVE')
            ->where('users.id', '!=', $ticket->assigned_to)
            ->whereNotIn('users.id', Team::query()
                ->where('status', 'ACTIVE')
                ->whereNotNull('leader_id')
                ->select('leader_id'))
            ->whereIn('users.id', SiteBackupTechnician::query()
                ->where('site_id', $ticket->site_id)
                ->select('user_id'))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->map(fn ($u) => (object) [
                'id' => (int) $u->id,
                'name' => $u->name,
                'group' => 'Teknisi Backup',
            ])
            ->values();
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

    /** Akun PIC internal ACTIVE sebuah site — penerima notifikasi tiket baru. */
    public function sitePicUsers(int $siteId): Collection
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
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | PEMBUATAN
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array  $data  hasil validasi controller: site_id, assigned_to, problem_id,
     *                       issue_id, description, device_id?, rca_id?, sub_rca_id?,
     *                       flag_alarm?, reported_at?
     * @param  UploadedFile[]  $files
     */
    /** Ringkasan singkat tiket: Initial Problem bila ada, selain itu potongan deskripsi. */
    private function summaryOf(TroubleTicket $ticket): string
    {
        return $ticket->issue_label ?: Str::limit((string) $ticket->description, 60);
    }

    public function create(User $actor, array $data, array $files = []): TroubleTicket
    {
        // Referensi dari klien tidak dipercaya: site, problem, severity, dan
        // teknisi diperiksa ulang terhadap master dan relasinya.
        $site = Site::where('status', 'ACTIVE')->find($data['site_id']);
        if (! $site) {
            $this->fail('Site tidak ditemukan atau tidak aktif.', 'site_id');
        }

        // Problem & Initial Problem tidak lagi diisi di form buat tiket; keduanya
        // tetap diterima (mis. integrasi) tapi opsional. Tanpa Problem, SLA jatuh
        // ke kebijakan umum (problem_id NULL) — lihat SlaPolicy::applicableForProblem().
        $problemId = $data['problem_id'] ?? null;
        if ($problemId && ! TroubleTicketProblem::where('status', 'ACTIVE')->whereKey($problemId)->exists()) {
            $this->fail('Problem tidak ditemukan atau tidak aktif.', 'problem_id');
        }

        $issueId = $data['issue_id'] ?? null;
        if ($issueId && ! TroubleTicketIssue::where('status', 'ACTIVE')->whereKey($issueId)->exists()) {
            $this->fail('Initial Problem tidak ditemukan atau tidak aktif.', 'issue_id');
        }

        $deviceId = $data['device_id'] ?? null;
        if ($deviceId && ! TroubleTicketDevice::where('status', 'ACTIVE')->whereKey($deviceId)->exists()) {
            $this->fail('Device tidak ditemukan atau tidak aktif.', 'device_id');
        }

        // Sub-RCA wajib milik RCA yang dipilih; RCA tanpa Sub-RCA diperbolehkan.
        $rcaId = $data['rca_id'] ?? null;
        $subRcaId = $data['sub_rca_id'] ?? null;
        if ($rcaId && ! TroubleTicketRca::where('status', 'ACTIVE')->whereKey($rcaId)->exists()) {
            $this->fail('RCA tidak ditemukan atau tidak aktif.', 'rca_id');
        }
        if ($subRcaId) {
            if (! $rcaId) {
                $this->fail('Pilih RCA lebih dulu sebelum Sub-RCA.', 'sub_rca_id');
            }
            if (! TroubleTicketSubRca::where('status', 'ACTIVE')->whereKey($subRcaId)->where('rca_id', $rcaId)->exists()) {
                $this->fail('Sub-RCA tidak sesuai dengan RCA yang dipilih.', 'sub_rca_id');
            }
        }

        // No. TT custom (form buat tiket / integrasi): harus belum terpakai.
        $nomorCustom = filled($data['ticket_number'] ?? null) ? trim((string) $data['ticket_number']) : null;
        if ($nomorCustom !== null && TroubleTicket::where('ticket_number', $nomorCustom)->exists()) {
            $this->fail('No. tiket itu sudah dipakai tiket lain.', 'ticket_number');
        }

        $picId = $this->picIdFor($site->id, (int) $data['assigned_to']);
        if (! $picId || ! $this->isEligibleTechnician($site->id, (int) $data['assigned_to'])) {
            $this->fail('Teknisi harus PIC internal aktif yang terdaftar di site ini.', 'assigned_to');
        }

        // Severity tidak lagi jadi masukan: kebijakan SLA dipilih per Problem.
        $policy = TroubleTicketSlaPolicy::applicableForProblem($problemId ? (int) $problemId : null);
        $stored = $this->storeFiles($files);

        $ticket = $this->withStoredFiles($stored, function () use ($actor, $data, $site, $problemId, $issueId, $deviceId, $rcaId, $subRcaId, $picId, $policy, $stored, $nomorCustom) {
            $now = now();

            $ticket = TroubleTicket::create([
                // No. TT custom dari form buat tiket; kosong = nomor otomatis.
                'ticket_number' => $nomorCustom ?? TroubleTicket::generateNumber($now),
                'external_ref' => $data['external_ref'] ?? null,
                'site_id' => $site->id,
                'customer_id' => $site->customer_id,
                'problem_id' => $problemId ? (int) $problemId : null,
                'issue_id' => $issueId ? (int) $issueId : null,
                'device_id' => $deviceId,
                'rca_id' => $rcaId,
                'sub_rca_id' => $subRcaId,
                'flag_alarm' => $data['flag_alarm'] ?? null,
                'description' => $data['description'],
                // Date Open — boleh diubah pembuat, bawaan hari ini.
                'reported_at' => $data['reported_at'] ?? $now,
                'status' => 'OPEN',
                'created_by' => $actor->id,
                'assigned_to' => (int) $data['assigned_to'],
                'pic_id' => $picId,
                'assigned_at' => $now,
                'sla_policy_id' => $policy?->id,
                'sla_response_minutes' => $policy?->response_minutes,
                'sla_travel_minutes' => $policy?->travel_minutes,
                'sla_resolution_minutes' => $policy?->resolution_minutes,
            ]);

            TroubleTicketAssignment::create([
                'trouble_ticket_id' => $ticket->id,
                'assigned_to' => $ticket->assigned_to,
                'assigned_by' => $actor->id,
                'assigned_at' => $now,
            ]);

            $created = $this->event($ticket, $actor, 'created', $this->summaryOf($ticket), [
                'site' => $site->tid,
            ]);
            $this->attach($ticket, $created, $actor, $stored);

            /* Penugasan awal TIDAK ditulis ke log (13 Okt 2026): teknisinya sudah
               terbaca di kepala tiket, dan baris sesudah "Ticket created"
               seharusnya "Ticket read" — saat teknisi membuka tiket dan jam
               Response berhenti (lihat recordAccess()). Pengalihan teknisi
               setelahnya tetap dicatat sebagai 'reassigned'. */

            return $ticket;
        });

        // Teknisi yang ditugaskan + seluruh PIC internal site (keputusan
        // 12 Okt 2026: tiket baru harus sampai ke PIC site, bukan hanya teknisi).
        $this->notify(
            $this->sitePicUsers($site->id)->push($ticket->technician)->filter()->unique('id'),
            $ticket,
            'Trouble Ticket baru',
            "{$ticket->ticket_number} — {$site->tid} {$site->sitename}: ".$this->summaryOf($ticket)
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
     *
     * Sejak 19 Okt 2026 tidak ada lagi tombol Remote: tiket OPEN yang dibuka
     * teknisinya LANGSUNG masuk tahap Remote (penanganan jarak jauh) dalam
     * transaksi yang sama — SLA Response berhenti di sini dan SLA Resolution
     * mulai berjalan. Dari Remote teknisi menutup tiket atau membuat Plan.
     */
    public function recordAccess(TroubleTicket $ticket, User $user): bool
    {
        if (! $this->isTechnician($user, $ticket) || $ticket->isFinal()) {
            return false;
        }

        $assignment = $ticket->assignments()->whereNull('unassigned_at')->first();
        if ($ticket->acknowledged_at !== null
            && $ticket->status !== 'OPEN'
            && ($assignment === null || $assignment->responded_at !== null)) {
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
                    'response_seconds' => max(0, $now->getTimestamp() - $t->created_at->getTimestamp()),
                ];
                $recorded = true;
            }

            if ($changes) {
                $t->update($changes);
            }

            // Tiket OPEN yang dibuka teknisinya langsung masuk tahap Remote.
            $keRemote = $t->status === 'OPEN';

            if ($keRemote) {
                $t->update([
                    'status' => 'REMOTE',
                    'visit_decision' => TroubleTicket::NON_VISIT,
                    'visit_decided_by' => $user->id,
                    'visit_decided_at' => $now,
                    'visit_site' => null,
                    'approval' => null,
                    'progress_started_at' => $t->progress_started_at ?? $now,
                ]);

                $recorded = true;
            }

            /* Satu kejadian untuk satu tindakan (19 Okt 2026): membuka tiket
               dan masuk tahap Remote terjadi bersamaan, jadi log & timeline
               menulisnya sebagai satu baris 'responded_remote', bukan
               'responded' lalu 'remote_started' pada detik yang sama. */
            if ($recorded) {
                $this->event($t, $user, $keRemote ? 'responded_remote' : 'responded', null, [
                    'first' => isset($changes['acknowledged_at']),
                ]);
            }

            $ticket->setRawAttributes($t->getAttributes(), true);

            return $recorded;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | REMOTE → PLAN
    |--------------------------------------------------------------------------
    */

    /**
     * Membuat / mengubah Plan Visit: tiket menunggu tanggal plan visit. Tidak
     * ada approval plan (keputusan 12 Okt 2026), dan tiket TIDAK naik sendiri
     * ke tahap Trip saat tanggalnya tiba (keputusan 19 Okt 2026) — teknisi
     * yang menggeser "Swipe for travel" (lihat startTravel()).
     *
     * @param  string  $visitSite  tanggal rencana kunjungan (Y-m-d)
     */
    /*
     * Plan visit = JADWAL saja (keputusan Okt 2026). Biaya diajukan terpisah lewat tab
     * Pengajuan; Create Plan tidak lagi menulis/menyentuh biaya maupun approval biaya.
     */
    public function createPlan(TroubleTicket $ticket, User $user, string $visitSite, ?string $targetOnline = null, ?string $reason = null): void
    {
        if (blank($visitSite)) {
            $this->fail('Tanggal plan visit wajib diisi.', 'visit_site');
        }

        DB::transaction(function () use ($ticket, $user, $visitSite, $targetOnline, $reason) {
            $t = $this->lock($ticket);
            // Plan pertama: teknisi atau supervisor. Mengubah tanggal plan
            // yang sudah ada: supervisor saja (keputusan 19 Okt 2026).
            $this->authorizeIf(
                $this->canPlan($user, $t),
                in_array($t->status, ['PLAN', 'TRIP'], true)
                    ? 'Hanya supervisor yang dapat mengubah tanggal plan visit.'
                    : 'Hanya teknisi yang ditugaskan atau supervisor yang dapat membuat plan.',
            );
            // Remote (berbalik jadi kunjungan), Plan (ubah tanggal), Trip
            // (reschedule saat kunjungan batal). Dari Open tidak bisa:
            // teknisi memilih Remote dulu (keputusan 15 Okt 2026).
            $this->expect($t, ['REMOTE', 'PLAN', 'TRIP'], 'Plan hanya bisa dibuat atau diubah dari tahap Remote, Plan, atau Trip.');
            $this->assertVisitDateAllowed($t, $visitSite);

            if ($t->status === 'TRIP' && $t->travel_arrived_at !== null) {
                $this->fail('Teknisi sudah tiba di site; jadwal kunjungan tidak bisa diubah lagi.');
            }

            $t->update([
                'status' => 'PLAN',
                'visit_decision' => TroubleTicket::VISIT,
                'visit_decided_by' => $user->id,
                'visit_decided_at' => now(),
                'visit_decision_reason' => $reason,
                'visit_site' => $visitSite,
                'target_online' => $targetOnline,
                'approval' => null,
                // Pindah dari Remote ke kunjungan: SLA Resolution yang sempat
                // berjalan dibatalkan — hitungannya dimulai lagi saat progres
                // di site dimulai.
                'progress_started_at' => null,
                // Reschedule dari Trip: jejak perjalanan yang batal dikosongkan
                // supaya SLA Travel dihitung ulang dari keberangkatan baru.
                'travel_started_at' => null,
                'travel_arrived_at' => null,
                'travel_seconds' => null,
            ]);

            $this->event($t, $user, $t->wasChanged('status') ? 'plan_created' : 'plan_updated', $reason, [
                'visit_site' => $t->visit_site?->toDateString(),
                'target_online' => $t->target_online?->toDateString(),
            ]);
        });

        $ticket->refresh();
        $this->notify(
            $this->supervisorsFor((int) $ticket->assigned_to)->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Plan Visit dibuat',
            "{$user->name} membuat plan {$ticket->ticket_number} untuk ".$ticket->visit_site?->format('d/m/Y').'.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PENGAJUAN BIAYA (pra-Cost Request)
    |--------------------------------------------------------------------------
    | Terlepas dari Plan Visit (keputusan Okt 2026): Create Plan hanya jadwal,
    | biaya diajukan lewat tab Pengajuan. Satu tiket boleh punya banyak
    | pengajuan, di tahap mana pun kecuali CLOSED. Cost Request (berstatus DRAFT)
    | baru lahir saat supervisor meng-approve. Modul ini TIDAK menampilkan atau
    | mengecek sisa pagu, NOB, maupun kuota Cash Advance - itu urusan modul Cost
    | Request saat CR di-submit.
    |
    | Setiap transisi: transaksi + kunci baris tiket lalu baris pengajuan
    | (urutan tetap) + pemeriksaan status asal pada baris yang terkunci.
    */

    /** Boleh MEMBUAT atau mengelola pengajuan sendiri: teknisi tiket ini (atau superadmin), tiket belum CLOSED. */
    public function canRequest(User $user, TroubleTicket $ticket): bool
    {
        return $ticket->status !== 'CLOSED' && $this->actsAsTechnician($user, $ticket);
    }

    /**
     * Mengubah / mengajukan / membatalkan pengajuan yang SUDAH ADA sebagai
     * pemohon: pembuatnya sendiri (atau superadmin), tiket belum CLOSED.
     */
    public function canManageRequest(User $user, TroubleTicket $ticket, TroubleTicketRequest $req): bool
    {
        return $this->canRequest($user, $ticket)
            && ((int) $req->requested_by === (int) $user->id || $user->hasRole(AccessRoles::TROUBLE_TICKET_FULL_SCOPE));
    }

    /**
     * Reviewer pengajuan (Q4): user ber-role supervisor yang berada dalam TEAM
     * YANG SAMA dengan teknisi tiket (leader atau anggota ACTIVE team ACTIVE
     * itu), BUKAN pembuat pengajuan. Superadmin tetap boleh, tetapi tidak untuk
     * pengajuan buatannya sendiri.
     */
    public function canReviewRequest(User $user, TroubleTicket $ticket, TroubleTicketRequest $req): bool
    {
        if ((int) $req->requested_by === (int) $user->id) {
            return false;
        }

        if (! $user->hasRole(AccessRoles::TROUBLE_TICKET_APPROVE)) {
            return false;
        }

        return $user->hasRole(AccessRoles::TROUBLE_TICKET_FULL_SCOPE)
            || $this->sameActiveTeam((int) $user->id, (int) $ticket->assigned_to);
    }

    /** User dan teknisi sama-sama ada di satu team ACTIVE (sebagai leader atau anggota ACTIVE). */
    private function sameActiveTeam(int $userId, int $technicianId): bool
    {
        return Team::query()
            ->where('status', 'ACTIVE')
            ->whereHas('members', fn ($m) => $m->where('user_id', $technicianId)->where('status', 'ACTIVE'))
            ->where(fn ($q) => $q
                ->where('leader_id', $userId)
                ->orWhereHas('members', fn ($m) => $m->where('user_id', $userId)->where('status', 'ACTIVE')))
            ->exists();
    }

    /**
     * id semua anggota ACTIVE team ACTIVE tempat user menjadi leader atau anggota ACTIVE
     * (termasuk dirinya bila anggota). Dasar scope antrean Todo List "Review Pengajuan":
     * teknisi tiket harus ada di himpunan ini (cermin sameActiveTeam()).
     *
     * @return Collection<int, int>
     */
    public function teamMemberIdsFor(int $userId): Collection
    {
        return Team::query()
            ->where('status', 'ACTIVE')
            ->where(fn ($q) => $q
                ->where('leader_id', $userId)
                ->orWhereHas('members', fn ($m) => $m->where('user_id', $userId)->where('status', 'ACTIVE')))
            ->with(['members' => fn ($m) => $m->where('status', 'ACTIVE')])
            ->get()
            ->flatMap(fn ($t) => $t->members->pluck('user_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /** Akun yang boleh me-review pengajuan tiket ini: supervisor ACTIVE di team teknisi (penerima notifikasi). */
    public function requestReviewers(TroubleTicket $ticket): Collection
    {
        $teams = Team::query()
            ->where('status', 'ACTIVE')
            ->whereHas('members', fn ($m) => $m->where('user_id', $ticket->assigned_to)->where('status', 'ACTIVE'))
            ->with(['members' => fn ($m) => $m->where('status', 'ACTIVE')])
            ->get();

        $ids = $teams->flatMap(fn ($t) => $t->members->pluck('user_id')->push($t->leader_id))
            ->filter()->map(fn ($id) => (int) $id)->unique();

        return app(RoleDirectory::class)->usersWithRoles(['supervisor'])
            ->filter(fn ($u) => $ids->contains((int) $u->id))
            ->values();
    }

    /**
     * Memvalidasi & menormalkan isian pengajuan. Total tiap baris (harga × qty)
     * DIHITUNG di sini; angka kiriman browser tidak pernah menentukan nilai tersimpan.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{plan: BudgetPlan, cluster: ?Cluster, typeId: int, isCa: bool, beneficiaryId: ?int, description: string, rows: array<int, array<string, mixed>>, total: int}
     */
    private function normalisasiPengajuan(TroubleTicket $t, array $header, array $items): array
    {
        $plan = ! empty($header['budget_plan_id']) ? BudgetPlan::with('project')->find((int) $header['budget_plan_id']) : null;

        if ($plan === null) {
            $this->fail('Budget Plan wajib dipilih.', 'budget_plan_id');
        }
        if ($plan->status !== 'ACTIVE') {
            $this->fail('Budget Plan harus berstatus ACTIVE.', 'budget_plan_id');
        }

        // Budget PROYEK wajib bercluster - aturan yang sama dengan form Cost Request.
        $cluster = null;
        if ($plan->isProjectBudget()) {
            // Sumber yang sama dengan dropdown Project di form Cost Request (milestone project).
            $kandidat = $plan->projectClusters();

            if ($kandidat->isEmpty()) {
                $this->fail(
                    'Budget Plan "' . $plan->code . '" bersumber dari proyek "' . ($plan->project?->name ?? $plan->project?->code ?? '-')
                    . '" yang belum punya Project (cluster) terhubung, padahal Cost Request dari budget proyek wajib memilih Project. '
                    . 'Hubungkan di milestone proyek (Project Master), atau pilih Budget Plan lain.',
                    'budget_plan_id'
                );
            }

            $cluster = ! empty($header['cluster_id'])
                ? $kandidat->firstWhere('id', (int) $header['cluster_id'])
                : ($kandidat->count() === 1 ? $kandidat->first() : null);

            if ($cluster === null) {
                $this->fail('Project wajib dipilih untuk budget proyek.', 'cluster_id');
            }
        }

        $type = ! empty($header['request_type_id']) ? RequestType::find((int) $header['request_type_id']) : null;
        if ($type === null) {
            $this->fail('Jenis Cost Request wajib dipilih.', 'request_type_id');
        }
        $isCa = in_array((int) $type->id, BudgetRequest::cashAdvanceTypeIds(), true);

        if ($isCa) {
            // Penerima dana = beneficiary INTERNAL milik PIC teknisi tiket ini (otomatis).
            $beneficiary = Beneficiary::where('category', 'INTERNAL')->where('status', 'ACTIVE')->where('pic_id', $t->pic_id)->first();
            if ($beneficiary === null) {
                $this->fail('Beneficiary Internal untuk PIC teknisi tiket ini belum ada (Master Data > Beneficiary).', 'beneficiary_id');
            }
        } else {
            $beneficiary = ! empty($header['beneficiary_id'])
                ? Beneficiary::where('status', 'ACTIVE')->find((int) $header['beneficiary_id'])
                : null;
            if ($beneficiary === null) {
                $this->fail('Beneficiary wajib dipilih untuk jenis ini.', 'beneficiary_id');
            }
        }

        $description = trim((string) ($header['description'] ?? ''));
        if ($description === '') {
            $this->fail('Deskripsi pengajuan wajib diisi.', 'description');
        }

        $items = array_values(array_filter($items, fn ($r) => filled($r['description'] ?? null) || filled($r['budget_detail_id'] ?? null)));
        if ($items === []) {
            $this->fail('Minimal satu baris biaya wajib diisi.', 'items');
        }

        // Baris bersandar pada SUB-ITEM budget plan, bukan COA lepas.
        $subItem = BudgetPlanDetail::with('account')
            ->where('budget_plan_id', $plan->id)
            ->whereIn('id', array_map(fn ($r) => (int) ($r['budget_detail_id'] ?? 0), $items))
            ->get()
            ->keyBy('id');

        $rows = [];
        $total = 0;

        foreach ($items as $i => $r) {
            $nomor = $i + 1;
            $detail = $subItem->get((int) ($r['budget_detail_id'] ?? 0));
            if ($detail === null) {
                $this->fail("Baris {$nomor}: sub-item biaya harus dipilih dari Budget Plan yang sama.", 'items');
            }

            $deskripsi = trim((string) ($r['description'] ?? ''));
            if ($deskripsi === '') {
                $this->fail("Baris {$nomor}: deskripsi wajib diisi.", 'items');
            }

            $harga = (int) ($r['amount'] ?? 0);
            $qty = (int) ($r['qty'] ?? 0);
            if ($harga <= 0 || $qty <= 0) {
                $this->fail("Baris {$nomor}: harga dan qty harus lebih dari nol.", 'items');
            }

            $baris = $harga * $qty;
            $total += $baris;

            $rows[] = [
                'source_item_id'   => ! empty($r['source_item_id']) ? (int) $r['source_item_id'] : null,
                'budget_detail_id' => $detail->id,
                'account_id'       => $detail->account_id,
                'account_name'     => $detail->account?->name,
                'sub_item_name'    => $detail->deskripsi,
                'description'      => mb_substr($deskripsi, 0, 255),
                'amount'           => $harga,
                'qty'              => $qty,
                'total'            => $baris,
                'keep'             => array_map('intval', (array) ($r['keep_attachment_ids'] ?? [])),
                'stored'           => (array) ($r['stored'] ?? []),
            ];
        }

        return [
            'plan' => $plan, 'cluster' => $cluster, 'typeId' => (int) $type->id, 'isCa' => $isCa,
            'beneficiaryId' => (int) $beneficiary->id, 'description' => mb_substr($description, 0, 500),
            'rows' => $rows, 'total' => $total,
        ];
    }

    /**
     * Membuat atau memperbarui pengajuan (oleh teknisi), sebagai REVISI BARU: baris
     * revisi sebelumnya tidak diubah atau dihapus. Status hasilnya DRAFT; dengan
     * $submit = true langsung diajukan (SUBMITTED) dalam transaksi yang sama.
     *
     * @param  array<string, mixed>  $header  budget_plan_id, request_type_id, cluster_id, beneficiary_id, description
     * @param  array<int, array<string, mixed>>  $items  source_item_id, budget_detail_id, description, amount, qty, keep_attachment_ids, files
     * @param  UploadedFile[]  $generalFiles  lampiran umum (tidak terikat baris)
     * @param  int[]  $keepGeneral  id lampiran umum yang dipertahankan
     */
    public function saveRequest(TroubleTicket $ticket, User $user, array $header, array $items, array $generalFiles = [], array $keepGeneral = [], ?int $requestId = null, bool $submit = false): TroubleTicketRequest
    {
        [$stored, $storedGeneral] = $this->simpanBerkasPengajuan($items, $generalFiles);
        $semuaPath = array_merge(...array_map(fn ($s) => array_column($s, 'path'), array_merge($stored, [$storedGeneral])));

        $req = null;

        try {
            DB::transaction(function () use ($ticket, $user, $header, $items, $stored, $storedGeneral, $keepGeneral, $requestId, $submit, &$req) {
                $t = $this->lock($ticket);
                $this->authorizeIf($this->canRequest($user, $t), 'Hanya teknisi tiket ini atau superadmin yang dapat mengajukan biaya, dan tiket belum Closed.');

                $req = $requestId
                    ? TroubleTicketRequest::where('trouble_ticket_id', $t->id)->whereKey($requestId)->lockForUpdate()->firstOrFail()
                    : null;

                if ($req) {
                    $this->authorizeIf($this->canManageRequest($user, $t, $req), 'Hanya pembuat pengajuan yang dapat mengubahnya.');
                    if (! in_array($req->status, TroubleTicketRequest::EDITABLE_BY_REQUESTER, true)) {
                        $this->fail('Pengajuan berstatus ' . $req->status_label . ' tidak dapat diubah.');
                    }
                }

                // Berkas yang diunggah ikut urutan baris setelah disaring; sisipkan ke hasil normalisasi.
                $norm = $this->normalisasiPengajuan($t, $header, $this->gabungBerkas($items, $stored));
                $req = $this->tulisRevisi($t, $req, $user, $norm, $storedGeneral, $keepGeneral, TroubleTicketRequest::DRAFT, true);

                if ($submit) {
                    $this->ajukan($t, $req, $user);
                }
            });
        } catch (\Throwable $e) {
            foreach ($semuaPath as $path) {
                Storage::disk('secure')->delete($path);
            }
            throw $e;
        }

        if ($submit) {
            $this->notifikasiPengajuanDiajukan($ticket->refresh(), $req, $user);
        }

        return $req;
    }

    /** Mengajukan pengajuan DRAFT yang sudah tersimpan (tanpa mengubah isinya). */
    public function submitRequest(TroubleTicket $ticket, User $user, int $requestId): TroubleTicketRequest
    {
        $req = null;

        DB::transaction(function () use ($ticket, $user, $requestId, &$req) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->canRequest($user, $t), 'Hanya teknisi tiket ini atau superadmin yang dapat mengajukan biaya, dan tiket belum Closed.');

            $req = TroubleTicketRequest::where('trouble_ticket_id', $t->id)->whereKey($requestId)->lockForUpdate()->firstOrFail();
            $this->authorizeIf($this->canManageRequest($user, $t, $req), 'Hanya pembuat pengajuan yang dapat mengajukannya.');

            if ($req->status !== TroubleTicketRequest::DRAFT) {
                $this->fail('Hanya pengajuan berstatus Draft yang dapat diajukan.');
            }

            $this->ajukan($t, $req, $user);
        });

        $this->notifikasiPengajuanDiajukan($ticket->refresh(), $req, $user);

        return $req;
    }

    /** DRAFT -> SUBMITTED + kejadian (dengan salinan baris) di timeline. Dipanggil di dalam transaksi. */
    private function ajukan(TroubleTicket $t, TroubleTicketRequest $req, User $user): void
    {
        $req->update([
            'status' => TroubleTicketRequest::SUBMITTED, 'submitted_at' => now(),
            'reviewed_by' => null, 'reviewed_at' => null, 'review_notes' => null,
        ]);

        $this->event($t, $user, 'request_submitted', $req->description, $this->metaPengajuan($req));
    }

    private function notifikasiPengajuanDiajukan(TroubleTicket $ticket, TroubleTicketRequest $req, User $user): void
    {
        $this->notify(
            $this->requestReviewers($ticket)->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Pengajuan biaya menunggu review',
            "{$user->name} mengajukan biaya {$ticket->ticket_number}: Rp " . number_format((int) $req->total, 0, ',', '.') . '.',
        );
    }

    /** Ringkasan pengajuan untuk metadata kejadian: jenis, budget plan, total, dan salinan baris. */
    private function metaPengajuan(TroubleTicketRequest $req, array $tambahan = []): array
    {
        $req->loadMissing(['requestType', 'budgetPlan']);

        return array_merge([
            'request_id'  => $req->id,
            'revision'    => $req->revision,
            'type'        => $req->requestType?->name,
            'budget_plan' => $req->budgetPlan?->code,
            'cost_total'  => (int) $req->total,
            'items'       => $req->currentItems()->get()->map(fn ($i) => [
                'account' => $i->account_name, 'sub_item' => $i->sub_item_name, 'description' => $i->description,
                'amount' => (int) $i->amount, 'qty' => (int) $i->qty, 'total' => (int) $i->total,
            ])->all(),
        ], $tambahan);
    }

    /**
     * Supervisor MENGUBAH isi pengajuan sebelum approve (Q7). Revisi baru dengan
     * created_by = supervisor, status tetap SUBMITTED; kejadian request_edited
     * mencatat siapa mengubah apa (baris +/-/~, total lama -> baru).
     */
    public function editRequestByReviewer(TroubleTicket $ticket, User $user, int $requestId, array $header, array $items, array $generalFiles = [], array $keepGeneral = []): TroubleTicketRequest
    {
        [$stored, $storedGeneral] = $this->simpanBerkasPengajuan($items, $generalFiles);
        $semuaPath = array_merge(...array_map(fn ($s) => array_column($s, 'path'), array_merge($stored, [$storedGeneral])));

        $req = null;

        try {
            DB::transaction(function () use ($ticket, $user, $requestId, $header, $items, $stored, $storedGeneral, $keepGeneral, &$req) {
                $t = $this->lock($ticket);
                $req = TroubleTicketRequest::where('trouble_ticket_id', $t->id)->whereKey($requestId)->lockForUpdate()->firstOrFail();

                $this->authorizeIf($this->canReviewRequest($user, $t, $req), 'Hanya supervisor team teknisi (bukan pembuat pengajuan) yang dapat mengubah pengajuan.');
                if ($req->status !== TroubleTicketRequest::SUBMITTED) {
                    $this->fail('Hanya pengajuan yang menunggu review yang dapat diubah supervisor.');
                }

                $norm = $this->normalisasiPengajuan($t, $header, $this->gabungBerkas($items, $stored));
                $req = $this->tulisRevisi($t, $req, $user, $norm, $storedGeneral, $keepGeneral, TroubleTicketRequest::SUBMITTED, false);
            });
        } catch (\Throwable $e) {
            foreach ($semuaPath as $path) {
                Storage::disk('secure')->delete($path);
            }
            throw $e;
        }

        $ticket->refresh();
        $this->notify(
            collect([$req->requester])->filter()->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Pengajuan biaya diubah supervisor',
            "{$ticket->ticket_number}: {$user->name} mengubah pengajuan biaya Anda (total Rp " . number_format((int) $req->total, 0, ',', '.') . ').',
        );

        return $req;
    }

    /**
     * Menulis revisi: header diperbarui, baris BARU ditambahkan (revisi + 1,
     * baris lama utuh), lampiran dibawa/ditambah. Mengembalikan pengajuan.
     * $tulisEventEdit = false pada penyimpanan oleh pemohon (draft tidak ditulis ke
     * timeline); true bila yang mengubah supervisor.
     *
     * @param  array<string, mixed>  $norm  hasil normalisasiPengajuan()
     * @param  array<int, array<string, mixed>>  $storedGeneral
     */
    private function tulisRevisi(TroubleTicket $t, ?TroubleTicketRequest $req, User $user, array $norm, array $storedGeneral, array $keepGeneral, string $statusAkhir, bool $olehPemohon): TroubleTicketRequest
    {
        $sebelum = $req ? $req->currentItems()->get() : collect();
        $totalLama = (int) ($req?->total ?? 0);
        $ringkasanHeader = $req ? $this->bedaHeader($req, $norm) : [];
        $revisi = $req ? $req->revision + 1 : 1;

        // Baris sumber harus benar-benar baris revisi berlaku pengajuan ini.
        $idSebelum = $sebelum->pluck('id')->map(fn ($v) => (int) $v)->all();
        foreach ($norm['rows'] as $r) {
            if ($r['source_item_id'] !== null && ! in_array($r['source_item_id'], $idSebelum, true)) {
                $this->fail('Baris sumber tidak ditemukan pada pengajuan ini.', 'items');
            }
        }

        // Lampiran Q10: jenis non-Cash Advance wajib berlampiran PER BARIS.
        if (! $norm['isCa']) {
            foreach ($norm['rows'] as $i => $r) {
                $ada = count($this->lampiranDipertahankan($req, $r['keep'])) + count($r['stored'] ?? []);
                if ($ada < 1) {
                    $this->fail('Baris ' . ($i + 1) . ': lampiran wajib untuk jenis Cost Request selain Cash Advance.', 'attachments');
                }
            }
        }

        $atribut = [
            'budget_plan_id' => $norm['plan']->id, 'cluster_id' => $norm['cluster']?->id,
            'request_type_id' => $norm['typeId'], 'beneficiary_id' => $norm['beneficiaryId'],
            'description' => $norm['description'], 'total' => $norm['total'],
            'status' => $statusAkhir, 'revision' => $revisi,
        ];

        if ($req) {
            $req->update($atribut);
        } else {
            $req = TroubleTicketRequest::create($atribut + [
                'trouble_ticket_id' => $t->id, 'requested_by' => $user->id,
            ]);
        }

        foreach ($norm['rows'] as $r) {
            $item = TroubleTicketRequestItem::create([
                'trouble_ticket_request_id' => $req->id, 'revision' => $revisi,
                'budget_detail_id' => $r['budget_detail_id'], 'account_id' => $r['account_id'],
                'account_name' => $r['account_name'], 'sub_item_name' => $r['sub_item_name'],
                'description' => $r['description'], 'amount' => $r['amount'], 'qty' => $r['qty'],
                'total' => $r['total'], 'created_by' => $user->id,
            ]);

            // Lampiran yang dipertahankan DISALIN sebagai baris baru (path sama): revisi lama tetap utuh.
            foreach ($this->lampiranDipertahankan($req, $r['keep']) as $lama) {
                TroubleTicketRequestAttachment::create([
                    'trouble_ticket_request_id' => $req->id, 'item_id' => $item->id, 'path' => $lama->path,
                    'original_name' => $lama->original_name, 'mime' => $lama->mime, 'size' => $lama->size,
                    'uploaded_by' => $lama->uploaded_by,
                ]);
            }
            foreach ($r['stored'] ?? [] as $s) {
                TroubleTicketRequestAttachment::create([
                    'trouble_ticket_request_id' => $req->id, 'item_id' => $item->id, 'path' => $s['path'],
                    'original_name' => $s['name'], 'mime' => $s['mime'], 'size' => $s['size'], 'uploaded_by' => $user->id,
                ]);
            }
        }

        // Lampiran umum: yang tidak dipertahankan dilepas (berkasnya tetap di disk), yang baru ditambahkan.
        if ($req->wasRecentlyCreated === false) {
            $req->attachments()->whereNull('item_id')->whereNotIn('id', array_map('intval', $keepGeneral) ?: [0])->delete();
        }
        foreach ($storedGeneral as $s) {
            TroubleTicketRequestAttachment::create([
                'trouble_ticket_request_id' => $req->id, 'item_id' => null, 'path' => $s['path'],
                'original_name' => $s['name'], 'mime' => $s['mime'], 'size' => $s['size'], 'uploaded_by' => $user->id,
            ]);
        }

        if (! $olehPemohon) {
            $diff = $this->bedaBaris($sebelum, $norm['rows'], $totalLama, (int) $norm['total'], $ringkasanHeader);
            $this->event($t, $user, 'request_edited', $diff['teks'], $this->metaPengajuan($req, ['perubahan' => $diff['data']]));
        }

        return $req->refresh();
    }

    /** Lampiran lama milik pengajuan ini yang ditandai "dipertahankan" pada sebuah baris. */
    private function lampiranDipertahankan(?TroubleTicketRequest $req, array $ids): Collection
    {
        if ($req === null || $ids === []) {
            return collect();
        }

        return $req->attachments()->whereIn('id', $ids)->get();
    }

    /** Perbedaan header (jenis, budget plan, cluster, beneficiary, deskripsi) lama -> baru, sebagai teks. */
    private function bedaHeader(TroubleTicketRequest $req, array $norm): array
    {
        $req->loadMissing(['requestType', 'budgetPlan', 'cluster', 'beneficiary']);
        $beda = [];
        $cek = function (string $nama, $lama, $baru) use (&$beda) {
            if ((string) $lama !== (string) $baru) {
                $beda[] = "{$nama}: " . ($lama ?: '-') . ' -> ' . ($baru ?: '-');
            }
        };
        $cek('Jenis', $req->requestType?->name, RequestType::find($norm['typeId'])?->name);
        $cek('Budget Plan', $req->budgetPlan?->code, $norm['plan']->code);
        $cek('Project', $req->cluster?->name, $norm['cluster']?->name);
        $cek('Beneficiary', $req->beneficiary?->nickname, Beneficiary::find($norm['beneficiaryId'])?->nickname);
        $cek('Deskripsi', $req->description, $norm['description']);

        return $beda;
    }

    /**
     * Ringkasan perubahan baris revisi sebelumnya -> yang baru (Q7). Baris cocok lewat
     * source_item_id; tanpa sumber = ditambah; baris lama tak dirujuk = dihapus.
     *
     * @return array{teks: string, data: array<string, mixed>}
     */
    private function bedaBaris(Collection $sebelum, array $rows, int $totalLama, int $totalBaru, array $header): array
    {
        $dirujuk = collect($rows)->pluck('source_item_id')->filter()->map(fn ($v) => (int) $v);
        $ditambah = [];
        $diubah = [];
        $dihapus = [];

        foreach ($rows as $r) {
            $lama = $r['source_item_id'] ? $sebelum->firstWhere('id', $r['source_item_id']) : null;
            if ($lama === null) {
                $ditambah[] = $r['description'] . ' (' . number_format($r['amount'], 0, ',', '.') . ' x ' . $r['qty'] . ')';
                continue;
            }
            if ((int) $lama->budget_detail_id !== (int) $r['budget_detail_id'] || $lama->description !== $r['description']
                || (int) $lama->amount !== $r['amount'] || (int) $lama->qty !== $r['qty']) {
                $diubah[] = $r['description'] . ': ' . number_format((int) $lama->amount, 0, ',', '.') . ' x ' . (int) $lama->qty
                    . ' -> ' . number_format($r['amount'], 0, ',', '.') . ' x ' . $r['qty'];
            }
        }
        foreach ($sebelum as $lama) {
            if (! $dirujuk->contains((int) $lama->id)) {
                $dihapus[] = $lama->description . ' (' . number_format((int) $lama->total, 0, ',', '.') . ')';
            }
        }

        $teks = [];
        if ($ditambah) { $teks[] = 'Ditambah ' . count($ditambah) . ' baris'; }
        if ($dihapus)  { $teks[] = 'Dihapus ' . count($dihapus) . ' baris'; }
        if ($diubah)   { $teks[] = 'Diubah ' . count($diubah) . ' baris'; }
        if ($header)   { $teks[] = implode('; ', $header); }
        $teks[] = 'Total Rp ' . number_format($totalLama, 0, ',', '.') . ' -> Rp ' . number_format($totalBaru, 0, ',', '.');

        return [
            'teks' => implode('. ', $teks) . '.',
            'data' => [
                'ditambah' => $ditambah, 'dihapus' => $dihapus, 'diubah' => $diubah, 'header' => $header,
                'total_lama' => $totalLama, 'total_baru' => $totalBaru,
            ],
        ];
    }

    /**
     * Menyimpan berkas pengajuan ke disk SEBELUM transaksi (pola storeFiles): satu berkas
     * ditolak = tidak ada yang tersimpan. Mengembalikan [per baris, umum].
     *
     * @return array{0: array<int, array<int, array<string, mixed>>>, 1: array<int, array<string, mixed>>}
     */
    private function simpanBerkasPengajuan(array $items, array $generalFiles): array
    {
        $perBaris = [];
        $jejak = [];

        try {
            foreach (array_values($items) as $i => $r) {
                $perBaris[$i] = $this->storeFiles((array) ($r['files'] ?? []));
                $jejak = array_merge($jejak, array_column($perBaris[$i], 'path'));
            }
            $umum = $this->storeFiles($generalFiles);
        } catch (\Throwable $e) {
            foreach ($jejak as $path) {
                Storage::disk('secure')->delete($path);
            }
            throw $e;
        }

        return [$perBaris, $umum];
    }

    /** Sisipkan hasil penyimpanan berkas ke baris yang sesuai (kunci 'stored'). */
    private function gabungBerkas(array $items, array $stored): array
    {
        $hasil = [];
        foreach (array_values($items) as $i => $r) {
            $r['stored'] = $stored[$i] ?? [];
            unset($r['files']);
            $hasil[] = $r;
        }

        return $hasil;
    }

    /**
     * Supervisor menyetujui pengajuan: membentuk Cost Request berstatus DRAFT dari
     * revisi terakhir. Applicant CR = supervisor yang meng-approve (Q6). Dua approve
     * bersamaan tidak membuat CR ganda: baris pengajuan terkunci, dan
     * budget_request_id UNIQUE.
     */
    public function approveRequest(TroubleTicket $ticket, User $user, int $requestId, ?string $notes = null): TroubleTicketRequest
    {
        $req = null;
        $cr = null;

        DB::transaction(function () use ($ticket, $user, $requestId, $notes, &$req, &$cr) {
            $t = $this->lock($ticket);
            $req = TroubleTicketRequest::where('trouble_ticket_id', $t->id)->whereKey($requestId)->lockForUpdate()->firstOrFail();

            $this->authorizeIf($this->canReviewRequest($user, $t, $req), 'Hanya supervisor team teknisi (bukan pembuat pengajuan) yang dapat menyetujui.');
            if ($req->status !== TroubleTicketRequest::SUBMITTED) {
                $this->fail('Hanya pengajuan yang menunggu review yang dapat disetujui.');
            }
            if ($req->budget_request_id !== null) {
                $this->fail('Pengajuan ini sudah punya Cost Request.');
            }

            $cr = $this->buatDraftCostRequest($t, $req, $user);

            $req->update([
                'status' => TroubleTicketRequest::APPROVED, 'reviewed_by' => $user->id,
                'reviewed_at' => now(), 'review_notes' => $notes, 'budget_request_id' => $cr->id,
            ]);

            $this->event($t, $user, 'request_approved', $notes, $this->metaPengajuan($req, ['cost_request' => $cr->code]));
        });

        $ticket->refresh();
        $this->notify(
            collect([$req->requester])->filter()->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Pengajuan biaya disetujui',
            "{$ticket->ticket_number}: pengajuan biaya Anda disetujui {$user->name}. Cost Request {$cr->code} dibuat sebagai draft.",
        );

        return $req;
    }

    /** Supervisor menolak pengajuan (alasan wajib); teknisi merevisi lalu mengajukan lagi. */
    public function rejectRequest(TroubleTicket $ticket, User $user, int $requestId, string $notes): TroubleTicketRequest
    {
        if (blank($notes)) {
            $this->fail('Alasan penolakan wajib diisi.', 'notes');
        }

        $req = null;

        DB::transaction(function () use ($ticket, $user, $requestId, $notes, &$req) {
            $t = $this->lock($ticket);
            $req = TroubleTicketRequest::where('trouble_ticket_id', $t->id)->whereKey($requestId)->lockForUpdate()->firstOrFail();

            $this->authorizeIf($this->canReviewRequest($user, $t, $req), 'Hanya supervisor team teknisi (bukan pembuat pengajuan) yang dapat menolak.');
            if ($req->status !== TroubleTicketRequest::SUBMITTED) {
                $this->fail('Hanya pengajuan yang menunggu review yang dapat ditolak.');
            }

            $req->update([
                'status' => TroubleTicketRequest::REJECTED, 'reviewed_by' => $user->id,
                'reviewed_at' => now(), 'review_notes' => $notes,
            ]);

            $this->event($t, $user, 'request_rejected', $notes, $this->metaPengajuan($req));
        });

        $ticket->refresh();
        $this->notify(
            collect([$req->requester])->filter()->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Pengajuan biaya ditolak',
            "{$ticket->ticket_number}: pengajuan biaya ditolak {$user->name}. Perbaiki lalu ajukan lagi.",
        );

        return $req;
    }

    /** Membatalkan pengajuan (alasan wajib): pembuatnya, atau reviewer. Dari Draft/Submitted/Rejected. */
    public function cancelRequest(TroubleTicket $ticket, User $user, int $requestId, string $reason): TroubleTicketRequest
    {
        if (blank($reason)) {
            $this->fail('Alasan pembatalan wajib diisi.', 'reason');
        }

        $req = null;

        DB::transaction(function () use ($ticket, $user, $requestId, $reason, &$req) {
            $t = $this->lock($ticket);
            $req = TroubleTicketRequest::where('trouble_ticket_id', $t->id)->whereKey($requestId)->lockForUpdate()->firstOrFail();

            $this->authorizeIf(
                $this->canManageRequest($user, $t, $req) || $this->canReviewRequest($user, $t, $req),
                'Hanya pembuat pengajuan atau reviewer yang dapat membatalkan.'
            );
            if (! in_array($req->status, TroubleTicketRequest::CANCELLABLE, true)) {
                $this->fail('Pengajuan berstatus ' . $req->status_label . ' tidak dapat dibatalkan.');
            }

            $req->update([
                'status' => TroubleTicketRequest::CANCELLED, 'cancelled_by' => $user->id,
                'cancelled_at' => now(), 'cancel_reason' => $reason,
            ]);

            $this->event($t, $user, 'request_cancelled', $reason, $this->metaPengajuan($req));
        });

        $ticket->refresh();
        $penerima = (int) $user->id === (int) $req->requested_by
            ? $this->requestReviewers($ticket)
            : collect([$req->requester])->filter();
        $this->notify($penerima->reject(fn ($u) => (int) $u->id === (int) $user->id), $ticket, 'Pengajuan biaya dibatalkan',
            "{$ticket->ticket_number}: pengajuan biaya dibatalkan {$user->name}.");

        return $req;
    }

    /**
     * Membuat Cost Request berstatus DRAFT dari revisi terakhir pengajuan.
     *
     * Aturan CR yang sudah berlaku diikuti apa adanya, tidak ditulis ulang: kode lewat
     * generateCode(), NOB lewat BudgetPlan::isNob()/nobOverage() (satu sumber dengan
     * form CR), rincian bersandar pada sub-item budget plan. Tidak ada tahap alur uang
     * yang dilewati: CR lahir sebagai DRAFT dan menempuh seluruh alur Cost Request.
     * Kuota Cash Advance TIDAK dicek di sini (tetap ditegakkan saat CR di-submit).
     */
    private function buatDraftCostRequest(TroubleTicket $t, TroubleTicketRequest $req, User $reviewer): BudgetRequest
    {
        $plan = BudgetPlan::with(['project', 'costCenter'])->find($req->budget_plan_id);

        if ($plan === null || $plan->status !== 'ACTIVE') {
            $this->fail('Budget Plan pengajuan ini tidak ditemukan atau sudah tidak ACTIVE.');
        }

        $baris = $req->currentItems()->with('budgetDetail')->orderBy('id')->get();

        if ($baris->isEmpty()) {
            $this->fail('Pengajuan ini belum punya baris biaya.');
        }

        $total = (int) $baris->sum('total');

        $sourceCode = $plan->isCostCenterBudget()
            ? (preg_replace('/\D/', '', $plan->costCenter?->code ?? '') ?: '0000')
            : ($plan->project?->code ?? '0000');

        $cr = BudgetRequest::create([
            'code'           => BudgetRequest::generateCode($sourceCode),
            'description'    => mb_substr(strtoupper('BIAYA ' . $t->ticket_number . ' - ' . ($t->site?->sitename ?? '') . ' - ' . $req->description), 0, 255),
            'is_nob'         => $plan->isNob($total),
            'nob_amount'     => $plan->nobOverage($total),
            'amount'         => 0,
            'notes'          => 'Dibuat dari pengajuan biaya Trouble Ticket ' . $t->ticket_number . '.',
            'applicant_id'   => $reviewer->id,
            'beneficiary_id' => $req->beneficiary_id,
            'type_id'        => $req->request_type_id,
            'cluster_id'     => $req->cluster_id,
            'budget_plan_id' => $plan->id,
            'status'         => 'DRAFT',
        ]);

        // Sisa sub-item (dasar NOB per baris) dimuat sekaligus untuk semua baris.
        \App\Models\BudgetPlanDetail::primeCostLines($baris->pluck('budgetDetail')->filter());

        foreach ($baris as $b) {
            // Qty pengajuan maupun rincian CR sama-sama bilangan bulat: baris pindah apa adanya.
            $sisaDetail = $b->budgetDetail?->remainingAmount() ?? 0;

            $detail = $cr->details()->create([
                'budget_detail_id' => $b->budget_detail_id,
                'account_id'       => $b->account_id,
                'description'      => trim(($b->account_name ?? '-') . ($b->sub_item_name ? ' — ' . $b->sub_item_name : '')),
                'amount'           => (int) $b->amount,
                'qty'              => (int) $b->qty,
                'total'            => (int) $b->total,
                'notes'            => $b->description,
                'is_nob'           => (int) $b->total > $sisaDetail,
                'nob_amount'       => (int) max(0, (int) $b->total - $sisaDetail),
            ]);

            // Lampiran baris -> "Lampiran Item" CR (berkas DISALIN: CR boleh menghapus berkasnya sendiri).
            foreach ($b->attachments as $a) {
                $salinan = $this->salinBerkas($a->path, 'cost_request_detail_attachments');
                if ($salinan) {
                    $detail->attachments()->create([
                        'original_name' => $a->original_name, 'stored_name' => basename($salinan), 'path' => $salinan,
                        'mime_type' => $a->mime, 'size' => $a->size, 'uploaded_by' => $reviewer->id,
                    ]);
                }
            }
        }

        // Lampiran umum pengajuan -> lampiran global CR.
        foreach ($req->attachments()->whereNull('item_id')->get() as $a) {
            $salinan = $this->salinBerkas($a->path, 'cost_request_attachments');
            if ($salinan) {
                $cr->attachments()->create([
                    'original_name' => $a->original_name, 'stored_name' => basename($salinan), 'path' => $salinan,
                    'mime_type' => $a->mime, 'size' => $a->size, 'uploaded_by' => $reviewer->id,
                ]);
            }
        }

        $this->lampirkanDetailTiket($cr, $t, $baris, $reviewer);

        $cr->recalculateAmount();
        $cr->addLog('DRAFT', 'DRAFT', 'created');

        return $cr->refresh();
    }

    /** Menyalin berkas di disk secure ke folder lain dengan nama acak; null bila sumbernya hilang. */
    private function salinBerkas(string $path, string $folder): ?string
    {
        $disk = Storage::disk('secure');

        if (! $disk->exists($path)) {
            Log::warning('Lampiran pengajuan tidak ditemukan saat membentuk Cost Request', ['path' => $path]);

            return null;
        }

        $baru = $folder . '/' . Str::random(40) . (($ext = pathinfo($path, PATHINFO_EXTENSION)) ? '.' . $ext : '');
        $disk->copy($path, $baru);

        return $baru;
    }

    /**
     * Mencatat bahwa Cost Request milik sebuah tiket SUDAH DIBAYAR, sebagai
     * kejadian di timeline chat & tab Log tiket (keputusan 20 Okt 2026).
     *
     * Dipanggil dari Treasury saat batch ditandai Paid. Tidak mengubah status
     * tiket maupun CR — murni jejak baca, supaya orang lapangan tahu dananya
     * sudah cair tanpa membuka modul Finance.
     *
     * Aman dipanggil berulang: tiket yang kejadiannya sudah ada dilewati,
     * sehingga pembayaran yang diproses dua kali tidak menggandakan baris.
     */
    public function recordCostRequestPaid(BudgetRequest $cr, User $user, ?string $notes = null): void
    {
        // Lewat pengajuan (baru) atau kolom budget_request_id tiket (data lama); satu kejadian PER CR.
        $ticket = TroubleTicket::forCostRequest($cr->id)->first();

        if ($ticket === null) {
            return;
        }

        if (! $this->tulisKejadianDibayar($ticket, $cr, $user, $notes)) {
            return;
        }

        $this->notify(
            collect([$ticket->technician])->filter()->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Biaya visit sudah dibayar',
            "{$ticket->ticket_number}: Cost Request {$cr->code} sudah dibayar.",
        );
    }

    /**
     * Menyamakan timeline tiket dengan keadaan CR-nya saat tiket dibuka.
     *
     * Hook di Treasury hanya menangkap pembayaran yang MELEWATI markPaid().
     * CR yang statusnya berubah lewat jalur lain — perbaikan data, impor, atau
     * alur lama — tidak akan pernah memicunya, sehingga timeline tiket diam
     * saja padahal dananya sudah cair. Pemeriksaan murah ini menutup celah
     * itu: satu query, dan hanya menulis bila kejadiannya memang belum ada.
     *
     * Pelakunya dicatat sebagai pemohon CR (atau penyetuju plan), bukan orang
     * yang kebetulan membuka tiket.
     */
    public function syncCostRequestPaid(TroubleTicket $ticket): void
    {
        // Semua CR tiket ini: satu dari kolom lama + satu per pengajuan yang disetujui.
        foreach ($this->costRequestsOf($ticket) as $cr) {
            // CLOSED ikut dihitung: CR non-Cash Advance langsung ditutup saat dibayar.
            if (! in_array($cr->status, ['PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'CLOSED'], true)) {
                continue;
            }

            $pelaku = $cr->applicant ?? $ticket->planApprover;

            if ($pelaku === null) {
                continue;
            }

            $this->tulisKejadianDibayar($ticket, $cr, $pelaku, null);
        }
    }

    /**
     * Semua Cost Request milik tiket: dari pengajuan yang disetujui, ditambah CR lama
     * (trouble_tickets.budget_request_id, sebelum Pengajuan Biaya) bila ada.
     *
     * @return Collection<int, BudgetRequest>
     */
    public function costRequestsOf(TroubleTicket $ticket): Collection
    {
        $ids = $ticket->requests()->whereNotNull('budget_request_id')->pluck('budget_request_id')
            ->when($ticket->budget_request_id, fn ($c) => $c->push($ticket->budget_request_id))
            ->unique()->values();

        return $ids->isEmpty()
            ? collect()
            : BudgetRequest::with('applicant')->whereIn('id', $ids)->orderBy('id')->get();
    }

    /** Menulis kejadian sekali saja; true bila baris benar-benar dibuat. */
    private function tulisKejadianDibayar(TroubleTicket $ticket, BudgetRequest $cr, User $pelaku, ?string $notes): bool
    {
        // Unik PER CR (bukan per tiket): satu tiket bisa punya banyak CR yang dibayar sendiri-sendiri.
        $sudah = TroubleTicketMessage::where('trouble_ticket_id', $ticket->id)
            ->where('event', 'cost_request_paid')
            ->get(['id', 'metadata'])
            ->contains(fn ($m) => ($m->metadata['cost_request'] ?? null) === $cr->code);

        if ($sudah) {
            return false;
        }

        $this->event($ticket, $pelaku, 'cost_request_paid', $notes, [
            'cost_request' => $cr->code,
            'cost_total'   => (int) $cr->amount,
            'paid_at'      => $cr->paid_at?->toDateTimeString(),
        ]);

        return true;
    }

    /**
     * Melampirkan ringkasan detail tiket (PNG) sebagai "Lampiran Tambahan /
     * Pendukung" pada Cost Request yang baru dibuat.
     *
     * Tipe Cash Advance MEWAJIBKAN lampiran global; tanpa ini CR draft hasil
     * approve tidak bisa disimpan ulang oleh Finance karena lampirannya kosong
     * (keputusan 20 Okt 2026). Isinya cermin modal Info tiket tanpa bagian SLA.
     *
     * Kegagalan menggambar TIDAK membatalkan approve: CR-nya tetap sah, hanya
     * lampirannya perlu diisi manusia. Karena itu dibungkus try/catch dan
     * dicatat di log aplikasi.
     *
     * @param  \Illuminate\Support\Collection  $baris  baris biaya pengajuan
     */
    private function lampirkanDetailTiket(BudgetRequest $cr, TroubleTicket $t, $baris, User $user): void
    {
        try {
            $png = TicketDetailImage::render($t, $baris);
            $nama = 'detail-tiket-' . str_replace(['/', '\\', ' '], '-', $t->ticket_number) . '.png';
            $path = 'cost_request_attachments/' . Str::random(40) . '.png';

            Storage::disk('secure')->put($path, $png);

            $cr->attachments()->create([
                'original_name' => $nama,
                'stored_name'   => basename($path),
                'path'          => $path,
                'mime_type'     => 'image/png',
                'size'          => strlen($png),
                'uploaded_by'   => $user->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Lampiran detail tiket gagal dibuat', [
                'ticket' => $t->ticket_number,
                'cr'     => $cr->code,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PLAN → TRIP (digeser teknisi)
    |--------------------------------------------------------------------------
    */

    /** Tanggal plan visit sudah tiba — swipe "Swipe for travel" boleh digeser. */
    public function tripDue(TroubleTicket $ticket): bool
    {
        return $ticket->status === 'PLAN'
            && $ticket->visit_site !== null
            && ! $ticket->visit_site->copy()->startOfDay()->isFuture();
    }

    /*
    |--------------------------------------------------------------------------
    | TRIP — perjalanan, batal, on site
    |--------------------------------------------------------------------------
    */

    /**
     * Geser "Swipe for travel": tiket masuk tahap Trip dan SLA Travel mulai
     * berjalan. Tidak ada lagi swipe Trip tersendiri (keputusan 20 Okt 2026) —
     * tahap Trip dimulai oleh keberangkatan itu sendiri. Evidence perjalanan
     * wajib dilampirkan (keputusan 15 Okt 2026) — berkasnya menempel pada
     * kejadian `travel_started` di timeline, sama seperti evidence form close.
     *
     * @param  UploadedFile[]  $files
     */
    public function startTravel(TroubleTicket $ticket, User $user, array $files = []): void
    {
        $stored = $this->storeFiles($files);

        if (empty($stored)) {
            $this->fail('Evidence perjalanan wajib dilampirkan.', 'attachments');
        }

        $this->withStoredFiles($stored, function () use ($ticket, $user, $stored) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->actsAsTechnician($user, $t), 'Hanya teknisi yang ditugaskan atau superadmin yang dapat memulai perjalanan.');
            // Tahap Plan: tanggal visit harus sudah tiba. Tahap Trip tetap
            // diterima untuk tiket lama yang sudah terlanjur di tahap itu.
            $this->expect($t, ['PLAN', 'TRIP'], 'Perjalanan hanya bisa dimulai dari tahap Plan atau Trip.');

            if ($t->status === 'PLAN' && ! $this->tripDue($t)) {
                $this->fail('Tanggal plan visit belum tiba.');
            }
            if ($t->travel_started_at !== null) {
                $this->fail('Perjalanan sudah dimulai.');
            }

            $t->update([
                'status' => 'TRIP',
                'travel_started_at' => now(),
                'travel_arrived_at' => null,
                'travel_seconds' => null,
                'travel_cancelled_at' => null,
                'travel_cancel_reason' => null,
            ]);

            $message = $this->event($t, $user, 'travel_started');
            $this->attach($t, $message, $user, $stored);
        });
    }

    /**
     * Perjalanan dibatalkan di tengah jalan: SLA Travel direset (jejak
     * waktunya dikosongkan, jadi tidak ikut dihitung) dan tiket KEMBALI ke
     * tahap Plan dengan tanggal kunjungan berikutnya — bukan tetap di Trip
     * (keputusan 19 Okt 2026). Teknisi menggeser "Swipe for travel" lagi saat
     * tanggal itu tiba. Alasannya tetap tercatat di log.
     */
    public function cancelTravel(TroubleTicket $ticket, User $user, string $reason, ?string $visitSite = null): void
    {
        if (trim($reason) === '') {
            $this->fail('Alasan pembatalan wajib diisi.', 'reason');
        }
        if (blank($visitSite)) {
            $this->fail('Tanggal plan visit berikutnya wajib diisi.', 'visit_site');
        }

        DB::transaction(function () use ($ticket, $user, $reason, $visitSite) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->actsAsTechnician($user, $t), 'Hanya teknisi yang ditugaskan atau superadmin yang dapat membatalkan perjalanan.');
            $this->expect($t, ['TRIP'], 'Pembatalan perjalanan hanya berlaku pada tahap Trip.');

            if ($t->travel_started_at === null) {
                $this->fail('Perjalanan belum dimulai.');
            }
            if ($t->travel_arrived_at !== null) {
                $this->fail('Teknisi sudah tiba di site; perjalanan tidak bisa dibatalkan.');
            }
            $this->assertVisitDateAllowed($t, $visitSite);

            $mulai = $t->travel_started_at;

            // SLA Travel kembali ke 0 — jejaknya tetap ada di travel_cancelled_at,
            // travel_cancel_reason dan kejadian `travel_cancelled` (berisi jam
            // keberangkatan yang dibatalkan), jadi riwayatnya tidak hilang.
            $t->update([
                'travel_started_at' => null,
                'travel_seconds' => null,
                'travel_cancelled_at' => now(),
                'travel_cancel_reason' => $reason,
                'visit_site' => $visitSite,
                // Selalu kembali menunggu di tahap Plan, walau tanggal
                // berikutnya hari ini juga: tahap Trip dimulai oleh teknisi.
                'status' => 'PLAN',
            ]);

            $this->event($t, $user, 'travel_cancelled', $reason, [
                'started_at' => $mulai?->toIso8601String(),
                'visit_site' => $t->visit_site?->toDateString(),
            ]);
        });

        $ticket->refresh();
        $this->notify(
            $this->supervisorsFor((int) $ticket->assigned_to),
            $ticket,
            'Visit dibatalkan',
            "{$user->name} membatalkan perjalanan {$ticket->ticket_number}: {$reason} Plan visit berikutnya ".$ticket->visit_site?->format('d/m/Y').'.'
        );
    }

    /** Konfirmasi tiba di site: SLA Travel berhenti. */
    public function arriveOnSite(TroubleTicket $ticket, User $user): void
    {
        DB::transaction(function () use ($ticket, $user) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->actsAsTechnician($user, $t), 'Hanya teknisi yang ditugaskan atau superadmin yang dapat konfirmasi On Site.');
            $this->expect($t, ['TRIP'], 'Konfirmasi On Site hanya berlaku pada tahap Trip.');

            if ($t->travel_started_at === null) {
                $this->fail('Mulai perjalanan dulu sebelum konfirmasi On Site.');
            }
            if ($t->travel_arrived_at !== null) {
                $this->fail('Kedatangan sudah dikonfirmasi.');
            }

            $now = now();
            $t->update([
                'travel_arrived_at' => $now,
                'travel_seconds' => max(0, $now->getTimestamp() - $t->travel_started_at->getTimestamp()),
            ]);

            $this->event($t, $user, 'arrived_on_site');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | IN PROGRESS
    |--------------------------------------------------------------------------
    */

    /** Geser "mulai progres": SLA Resolution mulai berjalan. */
    public function startProgress(TroubleTicket $ticket, User $user): void
    {
        DB::transaction(function () use ($ticket, $user) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->actsAsTechnician($user, $t), 'Hanya teknisi yang ditugaskan atau superadmin yang dapat memulai progres.');
            $this->expect($t, ['TRIP'], 'Progres dimulai setelah teknisi tiba di site.');

            if ($t->travel_arrived_at === null) {
                $this->fail('Konfirmasi On Site dulu sebelum memulai progres.');
            }

            // Timestamp troubleshooting tidak pernah ditimpa.
            $t->update([
                'status' => 'IN_PROGRESS',
                'progress_started_at' => $t->progress_started_at ?? now(),
            ]);

            $this->event($t, $user, 'progress_started');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | PROGRESS DONE → CLOSED
    |--------------------------------------------------------------------------
    */

    /**
     * Teknisi mengirim form close (RCA, Sub-RCA, keterangan, evidence) dari
     * jalur Remote maupun In Progress. Tiket belum selesai: ia menunggu
     * approval supervisor di tahap Progress Done — SLA Resolution masih jalan.
     *
     * @param  array  $data  rca (wajib), sub_rca?, resolution_summary (wajib)
     * @param  UploadedFile[]  $files
     */
    public function close(TroubleTicket $ticket, User $user, array $data, array $files = []): void
    {
        $stored = $this->storeFiles($files);

        $this->withStoredFiles($stored, function () use ($ticket, $user, $data, $stored) {
            $t = $this->lock($ticket);
            $this->expect($t, TroubleTicket::CLOSABLE, 'Form close hanya bisa dikirim dari tahap Open, Remote, Plan, Trip, atau In Progress.');

            // Sudah On Site: TEKNISI harus memulai progres dulu (19 Okt 2026),
            // tiket ditutup dari In Progress. Peran lain tidak terikat ini.
            if ($t->status === 'TRIP' && $t->travel_arrived_at !== null && $this->isTechnician($user, $t)) {
                $this->fail('Teknisi sudah di site; mulai progres dulu sebelum mengirim form close.');
            }

            // Sisanya (siapa yang boleh, tahap Trip bagi teknisi) di canClose().
            $this->authorizeIf($this->canClose($user, $t), 'Anda tidak berhak mengirim form close untuk tiket ini.');

            // Close dari tahap Trip sebelum teknisi tiba: perjalanan yang
            // sedang berjalan dibatalkan otomatis (keputusan 15 Okt 2026),
            // jadi SLA Travel tidak ikut dihitung.
            if ($t->status === 'TRIP' && $t->travel_started_at !== null && $t->travel_arrived_at === null) {
                $alasanBatal = 'Gangguan selesai sebelum tiba di site; form close dikirim.';
                $t->update([
                    'travel_started_at' => null,
                    'travel_cancelled_at' => $t->travel_cancelled_at ?? now(),
                    'travel_cancel_reason' => $alasanBatal,
                ]);
                $this->event($t, $user, 'travel_cancelled', $alasanBatal);
            }

            // Evidence wajib untuk SEMUA tiket (keputusan 12 Okt 2026).
            if (empty($stored)) {
                $this->fail('Evidence wajib dilampirkan.', 'attachments');
            }

            $now = now();

            // RCA/Sub-RCA tiket DIPERBARUI sesuai isian form close (keputusan
            // 12 Okt 2026): nilai saat tiket dibuat hanya dugaan awal. Teks
            // diambil dari master supaya tetap terisi walau browser tidak
            // sempat menyalin namanya.
            $rcaId = isset($data['rca_id']) ? (int) $data['rca_id'] : $t->rca_id;
            $subRcaId = isset($data['sub_rca_id']) ? (int) $data['sub_rca_id'] : null;
            $data['rca'] = $data['rca'] ?: TroubleTicketRca::whereKey($rcaId)->value('name');
            $data['sub_rca'] = ($data['sub_rca'] ?? null) ?: ($subRcaId ? TroubleTicketSubRca::whereKey($subRcaId)->value('name') : null);

            $hasil = [
                'rca' => $data['rca'],
                'sub_rca' => $data['sub_rca'] ?? null,
                // Kolom `action` tidak diisi lagi (keputusan 12 Okt 2026);
                // keterangan penyelesaian yang jadi isian wajib.
                'resolution_summary' => $data['resolution_summary'],
            ];

            $t->update($hasil + [
                'status' => 'PROGRESS_DONE',
                // Tiket yang ditutup langsung dari tahap Plan belum pernah
                // memulai progres; patokan SLA Resolution-nya saat form dikirim.
                'progress_started_at' => $t->progress_started_at ?? $now,
                'rca_id' => $rcaId,
                'sub_rca_id' => $subRcaId,
                'resolved_at' => $now,
                'progress_done_at' => $now,
                'approval' => 'PENDING',
                // "Close by" = pelaku yang mengisi & mengirim form close
                // (keputusan 16 Okt 2026), bukan yang menyetujuinya; penyetuju
                // tercatat terpisah di approved_by.
                'closed_by' => $user->id,
            ]);

            $message = $this->event($t, $user, 'progress_done', $hasil['resolution_summary'], [
                'rca' => $hasil['rca'],
                'sub_rca' => $hasil['sub_rca'],
            ]);
            $this->attach($t, $message, $user, $stored);
        });

        $ticket->refresh();

        $this->notify(
            $this->approvers($ticket)->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Trouble Ticket menunggu approval',
            "{$ticket->ticket_number} selesai dikerjakan {$user->name} dan menunggu persetujuan Anda."
        );
    }

    /** Supervisor menyetujui penutupan — tiket CLOSED, SLA Resolution berhenti. */
    public function approveClose(TroubleTicket $ticket, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($ticket, $user, $notes) {
            $t = $this->lock($ticket);
            $this->expect($t, ['PROGRESS_DONE'], 'Tiket tidak sedang menunggu approval.');
            $this->authorizeIf($this->canApproveClose($user, $t), 'Hanya supervisor yang dapat menyetujui penutupan tiket.');

            $now = now();
            $t->update([
                'status' => 'CLOSED',
                'approval' => 'APPROVED',
                'approved_at' => $now,
                'approved_by' => $user->id,
                'closed_at' => $now,
                // closed_by tidak ditimpa: sudah diisi saat form close dikirim.
                'resolution_seconds' => $t->progress_started_at
                    ? max(0, $now->getTimestamp() - $t->progress_started_at->getTimestamp())
                    : null,
            ]);

            // Jalur penanganan ikut dicatat supaya label log bisa menulis
            // "Closed - Remote" untuk tiket yang ditutup tanpa kunjungan.
            $this->event($t, $user, 'closed', $notes, [
                'visit_decision' => $t->visit_decision,
            ]);
        });

        $ticket->refresh();
        $penerima = collect([$ticket->technician, $ticket->creator])
            ->filter()
            ->reject(fn ($u) => (int) $u->id === (int) $user->id);

        $this->notify($penerima, $ticket, 'Trouble Ticket Closed',
            "{$ticket->ticket_number} disetujui dan ditutup oleh {$user->name}.");
    }

    /**
     * Supervisor menolak penutupan — tiket kembali ke tahap pengerjaannya
     * (Remote untuk jalur non-visit, In Progress untuk jalur visit) supaya
     * teknisi memperbaiki isian form close. SLA Resolution tetap berjalan.
     */
    public function rejectClose(TroubleTicket $ticket, User $user, string $notes): void
    {
        if (trim($notes) === '') {
            $this->fail('Alasan penolakan wajib diisi.', 'notes');
        }

        DB::transaction(function () use ($ticket, $user, $notes) {
            $t = $this->lock($ticket);
            $this->expect($t, ['PROGRESS_DONE'], 'Tiket tidak sedang menunggu approval.');
            $this->authorizeIf($this->canApproveClose($user, $t), 'Hanya supervisor yang dapat menolak penutupan tiket.');

            $t->update([
                'status' => $t->visit_decision === TroubleTicket::NON_VISIT ? 'REMOTE' : 'IN_PROGRESS',
                'approval' => 'REJECTED',
                'resolved_at' => null,
                'progress_done_at' => null,
                // Pengajuan close ditolak: penutupnya ikut dikosongkan, diisi
                // lagi oleh siapa pun yang mengirim form close berikutnya.
                'closed_by' => null,
            ]);

            $this->event($t, $user, 'close_rejected', $notes);
        });

        $ticket->refresh();
        $this->notify([$ticket->technician], $ticket, 'Penutupan tiket ditolak',
            "{$ticket->ticket_number} dikembalikan oleh {$user->name}: {$notes}");
    }

    /** Akun yang berhak approve penutupan tiket (role supervisor & superadmin). */
    public function approvers(TroubleTicket $ticket): Collection
    {
        return app(RoleDirectory::class)->usersWithRoles(AccessRoles::TROUBLE_TICKET_APPROVE);
    }

    /*
    |--------------------------------------------------------------------------
    | REASSIGN
    |--------------------------------------------------------------------------
    */

    /**
     * Status yang boleh dialihkan teknisinya. Jendelanya sempit (15 Okt 2026):
     * sejak plan visit dibuat sampai tepat sebelum teknisi menggeser "mulai
     * perjalanan". Begitu perjalanan jalan — apalagi setelah On Site — tiket
     * tidak bisa dipindahkan lagi; lihat canReassign().
     */
    public const REASSIGNABLE = ['PLAN', 'TRIP'];

    /** Tiket masih boleh ganti teknisi: tahap Plan/Trip dan perjalanan belum dimulai. */
    public function canReassign(TroubleTicket $ticket): bool
    {
        return in_array($ticket->status, self::REASSIGNABLE, true)
            && $ticket->travel_started_at === null
            && $ticket->travel_arrived_at === null;
    }

    public function reassign(TroubleTicket $ticket, User $user, int $newTechnicianId, string $reason, ?string $visitSite = null): void
    {
        $lama = null;

        DB::transaction(function () use ($ticket, $user, $newTechnicianId, $reason, $visitSite, &$lama) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->canReassignBy($user, $t), 'Hanya leader team teknisi atau supervisor yang dapat mengalihkan tiket.');
            $this->expect($t, self::REASSIGNABLE, 'Tiket pada status ini tidak bisa dialihkan.');
            if (! $this->canReassign($t)) {
                $this->fail('Perjalanan sudah dimulai; teknisi tidak bisa diganti lagi.');
            }

            if ((int) $t->assigned_to === $newTechnicianId) {
                $this->fail('Teknisi baru sama dengan teknisi saat ini.', 'assigned_to');
            }
            // Kandidat Change Teknisi = Teknisi Backup site ini (16 Okt 2026).
            if (! $this->reassignCandidates($t)->contains('id', $newTechnicianId)) {
                $this->fail('Teknisi harus terdaftar sebagai Teknisi Backup site ini dan berstatus aktif.', 'assigned_to');
            }

            $now = now();
            $lama = (int) $t->assigned_to;

            TroubleTicketAssignment::where('trouble_ticket_id', $t->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => $now]);

            TroubleTicketAssignment::create([
                'trouble_ticket_id' => $t->id,
                'assigned_to' => $newTechnicianId,
                'assigned_by' => $user->id,
                'assigned_at' => $now,
                'reason' => $reason,
            ]);

            $changes = [
                'assigned_to' => $newTechnicianId,
                'pic_id' => $this->picIdFor($t->site_id, $newTechnicianId),
                'assigned_at' => $now,
            ];
            // Belum ada keputusan kunjungan: teknisi baru yang merespons & memutuskan.
            if ($t->visit_decision === null) {
                $changes['status'] = 'OPEN';
            }
            // Reschedule tahap Plan & Trip dilakukan dari form ini (tidak ada
            // tombol Reschedule tersendiri di tahap Trip): tiket kembali ke Plan
            // dengan tanggal baru, dan jejak perjalanan yang batal dikosongkan
            // supaya SLA Travel dihitung ulang dari keberangkatan berikutnya.
            $jadwalUlang = filled($visitSite)
                && in_array($t->status, ['PLAN', 'TRIP'], true)
                && $t->travel_arrived_at === null;
            if ($jadwalUlang) {
                $this->assertVisitDateAllowed($t, $visitSite);
                $changes = array_merge($changes, [
                    'status' => 'PLAN',
                    'visit_site' => $visitSite,
                    'travel_started_at' => null,
                    'travel_arrived_at' => null,
                    'travel_seconds' => null,
                ]);
            }
            $t->update($changes);

            /* Jadwal ulang ikut masuk ke kejadian `reassigned` ini — bukan
               kejadian `plan_updated` tersendiri (16 Okt 2026): satu aksi di
               layar sebaiknya satu baris log dengan satu keterangan, tidak
               terbaca seperti dua tindakan terpisah beralasan sama. */
            $this->event($t, $user, 'reassigned', $reason, array_filter([
                'from_id' => $lama,
                'from_name' => User::whereKey($lama)->value('name'),
                'to_id' => $newTechnicianId,
                'to_name' => User::whereKey($newTechnicianId)->value('name'),
                'visit_site' => $jadwalUlang ? $t->visit_site?->toDateString() : null,
                'target_online' => $jadwalUlang ? $t->target_online?->toDateString() : null,
            ], fn ($v) => $v !== null));
        });

        $ticket->refresh();
        $this->notify([$ticket->technician], $ticket, 'Trouble Ticket dialihkan kepada Anda',
            "{$ticket->ticket_number}: {$ticket->issue_label}");
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

        // Tiket CLOSED tetap bisa didiskusikan (keputusan 19 Okt 2026), dan
        // tidak ada lagi cara menutup diskusi sebuah tiket: fitur Stop Chat
        // dihapus (keputusan 19 Okt 2026).

        $body = $body !== null ? trim($body) : null;
        if (($body === null || $body === '') && empty($files)) {
            $this->fail('Pesan atau lampiran wajib diisi.', 'body');
        }

        $stored = $this->storeFiles($files);

        try {
            return $this->withStoredFiles($stored, function () use ($ticket, $user, $body, $stored, $clientToken) {
                $message = TroubleTicketMessage::create([
                    'trouble_ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'actor_name' => $user->name,
                    'actor_role' => $user->role?->name,
                    'type' => TroubleTicketMessage::TYPE_MESSAGE,
                    'body' => $body !== '' ? $body : null,
                    'client_token' => $clientToken,
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

    /**
     * Tanggal plan visit tidak boleh lebih awal dari batas tiket
     * (TroubleTicket::planMinDate(), permintaan 19 Okt 2026): hari ini untuk
     * plan pertama, hari plan dibuat untuk perubahan/reschedule.
     */
    private function assertVisitDateAllowed(TroubleTicket $t, string $visitSite): void
    {
        $min = $t->planMinDate();

        if (Carbon::parse($visitSite)->startOfDay()->lt($min)) {
            $this->fail(
                'Tanggal plan visit tidak boleh sebelum '.$min->format('d/m/Y').'.',
                'visit_site',
            );
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
            'user_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role?->name,
            'type' => TroubleTicketMessage::TYPE_EVENT,
            'event' => $code,
            'body' => $body,
            'metadata' => $meta ?: null,
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
                $this->fail('Isi berkas "'.$file->getClientOriginalName().'" tidak sesuai jenis yang diizinkan.', 'attachments');
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
                'message_id' => $message->id,
                'uploaded_by' => $user->id,
                'original_name' => $s['name'],
                'storage_path' => $s['path'],
                'mime_type' => $s['mime'],
                'size_bytes' => $s['size'],
                'file_hash' => $s['hash'],
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

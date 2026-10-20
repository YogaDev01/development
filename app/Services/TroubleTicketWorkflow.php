<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanDetail;
use App\Models\BudgetRequest;
use App\Models\Cluster;
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
use App\Models\TroubleTicketRca;
use App\Models\TroubleTicketSlaPolicy;
use App\Models\TroubleTicketSubRca;
use App\Models\User;
use App\Services\TicketDetailImage;
use App\Support\AccessRoles;
use App\Support\BudgetConsumption;
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
     * Memvalidasi & menormalkan isian Biaya pada Create Plan.
     *
     * Totalnya DIHITUNG di sini (harga × qty, dibulatkan ke rupiah penuh)
     * supaya angka kiriman browser tidak pernah menentukan nilai tersimpan.
     * Nama COA ikut disalin sebagai jejak baca.
     *
     * @return array{0: BudgetPlan, 1: array<int, array<string, mixed>>, 2: int}
     */
    private function siapkanBiayaPlan(?int $budgetPlanId, array $items): array
    {
        $budgetPlan = $budgetPlanId ? BudgetPlan::find($budgetPlanId) : null;

        if ($budgetPlan === null) {
            $this->fail('Budget Plan wajib dipilih.', 'budget_plan_id');
        }

        // Hanya pagu yang sudah berjalan yang boleh jadi sumber dana.
        if ($budgetPlan->status !== 'ACTIVE') {
            $this->fail('Budget Plan harus berstatus ACTIVE.', 'budget_plan_id');
        }

        /* Budget PROYEK wajib bercluster — aturannya sama dengan form Cost
           Request (cluster_id required bila isProjectBudget). Dulu syarat ini
           baru ketahuan saat supervisor menekan Approve, jadi plan sudah telanjur
           disusun lalu mentok tanpa penjelasan apa pun. Diperiksa sejak plan
           disimpan (20 Okt 2026), dengan pesan yang menyebut proyeknya dan
           tempat memperbaikinya. */
        if ($budgetPlan->isProjectBudget()
            && ! Cluster::where('project_id', $budgetPlan->project_id)->where('status', 'ACTIVE')->exists()) {
            $this->fail(
                'Budget Plan "' . $budgetPlan->code . '" bersumber dari proyek "'
                . ($budgetPlan->project?->name ?? $budgetPlan->project?->code ?? '-')
                . '" yang belum punya Cluster berstatus ACTIVE, padahal Cost Request dari budget proyek wajib bercluster. '
                . 'Tambahkan cluster-nya lebih dulu di Master Data > Project Master, atau pilih Budget Plan lain.',
                'budget_plan_id'
            );
        }

        $items = array_values(array_filter($items, fn ($r) => filled($r['description'] ?? null)));

        if ($items === []) {
            $this->fail('Minimal satu baris item biaya wajib diisi.', 'costs');
        }

        /* Baris digantung ke SUB-ITEM budget plan, bukan ke COA lepas: rincian
           Cost Request memang bersandar pada budget_plan_details, jadi draft CR
           nanti terbentuk tanpa menebak pasangan COA-nya. */
        $subItem = BudgetPlanDetail::with('account')
            ->where('budget_plan_id', $budgetPlan->id)
            ->whereIn('id', array_map(fn ($r) => (int) ($r['budget_detail_id'] ?? 0), $items))
            ->get()
            ->keyBy('id');

        $baris = [];
        $total = 0;

        foreach ($items as $r) {
            $detailId = (int) ($r['budget_detail_id'] ?? 0);
            if (! $subItem->has($detailId)) {
                $this->fail('Sub-item biaya harus dipilih dari Budget Plan yang sama.', 'costs');
            }

            $detail = $subItem[$detailId];
            $harga  = (int) ($r['amount'] ?? 0);
            // Qty bilangan bulat: satuannya trip/orang/hari/unit (20 Okt 2026).
            $qty    = (int) ($r['qty'] ?? 0);

            if ($harga <= 0 || $qty <= 0) {
                $this->fail('Harga dan qty setiap baris biaya harus lebih dari nol.', 'costs');
            }

            $barisTotal = $harga * $qty;
            $total += $barisTotal;

            $baris[] = [
                'budget_detail_id' => $detail->id,
                'account_id'       => $detail->account_id,
                'account_name'     => $detail->account?->name,
                'description'      => trim((string) $r['description']),
                'amount'           => $harga,
                'qty'              => $qty,
                'total'            => $barisTotal,
            ];
        }

        return [$budgetPlan, $baris, $total];
    }

    /**
     * Membuat / mengubah Plan Visit: tiket menunggu tanggal plan visit. Tidak
     * ada approval plan (keputusan 12 Okt 2026), dan tiket TIDAK naik sendiri
     * ke tahap Trip saat tanggalnya tiba (keputusan 19 Okt 2026) — teknisi
     * yang menggeser "Swipe for travel" (lihat startTravel()).
     *
     * @param  string  $visitSite  tanggal rencana kunjungan (Y-m-d)
     */
    /**
     * @param  int|null  $budgetPlanId  Budget Plan sumber dana (wajib, status ACTIVE)
     * @param  array<int, array{account_id:int, description:string, amount:int, qty:float}>  $items
     *         Baris estimasi biaya; total tiap baris DIHITUNG ULANG di sini, nilai
     *         kiriman browser tidak dipercaya.
     */
    public function createPlan(TroubleTicket $ticket, User $user, string $visitSite, ?string $targetOnline = null, ?string $reason = null, ?int $budgetPlanId = null, array $items = []): void
    {
        if (blank($visitSite)) {
            $this->fail('Tanggal plan visit wajib diisi.', 'visit_site');
        }

        DB::transaction(function () use ($ticket, $user, $visitSite, $targetOnline, $reason, $budgetPlanId, $items) {
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

            // Estimasi biaya wajib menyertai plan (keputusan 20 Okt 2026).
            [$budgetPlan, $baris, $totalBiaya] = $this->siapkanBiayaPlan($budgetPlanId, $items);

            $t->update([
                'status' => 'PLAN',
                'budget_plan_id' => $budgetPlan->id,
                'plan_cost_total' => $totalBiaya,
                /* Plan yang baru disimpan/diubah SELALU menunggu supervisor
                   lagi (keputusan 20 Okt 2026): revisi tanggal atau biaya
                   membatalkan persetujuan sebelumnya. CR draft yang terlanjur
                   dibuat TIDAK disentuh — ia hidup sendiri di Cost Request. */
                'plan_budget_status' => TroubleTicket::PLAN_BUDGET_PENDING,
                'plan_budget_notes' => null,
                'plan_approved_at' => null,
                'plan_approved_by' => null,
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

            /* Baris biaya DIGANTI seluruhnya tiap plan disimpan: satu tiket
               punya satu set estimasi yang berlaku, bukan riwayat bertumpuk. */
            $t->planCosts()->delete();
            $t->planCosts()->createMany($baris);

            $this->event($t, $user, $t->wasChanged('status') ? 'plan_created' : 'plan_updated', $reason, [
                'visit_site' => $t->visit_site?->toDateString(),
                'target_online' => $t->target_online?->toDateString(),
                'budget_plan' => $budgetPlan->code,
                'cost_total' => $totalBiaya,
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
    | APPROVAL PLAN & BIAYA → COST REQUEST DRAFT
    |--------------------------------------------------------------------------
    */

    /** Boleh memutus plan & biaya: pengawas tiket ini + supervisor. */
    public function canApprovePlanBudget(User $user, TroubleTicket $ticket): bool
    {
        return $ticket->planBudgetPending()
            && $ticket->status === 'PLAN'
            && ($this->canSupervise($user, $ticket) || $user->hasRole(AccessRoles::TROUBLE_TICKET_APPROVE));
    }

    /**
     * Supervisor menyetujui tanggal & biaya plan. Tiket TETAP di tahap Plan
     * (keputusan 20 Okt 2026); yang berubah hanya status approval, dan sistem
     * membuat Cost Request berstatus DRAFT dari estimasi biaya plan.
     *
     * @param  int|null  $clusterId  wajib bila budget plan-nya budget PROYEK
     *                               dan project itu punya lebih dari satu cluster
     */
    public function approvePlanBudget(TroubleTicket $ticket, User $user, ?int $clusterId = null, ?string $notes = null): void
    {
        $cr = null;

        DB::transaction(function () use ($ticket, $user, $clusterId, $notes, &$cr) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->canApprovePlanBudget($user, $t), 'Hanya supervisor yang dapat menyetujui plan & biaya.');
            $this->expect($t, ['PLAN'], 'Approval plan hanya bisa dilakukan saat tiket di tahap Plan.');

            $cr = $this->buatDraftCostRequest($t, $user, $clusterId);

            $t->update([
                'plan_budget_status' => TroubleTicket::PLAN_BUDGET_APPROVED,
                'plan_budget_notes'  => $notes,
                'plan_approved_at'   => now(),
                'plan_approved_by'   => $user->id,
                'budget_request_id'  => $cr->id,
            ]);

            $this->event($t, $user, 'plan_budget_approved', $notes, [
                'cost_request' => $cr->code,
                'cost_total'   => (int) $t->plan_cost_total,
            ]);
        });

        $ticket->refresh();

        $this->notify(
            collect([$ticket->technician])->filter()->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Plan & biaya disetujui',
            "{$ticket->ticket_number}: plan visit & biaya disetujui {$user->name}. Cost Request {$cr->code} dibuat sebagai draft.",
        );
    }

    /** Supervisor menolak plan & biaya - tiket tetap di Plan untuk direvisi. */
    public function rejectPlanBudget(TroubleTicket $ticket, User $user, string $notes): void
    {
        if (blank($notes)) {
            $this->fail('Alasan penolakan wajib diisi.', 'notes');
        }

        DB::transaction(function () use ($ticket, $user, $notes) {
            $t = $this->lock($ticket);
            $this->authorizeIf($this->canApprovePlanBudget($user, $t), 'Hanya supervisor yang dapat menolak plan & biaya.');
            $this->expect($t, ['PLAN'], 'Approval plan hanya bisa dilakukan saat tiket di tahap Plan.');

            $t->update([
                'plan_budget_status' => TroubleTicket::PLAN_BUDGET_REJECTED,
                'plan_budget_notes'  => $notes,
                'plan_approved_at'   => null,
                'plan_approved_by'   => null,
            ]);

            $this->event($t, $user, 'plan_budget_rejected', $notes);
        });

        $ticket->refresh();

        $this->notify(
            collect([$ticket->technician])->filter()->reject(fn ($u) => (int) $u->id === (int) $user->id),
            $ticket,
            'Plan & biaya ditolak',
            "{$ticket->ticket_number}: plan visit & biaya ditolak {$user->name}. Perbaiki tanggal/biaya lalu simpan lagi.",
        );
    }

    /**
     * Membuat Cost Request berstatus DRAFT dari estimasi biaya plan.
     *
     * Aturan CR yang sudah berlaku diikuti apa adanya, tidak ditulis ulang:
     * jenis Cash Advance, beneficiary INTERNAL, kode lewat generateCode(),
     * rincian bersandar pada sub-item budget plan, NOB dihitung dari
     * BudgetConsumption::COMMITTED. Pemohon = supervisor yang menyetujui.
     *
     * Tidak ada tahap alur uang yang dilewati: CR lahir sebagai DRAFT dan
     * tetap menempuh seluruh alur Cost Request seperti CR yang diketik manusia.
     */
    private function buatDraftCostRequest(TroubleTicket $t, User $user, ?int $clusterId): BudgetRequest
    {
        $plan = BudgetPlan::with(['project', 'costCenter'])->find($t->budget_plan_id);

        if ($plan === null) {
            $this->fail('Budget Plan plan ini tidak ditemukan lagi.');
        }

        $baris = $t->planCosts()->with('budgetDetail')->orderBy('id')->get();

        if ($baris->isEmpty()) {
            $this->fail('Plan ini belum punya baris biaya.');
        }

        if ($baris->contains(fn ($b) => $b->budget_detail_id === null)) {
            $this->fail('Ada baris biaya tanpa sub-item Budget Plan; simpan ulang plan-nya dulu.');
        }

        // Jenis CR = Cash Advance (tab Draft pada Cash Advance, 20 Okt 2026).
        $typeId = BudgetRequest::cashAdvanceTypeIds()[0] ?? null;
        if ($typeId === null) {
            $this->fail('Request Type "Cash Advance" belum ada di master.');
        }

        // Penerima dana = beneficiary INTERNAL milik PIC teknisi tiket ini.
        $beneficiary = Beneficiary::where('category', 'INTERNAL')
            ->where('status', 'ACTIVE')
            ->where('pic_id', $t->pic_id)
            ->first();

        if ($beneficiary === null) {
            $this->fail('Beneficiary Internal untuk PIC teknisi tiket ini belum ada (Master Data > Beneficiary).');
        }

        // Cluster hanya relevan untuk budget PROYEK - sama seperti form CR.
        $cluster = null;
        if ($plan->isProjectBudget()) {
            $kandidat = Cluster::where('project_id', $plan->project_id)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get();

            $cluster = $clusterId
                ? $kandidat->firstWhere('id', $clusterId)
                : ($kandidat->count() === 1 ? $kandidat->first() : null);

            if ($cluster === null) {
                /* Plan lama masih bisa sampai ke sini walau siapkanBiayaPlan()
                   kini menjaga di depan — pesannya menyebut proyek dan tempat
                   memperbaikinya, bukan cuma menyatakan buntu. */
                $this->fail($kandidat->isEmpty()
                    ? 'Proyek "' . ($plan->project?->name ?? $plan->project?->code ?? '-')
                        . '" (Budget Plan ' . $plan->code . ') belum punya Cluster berstatus ACTIVE, '
                        . 'padahal Cost Request dari budget proyek wajib bercluster. Tambahkan cluster-nya '
                        . 'di Master Data > Project Master, lalu approve lagi.'
                    : 'Cluster wajib dipilih untuk budget proyek.', 'cluster_id');
            }
        }

        $total = (int) $baris->sum('total');

        // Pelampauan anggaran: rumus yang sama dengan form Cost Request.
        $terpakai = (int) BudgetRequest::where('budget_plan_id', $plan->id)
            ->whereIn('status', BudgetConsumption::COMMITTED)
            ->sum(DB::raw(BudgetRequest::effectiveCostSql()));
        $nobAmount = (int) max(0, ($terpakai + $total) - ($plan->amount ?? 0));

        $sourceCode = $plan->isCostCenterBudget()
            ? (preg_replace('/\D/', '', $plan->costCenter?->code ?? '') ?: '0000')
            : ($plan->project?->code ?? '0000');

        $cr = BudgetRequest::create([
            'code'           => BudgetRequest::generateCode($sourceCode),
            'description'    => strtoupper('BIAYA VISIT ' . $t->ticket_number . ' - ' . ($t->site?->sitename ?? '')),
            'is_nob'         => $nobAmount > 0,
            'nob_amount'     => $nobAmount,
            'amount'         => 0,
            'notes'          => 'Dibuat otomatis dari plan visit Trouble Ticket ' . $t->ticket_number . '.',
            'applicant_id'   => $user->id,
            'beneficiary_id' => $beneficiary->id,
            'type_id'        => $typeId,
            'cluster_id'     => $cluster?->id,
            'budget_plan_id' => $plan->id,
            'status'         => 'DRAFT',
        ]);

        foreach ($baris as $b) {
            // Qty plan maupun rincian CR sama-sama bilangan bulat, jadi baris
            // pindah apa adanya tanpa perlu dirapikan jadi lumpsum.
            $sisaDetail = $b->budgetDetail?->remainingAmount() ?? 0;

            $cr->details()->create([
                'budget_detail_id' => $b->budget_detail_id,
                'account_id'       => $b->account_id,
                'description'      => trim(($b->account_name ?? '-') . ' - ' . $b->description),
                'amount'           => (int) $b->amount,
                'qty'              => (int) $b->qty,
                'total'            => (int) $b->total,
                'notes'            => $b->description,
                'is_nob'           => (int) $b->total > $sisaDetail,
                'nob_amount'       => (int) max(0, (int) $b->total - $sisaDetail),
            ]);
        }

        $this->lampirkanDetailTiket($cr, $t, $baris, $user);

        $cr->recalculateAmount();
        $cr->addLog('DRAFT', 'DRAFT', 'created');

        return $cr->refresh();
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
        $ticket = TroubleTicket::where('budget_request_id', $cr->id)->first();

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
        if ($ticket->budget_request_id === null) {
            return;
        }

        $cr = $ticket->budgetRequest;

        // CLOSED ikut dihitung: CR non-Cash Advance langsung ditutup saat dibayar.
        if ($cr === null || ! in_array($cr->status, ['PAID', 'REALIZED', 'SETTLE_CHECKED', 'SETTLE_VERIFIED', 'CLOSED'], true)) {
            return;
        }

        $pelaku = $cr->applicant ?? $ticket->planApprover;

        if ($pelaku === null) {
            return;
        }

        $this->tulisKejadianDibayar($ticket, $cr, $pelaku, null);
    }

    /** Menulis kejadian sekali saja; true bila baris benar-benar dibuat. */
    private function tulisKejadianDibayar(TroubleTicket $ticket, BudgetRequest $cr, User $pelaku, ?string $notes): bool
    {
        $sudah = TroubleTicketMessage::where('trouble_ticket_id', $ticket->id)
            ->where('event', 'cost_request_paid')
            ->exists();

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
     * @param  \Illuminate\Support\Collection  $baris  baris biaya plan
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

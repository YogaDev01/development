<?php

namespace App\Http\Controllers;

use App\Models\BudgetPlan;
use App\Models\BudgetPlanDetail;
use App\Models\Cluster;
use App\Models\Site;
use App\Models\Team;
use App\Models\TroubleTicket;
use App\Models\TroubleTicketAttachment;
use App\Models\TroubleTicketMessage;
use App\Models\TroubleTicketRca;
use App\Models\TroubleTicketSeverity;
use App\Models\TroubleTicketSlaPolicy;
use App\Models\TroubleTicketSubRca;
use App\Models\User;
use App\Services\TroubleTicketWorkflow;
use App\Support\AccessRoles;
use App\Support\WorkflowStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Trouble Ticket (TESYS Operation).
 *
 * Gerbang route: auth + menu:trouble-ticket. Keterlihatan baris lewat
 * TroubleTicket::scopeVisibleTo(); setiap aksi workflow lewat
 * App\Services\TroubleTicketWorkflow, yang memeriksa hak pelaku dan status
 * pada baris terkunci — controller ini hanya memvalidasi input dan memilih
 * respons. Lihat docs/modules/trouble-ticket.md.
 */
class TroubleTicketController extends Controller
{
    use Concerns\CapsLargeLists;

    /** Jumlah item timeline per muat. */
    private const MESSAGE_PAGE = 30;

    /** Batas tiket per sekali simpan dari form Buat Trouble Ticket. */
    private const MAX_TICKETS_PER_SUBMIT = 20;

    public function __construct(private TroubleTicketWorkflow $workflow) {}

    /*
    |--------------------------------------------------------------------------
    | DAFTAR
    |--------------------------------------------------------------------------
    */

    /**
     * Daftar tiket. Pola sama dengan halaman indeks lain (DataTables di
     * browser): sort semua kolom, pagination, pilih kolom, satu tombol Filter.
     * Baris dibatasi CapsLargeLists (1.000 terbaru); filter & sort berjalan
     * di atas baris itu, dan halaman memberi tahu bila ada yang terpotong.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = TroubleTicket::visibleTo($user)
            ->with([
                'site:id,tid,sitename',
                'customer:id,name',
                'problem:id,name',
                'issueRef:id,name',
                'deviceRef:id,name',
                'technician:id,name',
            ]);

        [$tickets, $terpotong, $total] = $this->cappedRows($query, 'trouble_tickets.created_at');

        // Kartu ringkasan = tahap alur (keputusan 12 Okt 2026): satu kartu per
        // tahap, Closed dibatasi bulan berjalan. Satu query agregat.
        $agregat = TroubleTicket::visibleTo($user)->toBase()
            ->selectRaw("SUM(status = 'OPEN') AS open")
            ->selectRaw("SUM(status = 'REMOTE') AS remote")
            ->selectRaw("SUM(status = 'PLAN') AS plan")
            ->selectRaw("SUM(status = 'TRIP') AS trip")
            ->selectRaw("SUM(status = 'IN_PROGRESS') AS in_progress")
            ->selectRaw("SUM(status = 'PROGRESS_DONE') AS progress_done")
            ->selectRaw('SUM(closed_at >= ?) AS closed_bulan_ini', [now()->startOfMonth()])
            ->first();
        $overdueIds = TroubleTicket::visibleTo($user)->overdue()->pluck('trouble_tickets.id')->flip();

        return view('trouble-ticket.index', [
            'tickets' => $tickets,
            'terpotong' => $terpotong,
            'total' => $total,
            'overdueIds' => $overdueIds,
            'stats' => [
                'OPEN' => (int) ($agregat->open ?? 0),
                'REMOTE' => (int) ($agregat->remote ?? 0),
                'PLAN' => (int) ($agregat->plan ?? 0),
                'TRIP' => (int) ($agregat->trip ?? 0),
                'IN_PROGRESS' => (int) ($agregat->in_progress ?? 0),
                'PROGRESS_DONE' => (int) ($agregat->progress_done ?? 0),
                'CLOSED' => (int) ($agregat->closed_bulan_ini ?? 0),
            ],
            // Tiket yang baru saja dibuat — disorot sebentar di daftar.
            'baruIds' => array_map('intval', (array) session('tt_baru', [])),
            // Tombol Close di kolom Action baris Progress Done. Kelayakan per
            // baris tetap diperiksa ulang server lewat canApproveClose().
            'canApprove' => $user->hasRole(AccessRoles::TROUBLE_TICKET_APPROVE),
            // Tombol "Buat Tiket" — teknisi hanya mengerjakan tiket, tidak membuatnya.
            'canCreate' => ! $user->hasRole(AccessRoles::TROUBLE_TICKET_CREATE_DENIED),
            'userId' => (int) $user->id,
        ]);
    }

    /**
     * Export Excel daftar tiket. Mengikuti filter tabel: browser mem-POST id
     * baris yang lolos (public/js/tesys-export-filtered.js); tanpa 'ids' =
     * seluruh tiket yang terlihat user. Keterlihatan selalu lewat
     * scopeVisibleTo() — id kiriman browser tidak bisa membuka tiket lain.
     */
    public function exportList(Request $request)
    {
        $ids = collect((array) $request->input('ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v);

        $tickets = TroubleTicket::visibleTo(Auth::user())
            ->with([
                'site:id,tid,sitename', 'customer:id,name',
                'deviceRef:id,name', 'rcaRef:id,name', 'subRcaRef:id,name',
                'technician:id,name',
            ])
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('trouble_tickets.id', $ids))
            ->orderByDesc('trouble_tickets.updated_at')
            ->limit(5000)
            ->get();

        // Kolom mengikuti data yang benar-benar dipakai sekarang (RCA/Sub-RCA,
        // Device); Problem, Initial Problem dan Flag Alarm sudah tidak diisi
        // dari form, jadi tidak lagi diekspor.
        $kolom = [
            'No. TT', 'Customer', 'Site ID', 'Nama Site', 'RCA', 'Sub-RCA', 'Aging (hari)', 'Severity',
            'Device', 'Keterangan', 'Teknisi', 'Status', 'Plan', 'Target Online',
            'Response (menit)', 'Travel (menit)', 'Resolution (menit)', 'Date Open', 'Dibuat', 'Date Update',
            'Closed', 'RCA Penyelesaian', 'Sub-RCA Penyelesaian', 'Action',
        ];
        $menit = fn (array $m) => $m['seconds'] === null ? null : round($m['seconds'] / 60, 1);
        $tgl = fn ($d) => $d?->format('Y-m-d H:i');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Trouble Ticket');
        $sheet->fromArray($kolom, null, 'A1');
        $akhir = Coordinate::stringFromColumnIndex(count($kolom));
        $header = $sheet->getStyle('A1:'.$akhir.'1');
        $header->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');

        $baris = 2;
        foreach ($tickets as $t) {
            $sheet->fromArray([
                $t->ticket_number, $t->customer?->name, $t->site?->tid, $t->site?->sitename,
                $t->rca_label, $t->sub_rca_label, $t->aging_days, $t->severity_label,
                $t->device_label, $t->description,
                $t->technician?->name, WorkflowStatus::label($t->status, 'trouble-ticket'),
                $t->visit_decision_label, $t->target_online?->format('Y-m-d'),
                $menit($t->slaMetric('response')), $menit($t->slaMetric('travel')), $menit($t->slaMetric('resolution')),
                $tgl($t->reported_at), $tgl($t->created_at), $tgl($t->updated_at), $tgl($t->closed_at),
                $t->rca, $t->sub_rca, $t->action,
            ], null, 'A'.$baris);
            $baris++;
        }
        foreach (range(1, count($kolom)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $nama = 'trouble-ticket-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $nama, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Modal "info" dari daftar: ringkasan tiket TANPA membuka tiket. Sengaja
     * tidak memanggil recordAccess(): melihat info dari daftar bukan respons
     * teknisi, jadi timer Response tidak berhenti.
     */
    public function info(TroubleTicket $ticket)
    {
        abort_unless($this->workflow->canView(Auth::user(), $ticket), 403);

        $ticket->load([
            'site.customer:id,name', 'problem', 'issueRef', 'deviceRef', 'rcaRef', 'subRcaRef', 'creator:id,name',
            'technician:id,name,phone', 'acknowledger:id,name', 'decider:id,name',
            'closer:id,name', 'planApprover:id,name', 'budgetPlan:id,code',
            // Total estimasi biaya menautkan ke CR ini (20 Okt 2026).
            'budgetRequest:id,code',
        ]);

        return view('trouble-ticket._info', [
            'ticket' => $ticket,
            // Log aktivitas tiket (kejadian workflow saja, terbaru di atas) —
            // isinya sama dengan tab Log di halaman detail.
            'statusLog' => $ticket->messages()
                ->where('type', TroubleTicketMessage::TYPE_EVENT)
                ->orderByDesc('id')
                ->get(['id', 'event', 'actor_name', 'body', 'metadata', 'created_at']),
            'supervisors' => $this->workflow->supervisorsFor((int) $ticket->assigned_to),
            // SLA disembunyikan dari teknisi tiket ini — sama dengan halaman
            // detail (ukuran waktu dipakai supervisor, bukan pelaksana).
            'isTechnician' => $this->workflow->isTechnician(Auth::user(), $ticket),
            // Estimasi biaya plan visit (20 Okt 2026).
            'planCosts' => $ticket->planCosts()->with('account:id,name')->orderBy('id')->get(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BUAT
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $this->abortIfCannotCreate();

        /* Customer mengikuti team pembuat tiket (19 Okt 2026): anggota ACTIVE
           sebuah team ACTIVE yang sudah diisi customer-nya tidak lagi memilih
           customer — ia terisi sendiri dan daftar site menyusut ke customer
           itu. Pembuat yang tidak terikat team mana pun (mis. superadmin)
           tetap memilih seperti sebelumnya. */
        $customerIds = $this->teamCustomerIds(Auth::user());

        $sites = Site::with('customer:id,name')
            ->where('status', 'ACTIVE')
            ->when($customerIds->isNotEmpty(), fn ($q) => $q->whereIn('customer_id', $customerIds))
            ->orderBy('tid')
            ->get(['id', 'tid', 'sitename', 'customer_id']);

        $customers = $sites->pluck('customer')->filter()->unique('id')->sortBy('name')->values();

        return view('trouble-ticket.create', [
            'sites' => $sites,
            // Daftarnya hanya customer yang punya site aktif, supaya tidak ada
            // pilihan yang menghasilkan site kosong.
            'customers' => $customers,
            /* Satu customer saja: kotak pilihan diganti teks baca-saja.
               Lebih dari satu team dengan customer berbeda tetap memilih,
               hanya daftarnya yang menyempit. */
            'customerTerkunci' => $customerIds->isNotEmpty() && $customers->count() === 1
                ? $customers->first()
                : null,
            /* Kenapa customer tidak terisi sendiri — ditulis apa adanya di
               form supaya penyebabnya terbaca tanpa menebak: kolom customer
               team belum ada/diisi, atau customer-nya belum punya site aktif. */
            'alasanCustomer' => match (true) {
                ! Team::supportsCustomer() => 'Migrasi teams.customer_id belum dijalankan.',
                $customers->isEmpty() => 'Customer team Anda belum punya site berstatus ACTIVE.',
                default => null,
            },
            'rcas' => TroubleTicketRca::active(),
            // Dipakai dropdown Sub-RCA di browser (tanpa request tambahan).
            'subRcas' => TroubleTicketSubRca::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'rca_id', 'name']),
        ]);
    }

    /**
     * Customer milik team ACTIVE tempat user terdaftar (anggota ACTIVE atau
     * leader) dan yang customer-nya sudah diisi di Master Data > Team.
     * Kosong = user tidak terikat customer mana pun; ia memilih sendiri.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function teamCustomerIds(User $user): Collection
    {
        if (! Team::supportsCustomer()) {
            return collect();
        }

        return Team::query()
            ->where('teams.status', 'ACTIVE')
            ->whereNotNull('customer_id')
            ->where(fn ($q) => $q
                ->where('leader_id', $user->id)
                ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id)->where('status', 'ACTIVE')))
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Teknisi hanya boleh memilih Budget Plan milik CUSTOMER team-nya sendiri
     * — customer dari team tempat supervisornya jadi leader (20 Okt 2026).
     *
     * Peran pengawas tidak dibatasi di sini: aturannya sengaja hanya berlaku
     * bagi teknisi tiket ini, dan hanya bila team-nya memang punya customer.
     */
    private function abortIfBudgetPlanLuarCustomer(User $user, TroubleTicket $ticket, int $budgetPlanId): void
    {
        $customerTeam = $this->teamCustomerIds($user);

        if ($customerTeam->isEmpty() || ! $this->workflow->isTechnician($user, $ticket)) {
            return;
        }

        $customerPlan = BudgetPlan::with('project:id,customer_id')->find($budgetPlanId)?->project?->customer_id;

        if ($customerPlan === null || ! $customerTeam->contains((int) $customerPlan)) {
            abort(403, 'Budget Plan ini di luar customer team Anda.');
        }
    }

    /** Teknisi tidak boleh membuat tiket; ia hanya mengerjakan yang ditugaskan. */
    private function abortIfCannotCreate(): void
    {
        if (auth()->user()->hasRole(AccessRoles::TROUBLE_TICKET_CREATE_DENIED)) {
            abort(403, 'Role Anda tidak berhak membuat trouble ticket.');
        }
    }

    /** Teknisi yang boleh ditugaskan di sebuah site (dropdown form & reassign). */
    public function technicians(Site $site)
    {
        return response()->json([
            'technicians' => $this->workflow->eligibleTechnicians($site->id)->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
            ])->values(),
        ]);
    }

    /**
     * Simpan satu atau beberapa tiket sekaligus.
     *
     * Form "Buat Trouble Ticket" mengirim satu blok gangguan per tiket
     * (tickets[0], tickets[1], …) untuk Site & Teknisi yang sama. Bentuk lama
     * (field datar, satu tiket) tetap diterima — dipakai integrasi dan tes —
     * dan di sini dibungkus jadi satu item supaya jalurnya cuma satu.
     */
    public function store(Request $request)
    {
        $this->abortIfCannotCreate();

        if (! is_array($request->input('tickets'))) {
            $request->merge(['tickets' => [$request->except(['site_id', 'assigned_to', '_token'])]]);
            if ($request->hasFile('attachments')) {
                $request->files->set('tickets', [['attachments' => $request->file('attachments')]]);
            }
        }

        $data = $request->validate([
            'site_id' => 'required|integer|exists:sites,id',
            'assigned_to' => 'required|integer|exists:users,id',
            'tickets' => 'required|array|min:1|max:'.self::MAX_TICKETS_PER_SUBMIT,
            'tickets.*.description' => 'required|string|max:10000',
            // Problem / Initial Problem / Flag Alarm tidak lagi ada di form buat
            // tiket (diisi belakangan dari halaman detail atau integrasi), tapi
            // tetap diterima dan divalidasi bila dikirim.
            'tickets.*.problem_id' => 'nullable|integer|exists:trouble_ticket_problems,id',
            'tickets.*.issue_id' => 'nullable|integer|exists:trouble_ticket_issues,id',
            'tickets.*.flag_alarm' => 'nullable|string|max:40',
            'tickets.*.device_id' => 'nullable|integer|exists:trouble_ticket_devices,id',
            // No. TT custom — kosong berarti nomor otomatis (DocumentSequence).
            // 'distinct' menjaga dua blok dalam satu simpan tidak memakai nomor sama.
            'tickets.*.ticket_number' => 'nullable|string|max:50|distinct|unique:trouble_tickets,ticket_number',
            'tickets.*.rca_id' => 'nullable|integer|exists:trouble_ticket_rcas,id',
            'tickets.*.sub_rca_id' => 'nullable|integer|exists:trouble_ticket_sub_rcas,id',
            // Date Open — bawaan hari ini, boleh diubah, tidak boleh di masa depan.
            'tickets.*.reported_at' => 'nullable|date|before_or_equal:now',
            'tickets.*.attachments' => 'nullable|array|max:'.TroubleTicketAttachment::MAX_FILES,
            'tickets.*.attachments.*' => TroubleTicketAttachment::rules(),
        ], $this->fileMessages() + [
            'tickets.*.ticket_number.unique' => 'No. tiket itu sudah dipakai tiket lain.',
            'tickets.*.ticket_number.distinct' => 'No. tiket custom tidak boleh sama antar gangguan.',
        ]);

        $pokok = ['site_id' => $data['site_id'], 'assigned_to' => $data['assigned_to']];
        $user = Auth::user();

        /* Gerbang server untuk aturan di create(): pembuat yang terikat team
           hanya boleh membuat tiket pada site milik customer team-nya —
           kiriman browser tidak dipercaya. */
        $customerIds = $this->teamCustomerIds($user);
        if ($customerIds->isNotEmpty()
            && ! $customerIds->contains((int) Site::whereKey($data['site_id'])->value('customer_id'))) {
            throw ValidationException::withMessages([
                'site_id' => 'Site itu bukan milik customer team Anda.',
            ]);
        }

        /* Dibuat berurutan, bukan dalam satu transaksi: tiap tiket punya nomor,
           lampiran dan notifikasinya sendiri, dan workflow->create() sudah
           menjaga keutuhan per tiket. Bila satu gagal, tiket sebelumnya tetap
           ada — pesan galat menyebut blok yang gagal. */
        $dibuat = [];
        foreach (array_values($data['tickets']) as $i => $item) {
            try {
                $dibuat[] = $this->workflow->create($user, $pokok + $item, $request->file("tickets.$i.attachments", []));
            } catch (ValidationException $e) {
                /* Galat dari workflow memakai kunci datar ('sub_rca_id'); form
                   menamai isiannya per blok, jadi kuncinya diberi awalan supaya
                   yang ditandai merah adalah blok yang benar. */
                $pesan = [];
                foreach ($e->errors() as $kunci => $isi) {
                    // site_id & assigned_to milik seluruh form, bukan satu blok.
                    $prefix = in_array($kunci, ['site_id', 'assigned_to'], true) ? '' : "tickets.$i.";
                    $pesan[$prefix.$kunci] = $isi;
                }
                $lanjut = ValidationException::withMessages($pesan);

                // Bila sudah ada tiket yang tersimpan, pemakai perlu tahu mana saja.
                if ($dibuat) {
                    $nomor = collect($dibuat)->map->ticket_number->implode(', ');
                    $lanjut->response = back()->withInput()
                        ->withErrors($pesan)
                        ->with('error', 'Gangguan '.($i + 1).' gagal disimpan: '.$e->validator->errors()->first()
                            .' Tiket yang sudah tersimpan: '.$nomor.'.');
                }

                throw $lanjut;
            }
        }

        /* Setelah simpan pemakai kembali ke DAFTAR, bukan ke detail tiket
           (15 Okt 2026): daftar dibuka pada tahap TT Open dan baris tiket baru
           disorot sebentar lewat session `tt_baru`. */
        $nomor = collect($dibuat)->map->ticket_number->implode(', ');

        return redirect()->route('trouble-ticket.index')
            ->with('tt_baru', collect($dibuat)->pluck('id')->all())
            ->with('success', count($dibuat) === 1
                ? "Trouble Ticket {$nomor} berhasil dibuat."
                : count($dibuat).' Trouble Ticket berhasil dibuat: '.$nomor.'.');
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL
    |--------------------------------------------------------------------------
    */

    public function show(TroubleTicket $ticket)
    {
        $user = Auth::user();
        abort_unless($this->workflow->canView($user, $ticket), 403);

        /* Respons pertama teknisi dicatat di server, sekali (lihat
           recordAccess()). Tiket OPEN yang dibuka teknisinya sekaligus masuk
           tahap Remote di sana — tidak ada lagi tombol Remote (19 Okt 2026). */
        $this->workflow->recordAccess($ticket, $user);

        $ticket->load([
            'site.customer:id,name',
            'site.picLinks.pic',
            'budgetPlan:id,code',
            'budgetRequest',
            'problem',
            'issueRef',
            'deviceRef',
            'rcaRef',
            'subRcaRef',
            'pic',
            'creator:id,name',
            'technician:id,name,phone',
            'acknowledger:id,name',
            'decider:id,name',
            'closer:id,name',
            'planApprover:id,name',
            'approver:id,name',
            'assignments.technician:id,name',
            'assignments.assigner:id,name',
        ]);

        [$messages, $hasOlder] = $this->messagePage($ticket);

        /* Timeline disamakan dengan keadaan CR-nya: pembayaran yang tidak
           melewati Treasury (perbaikan data, impor, alur lama) tidak pernah
           memicu hook di sana, jadi dicek sekali saat tiket dibuka. */
        $this->workflow->syncCostRequestPaid($ticket);

        $canSupervise = $this->workflow->canSupervise($user, $ticket);

        /* CR yang sudah cair boleh disettle dari sini. Statusnya mengikuti
           syarat BudgetRequestController::submitRealization(). */
        $bolehSettle = $ticket->budgetRequest
            && in_array($ticket->budgetRequest->status, ['PAID', 'SETTLE_REJECTED'], true)
            && ($this->workflow->isTechnician($user, $ticket)
                || $user->hasRole(['superadmin', 'manager', 'supervisor']));

        /* Pilihan Budget Plan dibatasi ke CUSTOMER milik user ini — customer
           team ACTIVE tempat ia terdaftar (keputusan 20 Okt 2026). User yang
           tidak terikat team mana pun (mis. superadmin) memakai customer tiket
           sebagai patokan. Budget cost center (overhead/payroll) TIDAK ikut:
           ia tidak terikat customer, dan dulu membanjiri daftar.
           Sub-itemnya dikirim sekali untuk disaring di browser, jadi tidak ada
           permintaan tambahan saat budget plan diganti. */
        $customerTeam = $this->teamCustomerIds($user);

        /* TEKNISI dikunci ke customer team-nya sendiri — customer dari team
           tempat supervisornya jadi leader (keputusan 20 Okt 2026). Customer
           TIKET tidak lagi otomatis ikut untuknya: pengajuan biaya harus jatuh
           ke pagu customer yang memang jadi tanggung jawab supervisornya,
           bukan ke pagu customer lain yang kebetulan punya tiket itu.

           Peran pengawas tetap memakai customer tiket sebagai cadangan:
           supervisor yang team-nya memegang customer lain (atau belum diisi)
           dan superadmin yang tidak terikat team mana pun harus tetap bisa
           menganggarkan tiket yang jadi tanggung jawabnya — tanpa itu
           daftarnya kosong sama sekali. */
        $kunciKeTeam = $this->workflow->isTechnician($user, $ticket) && $customerTeam->isNotEmpty();

        $customerIds = ($kunciKeTeam ? $customerTeam : $customerTeam->push($ticket->customer_id))
            ->filter()
            ->unique()
            ->values();

        $budgetPlans = BudgetPlan::with('project:id,customer_id')
            ->where('status', 'ACTIVE')
            ->whereHas('project', fn ($p) => $p->whereIn('customer_id', $customerIds))
            ->orderBy('code')
            ->get(['id', 'code', 'description', 'project_id', 'cost_center_id']);

        $budgetDetails = BudgetPlanDetail::with('account:id,name')
            ->whereIn('budget_plan_id', $budgetPlans->pluck('id'))
            ->orderBy('id')
            ->get(['id', 'budget_plan_id', 'account_id', 'deskripsi']);

        /* Cluster hanya ditanyakan saat budget plan-nya budget PROYEK dan
           project itu punya lebih dari satu cluster aktif — persis aturan
           form Cost Request. */
        $planClusters = $ticket->budget_plan_id
            ? Cluster::where('status', 'ACTIVE')
                ->whereIn('project_id', $budgetPlans->where('id', $ticket->budget_plan_id)->pluck('project_id')->filter())
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();
        // Change Teknisi: pengawas tiket ini + supervisor (20 Okt 2026).
        $bolehAlihkan = $this->workflow->canReassignBy($user, $ticket);

        /* Isi draft settlement yang SUDAH tersimpan, supaya modalnya boleh
           diisi sebagian lalu dilanjutkan lain waktu (20 Okt 2026): tanpa ini
           form selalu kembali ke nilai Request dan pekerjaan sebelumnya tidak
           terlihat. Dikunci per request_detail_id — satu baris realisasi per
           item, persis yang ditulis syncRealizations(). */
        $settleDetails = $bolehSettle
            ? $ticket->budgetRequest->details()->where('status', '!=', 'TAKEOUT')->orderBy('id')->get()
            : collect();

        $settleDrafts = $bolehSettle
            ? $ticket->budgetRequest->realizations()->withCount('attachments')->get()->keyBy('request_detail_id')
            : collect();

        /* Tanda tangan disimpan sebagai lampiran global bernama
           "ttd-<nomor baris>-<kode CR>.png" (storeSettlementSignatures), jadi
           nomor barisnya dibaca kembali dari nama berkas itu. */
        $settleSignedRows = $bolehSettle
            ? $ticket->budgetRequest->realizationAttachments()
                ->where('original_name', 'like', 'ttd-%-' . $ticket->budgetRequest->code . '.png')
                ->pluck('original_name')
                ->map(fn ($n) => (int) explode('-', $n)[1])
                ->all()
            : [];

        return view('trouble-ticket.show', [
            'ticket' => $ticket,
            // Evidence yang dikirim bersama form close — ditampilkan saat approval.
            'closureFiles' => $ticket->messages()
                ->where('event', 'progress_done')
                ->with('attachments')
                ->orderByDesc('id')
                ->first()?->attachments ?? collect(),
            // Dropdown RCA / Sub-RCA pada form close (Sub-RCA disaring di browser).
            'rcas' => TroubleTicketRca::active(),
            'subRcas' => TroubleTicketSubRca::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'rca_id', 'name']),
            'messages' => $messages,
            // Tab Log: seluruh kejadian workflow (tanpa pesan chat), terbaru di atas.
            'statusLog' => $ticket->messages()
                ->where('type', TroubleTicketMessage::TYPE_EVENT)
                ->orderByDesc('id')
                ->get(['id', 'event', 'actor_name', 'body', 'metadata', 'created_at']),
            'ctx' => $this->timelineContext($ticket),
            'hasOlder' => $hasOlder,
            'isTechnician' => $this->workflow->isTechnician($user, $ticket),
            // Swipe Trip: tanggal plan visit sudah tiba, tiket masih di Plan.
            'tripDue' => $this->workflow->tripDue($ticket),
            // Tombol tahap: teknisi tiket ini, atau superadmin.
            'canActAsTech' => $this->workflow->actsAsTechnician($user, $ticket),
            'canSupervise' => $canSupervise,
            /* Plan: membuat plan pertama = teknisi atau supervisor; mengubah
               tanggal plan yang sudah ada = supervisor saja (19 Okt 2026). */
            'canCreatePlan' => $this->workflow->canPlan($user, $ticket),
            'canApproveClose' => $this->workflow->canApproveClose($user, $ticket),
            /* Create Plan — Biaya (20 Okt 2026). Sumber dana hanya pagu yang
               sudah berjalan DAN sesuai customer tiket ini (budget proyek
               customer itu + budget cost center yang tidak terikat customer).
               Baris biaya memilih SUB-ITEM budget plan, bukan COA lepas:
               rincian Cost Request memang bersandar pada sub-item. */
            'budgetPlans' => $budgetPlans,
            'budgetDetails' => $budgetDetails,
            'planCosts' => $ticket->planCosts()->with(['account:id,name', 'budgetDetail'])->orderBy('id')->get(),
            // Approval plan & biaya oleh supervisor → Cost Request draft.
            'canApprovePlanBudget' => $this->workflow->canApprovePlanBudget($user, $ticket),
            /* Settlement dari halaman tiket (20 Okt 2026): hanya saat CR-nya
               sudah cair dan pelakunya berhak — teknisi tiket ini, atau peran
               yang memang sudah boleh menyettle di modul Cost Request.
               Gerbang sebenarnya tetap di BudgetRequestController. */
            'canSettle' => $bolehSettle,
            'settleDetails' => $settleDetails,
            'settleDrafts' => $settleDrafts,
            'settleSignedRows' => $settleSignedRows,
            'planClusters' => $planClusters,
            // Form close: semua peran yang boleh melihat tiket (20 Okt 2026).
            'canClose' => $this->workflow->canClose($user, $ticket),
            'canMessage' => $this->workflow->canMessage($user, $ticket),
            // Change Teknisi: sejak plan dibuat sampai sebelum perjalanan mulai.
            'canReassign' => $bolehAlihkan && $this->workflow->canReassign($ticket),
            // Kandidatnya: Teknisi Backup site ini (Master Data > Site).
            'reassignTo' => $bolehAlihkan && $this->workflow->canReassign($ticket)
                ? $this->workflow->reassignCandidates($ticket)
                : collect(),
        ]);
    }

    /**
     * Potongan timeline (HTML). before_id = muat yang lebih lama;
     * after_id = ambil yang baru (polling ringan dari halaman detail).
     */
    public function messages(Request $request, TroubleTicket $ticket)
    {
        abort_unless($this->workflow->canView(Auth::user(), $ticket), 403);

        $data = $request->validate([
            'before_id' => 'nullable|integer|min:1',
            'after_id' => 'nullable|integer|min:0',
        ]);

        if (isset($data['after_id'])) {
            $messages = $ticket->messages()
                ->with(['attachments', 'user:id,name'])
                ->where('id', '>', $data['after_id'])
                ->orderBy('id')
                ->limit(100)
                ->get();
            $hasOlder = null;
        } else {
            [$messages, $hasOlder] = $this->messagePage($ticket, $data['before_id'] ?? null);
        }

        return response()->json([
            'html' => $messages->isEmpty() ? '' : view('trouble-ticket._messages', [
                'messages' => $messages,
                'ctx' => $this->timelineContext($ticket),
            ])->render(),
            'oldest_id' => $messages->first()?->id,
            'newest_id' => $messages->last()?->id,
            'has_older' => $hasOlder,
            'status' => $ticket->fresh()->status,
        ]);
    }

    public function postMessage(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate([
            'body' => 'nullable|string|max:5000',
            'client_token' => 'nullable|uuid',
            'attachments' => 'nullable|array|max:'.TroubleTicketAttachment::MAX_FILES,
            'attachments.*' => TroubleTicketAttachment::rules(),
        ], $this->fileMessages());

        $message = $this->workflow->postMessage(
            $ticket,
            Auth::user(),
            $data['body'] ?? null,
            $request->file('attachments', []),
            $data['client_token'] ?? null,
        );

        if ($request->expectsJson()) {
            $message->load(['attachments', 'user:id,name']);

            return response()->json([
                'id' => $message->id,
                'html' => view('trouble-ticket._messages', [
                    'messages' => collect([$message]),
                    'ctx' => $this->timelineContext($ticket),
                ])->render(),
            ]);
        }

        return back()->with('success', 'Pesan terkirim.');
    }

    /** Unduh/pratinjau lampiran — keterlihatan mengikuti tiketnya. */
    public function download(TroubleTicketAttachment $attachment)
    {
        $ticket = $attachment->ticket;
        abort_unless($ticket && $this->workflow->canView(Auth::user(), $ticket), 403);

        $disk = Storage::disk('secure');
        abort_unless($disk->exists($attachment->storage_path), 404);

        $inline = $attachment->is_inline && request()->boolean('inline');

        return $disk->response($attachment->storage_path, $attachment->original_name, [
            'Content-Type' => $attachment->is_inline ? $attachment->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=604800',
        ], $inline ? 'inline' : 'attachment');
    }

    /*
    |--------------------------------------------------------------------------
    | AKSI WORKFLOW
    |--------------------------------------------------------------------------
    */

    /** Membuat Plan Visit (teknisi/supervisor) atau mengubah tanggalnya (supervisor). */
    public function createPlan(Request $request, TroubleTicket $ticket)
    {
        // Batas tanggal plan: hari ini untuk plan pertama, hari plan dibuat
        // saat tanggalnya diubah (TroubleTicket::planMinDate()).
        $minVisit = $ticket->planMinDate()->toDateString();

        /* Harga diketik bertitik ribuan ("1.500.000"), seperti form Budget
           Plan & Cost Request. Titiknya dibuang SEBELUM validasi supaya aturan
           integer di bawah menilai angkanya, bukan teksnya. */
        $request->merge([
            'costs' => collect($request->input('costs', []))
                ->map(function ($row) {
                    if (isset($row['amount'])) {
                        $row['amount'] = preg_replace('/\D/', '', (string) $row['amount']);
                    }

                    return $row;
                })
                ->all(),
        ]);

        $data = $request->validate([
            'visit_site' => 'required|date|after_or_equal:'.$minVisit,
            'target_online' => 'nullable|date|after_or_equal:today',
            // Plan pertama tidak perlu alasan; mengubah tanggal yang sudah ada wajib.
            'reason' => in_array($ticket->status, ['PLAN', 'TRIP'], true)
                ? 'required|string|max:500'
                : 'nullable|string|max:500',
            /* Biaya plan wajib (keputusan 20 Okt 2026). Nilai 'total' tiap
               baris sengaja TIDAK divalidasi/dipakai: workflow menghitung
               ulang harga × qty. */
            'budget_plan_id' => 'required|integer|exists:budget_plans,id',
            'costs' => 'required|array|min:1',
            'costs.*.budget_detail_id' => 'required|integer|exists:budget_plan_details,id',
            'costs.*.description' => 'required|string|max:255',
            'costs.*.amount' => 'required|integer|min:1',
            'costs.*.qty' => 'required|integer|min:1',
        ], [
            'reason.required' => 'Keterangan perubahan tanggal visit wajib diisi.',
            'budget_plan_id.required' => 'Budget Plan wajib dipilih.',
            'costs.required' => 'Minimal satu baris item biaya wajib diisi.',
        ]);

        /* Gerbang yang sama dengan penyaringan daftar di show(): teknisi hanya
           boleh menganggarkan dari pagu CUSTOMER team-nya sendiri. Daftar di
           form sudah disaring, tapi id-nya tetap datang dari browser — tanpa
           pemeriksaan ini batasannya cuma tampilan (20 Okt 2026). */
        $this->abortIfBudgetPlanLuarCustomer(Auth::user(), $ticket, (int) $data['budget_plan_id']);

        $this->workflow->createPlan(
            $ticket, Auth::user(), $data['visit_site'], $data['target_online'] ?? null, $data['reason'] ?? null,
            (int) $data['budget_plan_id'], $data['costs'],
        );

        return back()->with('success', 'Plan visit dibuat.');
    }

    /**
     * Supervisor menyetujui plan & biaya. Tiket tetap di tahap Plan; sistem
     * membuat Cost Request berstatus DRAFT dari estimasi biaya plan.
     */
    public function approvePlanBudget(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate([
            'cluster_id' => 'nullable|integer|exists:clusters,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->workflow->approvePlanBudget(
            $ticket, Auth::user(), isset($data['cluster_id']) ? (int) $data['cluster_id'] : null, $data['notes'] ?? null,
        );

        $ticket->refresh();

        return back()->with('success', 'Plan & biaya disetujui. Cost Request draft: '
            . ($ticket->budgetRequest?->code ?? '-') . '.');
    }

    /** Supervisor menolak plan & biaya — tiket tetap di Plan untuk direvisi. */
    public function rejectPlanBudget(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate(
            ['notes' => 'required|string|max:500'],
            ['notes.required' => 'Alasan penolakan wajib diisi.'],
        );

        $this->workflow->rejectPlanBudget($ticket, Auth::user(), $data['notes']);

        return back()->with('success', 'Plan & biaya ditolak.');
    }

    /** Geser "mulai perjalanan" — SLA Travel mulai. Evidence perjalanan wajib. */
    public function startTravel(Request $request, TroubleTicket $ticket)
    {
        $request->validate([
            'attachments' => 'required|array|min:1|max:'.TroubleTicketAttachment::MAX_FILES,
            'attachments.*' => TroubleTicketAttachment::rules(),
        ], $this->fileMessages());

        $this->workflow->startTravel($ticket, Auth::user(), $request->file('attachments', []));

        return back()->with('success', 'Perjalanan dimulai.');
    }

    /** Batal visit di tengah perjalanan — SLA Travel kembali ke 0, jadwal plan diisi ulang. */
    public function cancelTravel(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
            // Jadwal kunjungan berikutnya wajib: tiket tidak ditinggalkan tanpa plan.
            'visit_site' => 'required|date|after_or_equal:'.$ticket->planMinDate()->toDateString(),
        ]);

        $this->workflow->cancelTravel($ticket, Auth::user(), $data['reason'], $data['visit_site']);

        return back()->with('success', 'Perjalanan dibatalkan.');
    }

    /** Konfirmasi site sudah benar — SLA Travel berhenti. */
    public function arrive(TroubleTicket $ticket)
    {
        $this->workflow->arriveOnSite($ticket, Auth::user());

        return back()->with('success', 'Kedatangan dikonfirmasi.');
    }

    public function startProgress(TroubleTicket $ticket)
    {
        $this->workflow->startProgress($ticket, Auth::user());

        return back()->with('success', 'Progres dimulai.');
    }

    public function close(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate([
            // Teks RCA/Sub-RCA hanya penyalin nama master; yang menentukan id-nya.
            'rca' => 'nullable|string|max:40',
            'rca_id' => 'required|integer|exists:trouble_ticket_rcas,id',
            'sub_rca' => 'nullable|string|max:150',
            'sub_rca_id' => 'nullable|integer|exists:trouble_ticket_sub_rcas,id',
            // Keterangan & evidence wajib (keputusan 12 Okt 2026); kolom Action dihapus dari form.
            'resolution_summary' => 'required|string|max:5000',
            'attachments' => 'required|array|min:1|max:'.TroubleTicketAttachment::MAX_FILES,
            'attachments.*' => TroubleTicketAttachment::rules(),
        ], $this->fileMessages());

        $this->workflow->close($ticket, Auth::user(), $data, $request->file('attachments', []));

        return back()->with('success', 'Form close dikirim. Tiket menunggu approval supervisor.');
    }

    /** Supervisor menyetujui penutupan — tiket CLOSED. */
    public function approveClose(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate(['notes' => 'nullable|string|max:1000']);

        $this->workflow->approveClose($ticket, Auth::user(), $data['notes'] ?? null);

        return back()->with('success', 'Tiket disetujui dan ditutup.');
    }

    /** Supervisor menolak penutupan — tiket kembali ke teknisi. */
    public function rejectClose(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate(['notes' => 'required|string|max:1000']);

        $this->workflow->rejectClose($ticket, Auth::user(), $data['notes']);

        return back()->with('success', 'Penutupan ditolak; tiket dikembalikan ke teknisi.');
    }

    public function reassign(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate([
            'assigned_to' => 'required|integer|exists:users,id',
            'reason' => 'required|string|max:500',
            // Reschedule tahap Plan & Trip ikut di form ini; tahap lain tidak mengirimnya.
            'visit_site' => 'nullable|date|after_or_equal:'.$ticket->planMinDate()->toDateString(),
        ]);

        $this->workflow->reassign(
            $ticket, Auth::user(), (int) $data['assigned_to'], $data['reason'], $data['visit_site'] ?? null,
        );

        return back()->with('success', 'Teknisi dialihkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | PENGATURAN — gerbang role: AccessRoles::TROUBLE_TICKET_SETTINGS.
    | Satu halaman bertab: kebijakan SLA + master RCA, Sub-RCA dan Severity.
    | Problem tidak punya layar (tidak lagi diisi dari form buat tiket).
    |--------------------------------------------------------------------------
    */

    public function settings()
    {
        return view('trouble-ticket.settings', [
            'severities' => TroubleTicketSeverity::orderBy('level')->orderBy('name')->get(),
            'rcas' => TroubleTicketRca::orderBy('name')->get(),
            'subRcas' => TroubleTicketSubRca::with('rca:id,name')
                ->join('trouble_ticket_rcas', 'trouble_ticket_rcas.id', '=', 'trouble_ticket_sub_rcas.rca_id')
                ->orderBy('trouble_ticket_rcas.name')
                ->orderBy('trouble_ticket_sub_rcas.name')
                ->select('trouble_ticket_sub_rcas.*')
                ->get(),
            // Satu kebijakan saja yang ditampilkan: yang dipakai tiket baru.
            // Bisa null kalau perusahaan belum pernah mengisi target.
            'slaPolicy' => TroubleTicketSlaPolicy::applicableForProblem(null),
        ]);
    }

    /*
    | Master RCA / Sub-RCA / Severity. Pola sama dengan kebijakan SLA: hanya
    | tambah dan aktif/nonaktif — tidak ada hapus, karena tiket lama menunjuk
    | baris ini (FK RESTRICT) dan riwayatnya harus tetap terbaca.
    */

    public function storeRca(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:60|unique:trouble_ticket_rcas,name',
        ]);

        TroubleTicketRca::create($data + ['status' => 'ACTIVE']);

        return back()->with('success', 'RCA ditambahkan.');
    }

    public function updateRca(Request $request, TroubleTicketRca $rca)
    {
        $data = $request->validate([
            'name' => 'required|string|max:60|unique:trouble_ticket_rcas,name,'.$rca->id,
        ]);

        $rca->update($data);

        return back()->with('success', 'RCA diperbarui.');
    }

    public function toggleRca(TroubleTicketRca $rca)
    {
        $rca->update(['status' => $rca->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE']);

        return back()->with('success', 'Status RCA diperbarui.');
    }

    public function storeSubRca(Request $request)
    {
        $data = $request->validate([
            'rca_id' => 'required|integer|exists:trouble_ticket_rcas,id',
            'name' => 'required|string|max:150',
        ]);

        if (TroubleTicketSubRca::where('rca_id', $data['rca_id'])->where('name', $data['name'])->exists()) {
            return back()->withInput()->with('error', 'Sub-RCA dengan nama itu sudah ada pada RCA tersebut.');
        }

        TroubleTicketSubRca::create($data + ['status' => 'ACTIVE']);

        return back()->with('success', 'Sub-RCA ditambahkan.');
    }

    public function updateSubRca(Request $request, TroubleTicketSubRca $subRca)
    {
        $data = $request->validate([
            'rca_id' => 'required|integer|exists:trouble_ticket_rcas,id',
            'name' => 'required|string|max:150',
        ]);

        $kembar = TroubleTicketSubRca::where('rca_id', $data['rca_id'])
            ->where('name', $data['name'])
            ->whereKeyNot($subRca->id)
            ->exists();
        if ($kembar) {
            return back()->with('error', 'Sub-RCA dengan nama itu sudah ada pada RCA tersebut.');
        }

        $subRca->update($data);

        return back()->with('success', 'Sub-RCA diperbarui.');
    }

    public function toggleSubRca(TroubleTicketSubRca $subRca)
    {
        $subRca->update(['status' => $subRca->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE']);

        return back()->with('success', 'Status Sub-RCA diperbarui.');
    }

    public function storeSeverity(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|regex:/^[A-Za-z0-9_-]+$/|unique:trouble_ticket_severities,code',
            'name' => 'required|string|max:50',
            // 1 = paling parah; menentukan urutan & warna di layar.
            'level' => 'required|integer|min:1|max:9',
            // Rentang umur tiket (hari) yang memakai severity ini; batas atas
            // boleh kosong = tanpa batas. Lihat TroubleTicketSeverity::agingSteps().
            'aging_from' => 'nullable|integer|min:0|max:3650',
            'aging_to' => 'nullable|integer|min:0|max:3650|gte:aging_from',
        ]);
        $data['code'] = strtoupper($data['code']);

        TroubleTicketSeverity::create($data + ['status' => 'ACTIVE']);
        TroubleTicketSeverity::lupakanTangga();

        return back()->with('success', 'Severity ditambahkan.');
    }

    /**
     * Kode severity dipakai tiket lama (`trouble_tickets.severity`, FK
     * ON UPDATE CASCADE) — mengubahnya ikut memperbarui tiket yang memakainya.
     */
    public function updateSeverity(Request $request, TroubleTicketSeverity $severity)
    {
        $data = $request->validate([
            'code' => 'required|string|max:20|regex:/^[A-Za-z0-9_-]+$/|unique:trouble_ticket_severities,code,'.$severity->id,
            'name' => 'required|string|max:50',
            'level' => 'required|integer|min:1|max:9',
            'aging_from' => 'nullable|integer|min:0|max:3650',
            'aging_to' => 'nullable|integer|min:0|max:3650|gte:aging_from',
        ]);
        $data['code'] = strtoupper($data['code']);

        $severity->update($data);
        TroubleTicketSeverity::lupakanTangga();

        return back()->with('success', 'Severity diperbarui.');
    }

    public function toggleSeverity(TroubleTicketSeverity $severity)
    {
        $severity->update(['status' => $severity->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE']);
        TroubleTicketSeverity::lupakanTangga();

        return back()->with('success', 'Status severity diperbarui.');
    }

    /**
     * Limit SLA: Response / Travel / Resolution, dalam menit.
     *
     * Halaman Pengaturan hanya menampilkan tiga angka itu, jadi yang diubah
     * selalu kebijakan yang MEMANG dipakai tiket baru
     * (applicableForProblem(null)) — bukan baris sembarangan. Kalau belum ada
     * satu pun, baris umum dibuat saat penyimpanan pertama.
     *
     * Tiket lama menyimpan snapshot targetnya sendiri, jadi perubahan di sini
     * hanya berlaku untuk tiket yang dibuat sesudahnya.
     */
    public function updateSlaLimit(Request $request)
    {
        $kolom = [
            'response' => 'response_minutes',
            'travel' => 'travel_minutes',
            'resolution' => 'resolution_minutes',
        ];

        $data = $request->validate([
            'metric' => 'required|string|in:'.implode(',', array_keys($kolom)),
            // 0 = tidak terhingga; disimpan NULL, sama artinya dengan tanpa target.
            'minutes' => 'nullable|integer|min:0|max:525600',
        ]);

        $policy = TroubleTicketSlaPolicy::applicableForProblem(null)
            ?? TroubleTicketSlaPolicy::create([
                'severity' => null,
                'problem_id' => null,
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
            ]);

        $policy->update([$kolom[$data['metric']] => $data['minutes'] ?: null]);

        return back()->with('success', 'Limit SLA diperbarui. Tiket lama tetap memakai target lamanya.');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /** @return array{0: Collection, 1: bool} */
    private function messagePage(TroubleTicket $ticket, ?int $beforeId = null): array
    {
        $rows = $ticket->messages()
            ->with(['attachments', 'user:id,name'])
            ->when($beforeId, fn ($q) => $q->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit(self::MESSAGE_PAGE + 1)
            ->get();

        $hasOlder = $rows->count() > self::MESSAGE_PAGE;

        return [$rows->take(self::MESSAGE_PAGE)->reverse()->values(), $hasOlder];
    }

    /** Siapa teknisi (semua penugasan) & leader team — untuk label peran di timeline. */
    private function timelineContext(TroubleTicket $ticket): array
    {
        return [
            'tech' => $ticket->assignments()->pluck('assigned_to')->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'sup' => $this->workflow->supervisorIdsFor((int) $ticket->assigned_to)->all(),
        ];
    }

    private function fileMessages(): array
    {
        $ext = implode(', ', TroubleTicketAttachment::EXTENSIONS);

        return [
            'attachments.max' => 'Paling banyak '.TroubleTicketAttachment::MAX_FILES.' berkas sekali kirim.',
            'attachments.*.max' => 'Ukuran berkas maksimal 10 MB.',
            'attachments.*.mimes' => "Jenis berkas tidak diizinkan. Diterima: {$ext}.",
            'attachments.*.extensions' => "Jenis berkas tidak diizinkan. Diterima: {$ext}.",
        ];
    }
}

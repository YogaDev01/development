<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Site;
use App\Models\TroubleTicket;
use App\Models\TroubleTicketAttachment;
use App\Models\TroubleTicketProblem;
use App\Models\TroubleTicketSeverity;
use App\Models\TroubleTicketSlaPolicy;
use App\Models\User;
use App\Services\TroubleTicketWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
    private const PER_PAGE = 25;

    /** Kolom yang boleh dipakai mengurutkan daftar => ekspresi SQL. */
    private const SORTS = [
        'ticket_number' => 'trouble_tickets.id',
        // Level dari tabel severity (1 = paling parah); kode tak dikenal di akhir.
        'severity'   => '(SELECT COALESCE(MIN(s.level), 255) FROM trouble_ticket_severities s WHERE s.code = trouble_tickets.severity)',
        'reported_at' => 'trouble_tickets.reported_at',
        'status'     => "FIELD(trouble_tickets.status, 'OPEN', 'APPROVED', 'ON_PLAN', 'IN_PROGRESS', 'CLOSED')",
        'created_at' => 'trouble_tickets.created_at',
        'updated_at' => 'trouble_tickets.updated_at',
    ];

    /** Jumlah item timeline per muat. */
    private const MESSAGE_PAGE = 30;

    public function __construct(private TroubleTicketWorkflow $workflow)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $user = Auth::user();

        $filters = $request->validate([
            'q'           => 'nullable|string|max:100',
            'status'      => 'nullable|in:' . implode(',', TroubleTicket::STATUSES),
            'severity'    => 'nullable|string|max:20',
            'problem_id'  => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'site_id'     => 'nullable|integer',
            'assigned_to' => 'nullable|integer',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date',
            'overdue'     => 'nullable|boolean',
            'sort'        => 'nullable|in:' . implode(',', array_keys(self::SORTS)),
            'dir'         => 'nullable|in:asc,desc',
        ]);

        $query = TroubleTicket::visibleTo($user)
            ->with([
                'site:id,tid,sitename',
                'customer:id,name',
                'problem:id,name',
                'severityRef',
                'technician:id,name',
            ]);

        if ($q = trim($filters['q'] ?? '')) {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($w) use ($like) {
                $w->where('trouble_tickets.ticket_number', 'like', $like)
                  ->orWhere('trouble_tickets.external_ref', 'like', $like)
                  ->orWhere('trouble_tickets.issue', 'like', $like)
                  ->orWhere('trouble_tickets.device', 'like', $like)
                  ->orWhere('trouble_tickets.flag_alarm', 'like', $like)
                  ->orWhere('trouble_tickets.description', 'like', $like)
                  ->orWhereHas('site', fn ($s) => $s->where('tid', 'like', $like)->orWhere('sitename', 'like', $like))
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like));
            });
        }

        foreach (['status', 'severity', 'problem_id', 'customer_id', 'site_id', 'assigned_to'] as $kolom) {
            if (! empty($filters[$kolom])) {
                $query->where('trouble_tickets.' . $kolom, $filters[$kolom]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('trouble_tickets.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('trouble_tickets.created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['overdue'])) {
            $query->overdue();
        }

        $sort = $filters['sort'] ?? 'created_at';
        $dir  = $filters['dir'] ?? 'desc';
        $query->orderByRaw(self::SORTS[$sort] . ' ' . $dir)->orderByDesc('trouble_tickets.id');

        $tickets = $query->paginate(self::PER_PAGE)->withQueryString();

        // Kartu ringkasan — satu query agregat + satu query overdue.
        $agregat = TroubleTicket::visibleTo($user)->toBase()
            ->selectRaw("SUM(status <> 'CLOSED') AS terbuka")
            ->selectRaw("SUM(status = 'APPROVED') AS menunggu_approval")
            ->selectRaw('SUM(closed_at >= ?) AS closed_bulan_ini', [now()->startOfMonth()])
            ->first();
        $overdue = TroubleTicket::visibleTo($user)->overdue()->count();

        $visibleIds = TroubleTicket::visibleTo($user)->toBase();
        $options = [
            'customers'   => Customer::whereIn('id', (clone $visibleIds)->select('customer_id'))->orderBy('name')->get(['id', 'name']),
            'sites'       => Site::whereIn('id', (clone $visibleIds)->select('site_id'))->orderBy('tid')->get(['id', 'tid', 'sitename']),
            'technicians' => User::whereIn('id', (clone $visibleIds)->select('assigned_to'))->orderBy('name')->get(['id', 'name']),
            'severities'  => TroubleTicketSeverity::orderBy('level')->orderBy('name')->get(),
            'problems'    => TroubleTicketProblem::orderBy('name')->get(['id', 'name']),
        ];

        return view('trouble-ticket.index', [
            'tickets'  => $tickets,
            'filters'  => $filters,
            'sort'     => $sort,
            'dir'      => $dir,
            'options'  => $options,
            'stats'    => [
                'terbuka'            => (int) ($agregat->terbuka ?? 0),
                'overdue'            => $overdue,
                'menunggu_approval'  => (int) ($agregat->menunggu_approval ?? 0),
                'closed_bulan_ini'   => (int) ($agregat->closed_bulan_ini ?? 0),
            ],
            'canSettings' => $user->hasRole(\App\Support\AccessRoles::TROUBLE_TICKET_SETTINGS),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BUAT
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return view('trouble-ticket.create', [
            'sites'      => Site::with('customer:id,name')->where('status', 'ACTIVE')->orderBy('tid')->get(['id', 'tid', 'sitename', 'customer_id']),
            'problems'   => TroubleTicketProblem::where('status', 'ACTIVE')->orderBy('name')->get(),
            'severities' => TroubleTicketSeverity::active(),
        ]);
    }

    /** Teknisi yang boleh ditugaskan di sebuah site (dropdown form & reassign). */
    public function technicians(Site $site)
    {
        return response()->json([
            'technicians' => $this->workflow->eligibleTechnicians($site->id)->map(fn ($u) => [
                'id'   => $u->id,
                'name' => $u->name,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'site_id'       => 'required|integer|exists:sites,id',
            'assigned_to'   => 'required|integer|exists:users,id',
            'problem_id'    => 'required|integer|exists:trouble_ticket_problems,id',
            'severity'      => 'required|string|max:20|exists:trouble_ticket_severities,code',
            'issue'         => 'required|string|max:200',
            'description'   => 'required|string|max:10000',
            'external_ref'  => 'nullable|string|max:40|unique:trouble_tickets,external_ref',
            'device'        => 'nullable|string|max:40',
            'flag_alarm'    => 'nullable|string|max:40',
            'reported_at'   => 'nullable|date|before_or_equal:now',
            'reporter_name' => 'nullable|string|max:150',
            'attachments'   => 'nullable|array|max:' . TroubleTicketAttachment::MAX_FILES,
            'attachments.*' => TroubleTicketAttachment::rules(),
        ], $this->fileMessages());

        $ticket = $this->workflow->create(Auth::user(), $data, $request->file('attachments', []));

        return redirect()->route('trouble-ticket.show', $ticket)
            ->with('success', "Trouble Ticket {$ticket->ticket_number} berhasil dibuat.");
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

        // Respons pertama teknisi dicatat di server, sekali (lihat recordAccess()).
        $this->workflow->recordAccess($ticket, $user);

        $ticket->load([
            'site.customer:id,name',
            'site.picLinks.pic',
            'problem',
            'severityRef',
            'pic',
            'creator:id,name',
            'technician:id,name,phone',
            'acknowledger:id,name',
            'decider:id,name',
            'closer:id,name',
            'planApprover:id,name',
            'assignments.technician:id,name',
            'assignments.assigner:id,name',
        ]);

        [$messages, $hasOlder] = $this->messagePage($ticket);

        $canSupervise = $this->workflow->canSupervise($user, $ticket);

        return view('trouble-ticket.show', [
            'ticket'        => $ticket,
            'messages'      => $messages,
            'ctx'           => $this->timelineContext($ticket),
            'hasOlder'      => $hasOlder,
            'isTechnician'  => $this->workflow->isTechnician($user, $ticket),
            'mustDecide'    => $this->workflow->mustDecide($user, $ticket),
            'canSupervise'  => $canSupervise,
            'canReviewPlan' => $this->workflow->canReviewPlan($user, $ticket),
            'canMessage'    => $this->workflow->canMessage($user, $ticket),
            'supervisors'   => $this->workflow->supervisorsFor((int) $ticket->assigned_to),
            'reassignTo'    => $canSupervise && in_array($ticket->status, TroubleTicketWorkflow::REASSIGNABLE, true)
                ? $this->workflow->eligibleTechnicians($ticket->site_id)->where('id', '!=', $ticket->assigned_to)->values()
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
            'after_id'  => 'nullable|integer|min:0',
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
            'html'      => $messages->isEmpty() ? '' : view('trouble-ticket._messages', [
                'messages' => $messages,
                'ctx'      => $this->timelineContext($ticket),
            ])->render(),
            'oldest_id' => $messages->first()?->id,
            'newest_id' => $messages->last()?->id,
            'has_older' => $hasOlder,
            'status'    => $ticket->fresh()->status,
        ]);
    }

    public function postMessage(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate([
            'body'          => 'nullable|string|max:5000',
            'client_token'  => 'nullable|uuid',
            'attachments'   => 'nullable|array|max:' . TroubleTicketAttachment::MAX_FILES,
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
                'id'   => $message->id,
                'html' => view('trouble-ticket._messages', [
                    'messages' => collect([$message]),
                    'ctx'      => $this->timelineContext($ticket),
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
            'Content-Type'           => $attachment->is_inline ? $attachment->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=604800',
        ], $inline ? 'inline' : 'attachment');
    }

    /*
    |--------------------------------------------------------------------------
    | AKSI WORKFLOW
    |--------------------------------------------------------------------------
    */

    public function submitPlan(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate([
            'decision' => 'required|in:' . implode(',', array_keys(TroubleTicket::VISIT_DECISIONS)),
            'reason'        => 'nullable|string|max:1000',
            'visit_site'    => 'nullable|date|after_or_equal:today',
            'target_online' => 'nullable|date|after_or_equal:today',
        ]);

        $this->workflow->submitPlan(
            $ticket, Auth::user(), $data['decision'], $data['reason'] ?? null,
            $data['visit_site'] ?? null, $data['target_online'] ?? null,
        );

        return back()->with('success', 'Plan diajukan ke leader team untuk approval.');
    }

    public function approvePlan(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate(['notes' => 'nullable|string|max:1000']);

        $this->workflow->approvePlan($ticket, Auth::user(), $data['notes'] ?? null);

        return back()->with('success', 'Plan disetujui. Tiket siap dikerjakan.');
    }

    public function rejectPlan(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate(['notes' => 'required|string|max:1000']);

        $this->workflow->rejectPlan($ticket, Auth::user(), $data['notes']);

        return back()->with('success', 'Plan ditolak. Tiket kembali Open.');
    }

    public function correctDecision(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate([
            'decision' => 'required|in:' . implode(',', array_keys(TroubleTicket::VISIT_DECISIONS)),
            'reason'     => 'required|string|max:1000',
            'visit_site' => 'nullable|date|after_or_equal:today',
        ]);

        $this->workflow->correctDecision($ticket, Auth::user(), $data['decision'], $data['reason'], $data['visit_site'] ?? null);

        return back()->with('success', 'Plan dikoreksi.');
    }

    public function startProgress(TroubleTicket $ticket)
    {
        $this->workflow->startProgress($ticket, Auth::user());

        return back()->with('success', 'Troubleshooting dimulai.');
    }

    public function close(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate([
            'rca'                => 'required|string|max:40',
            'sub_rca'            => 'nullable|string|max:150',
            'action'             => 'required|string|max:150',
            'resolution_summary' => 'nullable|string|max:5000',
            'attachments'        => 'nullable|array|max:' . TroubleTicketAttachment::MAX_FILES,
            'attachments.*'      => TroubleTicketAttachment::rules(),
        ], $this->fileMessages());

        $this->workflow->close($ticket, Auth::user(), $data, $request->file('attachments', []));

        return back()->with('success', 'Tiket ditutup.');
    }

    public function reassign(Request $request, TroubleTicket $ticket)
    {
        $data = $request->validate([
            'assigned_to' => 'required|integer|exists:users,id',
            'reason'      => 'required|string|max:500',
        ]);

        $this->workflow->reassign($ticket, Auth::user(), (int) $data['assigned_to'], $data['reason']);

        return back()->with('success', 'Teknisi dialihkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | PENGATURAN SLA — gerbang role: AccessRoles::TROUBLE_TICKET_SETTINGS.
    | Problem & Severity TIDAK punya UI: diisi langsung di basis data.
    |--------------------------------------------------------------------------
    */

    public function settings()
    {
        return view('trouble-ticket.settings', [
            'severities' => TroubleTicketSeverity::orderBy('level')->orderBy('name')->get(),
            'problems'   => TroubleTicketProblem::orderBy('name')->get(),
            'policies'   => TroubleTicketSlaPolicy::with(['problem:id,name', 'severityRef'])
                ->orderByRaw('(SELECT COALESCE(MIN(s.level), 255) FROM trouble_ticket_severities s WHERE s.code = trouble_ticket_sla_policies.severity)')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function storePolicy(Request $request)
    {
        $data = $request->validate([
            'severity'           => 'required|string|max:20|exists:trouble_ticket_severities,code',
            'problem_id'         => 'nullable|integer|exists:trouble_ticket_problems,id',
            'response_minutes'   => 'nullable|integer|min:1|max:525600',
            'travel_minutes'     => 'nullable|integer|min:1|max:525600',
            'resolution_minutes' => 'nullable|integer|min:1|max:525600',
            'effective_from'     => 'nullable|date',
        ]);

        if (empty($data['response_minutes']) && empty($data['travel_minutes']) && empty($data['resolution_minutes'])) {
            return back()->withInput()->with('error', 'Isi minimal satu target SLA.');
        }

        TroubleTicketSlaPolicy::create($data + ['status' => 'ACTIVE', 'created_by' => Auth::id()]);

        return back()->with('success', 'Kebijakan SLA ditambahkan. Berlaku untuk tiket yang dibuat setelah ini.');
    }

    /**
     * Kebijakan tidak diubah angkanya (tiket lama menyimpan snapshot, tapi
     * riwayat kebijakan tetap harus terbaca) — hanya diaktifkan/nonaktifkan.
     */
    public function togglePolicy(TroubleTicketSlaPolicy $policy)
    {
        $policy->update(['status' => $policy->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE']);

        return back()->with('success', 'Status kebijakan SLA diperbarui.');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /** @return array{0: \Illuminate\Support\Collection, 1: bool} */
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
            'sup'  => $this->workflow->supervisorIdsFor((int) $ticket->assigned_to)->all(),
        ];
    }

    private function fileMessages(): array
    {
        $ext = implode(', ', TroubleTicketAttachment::EXTENSIONS);

        return [
            'attachments.max'          => 'Paling banyak ' . TroubleTicketAttachment::MAX_FILES . ' berkas sekali kirim.',
            'attachments.*.max'        => 'Ukuran berkas maksimal 10 MB.',
            'attachments.*.mimes'      => "Jenis berkas tidak diizinkan. Diterima: {$ext}.",
            'attachments.*.extensions' => "Jenis berkas tidak diizinkan. Diterima: {$ext}.",
        ];
    }
}

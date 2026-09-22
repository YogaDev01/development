<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Project;
use App\Models\ProjectDetail;
use App\Models\ProjectMonitoringLog;
use App\Models\Cluster;
use App\Models\User;
use App\Models\AccessGroupDetail;
use App\Models\RequestStatusLog;
use App\Services\NotifyService;

class ProjectMonitoringController extends Controller
{
    /**
     * Role yang bisa melihat seluruh PO tanpa dibatasi Access Group.
     */
    private const PRIVILEGED_ROLES = ['superadmin', 'admin', 'approver', 'cost_control', 'treasury', 'collection', 'accounting', 'guest'];

    /**
     * Role yang selalu bisa di-mention di PO manapun, terlepas dari
     * keanggotaan Access Group-nya (finance/finance manager/approver
     * lintas-PO).
     */
    private const MENTION_BYPASS_ROLES = ['superadmin', 'admin', 'approver', 'finance', 'finance_manager', 'cost_control', 'treasury', 'collection', 'accounting'];

    /**
     * Role yang berperan sebagai "Head" — bisa meng-ACC/tolak pengajuan
     * milestone selesai.
     */
    private const HEAD_ROLES = ['superadmin', 'approver'];

    /** Status Cost Request yang dianggap relevan untuk notifikasi feed. */
    private const COST_REQUEST_STATUS_LABELS = [
        'SUBMITTED' => 'disubmit',
        'APPROVED'  => 'disetujui',
        'VERIFIED'  => 'diverifikasi',
        'QUEUED'    => 'dimasukkan ke antrean',
        'PAID'      => 'diproses pembayaran',
        'REALIZED'  => 'direalisasi',
        'CLOSED'    => 'ditutup',
    ];

    private function isPrivileged(): bool
    {
        // Role istimewa (termasuk finance, yang tidak selalu punya Access
        // Group project sendiri) melihat semua PO. Role lain tetap dibatasi
        // ke PO yang ada di Access Group mereka — jangan hardcode true di
        // sini, itu pernah menyebabkan semua user melihat seluruh data PO.
        $role = strtolower(Auth::user()->role->name ?? '');
        return in_array($role, self::MENTION_BYPASS_ROLES, true);
    }

    private function isHead(): bool
    {
        $role = strtolower(Auth::user()->role->name ?? '');
        return in_array($role, self::HEAD_ROLES, true);
    }

    /** Supervisor hanya boleh Update Log — dilarang set status/finish milestone. */
    private function canSetMilestone(): bool
    {
        $role = strtolower(Auth::user()->role->name ?? '');
        return $role !== 'supervisor';
    }

    /**
     * ID PO yang boleh diakses user saat ini (null = semua/tidak dibatasi).
     */
    private function accessibleProjectIds(): ?array
    {
        if ($this->isPrivileged()) {
            return null;
        }

        $user = Auth::user();
        return $user->accessibleProjectIds()->all();
    }

    private function canManageProject(int $projectId): bool
    {
        if ($this->isPrivileged()) {
            return true;
        }

        return Auth::user()->accessibleProjectIds()->contains($projectId);
    }

    private function findAccessibleProject(int $id): Project
    {
        $projectIds = $this->accessibleProjectIds();

        return Project::when(is_array($projectIds), fn ($q) => $q->whereIn('id', $projectIds))
            ->findOrFail($id);
    }

    /**
     * User yang bisa di-mention untuk PO ini: user > access_group_id >
     * access_group_details > project_id (yang aksesnya memang mencakup PO
     * ini), ditambah role privileged yang bisa mengakses semua PO.
     */
    private function mentionableUsers(int $projectId)
    {
        $accessGroupIds = AccessGroupDetail::where('project_id', $projectId)
            ->pluck('access_group_id')
            ->unique();

        return User::where('status', 'ACTIVE')
            ->where(function ($q) use ($accessGroupIds) {
                $q->whereIn('access_group_id', $accessGroupIds)
                  ->orWhereHas('role', fn ($r) => $r->whereRaw('LOWER(name) IN (\'' . implode("','", self::MENTION_BYPASS_ROLES) . '\')'));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'username']);
    }

    public function index()
    {
        $projectIds = $this->accessibleProjectIds();

        $projects = Project::with(['customer', 'manager', 'details.stage'])
            ->withMax('monitoringLogs as last_update_at', 'created_at')
            ->when(is_array($projectIds), fn ($q) => $q->whereIn('id', $projectIds))
            ->orderByDesc('id')
            ->get()
            ->sortByDesc(fn ($p) => $p->last_update_at ?? $p->created_at)
            ->values();

        /*
        | Dihitung, tidak disimpan — sama seperti daftar Purchase Order.
        | Halaman daftar tidak boleh menulis; penulisan terjadi saat status
        | milestone benar-benar diubah (updateDetailStatus di bawah).
        */
        $projects->each(function ($p) {
            foreach ($p->monitoringStatusesAsComputed() as $kolom => $nilai) {
                $p->setAttribute($kolom, $nilai);
            }
        });

        $summary = [
            'total'            => $projects->count(),
            'po_received_done' => $projects->where('monitoring_po_received_status', 'DONE')->count(),
            'bast_done'        => $projects->where('monitoring_bast_status', 'DONE')->count(),
            'invoice_done'     => $projects->where('monitoring_invoice_status', 'DONE')->count(),
            'payment_done'     => $projects->where('monitoring_payment_status', 'DONE')->count(),
        ];

        return view('project-monitoring.index', compact('projects', 'summary'));
    }

    public function show($id)
    {
        $project = $this->findAccessibleProject((int) $id);

        $project->load([
            'customer',
            'manager',
            'details.cluster',
            'details.stage',
            'details.submittedDoneBy',
            'monitoringLogs.user',
            'monitoringLogs.attachments',
        ]);

        /*
        | Di halaman detail — satu proyek, dan inilah halaman yang memang
        | memiliki data monitoring — penyelarasan tetap DISIMPAN. Fungsinya
        | memperbaiki diri: kalau kolom header pernah tertinggal dari status
        | milestone, membuka halamannya merapikan sekali, bukan setiap kali
        | daftar dibuka untuk semua proyek.
        */
        $this->reconcileMonitoringStatuses($project);

        /*
        | Milestone (project_details) terhubung ke PO lewat project_id-nya
        | sendiri — BUKAN lewat clusters.project_id, karena data cluster lama
        | bisa saja belum/tidak punya project_id yang cocok.
        */
        $clusters = Cluster::where('project_id', $project->id)->get()
            ->merge($project->details->pluck('cluster')->filter())
            ->unique('id')
            ->sortBy('name')
            ->values();

        $detailsByCluster = $project->details->groupBy('cluster_id');

        $canManage = $this->canManageProject((int) $id);
        $canSetMilestone = $canManage && $this->canSetMilestone();
        $isHead = $this->isHead();
        $mentionUsers = $this->mentionableUsers($project->id);

        [$budgetPlans, $costRequestFeeds] = $this->buildCostRequestFeeds($project);

        return view('project-monitoring.show', compact(
            'project', 'canManage', 'canSetMilestone', 'isHead', 'clusters', 'detailsByCluster', 'mentionUsers',
            'budgetPlans', 'costRequestFeeds'
        ));
    }

    /**
     * Untuk tiap Budget Plan milik PO ini, kumpulkan riwayat perubahan status
     * Cost Request (dari RequestStatusLog) sebagai feed notifikasi.
     */
    private function buildCostRequestFeeds(Project $project): array
    {
        $budgetPlans = $project->budgetPlans()->orderBy('code')->get();

        $feeds = [];

        foreach ($budgetPlans as $bp) {
            $crIds = $bp->costRequests()->pluck('id');

            $crEvents = RequestStatusLog::whereIn('budget_request_id', $crIds)
                ->whereIn('status_to', array_keys(self::COST_REQUEST_STATUS_LABELS))
                ->with('budgetRequest')
                ->get()
                ->map(function ($log) {
                    $cr = $log->budgetRequest;
                    $label = self::COST_REQUEST_STATUS_LABELS[$log->status_to] ?? strtolower($log->status_to);

                    return [
                        'type'    => 'cr_event',
                        'cr_id'   => $cr->id ?? null,
                        'cr_code' => $cr->code ?? '-',
                        'message' => $label . ' oleh ' . ($log->actor_name ?: 'System') . ($cr ? ' - ' . $cr->amount_formatted : ''),
                        'date'    => $log->created_at->format('d/m/Y H:i'),
                        'sort'    => $log->created_at,
                    ];
                });

            $chatLogs = ProjectMonitoringLog::where('budget_plan_id', $bp->id)
                ->with(['user', 'attachments'])
                ->get()
                ->map(fn ($log) => [
                    'type' => 'log',
                    'log'  => $log,
                    'sort' => $log->created_at,
                ]);

            $feeds[$bp->id] = $crEvents->concat($chatLogs)->sortBy('sort')->values();
        }

        return [$budgetPlans, $feeds];
    }

    /*
    |--------------------------------------------------------------------------
    | CHAT / LOG
    |--------------------------------------------------------------------------
    */

    public function storeMessage(Request $request, $id)
    {
        $project = $this->findAccessibleProject((int) $id);

        if (! $this->canManageProject($project->id)) {
            abort(403, 'Anda tidak memiliki akses untuk PO ini.');
        }

        $request->validate([
            'message'              => 'nullable|string|max:5000',
            'cluster_id'           => 'nullable|exists:clusters,id',
            'project_detail_id'    => 'nullable|exists:project_details,id',
            'budget_plan_id'       => 'nullable|exists:budget_plans,id',
            'attachments'          => 'nullable|array',
            'attachments.*'        => 'file|max:51200',
        ]);

        $fail = function (string $msg) use ($request) {
            return $request->expectsJson() || $request->ajax()
                ? response()->json(['ok' => false, 'error' => $msg], 422)
                : back()->with('error', $msg);
        };

        if (blank($request->project_detail_id) && blank($request->budget_plan_id)) {
            return $fail('Pilih milestone atau Cost Request dulu sebelum menulis aktivitas.');
        }

        $detail = null;
        $budgetPlan = null;

        if ($request->filled('project_detail_id')) {
            $detail = ProjectDetail::findOrFail($request->project_detail_id);

            if ($detail->status === 'NOT_STARTED') {
                return $fail('Milestone ini belum dimulai — gunakan menu (⋮) untuk Start Milestone.');
            }
        } else {
            $budgetPlan = \App\Models\BudgetPlan::findOrFail($request->budget_plan_id);

            if ($budgetPlan->status !== 'ACTIVE') {
                return $fail('Budget Plan ini tidak aktif — tidak bisa menulis aktivitas baru.');
            }
        }

        if (blank($request->message) && ! $request->hasFile('attachments')) {
            return $fail('Pesan atau lampiran wajib diisi.');
        }

        $log = ProjectMonitoringLog::create([
            'project_id'        => $project->id,
            'cluster_id'        => $request->cluster_id,
            'project_detail_id' => $detail?->id,
            'budget_plan_id'    => $budgetPlan?->id,
            'user_id'           => Auth::id(),
            'type'              => 'MESSAGE',
            'message'           => $request->message,
        ]);

        foreach ($request->file('attachments', []) as $file) {
            if (! $file->isValid()) continue;

            $path = $file->store('monitoring_attachments', 'secure');
            $log->attachments()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'input_by'  => Auth::id(),
            ]);
        }

        $this->notifyMentions($project, (string) $request->message);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'log_id' => $log->id]);
        }

        return back();
    }

    /**
     * Cari "@username" di pesan, cocokkan dengan user yang bisa di-mention
     * untuk PO ini, lalu kirim notifikasi (in-app + WhatsApp) ke masing-masing.
     */
    private function notifyMentions(Project $project, string $message): void
    {
        if (blank($message) || ! preg_match_all('/@([A-Za-z0-9_]+)/', $message, $matches)) {
            return;
        }

        $mentionedUsernames = collect($matches[1])->map(fn ($u) => strtolower($u))->unique();
        if ($mentionedUsernames->isEmpty()) {
            return;
        }

        $recipients = $this->mentionableUsers($project->id)
            ->filter(fn ($u) => $u->id !== Auth::id() && in_array(strtolower($u->username ?: ''), $mentionedUsernames->all(), true));

        if ($recipients->isEmpty()) {
            return;
        }

        NotifyService::send(
            $recipients,
            'Anda disebut di Project Monitoring',
            Auth::user()->name . ' menyebut Anda di "' . $project->name . '": ' . \Illuminate\Support\Str::limit($message, 120),
            route('project-monitoring.show', $project->id),
            'mention'
        );
    }

    /**
     * Polling realtime — ambil log baru (id > `after`) untuk PO ini.
     */
    public function fetchMessages(Request $request, $id)
    {
        $project = $this->findAccessibleProject((int) $id);
        $afterId = (int) $request->query('after', 0);

        $logs = $project->monitoringLogs()
            ->with(['user', 'attachments'])
            ->where('id', '>', $afterId)
            ->orderBy('created_at')
            ->get();

        $html = $logs->map(fn ($log) => view('project-monitoring._log_item', ['log' => $log])->render())->implode('');

        return response()->json([
            'html'    => $html,
            'last_id' => $logs->max('id') ?? $afterId,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MILESTONE — SET STATUS BEBAS (manager) / SET FINISH
    |--------------------------------------------------------------------------
    */

    /**
     * Manager bisa mengubah status milestone kapanpun tanpa alur ACC/Tolak.
     */
    public function setMilestoneStatus(Request $request, $detailId)
    {
        if (! $this->canSetMilestone()) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah status milestone.');
        }

        $detail = ProjectDetail::with('project', 'stage')->findOrFail($detailId);

        if (! $this->canManageProject($detail->project_id)) {
            abort(403, 'Anda tidak memiliki akses untuk PO ini.');
        }

        $request->validate([
            'status'       => 'required|in:ON_GOING,DONE',
            'actual_start' => 'nullable|date',
        ]);

        $oldStatus = $detail->status;
        $newStatus = $request->status;

        if ($oldStatus === $newStatus) {
            return back();
        }

        $updateData = ['status' => $newStatus];

        // NOT_STARTED -> ON_GOING ("Start Milestone") butuh actual_start.
        // Sekali sudah ON_GOING/DONE, tidak bisa kembali ke NOT_STARTED — hanya
        // toggle antar ON_GOING <-> DONE, jadi actual_start tidak disentuh lagi.
        if ($oldStatus === 'NOT_STARTED' && $newStatus === 'ON_GOING') {
            $updateData['actual_start'] = $request->filled('actual_start') ? $request->actual_start : today();
        }

        $detail->update($updateData);

        $stageName = $detail->stage->name ?? 'Milestone';

        ProjectMonitoringLog::create([
            'project_id'        => $detail->project_id,
            'cluster_id'        => $detail->cluster_id,
            'project_detail_id' => $detail->id,
            'user_id'           => Auth::id(),
            'type'              => 'SYSTEM',
            'message'           => Auth::user()->name . " mengubah status milestone \"{$stageName}\" menjadi " . str_replace('_', ' ', $newStatus),
        ]);

        $this->syncMonitoringHeaderStatus($detail->project, $stageName, $newStatus);

        NotifyService::send(
            $this->mentionableUsers($detail->project_id)->filter(fn ($u) => $u->id !== Auth::id()),
            'Status milestone diperbarui',
            Auth::user()->name . " mengubah status milestone \"{$stageName}\" (" . $detail->project->name . ") menjadi " . str_replace('_', ' ', $newStatus),
            route('project-monitoring.show', $detail->project_id),
            'milestone'
        );

        return back()->with('success', 'Status milestone diperbarui.');
    }

    /**
     * Set Actual Finish sekali saja — tidak bisa diubah lagi setelah diisi.
     */
    public function setMilestoneFinish(Request $request, $detailId)
    {
        if (! $this->canSetMilestone()) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah status milestone.');
        }

        $detail = ProjectDetail::with('project', 'stage')->findOrFail($detailId);

        if (! $this->canManageProject($detail->project_id)) {
            abort(403, 'Anda tidak memiliki akses untuk PO ini.');
        }

        if ($detail->actual_end) {
            return back()->with('error', 'Actual Finish sudah diset sebelumnya dan tidak bisa diubah.');
        }

        $request->validate(['actual_end' => 'nullable|date']);

        $detail->update([
            'actual_end' => $request->filled('actual_end') ? $request->actual_end : today(),
            'status'     => 'DONE',
        ]);

        $stageName = $detail->stage->name ?? 'Milestone';

        ProjectMonitoringLog::create([
            'project_id'        => $detail->project_id,
            'cluster_id'        => $detail->cluster_id,
            'project_detail_id' => $detail->id,
            'user_id'           => Auth::id(),
            'type'              => 'SYSTEM',
            'message'           => Auth::user()->name . " mengisi Actual Finish milestone \"{$stageName}\" — status DONE",
        ]);

        $this->syncMonitoringHeaderStatus($detail->project, $stageName, 'DONE');

        NotifyService::send(
            $this->mentionableUsers($detail->project_id)->filter(fn ($u) => $u->id !== Auth::id()),
            'Actual Finish diisi',
            Auth::user()->name . " mengisi Actual Finish milestone \"{$stageName}\" (" . $detail->project->name . ")",
            route('project-monitoring.show', $detail->project_id),
            'milestone'
        );

        return back()->with('success', 'Actual Finish berhasil diset.');
    }

    /**
     * Set bobot semua milestone PO ini sekaligus — submit langsung tanpa
     * approval (bukan alur Ajukan Done/ACC), untuk keperluan setup awal.
     */
    public function updateWeights(Request $request, $id)
    {
        $project = $this->findAccessibleProject((int) $id);

        if (! $this->canManageProject($project->id)) {
            abort(403, 'Anda tidak memiliki akses untuk PO ini.');
        }

        $request->validate([
            'weights'   => 'required|array',
            'weights.*' => 'required|integer|min:0|max:100',
        ]);

        $total = array_sum($request->weights);
        if ($total !== 100) {
            return back()->with('error', 'Total bobot milestone harus tepat 100 (saat ini ' . $total . ').');
        }

        $detailIds = ProjectDetail::where('project_id', $project->id)->pluck('id')->all();

        foreach ($request->weights as $detailId => $weight) {
            if (! in_array((int) $detailId, $detailIds, true)) continue;
            ProjectDetail::where('id', $detailId)->update(['weight' => (int) $weight]);
        }

        ProjectMonitoringLog::create([
            'project_id' => $project->id,
            'user_id'    => Auth::id(),
            'type'       => 'SYSTEM',
            'message'    => Auth::user()->name . ' memperbarui bobot milestone.',
        ]);

        return back()->with('success', 'Bobot milestone berhasil disimpan.');
    }

    /**
     * Rekonsiliasi status header (PO Received/BAST/Invoice/Payment) berdasarkan
     * status milestone SAAT INI — dipanggil tiap halaman index/detail dimuat,
     * supaya header tidak "nyangkut" kalau perubahan sebelumnya gagal ter-sync
     * (mis. data lama sebelum fitur auto-sync ini ada). Logikanya sendiri ada
     * di Project::reconcileMonitoringStatuses() supaya bisa dipakai ulang oleh
     * halaman lain yang menampilkan badge yang sama (mis. Project index).
     */
    private function reconcileMonitoringStatuses(Project $project): void
    {
        $project->reconcileMonitoringStatuses();
    }

    /**
     * Sinkronkan status header (PO Received/BAST/Invoice/Payment) di Project
     * berdasarkan status milestone yang namanya cocok — otomatis, bukan tombol
     * manual. Menulis log SYSTEM kalau ada perubahan nyata dan tidak silent
     * (silent dipakai reconcileMonitoringStatuses() supaya tidak membanjiri
     * log tiap kali halaman index dimuat).
     */
    private function syncMonitoringHeaderStatus(?Project $project, string $stageName, string $detailStatus, bool $silent = false): void
    {
        if (! $project) return;

        $changed = $project->syncMonitoringHeaderStatus($stageName, $detailStatus);
        if (! $changed || $silent) return;

        $labels = ['NOT_YET' => 'Not Yet', 'ON_GOING' => 'On Going', 'DONE' => 'Done'];

        ProjectMonitoringLog::create([
            'project_id' => $project->id,
            'user_id'    => Auth::id(),
            'type'       => 'SYSTEM',
            'message'    => "{$changed['label']} otomatis berubah menjadi \"" . ($labels[$changed['new']] ?? $changed['new']) . "\" (mengikuti milestone \"{$stageName}\")",
        ]);
    }
}

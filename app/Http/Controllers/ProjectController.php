<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\Customer;
use App\Models\MasterPic;
use App\Models\Cluster;
use App\Models\Stage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProjectController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function normalizeMoney(?string $value): int
    {
        if ($value === null || trim($value) === '') return 0;
        $stripped = preg_replace('/\D/', '', $value);
        return $stripped === '' ? 0 : (int) $stripped;
    }

    private function syncDetails(Project $project, Request $request): void
    {
        // Simpan actual_start/actual_end lama — form ini TIDAK mengedit kolom itu lagi
        // (akan ada form terpisah untuk itu), jadi nilainya harus dipertahankan supaya
        // tidak ke-reset null tiap kali Project & Milestone di-generate ulang di sini.
        $oldActuals = $project->details()->get()->keyBy(
            fn($d) => ($d->cluster_id ?? 'x') . '-' . ($d->stage_id ?? 'x')
        );

        $project->details()->delete();

        foreach ((array) $request->input('details', []) as $row) {
            if (blank($row['cluster_id'] ?? null) && blank($row['stage_id'] ?? null)) continue;

            $key = ($row['cluster_id'] ?: 'x') . '-' . ($row['stage_id'] ?: 'x');
            $old = $oldActuals->get($key);

            $project->details()->create([
                'cluster_id'   => $row['cluster_id']   ?: null,
                'stage_id'     => $row['stage_id']     ?: null,
                'plan_start'   => $row['plan_start']   ?: null,
                'plan_end'     => $row['plan_end']     ?: null,
                'actual_start' => $old->actual_start ?? null,
                'actual_end'   => $old->actual_end   ?? null,
                'notes'        => $row['notes']        ?? null,
            ]);
        }
    }

    private function syncPics(Project $project, Request $request): void
    {
        $ids = array_merge(
            (array) $request->input('pics_internal', []),
            (array) $request->input('pics_customer', [])
        );
        $ids = array_values(array_unique(array_filter($ids)));

        $project->pics()->sync($ids);
    }

    private function storeAttachments(Project $project, Request $request, string $type = 'general'): void
    {
        $key = $type === 'general' ? 'attachments' : 'closing_attachments';

        // Format: {key}[i][title] + {key}[i][file]
        $fileRows  = $request->file($key, []);
        $inputRows = $request->input($key, []);

        foreach ($fileRows as $i => $row) {
            $file = $row['file'] ?? null;
            if (!$file || !$file->isValid()) continue;

            $title = $inputRows[$i]['title'] ?? $file->getClientOriginalName();

            $path = $file->store('project_attachments', 'public');
            $project->attachments()->create([
                'type'      => $type,
                'title'     => strtoupper($title),
                'file_path' => $path,
                'file_name' => basename($path),
                'file_type' => $file->getClientMimeType(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $projects = Project::with(['customer', 'inputBy', 'budgetPlans'])->latest()->get();

        $summary = [
            'total'        => $projects->count(),
            'waiting_po'   => $projects->where('status', 'WAITING_PO')->count(),
            'active'       => $projects->where('status', 'ACTIVE')->count(),
            'progress_done'=> $projects->where('status', 'PROGRESS_DONE')->count(),
            'billed_paid'  => $projects->whereIn('status', ['BILLED', 'PAID', 'CLOSING'])->count(),
            'closed'       => $projects->where('status', 'CLOSED')->count(),
        ];

        return view('project.index', compact('projects', 'summary'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $customers     = Customer::where('status', 'ACTIVE')->orderBy('name')->get();
        $picsInternal  = MasterPic::where('type', 'internal')->where('status', 'ACTIVE')->orderBy('pic')->get();
        $picsCustomer  = MasterPic::where('type', 'customer')->where('status', 'ACTIVE')->orderBy('pic')->get();

        return view('project.create', compact('customers', 'picsInternal', 'picsCustomer'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'customer_id'    => 'nullable|exists:customers,id',
            'value'          => 'nullable',
            'term_of_payment' => 'nullable|string|max:255',
            'done_by'        => 'nullable|string|max:255',
            'notes'          => 'nullable|string',
            'attachments'    => 'nullable|array',
            'attachments.*.title' => 'nullable|string|max:255',
            'attachments.*.file'  => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $code = Project::generateNextCode();

        DB::transaction(function () use ($request, $code) {
            $project = Project::create([
                'code'           => $code,
                'name'           => strtoupper(trim($request->name)),
                'customer_id'    => $request->customer_id,
                'value'          => $this->normalizeMoney($request->value),
                'term_of_payment'     => $request->term_of_payment,
                'done_by'        => $request->done_by,
                'notes'          => $request->notes,
                'status'         => 'DRAFT',
                'input_by'       => Auth::id(),
            ]);

            $this->syncDetails($project, $request);
            $this->syncPics($project, $request);
            $this->storeAttachments($project, $request, 'general');
            $project->addLog('DRAFT', 'DRAFT', 'created');
        });

        return redirect('/project')->with('success', 'PO berhasil dibuat dengan kode ' . $code);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW JSON — AJAX detail modal
    |--------------------------------------------------------------------------
    */

    public function showJson($id)
    {
        $project = Project::with([
            'customer', 'inputBy', 'approvedBy', 'closedBy',
            'attachments', 'logs',
            'details.cluster', 'details.stage',
            'pics',
        ])->findOrFail($id);

        // Users with access to this project via access groups
        $accessUsers = \App\Models\User::whereHas('accessGroup.details', function($q) use ($project) {
            $q->where('project_id', $project->id);
        })->with('role')->get();

        // Budget plans linked to this project
        $budgetPlans = \App\Models\BudgetPlan::where('project_id', $project->id)
            ->withSum('details', 'amount')
            ->get();

        return response()->json([
            'id'              => $project->id,
            'code'            => $project->code,
            'name'            => $project->name,
            'po_number'       => $project->po_number,
            'done_by'         => $project->done_by,
            'value'           => $project->value,
            'value_formatted' => $project->value_formatted,
            'term_of_payment' => $project->term_of_payment,
            'status'          => $project->status,
            'status_label'    => $project->status_label,
            'status_color'    => $project->status_color,
            'notes'           => $project->notes,
            'closing_notes'   => $project->closing_notes,
            'customer'        => $project->customer->name ?? '-',
            'input_by'        => $project->inputBy->name ?? '-',
            'approved_by'     => $project->approvedBy->name ?? '-',
            'approved_at'     => $project->approved_at?->format('d M Y H:i'),
            'closed_by'       => $project->closedBy->name ?? '-',
            'closed_at'       => $project->closed_at?->format('d M Y H:i'),
            'submitted_at'    => $project->submitted_at?->format('d M Y H:i'),
            'created_at'      => $project->created_at->format('d M Y H:i'),
            'no_bast'               => $project->no_bast,
            'tanggal_bast'          => $project->tanggal_bast?->format('d M Y'),
            'nilai_bast'            => $project->nilai_bast,
            'nilai_bast_formatted'  => $project->nilai_bast_formatted,
            'bast_notes'            => $project->bast_notes,
            'bast_submitted_by'     => $project->bastSubmittedBy?->name ?? '-',
            'bast_submitted_at'     => $project->bast_submitted_at?->format('d M Y H:i'),

            'attachments' => $project->attachments->groupBy('type')->map(
                fn($g) => $g->map(fn($a) => [
                    'title' => $a->title ?? $a->file_name,
                    'path'  => $a->file_path,
                    'type'  => $a->type,
                ])
            ),

            'details' => $project->details->map(fn($d) => [
                'cluster'      => $d->cluster->name ?? '-',
                'stage'        => $d->stage->name ?? '-',
                'plan_start'   => $d->plan_start?->format('d M Y'),
                'plan_end'     => $d->plan_end?->format('d M Y'),
                'actual_start' => $d->actual_start?->format('d M Y'),
                'actual_end'   => $d->actual_end?->format('d M Y'),
                'notes'        => $d->notes,
            ]),

            'pics_internal' => $project->pics->where('type', 'internal')->values()->map(fn($p) => [
                'pic'      => $p->pic,
                'position' => $p->position,
                'phone'    => $p->phone,
                'email'    => $p->email,
            ]),

            'pics_customer' => $project->pics->where('type', 'customer')->values()->map(fn($p) => [
                'pic'      => $p->pic,
                'position' => $p->position,
                'phone'    => $p->phone,
                'email'    => $p->email,
            ]),

            'access_users' => $accessUsers->map(fn($u) => [
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role->name ?? '-',
                'access_group' => $u->accessGroup->name ?? '-',
            ]),

            'budget_plans' => $budgetPlans->map(fn($bp) => [
                'code'             => $bp->code,
                'description'      => $bp->description,
                'status'           => $bp->status,
                'status_color'     => $bp->status_color,
                'amount'           => (int) ($bp->amount ?? 0),
                'amount_formatted' => $bp->amount_formatted,
                // Terpakai oleh Cost Request (VERIFIED s/d CLOSED) — untuk kolom Used & Var
                'used'             => (int) $bp->usedAmount(),
            ]),

            'logs' => $project->logs->map(fn($l) => [
                'action_label' => $l->action_label,
                'action_color' => $l->action_color,
                'status_from'  => $l->status_from,
                'status_to'    => $l->status_to,
                'actor'        => $l->user_name ?? '-',
                'role'         => $l->role ?? '-',
                'notes'        => $l->notes,
                'date'         => $l->created_at->format('d M Y H:i'),
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $project   = Project::with(['attachments', 'pics'])->findOrFail($id);
        $user      = Auth::user();
        $role      = $user->role->name ?? '';

        // Head hanya bisa edit DRAFT
        if (in_array($role, ['head']) && !$project->isEditableByHead()) {
            return redirect('/project')
                ->with('error', 'PO hanya dapat diedit oleh Head saat berstatus DRAFT.');
        }

        // Finance bisa edit DRAFT, SUBMITTED, WAITING_PO
        if (in_array($role, ['finance']) && !$project->isEditableByFinance()) {
            return redirect('/project')
                ->with('error', 'PO tidak dapat diedit pada status ini.');
        }

        // ACTIVE & CLOSED tidak bisa diedit siapapun
        if (in_array($project->status, ['ACTIVE', 'CLOSED']) && !in_array($role, ['superadmin'])) {
            return redirect('/project')
                ->with('error', 'PO berstatus ACTIVE atau CLOSED tidak dapat diedit.');
        }

        $customers    = Customer::where('status', 'ACTIVE')->orderBy('name')->get();
        $clusters     = \App\Models\Cluster::orderBy('name')->get();
        $stages       = \App\Models\Stage::orderBy('name')->get();
        $picsInternal = MasterPic::where('type', 'internal')->where('status', 'ACTIVE')->orderBy('pic')->get();
        $picsCustomer = MasterPic::where('type', 'customer')->where('status', 'ACTIVE')->orderBy('pic')->get();

        return view('project.edit', compact('project', 'customers', 'clusters', 'stages', 'picsInternal', 'picsCustomer'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $user    = Auth::user();
        $role    = $user->role->name ?? '';

        // Cek akses edit
        if (in_array($role, ['head']) && !$project->isEditableByHead()) {
            return redirect('/project')->with('error', 'Tidak dapat mengedit PO pada status ini.');
        }

        $request->validate([
            'name'                   => 'required|string|max:255',
            'customer_id'            => 'nullable|exists:customers,id',
            'value'                  => 'nullable',
            'term_of_payment'         => 'nullable|string|max:255',
            'done_by'                => 'nullable|string|max:255',
            'po_number'              => 'nullable|string|max:255',
            'notes'                  => 'nullable|string',
            'attachments'            => 'nullable|array',
            'attachments.*.title'    => 'nullable|string|max:255',
            'attachments.*.file'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'delete_attachments'     => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $project) {
            $project->update([
                'name'           => strtoupper(trim($request->name)),
                'customer_id'    => $request->customer_id,
                'value'          => $this->normalizeMoney($request->value),
                'term_of_payment'     => $request->term_of_payment,
                'done_by'        => $request->done_by,
                'po_number'      => $request->po_number,
                'notes'          => $request->notes,
            ]);

            // Hapus attachment
            $deleteIds = (array) $request->input('delete_attachments', []);
            if (!empty($deleteIds)) {
                $toDelete = ProjectAttachment::where('project_id', $project->id)
                    ->whereIn('id', $deleteIds)->get();
                foreach ($toDelete as $att) {
                    Storage::disk('public')->delete($att->file_path);
                    $att->delete();
                }
            }

            $this->syncDetails($project, $request);
            $this->syncPics($project, $request);
            $this->storeAttachments($project, $request, 'general');
        });

        return redirect('/project')->with('success', 'PO berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT — DRAFT → SUBMITTED
    |--------------------------------------------------------------------------
    */

    public function submit($id)
    {
        $project = Project::findOrFail($id);

        if ($project->status !== 'DRAFT') {
            return back()->with('error', 'Hanya PO berstatus DRAFT yang dapat disubmit.');
        }

        $project->update([
            'status'       => 'SUBMITTED',
            'submitted_at' => now(),
        ]);
        $project->addLog('DRAFT', 'SUBMITTED', 'submitted');

        return redirect('/project')->with('success', 'PO berhasil diajukan ke Finance.');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE APPROVE — SUBMITTED → WAITING_PO atau ACTIVE
    | Finance memilih via dropdown
    |--------------------------------------------------------------------------
    */

    public function financeApprove(Request $request, $id)
    {
        $request->validate([
            'approval_type' => 'required|in:WAITING_PO,ACTIVE',
            'po_number'     => 'required_if:approval_type,ACTIVE|nullable|string|max:255',
            'notes'         => 'nullable|string|max:500',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'SUBMITTED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $newStatus = $request->approval_type;
        $action    = $newStatus === 'WAITING_PO' ? 'approved_waiting' : 'approved_active';

        $updateData = [
            'status'      => $newStatus,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ];

        if ($newStatus === 'ACTIVE' && $request->po_number) {
            $updateData['po_number'] = $request->po_number;
        }

        $project->update($updateData);
        $project->addLog('SUBMITTED', $newStatus, $action, $request->notes);

        $msg = $newStatus === 'WAITING_PO'
            ? 'PO disetujui dengan status Waiting PO.'
            : 'PO disetujui dan sekarang ACTIVE.';

        return redirect('/project')->with('success', $msg);
    }

    /*
    |--------------------------------------------------------------------------
    | WAITING PO → ACTIVE — Finance input no PO
    |--------------------------------------------------------------------------
    */

    public function activateFromWaiting(Request $request, $id)
    {
        $request->validate([
            'po_number' => 'required|string|max:255',
            'notes'     => 'nullable|string|max:500',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'WAITING_PO') {
            return back()->with('error', 'Hanya PO berstatus WAITING_PO yang dapat diaktifkan.');
        }

        $project->update([
            'status'    => 'ACTIVE',
            'po_number' => $request->po_number,
        ]);
        $project->addLog('WAITING_PO', 'ACTIVE', 'waiting_to_active', $request->notes);

        return redirect('/project')->with('success', 'PO berhasil diaktifkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVATE FORM — WAITING_PO → ACTIVE (form edit lengkap oleh Finance)
    |--------------------------------------------------------------------------
    */

    public function activateForm($id)
    {
        $project = Project::with(['attachments'])->findOrFail($id);
        $role    = Auth::user()->role->name ?? '';

        if (!in_array($role, ['superadmin', 'finance'])) {
            return redirect('/project')
                ->with('error', 'Hanya Finance yang dapat mengaktifkan PO.');
        }

        if ($project->status !== 'WAITING_PO') {
            return redirect('/project')
                ->with('error', 'Hanya PO berstatus WAITING_PO yang dapat diaktifkan.');
        }

        $customers = Customer::where('status', 'ACTIVE')->orderBy('name')->get();
        $clusters  = \App\Models\Cluster::orderBy('name')->get();
        $stages    = \App\Models\Stage::orderBy('name')->get();

        return view('project.activate', compact('project', 'customers', 'clusters', 'stages'));
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVATE — simpan perubahan + ubah status WAITING_PO → ACTIVE
    |--------------------------------------------------------------------------
    */

    public function activate(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $role    = Auth::user()->role->name ?? '';

        if (!in_array($role, ['superadmin', 'finance'])) {
            return redirect('/project')
                ->with('error', 'Hanya Finance yang dapat mengaktifkan PO.');
        }

        if ($project->status !== 'WAITING_PO') {
            return redirect('/project')
                ->with('error', 'Hanya PO berstatus WAITING_PO yang dapat diaktifkan.');
        }

        $request->validate([
            'name'               => 'required|string|max:255',
            'customer_id'        => 'nullable|exists:customers,id',
            'value'              => 'nullable',
            'term_of_payment'    => 'nullable|string|max:255',
            'done_by'            => 'nullable|string|max:255',
            'po_number'          => 'required|string|max:255',   // wajib untuk aktivasi
            'notes'              => 'nullable|string',
            'activation_notes'   => 'nullable|string|max:500',
            'attachments'        => 'nullable|array',
            'attachments.*.title' => 'nullable|string|max:255',
            'attachments.*.file'  => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'delete_attachments' => 'nullable|array',
        ]);

        DB::transaction(function () use ($request, $project) {

            // 1) Simpan seluruh perubahan (sama seperti update biasa)
            $project->update([
                'name'            => strtoupper(trim($request->name)),
                'customer_id'     => $request->customer_id,
                'value'           => $this->normalizeMoney($request->value),
                'term_of_payment' => $request->term_of_payment,
                'done_by'         => $request->done_by,
                'po_number'       => $request->po_number,
                'notes'           => $request->notes,
            ]);

            // Hapus attachment yang dicentang
            $deleteIds = (array) $request->input('delete_attachments', []);
            if (!empty($deleteIds)) {
                $toDelete = ProjectAttachment::where('project_id', $project->id)
                    ->whereIn('id', $deleteIds)->get();
                foreach ($toDelete as $att) {
                    Storage::disk('public')->delete($att->file_path);
                    $att->delete();
                }
            }

            $this->syncDetails($project, $request);
            $this->syncPics($project, $request);
            $this->storeAttachments($project, $request, 'general');

            // 2) Baru ubah status menjadi ACTIVE
            $project->update(['status' => 'ACTIVE']);
            $project->addLog('WAITING_PO', 'ACTIVE', 'waiting_to_active', $request->activation_notes);
        });

        return redirect('/project')
            ->with('success', 'PO berhasil diperbarui dan diaktifkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE REVISE — SUBMITTED → DRAFT
    |--------------------------------------------------------------------------
    */

    public function financeRevise(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        $project = Project::findOrFail($id);

        if ($project->status !== 'SUBMITTED') {
            return back()->with('error', 'Status tidak valid.');
        }

        $project->update(['status' => 'DRAFT']);
        $project->addLog('SUBMITTED', 'DRAFT', 'revised', $request->notes);

        return redirect('/project')->with('success', 'PO dikembalikan ke Head untuk direvisi.');
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE — ACTIVE → CLOSED (Finance, upload MoM)
    |--------------------------------------------------------------------------
    */

    public function close(Request $request, $id)
    {
        $request->validate([
            'closing_notes'          => 'nullable|string|max:1000',
            'closing_attachments'          => 'required|array|min:1',
            'closing_attachments.*.title'  => 'required|string|max:255',
            'closing_attachments.*.file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'ACTIVE') {
            return back()->with('error', 'Hanya PO berstatus ACTIVE yang dapat ditutup.');
        }

        DB::transaction(function () use ($request, $project) {
            $project->update([
                'status'        => 'CLOSED',
                'closed_by'     => Auth::id(),
                'closed_at'     => now(),
                'closing_notes' => $request->closing_notes,
            ]);

            $this->storeAttachments($project, $request, 'closing');
            $project->addLog('ACTIVE', 'CLOSED', 'closed', $request->closing_notes);
        });

        return redirect('/project')->with('success', 'PO berhasil ditutup.');
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT BAST — ACTIVE → PROGRESS_DONE (head)
    | Upload BAST + isi no/tanggal/nilai BAST
    |--------------------------------------------------------------------------
    */

    public function submitBast(Request $request, $id)
    {
        $request->validate([
            'no_bast'          => 'required|string|max:255',
            'tanggal_bast'     => 'required|date',
            'nilai_bast'       => 'required',
            'bast_notes'       => 'nullable|string|max:1000',
            'bast_attachments'          => 'required|array|min:1',
            'bast_attachments.*.title'  => 'required|string|max:255',
            'bast_attachments.*.file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'ACTIVE') {
            return back()->with('error', 'Hanya PO berstatus ACTIVE yang dapat disubmit BAST.');
        }

        DB::transaction(function () use ($request, $project) {
            $project->update([
                'status'            => 'PROGRESS_DONE',
                'no_bast'           => $request->no_bast,
                'tanggal_bast'      => $request->tanggal_bast,
                'nilai_bast'        => $this->normalizeMoney($request->nilai_bast),
                'bast_notes'        => $request->bast_notes,
                'bast_submitted_by' => Auth::id(),
                'bast_submitted_at' => now(),
            ]);

            $fileRows  = $request->file('bast_attachments', []);
            $inputRows = $request->input('bast_attachments', []);

            foreach ($fileRows as $i => $row) {
                $file = $row['file'] ?? null;
                if (!$file || !$file->isValid()) continue;

                $title = $inputRows[$i]['title'] ?? $file->getClientOriginalName();

                $path = $file->store('project_attachments', 'public');
                $project->attachments()->create([
                    'type'      => 'bast',
                    'title'     => strtoupper($title),
                    'file_path' => $path,
                    'file_name' => basename($path),
                    'file_type' => $file->getClientMimeType(),
                ]);
            }

            $project->addLog('ACTIVE', 'PROGRESS_DONE', 'bast_submitted', $request->bast_notes);
        });

        return redirect('/project')->with('success', 'BAST berhasil disubmit. Status PO menjadi Progress Done.');
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT CLOSING — PAID → CLOSING (head upload MoM)
    |--------------------------------------------------------------------------
    */

    public function submitClosing(Request $request, $id)
    {
        $request->validate([
            'closing_notes'          => 'nullable|string|max:1000',
            'closing_attachments'          => 'required|array|min:1',
            'closing_attachments.*.title'  => 'required|string|max:255',
            'closing_attachments.*.file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'PAID') {
            return back()->with('error', 'Hanya PO berstatus PAID yang dapat diajukan closing.');
        }

        DB::transaction(function () use ($request, $project) {
            $project->update([
                'status'        => 'CLOSING',
                'closing_notes' => $request->closing_notes,
            ]);

            $this->storeAttachments($project, $request, 'closing');

            $project->addLog('PAID', 'CLOSING', 'closing_submitted', $request->closing_notes);
        });

        return redirect('/project')->with('success', 'MoM Closing berhasil diajukan ke Finance.');
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY CLOSING — CLOSING → CLOSED (finance approve)
    |--------------------------------------------------------------------------
    */

    public function verifyClosing(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        if ($project->status !== 'CLOSING') {
            return back()->with('error', 'Status tidak valid.');
        }

        $project->update([
            'status'    => 'CLOSED',
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);
        $project->addLog('CLOSING', 'CLOSED', 'closing_approved', $request->notes);

        return redirect('/project')->with('success', 'PO berhasil ditutup.');
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT CLOSING — CLOSING → PAID (finance tolak MoM)
    |--------------------------------------------------------------------------
    */

    public function rejectClosing(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:500']);

        $project = Project::findOrFail($id);

        if ($project->status !== 'CLOSING') {
            return back()->with('error', 'Status tidak valid.');
        }

        $project->update(['status' => 'PAID']);
        $project->addLog('CLOSING', 'PAID', 'closing_rejected', $request->notes);

        return redirect('/project')->with('success', 'MoM Closing ditolak. PO dikembalikan ke status PAID.');
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT EXCEL — Project & Milestone
    |--------------------------------------------------------------------------
    | Sama polanya dengan import Detail Budget: import TIDAK langsung simpan ke
    | database, cuma parsing & balikin JSON — JS di form yang mengisi ulang
    | tabel Project & Milestone. Simpan tetap lewat tombol "Simpan" biasa.
    */

    private const DETAIL_IMPORT_HEADERS = ['Project', 'Milestone', 'Plan Start (dd-mm-yyyy)', 'Plan Finish (dd-mm-yyyy)'];

    /**
     * GET /project/import-template
     */
    public function downloadDetailTemplate()
    {
        $clusters = Cluster::orderBy('name')->get(['id', 'name']);
        $stages   = Stage::orderBy('name')->get(['id', 'name']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Project & Milestone');

        foreach (self::DETAIL_IMPORT_HEADERS as $i => $label) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $sheet->setCellValue('A2', $clusters->first()->name ?? 'NAMA PROJECT');
        $sheet->setCellValue('B2', $stages->first()->name ?? 'NAMA MILESTONE');
        $sheet->setCellValue('C2', now()->format('d-m-Y'));
        $sheet->setCellValue('D2', now()->addDays(7)->format('d-m-Y'));

        foreach (['A' => 32, 'B' => 26, 'C' => 22, 'D' => 22] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Sheet referensi untuk dropdown
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi');
        $refSheet->setCellValue('A1', 'Daftar Project Valid');
        $refSheet->setCellValue('B1', 'Daftar Milestone Valid');
        $refSheet->getStyle('A1:B1')->getFont()->setBold(true);

        foreach ($clusters->values() as $i => $c) {
            $refSheet->setCellValue('A' . ($i + 2), $c->name);
        }
        foreach ($stages->values() as $i => $s) {
            $refSheet->setCellValue('B' . ($i + 2), $s->name);
        }
        $refSheet->getColumnDimension('A')->setWidth(32);
        $refSheet->getColumnDimension('B')->setWidth(26);

        $clusterCount = max($clusters->count(), 1);
        $stageCount   = max($stages->count(), 1);

        for ($row = 2; $row <= 200; $row++) {
            $vCluster = $sheet->getCell('A' . $row)->getDataValidation();
            $vCluster->setType(DataValidation::TYPE_LIST);
            $vCluster->setErrorStyle(DataValidation::STYLE_WARNING);
            $vCluster->setAllowBlank(true);
            $vCluster->setShowInputMessage(true);
            $vCluster->setShowErrorMessage(true);
            $vCluster->setShowDropDown(false);
            $vCluster->setPromptTitle('Pilih Project');
            $vCluster->setPrompt('Pilih dari daftar di sheet Referensi.');
            $vCluster->setFormula1("Referensi!\$A\$2:\$A\$" . ($clusterCount + 1));

            $vStage = $sheet->getCell('B' . $row)->getDataValidation();
            $vStage->setType(DataValidation::TYPE_LIST);
            $vStage->setErrorStyle(DataValidation::STYLE_WARNING);
            $vStage->setAllowBlank(true);
            $vStage->setShowInputMessage(true);
            $vStage->setShowErrorMessage(true);
            $vStage->setShowDropDown(false);
            $vStage->setPromptTitle('Pilih Milestone');
            $vStage->setPrompt('Pilih dari daftar di sheet Referensi.');
            $vStage->setFormula1("Referensi!\$B\$2:\$B\$" . ($stageCount + 1));
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'template-project-milestone-' . now()->format('Ymd-His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * POST /project/import-preview
     */
    public function importDetailPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        $clusters = Cluster::orderBy('name')->get()->keyBy(fn($c) => strtoupper(trim($c->name)));
        $stages   = Stage::orderBy('name')->get()->keyBy(fn($s) => strtoupper(trim($s->name)));

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getSheetByName('Project & Milestone') ?? $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return response()->json([
                'rows'   => [],
                'errors' => ['File tidak dapat dibaca: ' . $e->getMessage()],
            ], 422);
        }

        $result = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) continue; // header

            [$clusterName, $stageName, $planStart, $planEnd] = array_pad($row, 4, null);

            if (blank($clusterName) && blank($stageName) && blank($planStart) && blank($planEnd)) {
                continue;
            }

            $rowNum = $i + 1;

            $clusterKey = strtoupper(trim((string) $clusterName));
            $stageKey   = strtoupper(trim((string) $stageName));
            $cluster    = $clusters->get($clusterKey);
            $stage      = $stages->get($stageKey);

            if (!$cluster && filled($clusterName)) {
                $errors[] = "Baris {$rowNum}: Project \"{$clusterName}\" tidak ditemukan.";
            }
            if (!$stage && filled($stageName)) {
                $errors[] = "Baris {$rowNum}: Milestone \"{$stageName}\" tidak ditemukan.";
            }

            $result[] = [
                'row'          => $rowNum,
                'cluster_id'   => $cluster->id ?? null,
                'cluster_name' => $cluster->name ?? $clusterName,
                'stage_id'     => $stage->id ?? null,
                'stage_name'   => $stage->name ?? $stageName,
                'plan_start'   => $this->parseImportDate($planStart),
                'plan_end'     => $this->parseImportDate($planEnd),
            ];
        }

        if (empty($result) && empty($errors)) {
            $errors[] = 'Tidak ada baris data yang terbaca dari file. Pastikan mengisi mulai baris ke-2.';
        }

        return response()->json([
            'rows'   => $result,
            'errors' => $errors,
        ]);
    }

    /**
     * Parsing tanggal fleksibel dari Excel: bisa berupa Excel date serial (angka)
     * atau string dd-mm-yyyy / yyyy-mm-dd. Balikin format Y-m-d untuk <input type="date">.
     */
    private function parseImportDate($value): ?string
    {
        if (blank($value)) return null;

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        $value = trim((string) $value);
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $value);
            if ($d) return $d->format('Y-m-d');
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY — hanya DRAFT
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $project = Project::with('attachments')->findOrFail($id);

        if ($project->status !== 'DRAFT') {
            return redirect('/project')
                ->with('error', 'Hanya PO berstatus DRAFT yang dapat dihapus.');
        }

        DB::transaction(function () use ($project) {
            foreach ($project->attachments as $att) {
                Storage::disk('public')->delete($att->file_path);
            }
            $project->delete();
        });

        return redirect('/project')->with('success', 'PO berhasil dihapus.');
    }
}
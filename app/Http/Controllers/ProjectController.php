<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use App\Models\BudgetPlan;
use App\Models\BudgetRequest;
use App\Models\BudgetPlanDetail;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\UserActivityLog;
use App\Models\Customer;
use App\Models\MasterPic;
use App\Models\Cluster;
use App\Models\Stage;
use App\Models\User;
use App\Services\NotifyService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Services\RoleDirectory;
use App\Support\AccessRoles;

class ProjectController extends Controller
{
    use \App\Http\Controllers\Concerns\CapsLargeLists;
    // Dipakai untuk membuat Budget Plan dari bagian Budget form PO (generateCode,
    // syncBudgetDetails, getAccountsFor). normalizeMoney() & syncDetails() di bawah
    // milik controller ini dan menang atas versi trait dengan nama yang sama.
    use \App\Http\Controllers\Concerns\BuildsBudgetPlan;

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

    /*
    |--------------------------------------------------------------------------
    | BUDGET PLAN YANG IKUT FORM PO (via_po_approval)
    |--------------------------------------------------------------------------
    | Keputusan 22 Okt 2026: Head mengisi satu Budget Plan langsung di form PO.
    | Status BP tidak berjalan di workflow Budget Plan sendiri, melainkan ikut
    | approval PO: submit -> CHECKED, verify -> VERIFIED, approve -> ACTIVE,
    | finance-revise -> DRAFT. Budget bersifat opsional.
    */

    /** Aturan validasi blok budget.* (semua opsional; kelengkapan dicek di budgetRowsOrFail()). */
    private function budgetRules(): array
    {
        return [
            'budget'                      => 'nullable|array',
            'budget.description'          => 'nullable|string|max:500',
            'budget.notes'                => 'nullable|string',
            'budget.hapus'                => 'nullable|boolean',
            'budget.details'              => 'nullable|array',
            'budget.details.*.account_id' => 'nullable|exists:accounts,id',
            'budget.details.*.deskripsi'  => 'nullable|string|max:255',
            'budget.details.*.amount'     => 'nullable|string|max:30',
            'budget.details.*.notes'      => 'nullable|string|max:1000',
            'budget.details.*.qty'        => 'nullable|numeric|min:0.01',
            'budget.details.*.unit'       => 'nullable|in:' . implode(',', BudgetPlanDetail::UNITS),
        ];
    }

    /** Hanya Head & superadmin yang bagian Budget-nya diproses (AccessRoles::PO_BUDGET_BUNDLE). */
    private function canFillPoBudget(): bool
    {
        return Auth::user()->hasRole(AccessRoles::PO_BUDGET_BUNDLE);
    }

    /**
     * Isi bagian Budget dari request: null bila tidak diisi sama sekali (budget
     * opsional), atau ['description','notes','rows'] bila diisi.
     * Bila diisi, deskripsi dan minimal satu baris bernominal wajib ada.
     */
    private function budgetRowsOrFail(Request $request): ?array
    {
        $in   = (array) $request->input('budget', []);
        $rows = collect((array) ($in['details'] ?? []))
            ->filter(fn ($r) => $this->normalizeMoney($r['amount'] ?? null) > 0)
            ->values()
            ->all();

        $desc = trim((string) ($in['description'] ?? ''));

        if ($desc === '' && empty($rows)) {
            return null;
        }

        $errors = [];
        if ($desc === '')   $errors['budget.description'] = 'Deskripsi budget wajib diisi bila bagian Budget dipakai.';
        if (empty($rows))   $errors['budget.details']     = 'Detail budget minimal 1 baris dengan harga satuan.';
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        return [
            'description' => strtoupper($desc),
            'notes'       => $in['notes'] ?? null,
            'rows'        => $rows,
        ];
    }

    /** GET project/budget-import-template - template Detail Budget; sumber dana terkunci PROJECT. */
    public function downloadBudgetTemplate()
    {
        return $this->budgetImportTemplateResponse('PROJECT');
    }

    /** POST project/budget-import-preview - hanya parsing, tidak menyimpan; sumber dana terkunci PROJECT. */
    public function importBudgetPreview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120']);

        return $this->budgetImportPreviewResponse($request, 'PROJECT');
    }

    /** Buat BP DRAFT bundled untuk PO ini (dipanggil di dalam transaksi PO). */
    private function createBundledBudgetPlan(Project $project, array $budget): void
    {
        $bp = BudgetPlan::create([
            'code'            => $this->generateCode($project),
            'project_id'      => $project->id,
            'cost_center_id'  => null,
            'description'     => $budget['description'],
            'type'            => 'one_time',
            'amount'          => 0,
            'status'          => 'DRAFT',
            'notes'           => $budget['notes'],
            'input_by'        => Auth::id(),
            'via_po_approval' => true,
        ]);

        $this->syncBudgetDetails($bp, $budget['rows']);
        $bp->addLog('DRAFT', 'DRAFT', 'created', 'Dibuat dari form PO ' . $project->code . '.');
    }

    /**
     * Budget Plan sudah dirujuk dokumen lain (Cost Request termasuk yang di-soft-delete,
     * pengajuan Trouble Ticket, rencana biaya tiket, log Project Monitoring, atau Payroll Run)?
     * Bila ya, tidak boleh dihapus. FK RESTRICT (Cost Request, pengajuan TT) jadi jaring
     * terakhir; FK log Monitoring & Payroll Run adalah nullOnDelete (referensi diam-diam
     * di-NULL-kan) dan trouble_tickets tanpa FK, jadi ketiganya wajib dicek eksplisit.
     */
    private function budgetDipakaiDokumenLain(BudgetPlan $bp): bool
    {
        if (BudgetRequest::withTrashed()->where('budget_plan_id', $bp->id)->exists()) {
            return true;
        }

        if (Schema::hasTable('trouble_ticket_requests')
            && DB::table('trouble_ticket_requests')->where('budget_plan_id', $bp->id)->exists()) {
            return true;
        }

        foreach (['project_monitoring_logs', 'payroll_runs'] as $tabel) {
            if (Schema::hasTable($tabel) && Schema::hasColumn($tabel, 'budget_plan_id')
                && DB::table($tabel)->where('budget_plan_id', $bp->id)->exists()) {
                return true;
            }
        }

        return Schema::hasColumn('trouble_tickets', 'budget_plan_id')
            && DB::table('trouble_tickets')->where('budget_plan_id', $bp->id)->exists();
    }

    /**
     * Budget Plan bundled ikut pindah status bersama PO. Hanya BP yang sedang
     * di status $from yang dipindah (BP yang sudah ACTIVE karena Early Start
     * dibuka lagi tidak ikut tersentuh). Dipanggil di dalam transaksi PO.
     */
    private function moveBundledBudgetPlans(Project $project, array $from, string $to, string $action, ?string $notes = null, array $extra = []): void
    {
        $project->bundledBudgetPlans()
            ->whereIn('status', $from)
            ->get()
            ->each(function (BudgetPlan $bp) use ($to, $action, $notes, $extra) {
                $dari = $bp->status;
                $bp->update(array_merge(['status' => $to], $extra));
                $bp->addLog($dari, $to, $action, $notes);
            });
    }

    /**
     * Peran yang melihat SELURUH PO, tanpa dibatasi Access Group.
     *
     * Daftarnya di App\Support\AccessRoles::PO_FULL_SCOPE — bukan literal di
     * sini — supaya halaman daftar, detail JSON, dan ekspor tidak bisa
     * berbeda pendapat tentang siapa yang berhak.
     */
    private function hasFullPoScope(): bool
    {
        return Auth::user()->hasRole(AccessRoles::PO_FULL_SCOPE);
    }

    /**
     * ID PO yang boleh dilihat user saat ini (null = tidak dibatasi).
     *
     * Bentuknya disamakan dengan ProjectMonitoringController::accessibleProjectIds()
     * supaya kedua modul menyaring dengan cara yang sama persis.
     */
    private function accessiblePoIds(): ?array
    {
        if ($this->hasFullPoScope()) {
            return null;
        }

        return Auth::user()->accessibleProjectIds()->all();
    }

    /** Boleh melihat kolom bernilai uang pada PO? */
    private function canSeePoValue(): bool
    {
        return Auth::user()->hasRole(AccessRoles::PO_VALUE);
    }

    /**
     * Apakah user boleh melihat detail JSON satu PO.
     *
     * Sebelum 26 Sep 2026 method ini praktis mengizinkan semua orang: daftar
     * peran literalnya memuat 'guest', dan cabang kedua meloloskan siapa pun
     * yang punya menu list-po — sementara halaman list-po sendiri tidak
     * menyaring baris sama sekali. Hasilnya nilai kontrak, Gross Profit dan
     * Net Profit setiap PO terbuka untuk hampir seluruh peran, membatalkan
     * aturan privasi yang dijaga ketat di Finance Summary.
     *
     * Sekarang: peran ber-scope penuh boleh semua; sisanya hanya PO yang ada
     * di Access Group-nya — aturan yang sama dengan daftarnya, jadi tidak ada
     * baris yang bisa dibuka lewat URL tapi tidak tampil di tabel.
     */
    private function canViewProjectJson(Project $project): bool
    {
        if ($this->hasFullPoScope()) {
            return true;
        }

        return Auth::user()->accessibleProjectIds()->contains($project->id);
    }

    /**
     * Kandidat Manager PO — user dengan role manager / head (case-insensitive).
     */
    private function managerOptions()
    {
        return User::whereHas('role', fn ($r) => $r->whereRaw("LOWER(name) IN ('manager', 'head')"))
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Milestone disimpan dengan UPSERT BY ID, bukan hapus-lalu-buat-ulang.
     *
     * Versi lama menjalankan $project->details()->delete() tiap kali form Edit
     * PO disimpan — termasuk saat yang diubah cuma Nilai Kontrak. Baris yang
     * "sama" lahir kembali dengan id baru, dan itu menghancurkan semua yang
     * menempel pada id tersebut:
     *
     *  - project_monitoring_logs.project_detail_id (nullOnDelete) → NULL, jadi
     *    seluruh percakapan milestone terlepas dan tidak tampil di mana pun;
     *  - status, weight, submitted_done_by/at ikut ter-reset ke nilai default
     *    (hanya actual_start/actual_end yang dulu diselamatkan, itu pun lewat
     *    kunci cluster_id-stage_id yang menimpa dirinya sendiri kalau satu PO
     *    punya dua milestone dengan kombinasi sama).
     *
     * Sekarang baris lama di-update di tempat sehingga id-nya stabil, dan
     * kolom milik Project Monitoring (status/weight/actual/submitted_done_*)
     * sengaja TIDAK disentuh dari sini — form ini memang tidak mengeditnya.
     * Pola keepIds -> hapus sisa -> upsert menyalin
     * BudgetRequestController::syncDetails().
     */
    private function syncDetails(Project $project, Request $request): void
    {
        $rows = (array) $request->input('details', []);

        $existing = $project->details()->get()->keyBy('id');

        // Hanya id milik PO ini yang diakui — id dari PO lain diperlakukan
        // sebagai baris baru, bukan dipindah-tangankan.
        $keepIds = collect($rows)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $existing->has($id))
            ->all();

        // Sisanya = milestone yang memang dibuang user dari form.
        $project->details()->whereNotIn('id', $keepIds ?: [0])->delete();

        foreach ($rows as $row) {
            if (blank($row['cluster_id'] ?? null) && blank($row['stage_id'] ?? null)) continue;

            $payload = [
                'cluster_id' => ($row['cluster_id'] ?? null) ?: null,
                'stage_id'   => ($row['stage_id']   ?? null) ?: null,
                'plan_start' => ($row['plan_start'] ?? null) ?: null,
                'plan_end'   => ($row['plan_end']   ?? null) ?: null,
                'notes'      => $row['notes'] ?? null,
            ];

            $rowId  = (int) ($row['id'] ?? 0);
            $detail = $rowId && $existing->has($rowId) ? $existing->get($rowId) : null;

            if ($detail) {
                $detail->update($payload);
            } else {
                $project->details()->create($payload);
            }
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

    /**
     * Sinkron Access Group mana saja yang punya akses ke PO ini — dipilih
     * langsung dari form Create/Edit PO, bukan lewat menu Access Group lagi.
     * access_group_details bisa juga menyimpan baris cost_center (bukan project),
     * jadi di sini cuma menambah/menghapus baris project_id milik PO ini saja.
     */
    private function syncAccessGroups(Project $project, Request $request): void
    {
        $selectedIds = array_map('intval', (array) $request->input('access_group_ids', []));
        $currentIds  = $project->accessGroupLinks()->pluck('access_group_id')->all();

        $toAdd    = array_diff($selectedIds, $currentIds);
        $toRemove = array_diff($currentIds, $selectedIds);

        foreach ($toAdd as $accessGroupId) {
            \App\Models\AccessGroupDetail::create([
                'access_group_id' => $accessGroupId,
                'project_id'      => $project->id,
                'cost_center_id'  => null,
            ]);
        }

        if (!empty($toRemove)) {
            $project->accessGroupLinks()->whereIn('access_group_id', $toRemove)->delete();
        }
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

            $path = $file->store('project_attachments', 'secure');
            $project->attachments()->create([
                'type'      => $type,
                'title'     => strtoupper($title),
                'file_path' => $path,
                'file_name' => basename($path),
                'file_type' => $file->getClientMimeType(),
            ]);
        }
    }

    /**
     * Tambah baris No. PO baru (append-only — dipanggil dari form Create & Edit).
     * Status baris ada siklus hidupnya sendiri (RELEASED -> WORK_DONE -> INVOICED
     * -> PAID) yang diubah lewat endpoint terpisah dari show modal, jadi form
     * utama TIDAK pernah menghapus/menimpa baris yang sudah ada.
     */
    private function storePoNumbers(Project $project, Request $request): void
    {
        $fileRows  = $request->file('po_numbers', []);
        $inputRows = (array) $request->input('po_numbers', []);

        foreach ($inputRows as $i => $row) {
            if (blank($row['po_number'] ?? null)) continue;

            $poNumber = $project->poNumbers()->create([
                'po_number'    => strtoupper(trim($row['po_number'])),
                'nominal'      => $this->normalizeMoney($row['nominal'] ?? 0),
                'release_date' => $row['release_date'] ?: null,
                'status'       => 'RELEASED',
                'input_by'     => Auth::id(),
            ]);

            $file = $fileRows[$i]['file'] ?? null;
            if ($file && $file->isValid()) {
                $path = $file->store('project_attachments', 'secure');
                $poNumber->attachments()->create([
                    'project_id' => $project->id,
                    'type'       => 'po_number',
                    'title'      => strtoupper($poNumber->po_number),
                    'file_path'  => $path,
                    'file_name'  => basename($path),
                    'file_type'  => $file->getClientMimeType(),
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        /*
        | Penyaringan baris per Access Group.
        |
        | Sebelumnya daftar ini TIDAK menyaring apa pun: gerbangnya hanya
        | menu:list-po, sehingga setiap pemegang menu melihat seluruh PO
        | perusahaan. Dua modul lain memperlakukan data yang sama sebagai
        | terbatas — Project Monitoring membatasi ke accessibleProjectIds(),
        | dan Finance Summary bahkan menyembunyikan NILAI PO dari Manager
        | dengan komentar "// PRIVASI:". Halaman ini melewati keduanya.
        |
        | Sekarang ketiganya sepakat. Peran ber-scope penuh
        | (AccessRoles::PO_FULL_SCOPE) tidak terpengaruh sama sekali.
        */
        $accessibleIds = $this->accessiblePoIds();

        $query = Project::with(['customer', 'inputBy', 'manager', 'budgetPlans', 'poNumbers', 'invoices', 'details.stage'])
            ->when(is_array($accessibleIds), fn ($q) => $q->whereIn('id', $accessibleIds ?: [0]));

        /*
        | Ringkasan dihitung agregat supaya tetap benar walau daftar barisnya
        | dibatasi (lihat Concerns\CapsLargeLists). Angka stepper harus
        | mencerminkan seluruh data; kalau ikut dipotong, ia salah tanpa ada
        | tanda apa pun bahwa ia salah.
        */
        $statusCounts = (clone $query)->toBase()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        [$projects, $truncated, $total] = $this->cappedRows($query, 'id');
        $maxRows = $this->maxRows;

        // Angka keuangan per baris (Variable Cost, Payment, margin) dimuat
        // massal sekali — lihat Project::preloadFinancials().
        Project::preloadFinancials($projects);

        /*
        | Badge R/B/I/P (PO Received/BAST/Invoice/Payment) diselaraskan dengan
        | status milestone Project Monitoring saat ini, supaya tidak "nyangkut"
        | abu-abu kalau milestone-nya sudah Done tapi kolom header belum
        | ter-sync.
        |
        | Penyelarasan ini sekarang HANYA DIHITUNG, tidak disimpan. Versi lama
        | memanggil reconcileMonitoringStatuses() yang mengeluarkan UPDATE per
        | baris: membuka daftar PO — permintaan GET biasa — diam-diam mengubah
        | data, sekali per proyek yang nilainya berbeda, tanpa jejak audit dan
        | makin berat seiring bertambahnya proyek. Penulisan tetap terjadi di
        | tempatnya: saat status milestone benar-benar diubah di Project
        | Monitoring (ProjectMonitoringController::syncMonitoringHeaderStatus).
        */
        $projects->each(function ($p) {
            foreach ($p->monitoringStatusesAsComputed() as $kolom => $nilai) {
                // setAttribute tanpa save(): nilai benar untuk ditampilkan,
                // tidak ada yang tertulis ke basis data.
                $p->setAttribute($kolom, $nilai);
            }
        });

        $summary = [
            'total'         => (int) $statusCounts->sum(),
            'draft'         => (int) ($statusCounts['DRAFT'] ?? 0),
            'submitted'     => (int) ($statusCounts['SUBMITTED'] ?? 0),
            'verified'      => (int) ($statusCounts['VERIFIED'] ?? 0),
            'early_start'   => (int) ($statusCounts['EARLY_START'] ?? 0),
            'waiting_po'    => (int) ($statusCounts['WAITING_PO'] ?? 0),
            'active'        => (int) ($statusCounts['ACTIVE'] ?? 0),
            'progress_done' => (int) ($statusCounts['PROGRESS_DONE'] ?? 0),
            'partially_invoiced' => (int) ($statusCounts['PARTIALLY_INVOICED'] ?? 0),
            'partially_paid'     => (int) ($statusCounts['PARTIALLY_PAID'] ?? 0),
            'paid'          => (int) ($statusCounts['PAID'] ?? 0),
            'closing'       => (int) ($statusCounts['CLOSING'] ?? 0),
            'closed'        => (int) ($statusCounts['CLOSED'] ?? 0),
        ];

        // Kolom bernilai uang ditutup di VIEW untuk peran yang tidak berhak —
        // kolomnya tetap ada supaya indeks kolom, filter kolom dan ekspor
        // yang mengandalkan posisi tidak bergeser.
        $canSeePoValue = $this->canSeePoValue();

        // Kolom uang (beserta dropdown "Kolom") hanya dirender untuk peran
        // tertentu; sisanya cuma melihat Budget & Variable Cost.
        $bolehKolomFinansial = Auth::user()->hasRole(AccessRoles::PO_MONEY_COLUMNS);

        return view('project.index', compact('projects', 'summary', 'truncated', 'total', 'maxRows', 'canSeePoValue', 'bolehKolomFinansial'));
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT EXCEL — daftar PO (superadmin & approver, gerbang di route)
    |--------------------------------------------------------------------------
    */

    public function exportExcel(Request $request)
    {
        /*
         | Export mengikuti hasil filter yang sedang aktif di tabel: browser
         | mengirim id baris yang lolos (lihat public/js/tesys-export-filtered.js).
         | Tanpa 'ids' — diakses langsung, atau memang tidak ada filter aktif —
         | perilakunya persis seperti sebelumnya: seluruh data.
         */
        $ids = collect((array) $request->input('ids', []))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v)
            /*
            | Dibatasi jumlahnya. Daftar id datang dari peramban, jadi
            | panjangnya ditentukan klien; tanpa batas, satu permintaan bisa
            | menyusun klausa whereIn yang menembus max_allowed_packet MySQL
            | dan gagal dengan galat yang tidak menunjuk sebab sebenarnya.
            */
            ->take(20000);

        // Ekspor mengikuti scope yang sama dengan daftarnya. Route ini sudah
        // digerbang role:superadmin,approver — keduanya ber-scope penuh — tapi
        // scope-nya tetap diterapkan supaya penambahan peran di route tidak
        // diam-diam membuka seluruh PO lewat tombol Export.
        $accessibleIds = $this->accessiblePoIds();

        $projects = Project::with(['customer', 'manager', 'budgetPlans', 'poNumbers', 'invoices'])
            ->when(is_array($accessibleIds), fn ($q) => $q->whereIn('id', $accessibleIds ?: [0]))
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids))
            ->latest()->get();
        Project::preloadFinancials($projects);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Purchase Order');

        $headers = [
            'Code', 'Scope of Work', 'Status', 'Customer', 'Manager', 'Financing', 'Progress %',
            'PO Value', 'Contract', 'Budget', 'Variable Cost', 'Received Payment', 'Gross Profit',
            'Overhead', 'Fixed Cost', 'Net Profit', 'Tipe', 'PO Quantity',
        ];
        $cols = range('A', 'R');
        foreach ($headers as $i => $label) {
            $c = $cols[$i];
            $sheet->setCellValue($c . '1', $label);
            $sheet->getStyle($c . '1')->getFont()->setBold(true);
            $sheet->getStyle($c . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($c . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row = 2;
        foreach ($projects as $p) {
            $variable = $p->variableCostTotal();
            $payment  = $p->paymentTotal();
            $overhead = (int) ($p->overhead ?? 0);
            $fixed    = (int) ($p->fixed_cost ?? 0);
            $gross    = $payment - $variable;
            $net      = $gross - $overhead - $fixed;

            $sheet->setCellValue('A' . $row, $p->code);
            $sheet->setCellValue('B' . $row, $p->name);
            $sheet->setCellValue('C' . $row, $p->status_label);
            $sheet->setCellValue('D' . $row, $p->customer->name ?? '-');
            $sheet->setCellValue('E' . $row, strtoupper($p->pm_internal_name ?: '-'));
            $sheet->setCellValue('F' . $row, $p->financing_label);
            $sheet->setCellValue('G' . $row, $p->implementationProgress());
            $sheet->setCellValue('H' . $row, (int) ($p->value ?? 0));
            $sheet->setCellValue('I' . $row, (int) ($p->contract ?? 0));
            $sheet->setCellValue('J' . $row, $p->budgetPlanTotal());
            $sheet->setCellValue('K' . $row, $variable);
            $sheet->setCellValue('L' . $row, $payment);
            $sheet->setCellValue('M' . $row, $gross);
            $sheet->setCellValue('N' . $row, $overhead);
            $sheet->setCellValue('O' . $row, $fixed);
            $sheet->setCellValue('P' . $row, $net);
            $sheet->setCellValue('Q' . $row, $p->type_label ?? '-');
            $sheet->setCellValue('R' . $row, $p->po_quantity);
            $row++;
        }

        foreach ($cols as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $filename = 'purchase-order-' . now()->format('Ymd-His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
        $accessGroups  = \App\Models\AccessGroup::with(['users' => function ($q) {
            $q->whereHas('role', fn ($r) => $r->where('name', 'manager'))->orderBy('name');
        }])->orderBy('name')->get();
        $managers      = $this->managerOptions();

        // Bagian Budget (opsional) — hanya untuk Head/superadmin.
        $canFillBudget = $this->canFillPoBudget();
        $budgetAccounts = $canFillBudget ? $this->getAccountsFor('PROJECT') : collect();

        return view('project.create', compact('customers', 'picsInternal', 'picsCustomer', 'accessGroups', 'managers', 'canFillBudget', 'budgetAccounts'));
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
            'manager_id'     => 'nullable|exists:users,id',
            'po_quantity'    => 'nullable|integer|min:0',
            'contract'       => 'nullable',
            'restitusi'      => 'nullable',
            'overhead'       => 'nullable',
            'fixed_cost'     => 'nullable',
            'term_of_payment' => 'nullable|string|max:255',
            'financing'      => 'nullable|in:' . implode(',', Project::FINANCING_TYPES),
            'type'           => 'nullable|in:' . implode(',', array_keys(Project::TYPES)),
            'notes'          => 'nullable|string',
            'attachments'    => 'nullable|array',
            'attachments.*.title' => 'nullable|string|max:255',
            'attachments.*.file'  => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'po_numbers'                 => 'nullable|array',
            'po_numbers.*.po_number'     => 'nullable|string|max:255',
            'po_numbers.*.nominal'       => 'nullable',
            'po_numbers.*.release_date'  => 'nullable|date',
            'po_numbers.*.file'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'access_group_ids'   => 'nullable|array',
            'access_group_ids.*' => 'exists:access_groups,id',
        ] + $this->budgetRules());

        // Bagian Budget opsional; hanya Head/superadmin yang boleh mengisinya.
        $budget = $this->canFillPoBudget() ? $this->budgetRowsOrFail($request) : null;

        $code = Project::generateNextCode();

        DB::transaction(function () use ($request, $code, $budget) {
            $project = Project::create([
                'code'           => $code,
                'name'           => strtoupper(trim($request->name)),
                'customer_id'    => $request->customer_id,
                'manager_id'     => $request->manager_id,
                'po_quantity'    => $request->filled('po_quantity') ? (int) $request->po_quantity : null,
                'contract'       => $this->normalizeMoney($request->contract),
                'restitusi'      => $this->normalizeMoney($request->restitusi),
                'overhead'       => $this->normalizeMoney($request->overhead),
                'fixed_cost'     => $this->normalizeMoney($request->fixed_cost),
                'term_of_payment'     => $request->term_of_payment,
                'financing'      => $request->financing ?? 'NL',
                'type'           => $request->filled('type') ? $request->type : null,
                'notes'          => $request->notes,
                'status'         => 'DRAFT',
                'input_by'       => Auth::id(),
            ]);

            $this->syncDetails($project, $request);
            $this->syncPics($project, $request);
            $this->storeAttachments($project, $request, 'general');
            $this->storePoNumbers($project, $request);
            $this->syncAccessGroups($project, $request);
            $project->addLog('DRAFT', 'DRAFT', 'created');

            if ($budget) {
                $this->createBundledBudgetPlan($project, $budget);
            }
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
            'customer', 'inputBy', 'approvedBy', 'closedBy', 'manager',
            'attachments', 'logs',
            'details.cluster', 'details.stage',
            'pics',
            'invoices' => fn ($q) => $q->with('cashReceipts')->orderBy('invoice_date', 'desc'),
            'poNumbers.attachments',
            'poNumbers.basts.attachments',
            'poNumbers.basts.invoices',
            'poNumbers.basts.parent.attachments',
        ])->findOrFail($id);

        abort_unless($this->canViewProjectJson($project), 403);

        $rp = fn ($v) => number_format((int) $v, 0, ',', '.');

        // Cost Request (Budget Request) yang tertaut ke Budget Plan PO ini —
        // dipakai tab "Cost". Kalau pengajuan sudah ada realisasinya
        // (REALIZED/CLOSED + punya baris realisasi), Variable Cost pakai nilai
        // realisasi & selisihnya jadi Refund. Kalau belum ada realisasi,
        // Variable Cost pakai nilai pengajuan penuh (Refund 0).
        $costRequests = \App\Models\BudgetRequest::whereIn('budget_plan_id', $project->budgetPlans->pluck('id'))
            ->disbursed()
            ->with(['beneficiary:id,nickname', 'realizations'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($cr) {
                // P-01: refund baru nyata setelah settlement — REALIZED
                // (realisasi terinput, belum disetujui) masih refund 0.
                $cr->refund_calc = $cr->refundAmount();
                return $cr;
            });

        $totalRequest = (int) $costRequests->sum('amount');
        $totalRefund  = (int) $costRequests->sum('refund_calc');
        $variableCost = $totalRequest - $totalRefund;
        $paymentTotal = $project->paymentTotal();
        $overhead     = (int) ($project->overhead ?? 0);
        $fixedCost    = (int) ($project->fixed_cost ?? 0);
        $totalCost    = $variableCost + $overhead + $fixedCost;
        $grossProfit  = $paymentTotal - $variableCost;
        $netProfit    = $grossProfit - $overhead - $fixedCost;

        // Nilai Contract seharusnya = total BAST + Restitusi; bila tidak sama,
        // selisihnya ditampilkan di modal (hanya tampilan, tidak disimpan).
        $bastTotal      = (int) $project->poNumbers->sum('nilai_bast');
        $restitusi      = (int) ($project->restitusi ?? 0);
        $contractSelisih = (int) $project->contract - ($bastTotal + $restitusi);

        // Users with access to this project via access groups
        $accessUsers = \App\Models\User::whereHas('accessGroup.details', function($q) use ($project) {
            $q->where('project_id', $project->id);
        })->with('role')->get();

        // Budget plans linked to this project
        $budgetPlans = \App\Models\BudgetPlan::where('project_id', $project->id)
            ->withSum('details', 'amount')
            ->with('details.account')
            ->get();

        return response()->json([
            'id'              => $project->id,
            'code'            => $project->code,
            'name'            => $project->name,
            'po_number'       => $project->po_number,
            'financing'       => $project->financing,
            'financing_label' => $project->financing_label,
            'financing_color' => $project->financing_color,
            'type'            => $project->type,
            'type_label'      => $project->type_label,
            'type_color'      => $project->type_color,
            'value'           => $project->value,
            'value_formatted' => $project->value_formatted,
            'po_quantity'              => $project->po_quantity,
            'po_numbers_count'         => $project->poNumbers->count(),
            'po_adjustment_required'   => $project->poAdjustmentRequired(),
            'contract'            => $project->contract,
            'contract_formatted'  => $project->contract_formatted,
            'bast_total'               => $bastTotal,
            'bast_total_formatted'     => $rp($bastTotal),
            'restitusi'                => $restitusi,
            'restitusi_formatted'      => $rp($restitusi),
            'contract_selisih'         => $contractSelisih,
            'contract_selisih_formatted' => ($contractSelisih < 0 ? '-' : '') . $rp(abs($contractSelisih)),
            'overhead'            => $project->overhead,
            'overhead_formatted'  => $project->overhead_formatted,
            'fixed_cost'           => $project->fixed_cost,
            'fixed_cost_formatted' => $project->fixed_cost_formatted,
            'manager'            => $project->manager?->username ?? '-',
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

            'attachments' => $project->attachments->groupBy('type')->map(
                fn($g) => $g->map(fn($a) => [
                    'title'     => $a->title ?? $a->file_name,
                    'file_name' => $a->file_name,
                    'path'      => $a->file_path,
                    'type'      => $a->type,
                    'is_image'  => $a->isImage(),
                    'is_pdf'    => $a->isPdf(),
                ])
            ),

            'details' => $project->details->map(fn($d) => [
                'id'               => $d->id,
                'cluster'          => $d->cluster->name ?? '-',
                'stage'            => $d->stage->name ?? '-',
                'plan_start'       => $d->plan_start?->format('d M Y'),
                'plan_end'         => $d->plan_end?->format('d M Y'),
                'actual_start'     => $d->actual_start?->format('d M Y'),
                'actual_end'       => $d->actual_end?->format('d M Y'),
                'plan_start_raw'   => $d->plan_start?->format('Y-m-d'),
                'plan_end_raw'     => $d->plan_end?->format('Y-m-d'),
                'actual_start_raw' => $d->actual_start?->format('Y-m-d'),
                'actual_end_raw'   => $d->actual_end?->format('Y-m-d'),
                'status'           => $d->status,
                'status_label'     => $d->status_label,
                'status_color'     => $d->status_color,
                'notes'            => $d->notes,
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

            'access_groups' => $project->accessGroups()->pluck('name'),

            'access_users' => $accessUsers->map(fn($u) => [
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role->name ?? '-',
                'access_group' => $u->accessGroup->name ?? '-',
            ]),

            'budget_plans' => $budgetPlans->map(fn($bp) => [
                'id'               => $bp->id,
                'code'             => $bp->code,
                'description'      => $bp->description,
                'status'           => $bp->status,
                'status_color'     => $bp->status_color,
                'via_po_approval'  => $bp->isBundled(),
                'amount'           => (int) ($bp->amount ?? 0),
                'amount_formatted' => $bp->amount_formatted,
                // Terpakai oleh Cost Request (VERIFIED s/d CLOSED) — untuk kolom Used & Var
                'used'             => (int) $bp->usedAmount(),
                'details' => $bp->details->map(function ($d) use ($bp) {
                    $used      = $d->usedAmount();
                    $remaining = $d->remainingAmount();

                    $crDetails = collect();
                    if (\Illuminate\Support\Facades\Schema::hasColumn('request_detail', 'budget_detail_id')) {
                        $crDetails = $d->costRequestDetails()
                            ->whereHas('budgetRequest', fn ($q) => $q->disbursed())
                            ->with(['budgetRequest:id,code,description,beneficiary_id,created_at', 'budgetRequest.beneficiary:id,nickname', 'realization'])
                            ->get()
                            ->map(function ($rd) {
                                $amount = (int) ($rd->realization->total ?? $rd->total);
                                return [
                                    'cr_id'                  => $rd->budgetRequest->id ?? null,
                                    'cr_code'                => $rd->budgetRequest->code ?? '-',
                                    'cr_date_formatted'      => $rd->budgetRequest->created_at?->format('d M Y') ?? '-',
                                    'beneficiary'            => $rd->budgetRequest->beneficiary->nickname ?? '-',
                                    'notes'                  => $rd->notes ?: '-',
                                    'unit_price_formatted'   => number_format((int) $rd->amount, 0, ',', '.'),
                                    'qty'                    => (float) $rd->qty,
                                    'amount'                 => $amount,
                                    'amount_formatted'       => number_format($amount, 0, ',', '.'),
                                ];
                            })
                            ->values();
                    }

                    return [
                        'budget_plan_code'    => $bp->code,
                        'account'             => $d->account->name ?? '-',
                        'deskripsi'           => $d->deskripsi,
                        'amount_formatted'    => number_format($d->amount, 0, ',', '.'),
                        'qty'                 => (float) $d->qty,
                        'unit'                => $d->unit,
                        'total'               => $d->total,
                        'total_formatted'     => number_format($d->total, 0, ',', '.'),
                        'used'                => $used,
                        'used_formatted'      => number_format($used, 0, ',', '.'),
                        'remaining'           => $remaining,
                        'remaining_formatted' => number_format($remaining, 0, ',', '.'),
                        'cr_details'          => $crDetails,
                    ];
                }),
            ]),

            'po_numbers' => $project->poNumbers->map(fn($p) => [
                'id'                  => $p->id,
                'po_number'           => $p->po_number,
                'nominal'             => (int) $p->nominal,
                'nominal_formatted'   => $p->nominal_formatted,
                'release_date'        => $p->release_date?->format('d M Y'),
                'status'              => $p->status,
                'status_label'        => $p->status_label,
                'status_color'        => $p->status_color,
                'no_bast'             => $p->no_bast,
                'tanggal_bast'        => $p->tanggal_bast?->format('d M Y'),
                'tanggal_bast_raw'    => $p->tanggal_bast?->format('Y-m-d'),
                'nilai_bast_formatted' => $p->nilai_bast_formatted,
                'bast_notes'          => $p->bast_notes,
                'nilai_bast'          => (int) $p->nilai_bast,
                // Jumlah dari rincian BAST (bukan kolom agregat) — dipakai kolom BAST di tab No. PO.
                'bast_total'          => (int) $p->basts->sum('nilai_bast'),
                'bast_total_formatted' => number_format((int) $p->basts->sum('nilai_bast'), 0, ',', '.'),
                // Rincian BAST (detail dari No. PO = header).
                'basts'               => $p->basts->map(fn ($b) => [
                    'id'                   => $b->id,
                    // Pecahan BAST sintetis: nomor induk (induknya sudah soft-delete) dan pecahan/sisa/biasa.
                    'split_from_no'        => $b->parent?->no_bast,
                    'origin'               => $b->origin,
                    // Sudah ditagih = punya invoice aktif (CANCELLED tidak dihitung).
                    'is_billed'            => $b->invoices->where('status', '!=', 'CANCELLED')->isNotEmpty(),
                    'billed_invoices'      => $b->invoices->where('status', '!=', 'CANCELLED')->pluck('code')->values(),
                    'no_bast'              => $b->no_bast,
                    'tanggal_bast'         => $b->tanggal_bast?->format('d M Y'),
                    'nilai_bast'           => (int) $b->nilai_bast,
                    'nilai_bast_formatted' => number_format((int) $b->nilai_bast, 0, ',', '.'),
                    // Lampiran pecahan tetap menempel di induk (tidak dipindah): tampilkan lampiran induk bila pecahan tidak punya.
                    'attachments'          => ($b->attachments->isEmpty() && $b->parent ? $b->parent->attachments : $b->attachments)->map(fn ($a) => [
                        'title'    => $a->title ?? $a->file_name,
                        'path'     => $a->file_path,
                        'is_image' => $a->isImage(),
                        'is_pdf'   => $a->isPdf(),
                    ])->values(),
                ])->values(),
                'attachments'         => $p->attachments->where('type', '!=', 'po_number_bast')->values()->map(fn($a) => [
                    'title'    => $a->title ?? $a->file_name,
                    'path'     => $a->file_path,
                    'is_image' => $a->isImage(),
                    'is_pdf'   => $a->isPdf(),
                ]),
                'bast_attachments'    => $p->attachments->where('type', 'po_number_bast')->values()->map(fn($a) => [
                    'title'    => $a->title ?? $a->file_name,
                    'path'     => $a->file_path,
                    'is_image' => $a->isImage(),
                    'is_pdf'   => $a->isPdf(),
                ]),
            ]),

            'work_progress_status'    => $project->workProgressStatus(),
            'billing_progress_status' => $project->billingProgressStatus(),
            'payment_progress_status' => $project->paymentProgressStatus(),

            'invoices' => $project->invoices->map(function ($inv) use ($project, $rp) {
                $received = (int) $inv->cashReceipts->sum('amount');
                $lastReceipt = $inv->cashReceipts->sortByDesc('received_date')->first();
                $receivedDate = $lastReceipt && $lastReceipt->received_date
                    ? $lastReceipt->received_date->format('d M Y')
                    : null;
                $poNumber = $project->poNumbers->firstWhere('id', $inv->project_po_number_id);
                return [
                    'id'                => $inv->id,
                    'code'              => $inv->code,
                    'po_number_id'      => $inv->project_po_number_id,
                    'po_number'         => $poNumber->po_number ?? null,
                    'payment_term'      => $inv->payment_term_label,
                    'invoice_date'      => $inv->invoice_date?->format('d M Y'),
                    'due_date'          => $inv->due_date?->format('d M Y'),
                    'billed_amount'     => (int) $inv->billed_amount,
                    'billed_amount_formatted' => $rp($inv->billed_amount),
                    // Nilai tersimpan apa adanya (dihitung InvoiceCalculationService saat invoice disimpan).
                    'tax_amount'        => (int) $inv->tax_amount,
                    'tax_amount_formatted' => $rp($inv->tax_amount),
                    'withholding_tax'   => (int) $inv->withholding_tax,
                    'withholding_tax_formatted' => $rp($inv->withholding_tax),
                    'captured_total'    => (int) $inv->captured_total,
                    'captured_total_formatted' => $inv->captured_total_formatted,
                    'received'          => $received,
                    'received_formatted' => $rp($received),
                    'received_date'     => $receivedDate,
                    'status'            => $inv->status,
                    'display_status'    => $inv->display_status,
                    'is_overdue'        => $inv->display_status === 'OVERDUE',
                ];
            }),

            // Tab "Cost" — ringkasan biaya + daftar Cost Request PO ini.
            'cost_summary' => [
                'total_request'          => $totalRequest,
                'total_request_formatted'=> $rp($totalRequest),
                'total_refund'           => $totalRefund,
                'total_refund_formatted' => $rp($totalRefund),
                'variable_cost'          => $variableCost,
                'variable_cost_formatted'=> $rp($variableCost),
                'overhead'               => $overhead,
                'overhead_formatted'     => $rp($overhead),
                'fixed_cost'             => $fixedCost,
                'fixed_cost_formatted'   => $rp($fixedCost),
                'total_cost'             => $totalCost,
                'total_cost_formatted'   => $rp($totalCost),
                'payment_total'          => $paymentTotal,
                'payment_total_formatted'=> $rp($paymentTotal),
                'gross_profit'           => $grossProfit,
                'gross_profit_formatted' => $rp($grossProfit),
                'net_profit'             => $netProfit,
                'net_profit_formatted'   => $rp($netProfit),
            ],
            'cost_requests' => $costRequests->map(fn ($cr) => [
                'code'         => $cr->code,
                'date'         => $cr->created_at?->format('d M Y'),
                'description'  => $cr->description,
                'beneficiary'  => $cr->beneficiary->nickname ?? '-',
                'request'           => (int) $cr->amount,
                'request_formatted' => $rp($cr->amount),
                'refund'            => (int) $cr->refund_calc,
                'refund_formatted'  => $rp($cr->refund_calc),
                'status'       => $cr->status,
                'status_label' => $cr->status_label,
                'status_color' => $cr->status_color,
            ]),

            'logs' => $project->logs->map(fn($l) => [
                'action_label' => $l->action_label,
                'action_color' => $l->action_color,
                'status_from'  => $l->status_from,
                'status_to'    => $l->status_to,
                'actor'        => $l->user_name ?? '-',
                'user_id'      => $l->user_id,
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
        $project   = Project::with(['attachments', 'pics', 'poNumbers.attachments'])->findOrFail($id);
        $user      = Auth::user();
        $role      = $user->role->name ?? '';

        /*
        | Approver & Finance Manager boleh mengedit PO pada status apa pun, jadi
        | seluruh gerbang per-status di bawah dilewati — termasuk pengalihan ke
        | halaman "tambah No. PO" saat WAITING_PO, karena buat mereka yang perlu
        | diedit justru bisa field mana saja.
        */
        if (!$project->isFullyEditableBy($user)) {

            // Saat WAITING_PO, semua role (superadmin/finance/head) diarahkan ke halaman
            // terpisah yang cuma berisi Detail No. PO — bukan form edit lengkap lagi
            // (lihat editPoNumbers()).
            if ($project->status === 'WAITING_PO' && in_array($role, ['superadmin', 'finance', 'finance_manager', 'head'])) {
                return redirect('/project/' . $project->id . '/edit-po-numbers');
            }

            if ($role === 'head' && !$project->isEditableByHead()) {
                return redirect('/project')
                    ->with('error', 'PO hanya dapat diedit oleh Head saat berstatus DRAFT.');
            }

            // Finance bisa edit DRAFT, SUBMITTED, WAITING_PO
            if (in_array($role, ['finance', 'finance_manager']) && !$project->isEditableByFinance()) {
                return redirect('/project')
                    ->with('error', 'PO tidak dapat diedit pada status ini.');
            }

            // ACTIVE (termasuk Partially Invoiced/Paid) & CLOSED tidak bisa diedit siapapun
            if (in_array($project->status, array_merge(['ACTIVE', 'CLOSED'], Project::PARTIAL_STATUSES)) && !in_array($role, ['superadmin'])) {
                return redirect('/project')
                    ->with('error', 'PO berstatus ACTIVE atau CLOSED tidak dapat diedit.');
            }

        }

        $customers    = Customer::where('status', 'ACTIVE')->orderBy('name')->get();
        $clusters     = \App\Models\Cluster::orderBy('name')->get();
        $stages       = \App\Models\Stage::orderBy('name')->get();
        $picsInternal = MasterPic::where('type', 'internal')->where('status', 'ACTIVE')->orderBy('pic')->get();
        $picsCustomer = MasterPic::where('type', 'customer')->where('status', 'ACTIVE')->orderBy('pic')->get();
        $accessGroups = \App\Models\AccessGroup::with(['users' => function ($q) {
            $q->whereHas('role', fn ($r) => $r->where('name', 'manager'))->orderBy('name');
        }])->orderBy('name')->get();
        $selectedAccessGroupIds = $project->accessGroupLinks()->pluck('access_group_id')->all();
        $managers = $this->managerOptions();

        // Bagian Budget: Head/superadmin saja. Bila PO sudah punya BP bundled,
        // isinya hanya bisa diubah selagi BP itu DRAFT (selain itu read-only).
        $canFillBudget  = $this->canFillPoBudget();
        $bundledBudget  = $project->bundledBudgetPlans()->with('details.account')->orderBy('id')->first();
        $budgetAccounts = $canFillBudget ? $this->getAccountsFor('PROJECT') : collect();

        return view('project.edit', compact(
            'project', 'customers', 'clusters', 'stages', 'picsInternal', 'picsCustomer',
            'accessGroups', 'selectedAccessGroupIds', 'managers',
            'canFillBudget', 'bundledBudget', 'budgetAccounts'
        ));
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

        // Cek akses edit — Approver & Finance Manager lolos untuk semua status
        // (lihat Project::FULL_EDIT_ROLES).
        if (!$project->isFullyEditableBy($user)) {
            if (in_array($role, ['head']) && !$project->isEditableByHead()) {
                return redirect('/project')->with('error', 'Tidak dapat mengedit PO pada status ini.');
            }

            if (in_array($role, ['finance']) && !$project->isEditableByFinance()) {
                return redirect('/project')->with('error', 'Tidak dapat mengedit PO pada status ini.');
            }
        }

        $request->validate([
            'name'                   => 'required|string|max:255',
            'customer_id'            => 'nullable|exists:customers,id',
            'manager_id'             => 'nullable|exists:users,id',
            'po_quantity'            => 'nullable|integer|min:0',
            'contract'               => 'nullable',
            'restitusi'              => 'nullable',
            'overhead'               => 'nullable',
            'fixed_cost'             => 'nullable',
            'term_of_payment'         => 'nullable|string|max:255',
            'financing'              => 'nullable|in:' . implode(',', Project::FINANCING_TYPES),
            'type'                   => 'nullable|in:' . implode(',', array_keys(Project::TYPES)),
            'po_number'              => 'nullable|string|max:255',
            'notes'                  => 'nullable|string',
            'attachments'            => 'nullable|array',
            'attachments.*.title'    => 'nullable|string|max:255',
            'attachments.*.file'     => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'delete_attachments'     => 'nullable|array',
            'po_numbers'                 => 'nullable|array',
            'po_numbers.*.po_number'     => 'nullable|string|max:255',
            'po_numbers.*.nominal'       => 'nullable',
            'po_numbers.*.release_date'  => 'nullable|date',
            'po_numbers.*.file'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'access_group_ids'   => 'nullable|array',
            'access_group_ids.*' => 'exists:access_groups,id',
        ] + $this->budgetRules());

        // Dipotret sebelum update supaya isi log bisa menyebut nilai lama -> baru.
        $sebelum = $project->getOriginal();

        /*
        | Bagian Budget (BP bundled), hanya Head/superadmin:
        |  - sudah ada BP bundled & SEMUANYA DRAFT -> detailnya diperbarui
        |    (tidak boleh dikosongkan);
        |  - sudah ada tapi ada yang bukan DRAFT   -> read-only, input diabaikan;
        |  - belum ada & PO berstatus DRAFT        -> boleh ditambah (opsional);
        |  - belum ada & PO bukan DRAFT            -> input diabaikan.
        | PO EARLY_START yang disunting kembali ke DRAFT TIDAK membuka BP yang
        | sudah ACTIVE (BP tetap ACTIVE & read-only).
        */
        $budget       = null;
        $bundledPlans = $project->bundledBudgetPlans()->orderBy('id')->get();

        /*
        | Hapus Budget (tombol eksplisit "Hapus Budget dari PO ini"): hard delete BP
        | bundled beserta detail, log, lampiran. Hanya bila Head/superadmin, semua BP
        | bundled DRAFT, dan PO asli DRAFT; selain itu flag diabaikan (jalur lama).
        | Tanpa flag, form budget kosong tetap error "tidak boleh dikosongkan".
        */
        $hapusBudget = $this->canFillPoBudget()
            && filter_var($request->input('budget.hapus'), FILTER_VALIDATE_BOOLEAN)
            && $bundledPlans->isNotEmpty()
            && $bundledPlans->every(fn ($bp) => $bp->isDraft())
            && ($sebelum['status'] ?? null) === 'DRAFT';

        // UI hanya mengenal satu budget per PO; bila data ternyata >1, jangan hapus sebagian diam-diam.
        if ($hapusBudget && $bundledPlans->count() > 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'budget.hapus' => 'PO ini punya lebih dari satu budget bundled; hapus tidak dilakukan otomatis. Hubungi admin.',
            ]);
        }

        if ($hapusBudget) {
            foreach ($bundledPlans as $bp) {
                if ($this->budgetDipakaiDokumenLain($bp)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'budget.hapus' => 'Budget sudah dipakai dokumen lain dan tidak dapat dihapus.',
                    ]);
                }
            }
        } elseif ($this->canFillPoBudget()) {
            if ($bundledPlans->isNotEmpty()) {
                if ($bundledPlans->every(fn ($bp) => $bp->isDraft())) {
                    $budget = $this->budgetRowsOrFail($request);
                    if (!$budget) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'budget.description' => 'Budget yang sudah dibuat dari form PO tidak dapat dikosongkan.',
                        ]);
                    }
                }
            } elseif (($sebelum['status'] ?? null) === 'DRAFT') {
                $budget = $this->budgetRowsOrFail($request);
            }
        }

        try {
        DB::transaction(function () use ($request, $project, $sebelum, $budget, $bundledPlans, $hapusBudget) {
            $project->update([
                'name'           => strtoupper(trim($request->name)),
                'customer_id'    => $request->customer_id,
                'manager_id'     => $request->manager_id,
                'po_quantity'    => $request->filled('po_quantity') ? (int) $request->po_quantity : null,
                'contract'       => $this->normalizeMoney($request->contract),
                'restitusi'      => $this->normalizeMoney($request->restitusi),
                'overhead'       => $this->normalizeMoney($request->overhead),
                'fixed_cost'     => $this->normalizeMoney($request->fixed_cost),
                'term_of_payment'     => $request->term_of_payment,
                'financing'      => $request->filled('financing') ? $request->financing : $project->financing,
                'type'           => $request->filled('type') ? $request->type : null,
                'po_number'      => $request->po_number,
                'notes'          => $request->notes,
            ]);

            $this->syncAccessGroups($project, $request);

            // Hapus attachment
            $deleteIds = (array) $request->input('delete_attachments', []);
            if (!empty($deleteIds)) {
                $toDelete = ProjectAttachment::where('project_id', $project->id)
                    ->whereIn('id', $deleteIds)->get();
                foreach ($toDelete as $att) {
                    Storage::disk('secure')->delete($att->file_path);
                    $att->delete();
                }
            }

            $this->syncDetails($project, $request);
            $this->syncPics($project, $request);
            $this->storeAttachments($project, $request, 'general');
            $this->storePoNumbers($project, $request);

            // logProjectEdit() memanggil $project->addLog('edited', ...) yang
            // sekaligus menulis UserActivityLog (lewat hook di model) berikut
            // rincian perubahan nilai — jadi TIDAK ada record() tambahan di sini
            // supaya satu edit = satu entri log.
            $this->logProjectEdit($project, $sebelum);

            if ($hapusBudget) {
                foreach ($bundledPlans as $plan) {
                    // Kunci baris & periksa ulang di dalam transaksi (status bisa berubah
                    // atau budget baru dirujuk dokumen lain sejak pemeriksaan awal).
                    $bp = BudgetPlan::with('attachments')->whereKey($plan->id)->lockForUpdate()->first();
                    if (! $bp) {
                        continue;
                    }
                    if (! $bp->isDraft()) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'budget.hapus' => 'Status Budget sudah berubah, muat ulang halaman.',
                        ]);
                    }
                    if ($this->budgetDipakaiDokumenLain($bp)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'budget.hapus' => 'Budget sudah dipakai dokumen lain dan tidak dapat dihapus.',
                        ]);
                    }

                    $paths = $bp->attachments->pluck('path')->filter()->all();

                    $project->addLog('DRAFT', 'DRAFT', 'budget_deleted', 'Budget bundled ' . $bp->code . ' dihapus lewat Edit PO.');
                    // Dicatat SEBELUM delete supaya subject/kode masih terbaca.
                    UserActivityLog::record(
                        'budget_plan',
                        'delete',
                        'Menghapus Budget Plan ' . $bp->code . ' lewat Edit PO #' . ($project->code ?? $project->id),
                        $bp
                    );

                    $bp->delete(); // detail, log, lampiran ikut terhapus (FK cascade)

                    // Berkas dihapus hanya setelah commit (pola BudgetPlanController::destroy).
                    DB::afterCommit(function () use ($paths) {
                        foreach ($paths as $path) {
                            Storage::disk('secure')->delete($path);
                        }
                    });
                }
            }

            if ($budget) {
                if ($bundledPlans->isNotEmpty()) {
                    $bp = $bundledPlans->first();
                    $bp->update([
                        'description' => $budget['description'],
                        'notes'       => $budget['notes'],
                    ]);
                    $this->syncBudgetDetails($bp, $budget['rows']);
                    $bp->addLog('DRAFT', 'DRAFT', 'updated', 'Diubah lewat form PO ' . $project->code . '.');
                } else {
                    $this->createBundledBudgetPlan($project, $budget);
                }
            }

            /*
             | Early Start yang disunting kembali ke DRAFT (lihat workflow Early
             | Start): PO ini sengaja di-approve dalam keadaan belum lengkap,
             | jadi begitu datanya dilengkapi ia harus melewati submit → verify
             | → approve lagi, bukan diam di Early Start. Pakai status ASLI
             | sebelum update() ($sebelum), bukan $project->status yang sudah
             | tertimpa data form.
             */
            if (($sebelum['status'] ?? null) === 'EARLY_START') {
                $project->update(['status' => 'DRAFT']);
                $project->addLog('EARLY_START', 'DRAFT', 'early_start_revised', 'Data Early Start dilengkapi — kembali ke Draft untuk disubmit ulang.');
            }
        });
        } catch (\Illuminate\Database\QueryException $e) {
            // FK RESTRICT (mis. Cost Request baru merujuk budget): seluruh perubahan di-rollback.
            // Hanya pelanggaran FK (SQLSTATE 23000 / MySQL 1451) yang dipetakan ke pesan ini.
            $pelanggaranFk = (string) $e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1451;
            if (! $hapusBudget || ! $pelanggaranFk) {
                throw $e; // handler Laravel yang mencatat (report() di sini akan menggandakan log)
            }

            return back()->withInput()->withErrors([
                'budget.hapus' => 'Budget sudah dipakai dokumen lain dan tidak dapat dihapus.',
            ]);
        }

        return redirect('/project')->with('success', $hapusBudget ? 'PO berhasil diupdate dan budget dihapus.' : 'PO berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT PO NUMBERS — halaman terpisah untuk Head saat WAITING_PO.
    | Cuma berisi Detail No. PO (tambah baris baru), field lain PO ini
    | sama sekali tidak bisa disentuh dari halaman ini.
    |--------------------------------------------------------------------------
    */

    /**
     * Satu baris log per penyimpanan form edit, berisi field apa saja yang
     * berubah beserta nilai lama -> barunya. Ditulis untuk SEMUA peran, bukan
     * cuma Approver/Finance Manager: begitu PO bisa diubah di status mana pun,
     * riwayat "siapa mengubah apa" jadi satu-satunya cara menelusurinya.
     *
     * Kalau tidak ada satu pun kolom PO yang berubah (mis. hanya menambah
     * lampiran atau milestone), log tetap ditulis tanpa rincian — yang penting
     * tercatat bahwa PO ini disunting dan oleh siapa.
     */
    private function logProjectEdit(Project $project, array $sebelum): void
    {
        $label = [
            'name'            => 'Scope of Work',
            'customer_id'     => 'Customer',
            'manager_id'      => 'Manager',
            'po_quantity'     => 'PO Quantity',
            'contract'        => 'Contract',
            'restitusi'       => 'Restitusi',
            'overhead'        => 'Overhead',
            'fixed_cost'      => 'Fixed Cost',
            'term_of_payment' => 'Term of Payment',
            'financing'       => 'Financing',
            'type'            => 'Tipe',
            'po_number'       => 'No. PO (header)',
            'notes'           => 'Catatan',
        ];

        $ubahan = [];
        foreach ($label as $kolom => $nama) {
            $lama = $sebelum[$kolom] ?? null;
            $baru = $project->getAttribute($kolom);
            if ((string) $lama === (string) $baru) {
                continue;
            }
            $ubahan[] = $nama . ': ' . ($lama === null || $lama === '' ? '-' : $lama)
                . ' -> ' . ($baru === null || $baru === '' ? '-' : $baru);
        }

        /*
        | Rincian perubahan terstruktur untuk UserActivityLog (credential):
        | nominal diformat rupiah (12.000.000 -> 13.000.000). Field uang
        | ditandai 'money' supaya terbaca manusia, bukan angka mentah.
        */
        $changeMap = [];
        foreach ($label as $kolom => $nama) {
            $changeMap[$kolom] = [
                'label' => $nama,
                'money' => in_array($kolom, ['contract', 'restitusi', 'overhead', 'fixed_cost'], true),
            ];
        }
        $changes = \App\Models\UserActivityLog::changeSet($sebelum, $project, $changeMap);

        $project->addLog(
            $project->status,
            $project->status,
            'edited',
            $ubahan ? implode('; ', $ubahan) : 'Disunting tanpa perubahan field PO.',
            $changes
        );
    }

    public function editPoNumbers($id)
    {
        $project = Project::with(['poNumbers.attachments'])->findOrFail($id);
        $role    = Auth::user()->role->name ?? '';

        if (!in_array($role, ['superadmin', 'approver', 'finance', 'finance_manager', 'head']) || $project->status !== 'WAITING_PO') {
            return redirect('/project')
                ->with('error', 'Halaman ini hanya untuk Superadmin/Finance/Head pada PO berstatus Waiting PO.');
        }

        return view('project.edit-po-numbers', compact('project'));
    }

    public function updatePoNumbers(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $role    = Auth::user()->role->name ?? '';

        if (!in_array($role, ['superadmin', 'approver', 'finance', 'finance_manager', 'head']) || $project->status !== 'WAITING_PO') {
            return redirect('/project')
                ->with('error', 'Halaman ini hanya untuk Superadmin/Approver/Finance/Head pada PO berstatus Waiting PO.');
        }

        $request->validate([
            'po_numbers'                 => 'nullable|array',
            'po_numbers.*.po_number'     => 'nullable|string|max:255',
            'po_numbers.*.nominal'       => 'nullable',
            'po_numbers.*.release_date'  => 'nullable|date',
            'po_numbers.*.file'          => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $sebelumJumlah = $project->poNumbers()->count();

        $this->storePoNumbers($project, $request);

        $ditambah = $project->poNumbers()->count() - $sebelumJumlah;
        $project->addLog(
            $project->status,
            $project->status,
            'po_number_added',
            $ditambah > 0 ? $ditambah . ' No. PO ditambahkan.' : 'Tidak ada No. PO baru yang tersimpan.'
        );

        return redirect('/project')->with('success', 'No. PO berhasil ditambahkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT EXCEL — No. PO
    |--------------------------------------------------------------------------
    | Sama polanya dengan import Project & Milestone: import TIDAK langsung
    | simpan ke database, cuma parsing & balikin JSON — JS di form yang mengisi
    | ulang tabel "Tambah No. PO Baru". Simpan tetap lewat tombol "Simpan No. PO".
    | Lampiran tidak bisa diimport lewat Excel, tetap diupload manual per baris.
    */

    private const PO_NUMBER_IMPORT_HEADERS = ['No. PO', 'Nominal PO', 'Tanggal Rilis (dd-mm-yyyy)'];

    /**
     * GET /project/po-number/import-template
     */
    public function downloadPoNumberTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('No. PO');

        foreach (self::PO_NUMBER_IMPORT_HEADERS as $i => $label) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getStyle($col . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('0D1B2A');
            $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        }

        $sheet->setCellValue('A2', 'PO-2026-001');
        $sheet->setCellValue('B2', 10000000);
        $sheet->setCellValue('C2', now()->format('d-m-Y'));

        foreach (['A' => 26, 'B' => 20, 'C' => 26] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $filename = 'template-no-po-' . now()->format('Ymd-His') . '.xlsx';
        $writer   = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * POST /project/po-number/import-preview
     */
    public function importPoNumberPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getSheetByName('No. PO') ?? $spreadsheet->getActiveSheet();
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

            [$poNumber, $nominal, $releaseDate] = array_pad($row, 3, null);

            if (blank($poNumber) && blank($nominal) && blank($releaseDate)) {
                continue;
            }

            $rowNum = $i + 1;

            if (blank($poNumber)) {
                $errors[] = "Baris {$rowNum}: No. PO wajib diisi.";
            }

            $result[] = [
                'row'          => $rowNum,
                'po_number'    => trim((string) $poNumber),
                'nominal'      => is_numeric($nominal) ? (string) $nominal : preg_replace('/\D/', '', (string) $nominal),
                'release_date' => $this->parseImportDate($releaseDate),
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

        // BP bundled tanpa detail tidak boleh ikut diajukan.
        $bpKosong = $project->bundledBudgetPlans()->where('status', 'DRAFT')->withCount('details')->get()
            ->contains(fn ($bp) => $bp->details_count === 0);
        if ($bpKosong) {
            return back()->with('error', 'Budget pada PO ini belum punya detail. Lengkapi lewat Edit PO sebelum disubmit.');
        }

        DB::transaction(function () use ($project) {
            $project->update([
                'status'       => 'SUBMITTED',
                'submitted_at' => now(),
            ]);
            $project->addLog('DRAFT', 'SUBMITTED', 'submitted');

            // Budget bundled melewati tahap Head-check BP: DRAFT -> CHECKED.
            $this->moveBundledBudgetPlans($project, ['DRAFT'], 'CHECKED', 'submitted',
                'Ikut submit PO ' . $project->code . ' (tahap Head-check Budget Plan dilewati).');
        });

        NotifyService::send(
            $this->financeUsers(),
            'Purchase Order menunggu approval',
            Auth::user()->name . ' mengajukan PO "' . $project->name . '" untuk disetujui.',
            route('project.index'),
            'workflow'
        );

        return redirect('/project')->with('success', 'PO berhasil diajukan ke Finance.');
    }

    /** Role Finance — penerima notifikasi workflow Purchase Order. */
    private function financeUsers()
    {
        // Satu sumber daftar peran: App\Services\RoleDirectory.
        return app(RoleDirectory::class)->finance();
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFY — SUBMITTED → VERIFIED (Finance)
    |
    | Langkah pertama dari dua tahap approval. Finance memverifikasi bahwa data
    | PO sudah layak lanjut; keputusan status akhir (Waiting PO / Active / Early
    | Start) ada di tangan Approver pada langkah berikutnya (approve()).
    |--------------------------------------------------------------------------
    */

    public function verify($id)
    {
        $project = Project::findOrFail($id);

        if ($project->status !== 'SUBMITTED') {
            return back()->with('error', 'Hanya PO berstatus Submitted yang dapat diverifikasi.');
        }

        DB::transaction(function () use ($project) {
            $project->update([
                'status'      => 'VERIFIED',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);
            $project->addLog('SUBMITTED', 'VERIFIED', 'verified');

            $this->moveBundledBudgetPlans($project, ['CHECKED'], 'VERIFIED', 'finance_verified',
                'Ikut verifikasi PO ' . $project->code . '.');
        });

        NotifyService::send(
            app(RoleDirectory::class)->approvers(),
            'Purchase Order menunggu approval',
            Auth::user()->name . ' memverifikasi PO "' . $project->name . '" — menunggu approval.',
            route('project.index'),
            'workflow'
        );

        return redirect('/project')->with('success', 'PO ' . $project->code . ' berhasil diverifikasi.');
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE — VERIFIED → EARLY_START | WAITING_PO | ACTIVE (Approver)
    |
    | Approver memilih:
    |   - decision=EARLY_START : PO baru diketahui scope-nya; sah & budget-able,
    |     tapi belum lengkap. Nanti dilengkapi lewat Edit (yang otomatis
    |     mengembalikannya ke Draft untuk disubmit ulang).
    |   - decision=APPROVE     : status akhir dipilih OTOMATIS oleh sistem —
    |     ACTIVE bila seluruh nominal No. PO sudah pas dengan kontrak, selain
    |     itu WAITING_PO (lihat Project::approvalAutoStatus()).
    |--------------------------------------------------------------------------
    */

    public function approve(Request $request, $id)
    {
        $request->validate([
            'decision' => 'required|in:APPROVE,EARLY_START',
            'notes'    => 'nullable|string|max:500',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'VERIFIED') {
            return back()->with('error', 'Hanya PO berstatus Verified yang dapat di-approve.');
        }

        if ($request->decision === 'EARLY_START') {
            $newStatus = 'EARLY_START';
            $action    = 'approved_early_start';
        } else {
            $newStatus = $project->approvalAutoStatus();
            $action    = $newStatus === 'ACTIVE' ? 'approved_active' : 'approved_waiting';
        }

        DB::transaction(function () use ($project, $newStatus, $action, $request) {
            $project->update([
                'status'      => $newStatus,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);
            $project->addLog('VERIFIED', $newStatus, $action, $request->notes);

            // Budget bundled ACTIVE untuk semua hasil approve (Early Start sudah
            // budgetable), sebelum syncStatusFromPoNumbers() di bawah.
            $this->moveBundledBudgetPlans($project, ['VERIFIED'], 'ACTIVE', 'ceo_approved',
                'Ikut approve PO ' . $project->code . ' (' . Project::statusLabelFor($newStatus) . ').',
                ['approved_by' => Auth::id(), 'approved_at' => now()]);
        });

        // Kalau langsung ACTIVE dan No. PO-nya kebetulan sudah BAST/Paid, teruskan.
        if ($newStatus === 'ACTIVE') {
            $project->syncStatusFromPoNumbers();
        }

        if ($project->inputBy) {
            NotifyService::send(
                [$project->inputBy],
                'Purchase Order disetujui',
                'PO "' . $project->name . '" disetujui — status: ' . Project::statusLabelFor($newStatus) . '.',
                route('project.index'),
                'workflow'
            );
        }

        $msg = match ($newStatus) {
            'EARLY_START' => 'PO disetujui sebagai Early Start.',
            'ACTIVE'      => 'PO disetujui dan sekarang Active.',
            default       => 'PO disetujui dengan status Waiting PO.',
        };

        return redirect('/project')->with('success', $msg);
    }

    /*
    |--------------------------------------------------------------------------
    | WAITING PO → ACTIVE — Finance input no PO
    |--------------------------------------------------------------------------
    */

    public function activateFromWaiting(Request $request, $id)
    {
        $role = Auth::user()->role->name ?? '';
        if (!in_array($role, ['superadmin', 'finance', 'finance_manager', 'head'])) {
            return redirect('/project')
                ->with('error', 'Hanya Head/Finance yang dapat mengaktifkan PO.');
        }

        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $project = Project::findOrFail($id);

        if ($project->status !== 'WAITING_PO') {
            return back()->with('error', 'Hanya PO berstatus WAITING_PO yang dapat diaktifkan.');
        }

        if ($project->poNumbers()->count() === 0) {
            return back()->with('error', 'Tambahkan minimal 1 No. PO dulu (lewat Edit) sebelum PO ini bisa di-Active-kan.');
        }

        $project->update(['status' => 'ACTIVE']);
        $project->addLog('WAITING_PO', 'ACTIVE', 'waiting_to_active', $request->notes);

        // Kalau semua No. PO kebetulan sudah BAST/Paid sebelum diaktifkan,
        // langsung lanjutkan ke PROGRESS_DONE/PAID saat itu juga.
        $project->syncStatusFromPoNumbers();

        return redirect('/project')->with('success', 'PO berhasil diaktifkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVATE FORM — WAITING_PO → ACTIVE (form edit lengkap oleh Finance)
    |--------------------------------------------------------------------------
    */

    public function activateForm($id)
    {
        $project = Project::with(['customer', 'attachments', 'details.cluster', 'details.stage', 'pics', 'poNumbers.attachments'])
            ->findOrFail($id);
        $role    = Auth::user()->role->name ?? '';

        if (!in_array($role, ['superadmin', 'finance', 'finance_manager', 'head'])) {
            return redirect('/project')
                ->with('error', 'Hanya Head/Finance yang dapat mengaktifkan PO.');
        }

        if ($project->status !== 'WAITING_PO') {
            return redirect('/project')
                ->with('error', 'Hanya PO berstatus WAITING_PO yang dapat diaktifkan.');
        }

        return view('project.activate', compact('project'));
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

        if (!in_array($role, ['superadmin', 'finance', 'finance_manager', 'head'])) {
            return redirect('/project')
                ->with('error', 'Hanya Head/Finance yang dapat mengaktifkan PO.');
        }

        if ($project->status !== 'WAITING_PO') {
            return redirect('/project')
                ->with('error', 'Hanya PO berstatus WAITING_PO yang dapat diaktifkan.');
        }

        if ($project->poNumbers()->count() === 0) {
            return redirect('/project')
                ->with('error', 'Tambahkan minimal 1 No. PO dulu sebelum PO ini bisa di-Active-kan.');
        }

        // Halaman ini murni konfirmasi — tidak ada field apapun (name/customer/
        // value/milestone/PIC/lampiran/No. PO) yang bisa diubah dari sini.
        // No. PO ditambah/diperbaiki lewat halaman Edit sebelum sampai ke sini.
        $request->validate([
            'activation_notes' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $project) {
            $project->update(['status' => 'ACTIVE']);
            $project->addLog('WAITING_PO', 'ACTIVE', 'waiting_to_active', $request->activation_notes);

            // Kalau semua No. PO kebetulan sudah BAST/Paid sebelum diaktifkan,
            // langsung lanjutkan ke PROGRESS_DONE/PAID saat itu juga.
            $project->syncStatusFromPoNumbers();
        });

        return redirect('/project')
            ->with('success', 'PO berhasil diaktifkan.');
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

        if (!in_array($project->status, ['SUBMITTED', 'VERIFIED'], true)) {
            return back()->with('error', 'Hanya PO berstatus Submitted atau Verified yang dapat dikembalikan untuk direvisi.');
        }

        $dari = $project->status;
        DB::transaction(function () use ($project, $dari, $request) {
            $project->update(['status' => 'DRAFT']);
            $project->addLog($dari, 'DRAFT', 'revised', $request->notes);

            // Budget bundled ikut kembali ke DRAFT (langsung, tanpa REJECTED).
            $this->moveBundledBudgetPlans($project, ['CHECKED', 'VERIFIED'], 'DRAFT', 'revised',
                'PO ' . $project->code . ' dikembalikan untuk revisi: ' . $request->notes);
        });

        if ($project->inputBy) {
            NotifyService::send(
                [$project->inputBy],
                'Purchase Order perlu direvisi',
                'PO "' . $project->name . '" dikembalikan Finance untuk direvisi: ' . $request->notes,
                route('project.index'),
                'workflow'
            );
        }

        return redirect('/project')->with('success', 'PO dikembalikan ke Head untuk direvisi.');
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE — ACTIVE → CLOSED (Finance, upload MoM)
    |--------------------------------------------------------------------------
    */

    /**
     * PO ditutup → Budget Plan ACTIVE/CLOSING miliknya ikut CLOSED. Dipanggil
     * di dalam transaksi penutupan PO dari ketiga jalur close.
     */
    private function closeBudgetPlans(Project $project, string $notes): void
    {
        $project->budgetPlans()
            ->whereIn('status', ['ACTIVE', 'CLOSING'])
            ->get()
            ->each(function ($bp) use ($notes) {
                $dari = $bp->status;
                $bp->update([
                    'status'      => 'CLOSED',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);
                $bp->addLog($dari, 'CLOSED', 'closed_with_po', $notes);
            });
    }

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
            $this->closeBudgetPlans($project, 'Otomatis: PO ' . $project->code . ' ditutup.');
        });

        return redirect('/project')->with('success', 'PO berhasil ditutup.');
    }

    /*
    |--------------------------------------------------------------------------
    | FINANCE CLOSE — PAID → CLOSED langsung, tanpa menunggu MoM dari Head.
    |--------------------------------------------------------------------------
    | Jalur normalnya PAID → CLOSING (Head submit MoM) → CLOSED (Finance
    | verifikasi). Jalur ini memangkasnya untuk PO yang tagihannya sudah lunas
    | dan tidak menyisakan MoM apa pun — polanya sama persis dengan
    | BudgetPlanController::financeClose(). Lampiran closing tidak diwajibkan
    | karena tidak ada dokumen MoM yang diverifikasi di sini; yang jadi jejaknya
    | adalah catatan wajib + baris log.
    */
    public function financeClose(Request $request, $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasRole(['superadmin', 'finance', 'finance_manager'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk menutup PO.');
        }

        $request->validate(['notes' => 'required|string|max:1000']);

        $project = Project::findOrFail($id);

        if ($project->status !== 'PAID') {
            return back()->with('error', 'Hanya PO berstatus PAID yang dapat ditutup langsung oleh Finance.');
        }

        DB::transaction(function () use ($request, $project) {
            $project->update([
                'status'        => 'CLOSED',
                'closed_by'     => Auth::id(),
                'closed_at'     => now(),
                'closing_notes' => $request->notes,
            ]);

            $project->addLog('PAID', 'CLOSED', 'finance_closed', $request->notes);
            $this->closeBudgetPlans($project, 'Otomatis: PO ' . $project->code . ' ditutup.');
        });

        return redirect('/project')->with('success', 'PO berhasil ditutup.');
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

        NotifyService::send(
            $this->financeUsers(),
            'MoM Closing menunggu verifikasi',
            Auth::user()->name . ' mengajukan MoM Closing untuk PO "' . $project->name . '".',
            route('project.index'),
            'workflow'
        );

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

        DB::transaction(function () use ($request, $project) {
            $project->update([
                'status'    => 'CLOSED',
                'closed_by' => Auth::id(),
                'closed_at' => now(),
            ]);
            $project->addLog('CLOSING', 'CLOSED', 'closing_approved', $request->notes);
            $this->closeBudgetPlans($project, 'Otomatis: PO ' . $project->code . ' ditutup.');
        });

        if ($project->inputBy) {
            NotifyService::send(
                [$project->inputBy],
                'Purchase Order ditutup',
                'PO "' . $project->name . '" telah diverifikasi Finance dan sekarang CLOSED.',
                route('project.index'),
                'workflow'
            );
        }

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

        if ($project->inputBy) {
            NotifyService::send(
                [$project->inputBy],
                'MoM Closing ditolak',
                'MoM Closing untuk PO "' . $project->name . '" ditolak: ' . $request->notes,
                route('project.index'),
                'workflow'
            );
        }

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

        // BP bundled yang sudah bergerak (mis. ACTIVE dari Early Start) tidak boleh hilang bersama PO.
        if ($project->bundledBudgetPlans()->where('status', '!=', 'DRAFT')->exists()) {
            return redirect('/project')
                ->with('error', 'PO tidak dapat dihapus karena memiliki Budget Plan yang sudah berjalan (bukan DRAFT).');
        }

        $projectCode = $project->code ?? $project->id;

        DB::transaction(function () use ($project) {
            foreach ($project->attachments as $att) {
                Storage::disk('secure')->delete($att->file_path);
            }
            $project->delete();
        });

        UserActivityLog::record('purchase_order', 'delete', 'Menghapus Purchase Order #' . $projectCode);

        return redirect('/project')->with('success', 'PO berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | PO NUMBER — update status siklus hidup satu baris No. PO
    |--------------------------------------------------------------------------
    */

    public function updatePoNumberStatus(Request $request, $poNumberId)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', \App\Models\ProjectPoNumber::STATUSES),
        ]);

        $poNumber = \App\Models\ProjectPoNumber::findOrFail($poNumberId);
        $poNumber->update(['status' => $request->status]);
        $poNumber->project->syncStatusFromPoNumbers();

        return back()->with('success', 'Status No. PO ' . $poNumber->po_number . ' diperbarui.');
    }

    /*
    |--------------------------------------------------------------------------
    | SUBMIT BAST — RELEASED -> PARTIAL -> BAST_SUBMITTED, bisa multi No. PO
    |--------------------------------------------------------------------------
    */

    /**
     * Halaman terpisah untuk submit BAST — bisa banyak No. PO sekaligus dalam
     * 1 kali submit (mis. PO A dapat 2 BAST, PO B dapat 1 BAST bersamaan),
     * supaya tidak perlu buka-modal-submit-tutup berulang per No. PO.
     */
    public function showBastPage($projectId)
    {
        $role = Auth::user()->role->name ?? '';
        if (!in_array($role, ['superadmin', 'head'])) {
            abort(403, 'Hanya Head/Superadmin yang dapat submit BAST.');
        }

        $project = \App\Models\Project::with(['customer', 'poNumbers' => function ($q) {
            $q->orderBy('po_number');
        }, 'poNumbers.bastAttachments'])->findOrFail($projectId);

        return view('project.submit-bast', compact('project'));
    }

    /**
     * Submit BAST untuk banyak No. PO sekaligus. Baris yang tidak dilampiri
     * file dilewati (tidak divalidasi wajib) — supaya user boleh isi No. PO
     * yang relevan saja dalam 1 form besar.
     */
    public function storeBastBatch(Request $request, $projectId)
    {
        $role = Auth::user()->role->name ?? '';
        if (!in_array($role, ['superadmin', 'head'])) {
            return back()->with('error', 'Hanya Head/Superadmin yang dapat submit BAST.');
        }

        $project = \App\Models\Project::findOrFail($projectId);

        $rows  = (array) $request->input('po_numbers', []);
        $files = (array) $request->file('po_numbers', []);
        // Diproses berurutan id No. PO: kunci No. PO diambil dalam urutan yang sama di setiap request.
        ksort($rows);

        $processed = [];
        $skipped   = [];
        $blocked   = [];

        DB::transaction(function () use ($rows, $files, $project, &$processed, &$skipped, &$blocked) {
            foreach ($rows as $poNumberId => $row) {
                $fileList = array_values(array_filter(
                    $files[$poNumberId]['attachments'] ?? [],
                    fn ($file) => $file && $file->isValid()
                ));

                // Baris tanpa lampiran = tidak diisi user, lewati diam-diam.
                if (empty($fileList)) {
                    continue;
                }

                // Dikunci: dua submit bersamaan tidak boleh sama-sama lolos cek total BAST di bawah.
                $poNumber = \App\Models\ProjectPoNumber::where('project_id', $project->id)->lockForUpdate()->find($poNumberId);
                $noBast      = trim($row['no_bast'] ?? '');
                $tanggalBast = $row['tanggal_bast'] ?? null;
                $nominalBast = $this->normalizeMoney($row['nominal_bast'] ?? null);

                if (!$poNumber || in_array($poNumber->status, ['INVOICED', 'PAID']) || !$noBast || !$tanggalBast || $nominalBast <= 0) {
                    $skipped[] = $poNumber->po_number ?? ('#' . $poNumberId);
                    continue;
                }

                // Total BAST (aktif) tidak boleh melebihi nominal No. PO; kurang dari nominal tetap boleh.
                $totalBaru = (int) $poNumber->basts()->sum('nilai_bast') + $nominalBast;
                if ($totalBaru > (int) $poNumber->nominal) {
                    $blocked[] = $poNumber->po_number . ' (total BAST Rp ' . number_format($totalBaru, 0, ',', '.')
                        . ' melebihi nominal Rp ' . number_format((int) $poNumber->nominal, 0, ',', '.') . ')';
                    continue;
                }

                $this->applyBastSubmission($poNumber, $noBast, $tanggalBast, $nominalBast, $fileList, !empty($row['mark_complete']));
                $processed[] = $poNumber->po_number;
            }
        });

        $pesanBlokir = $blocked
            ? 'BAST DIBLOKIR — total BAST melebihi nominal No. PO: ' . implode('; ', $blocked) . '.'
            : null;

        if (empty($processed)) {
            return back()->with('error', $pesanBlokir ?? 'Tidak ada No. PO yang disubmit — pastikan minimal 1 No. PO diisi No. BAST, Tanggal BAST, Nominal BAST, dan lampiran.');
        }

        $msg = count($processed) . ' No. PO berhasil disubmit BAST-nya: ' . implode(', ', $processed) . '.';
        if ($skipped) {
            $msg .= ' Dilewati (tidak valid/sudah Invoiced-Paid): ' . implode(', ', $skipped) . '.';
        }

        $balik = back()->with('success', $msg);

        return $pesanBlokir ? $balik->with('error', $pesanBlokir) : $balik;
    }

    /**
     * Terapkan 1 submission BAST ke 1 No. PO: update no_bast/tanggal_bast,
     * simpan lampiran, dan set status PARTIAL (atau BAST_SUBMITTED kalau
     * user sekalian menandai lengkap). Dipakai oleh storeBastBatch().
     */
    private function applyBastSubmission(\App\Models\ProjectPoNumber $poNumber, string $noBast, string $tanggalBast, int $nominalBast, array $files, bool $markComplete): void
    {
        $poNumber->update([
            'status'            => $markComplete ? 'BAST_SUBMITTED' : 'PARTIAL',
            'no_bast'           => $noBast,
            'tanggal_bast'      => $tanggalBast,
            // Akumulasi — 1 No. PO Partial bisa disubmit BAST-nya beberapa kali,
            // nilai_bast merepresentasikan total nilai BAST yang sudah masuk sejauh ini.
            'nilai_bast'        => (int) $poNumber->nilai_bast + $nominalBast,
            'bast_submitted_by' => Auth::id(),
            'bast_submitted_at' => now(),
        ]);

        // Rincian per submission: No. PO = header, BAST = detail (kolom agregat di atas tetap diisi).
        $bast = $poNumber->basts()->create([
            'no_bast'      => $noBast,
            'tanggal_bast' => $tanggalBast,
            'nilai_bast'   => $nominalBast,
            'submitted_by' => Auth::id(),
        ]);

        $attCount = count($files);
        foreach ($files as $i => $file) {
            $title = $attCount > 1 ? $noBast . ' - ' . ($i + 1) : $noBast;
            $path  = $file->store('project_attachments', 'secure');

            $poNumber->attachments()->create([
                'project_id' => $poNumber->project_id,
                'project_po_bast_id' => $bast->id,
                'type'       => 'po_number_bast',
                'title'      => strtoupper($title),
                'file_path'  => $path,
                'file_name'  => basename($path),
                'file_type'  => $file->getClientMimeType(),
            ]);
        }

        $note = 'BAST No. PO ' . $poNumber->po_number . ' disubmit' . ($markComplete ? ' dan ditandai lengkap.' : ' (partial).');
        $poNumber->project->addLog('ACTIVE', 'ACTIVE', 'bast_submitted', $note);
        // Status No. PO (dan header PO) dihitung dari BAST-nya, satu jalur dengan penagihan.
        $poNumber->syncInvoiceStatus();
    }

    /**
     * Tandai BAST sebuah No. PO sebagai lengkap (PARTIAL -> BAST_SUBMITTED).
     * Tidak butuh file baru — cuma konfirmasi manual dari user bahwa semua
     * dokumen BAST (1-4 dokumen) untuk No. PO ini sudah selesai di-upload.
     */
    public function completePoNumberBast($poNumberId)
    {
        $role = Auth::user()->role->name ?? '';
        if (!in_array($role, ['superadmin', 'head'])) {
            return back()->with('error', 'Hanya Head/Superadmin yang dapat menandai BAST lengkap.');
        }

        $poNumber = \App\Models\ProjectPoNumber::with('project')->findOrFail($poNumberId);

        if ($poNumber->status !== 'PARTIAL') {
            return back()->with('error', 'Hanya No. PO berstatus Partial yang bisa ditandai lengkap.');
        }

        $poNumber->update(['status' => 'BAST_SUBMITTED']);
        $poNumber->project->addLog('ACTIVE', 'ACTIVE', 'bast_completed', 'BAST No. PO ' . $poNumber->po_number . ' ditandai lengkap.');
        // Bila semua BAST-nya sudah ditagih, langsung naik ke INVOICED/PAID.
        $poNumber->usesBastBilling()
            ? $poNumber->syncInvoiceStatus()
            : $poNumber->project->syncStatusFromPoNumbers();

        return back()->with('success', 'No. PO ' . $poNumber->po_number . ' ditandai BAST lengkap.');
    }

    public function destroyPoNumber($poNumberId)
    {
        $poNumber = \App\Models\ProjectPoNumber::with('attachments')->findOrFail($poNumberId);

        if ($poNumber->status !== 'RELEASED') {
            return back()->with('error', 'No. PO yang sudah berjalan (bukan RELEASED) tidak dapat dihapus.');
        }

        // withTrashed: BAST yang sudah di-soft-delete tetap ada barisnya (FK restrict), jadi tetap menolak.
        if ($poNumber->basts()->withTrashed()->exists()) {
            return back()->with('error', 'No. PO yang sudah punya BAST tidak dapat dihapus.');
        }

        $paths = $poNumber->attachments->pluck('file_path')->all();

        DB::transaction(fn () => $poNumber->delete());

        // File fisik dihapus sesudah baris DB berhasil di-commit; kegagalan hapus file
        // tidak boleh membatalkan hasilnya.
        foreach ($paths as $path) {
            try {
                Storage::disk('secure')->delete($path);
            } catch (\Throwable $e) {
                \Log::warning('Gagal menghapus file lampiran No. PO: ' . $path . ' — ' . $e->getMessage());
            }
        }

        return back()->with('success', 'No. PO berhasil dihapus.');
    }

    /**
     * Hapus (soft delete) satu BAST — superadmin saja, hanya bila belum ditagih.
     * Baris & lampirannya TIDAK dihapus (jejak audit); agregat kolom lama No. PO
     * dan statusnya dihitung ulang dari BAST yang masih aktif.
     */
    public function destroyBast(Request $request, $bastId)
    {
        abort_unless(Auth::user()->hasRole(['superadmin']), 403);

        $request->validate(['delete_reason' => 'required|string|max:1000']);

        // No. PO dicari SEBELUM transaksi: kunci pertama di dalamnya harus baris No. PO supaya
        // pembacaan biasa sesudahnya melihat data terbaru (lihat InvoiceCashReceiptController::kunciBastUntukTagihan).
        $poId = \App\Models\ProjectPoBast::whereKey($bastId)->value('project_po_number_id');
        if (!$poId) {
            return back()->with('error', 'BAST tidak ditemukan atau sudah dihapus.');
        }

        try {
            DB::transaction(function () use ($bastId, $request, $poId) {
                // Urutan kunci sama di semua jalur BAST/invoice: (1) No. PO, (2) BAST, (3) invoice per id saja.
                $poNumber = \App\Models\ProjectPoNumber::whereKey($poId)->lockForUpdate()->firstOrFail();
                $bast     = \App\Models\ProjectPoBast::whereKey($bastId)->lockForUpdate()->first();
                if (!$bast) {
                    throw new \DomainException('BAST tidak ditemukan atau sudah dihapus.');
                }

                $invoice = $bast->activeInvoices()->first();
                if ($invoice) {
                    throw new \DomainException('BAST ' . $bast->no_bast . ' sudah ditagih pada invoice ' . $invoice->code . ' dan tidak dapat dihapus.');
                }

                $bast->update(['deleted_by' => Auth::id(), 'delete_reason' => $request->delete_reason]);
                $bast->delete();

                $poNumber->recalculateBastAggregate();
                $poNumber->syncInvoiceStatus();

                $status = $poNumber->project->status;
                $poNumber->project->addLog($status, $status, 'bast_deleted',
                    'BAST ' . $bast->no_bast . ' (No. PO ' . $poNumber->po_number . ') dihapus: ' . $request->delete_reason);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'BAST berhasil dihapus.');
    }
}
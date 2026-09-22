<?php

namespace App\Services\Finance;

use App\Models\BudgetRequest;
use App\Models\BudgetRequestRealization;
use App\Models\CashReceipt;
use App\Models\DailyTransaction;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RequestDetail;
use App\Models\User;
use Carbon\Carbon;

/**
 * Lapisan data khusus TesysAI — wrapper TIPIS di atas Model/relasi yang
 * sudah ada di aplikasi (Project::budgetPlanTotal(), BudgetRequest::
 * isAccessibleBy(), dst). Tidak ada aturan akses baru yang dibuat khusus
 * di sini — semua scoping Access Group/Cost Center/Payroll mengacu PERSIS
 * ke method yang sudah dipakai halaman Cost Request, supaya tidak ada dua
 * sumber kebenaran yang bisa berbeda.
 *
 * Setiap method menerima $scopedProjectIds (untuk lookup Project by nama/kode):
 *   - null       => tidak dibatasi (superadmin/approver)
 *   - array []   => dibatasi tapi user tidak punya akses project apa pun
 *   - array [..] => dibatasi ke daftar project_id tsb (Access Group)
 */
class AiDataService
{
    /**
     * Cari Project by nama/kode TANPA filter akses dulu — supaya bisa
     * membedakan "project benar-benar tidak ada" vs "project ada tapi di
     * luar akses user" (pesannya sengaja dibedakan, bukan disamarkan jadi
     * "tidak ditemukan" untuk kedua kasus).
     *
     * @return array{project: ?Project, error: ?array}
     */
    private function resolveProject(?string $query, ?array $scopedProjectIds): array
    {
        if (blank($query)) {
            return ['project' => null, 'error' => null];
        }

        // Tahap 0: kode PERSIS (mis. user sebut "PO 0006" — kode Project cuma
        // 4 digit, jadi WAJIB dicek exact match dulu SEBELUM substring/LIKE,
        // supaya tidak ketiban project lain yang kebetulan namanya mengandung
        // "0006" di suatu tempat dan lebih baru (orderByDesc mengalahkannya).
        $project = Project::with('customer:id,name')->where('code', trim($query))->first();

        // Tahap 1: kalau tidak ketemu, coba cocokkan frasa utuh (substring).
        if (!$project) {
            $project = Project::with('customer:id,name')
                ->where(function ($w) use ($query) {
                    $w->where('name', 'like', "%{$query}%")->orWhere('code', 'like', "%{$query}%");
                })
                ->orderByDesc('created_at')
                ->first();
        }

        // Tahap 2: kalau tidak ketemu, coba lagi PER-KATA (semua kata harus
        // muncul, urutan/kata sisipan bebas) — supaya "survey sitac bandung"
        // tetap ketemu "FMI - SURVEY & SITAC KAB. BANDUNG", bukan cuma cocok
        // kalau user mengetik persis nama lengkapnya.
        if (!$project) {
            $words = collect(preg_split('/\s+/', trim($query)))->filter(fn ($w) => mb_strlen($w) >= 2)->values();
            if ($words->isNotEmpty()) {
                $wordQuery = Project::with('customer:id,name');
                foreach ($words as $word) {
                    $wordQuery->where(fn ($w) => $w->where('name', 'like', "%{$word}%")->orWhere('code', 'like', "%{$word}%"));
                }
                $project = $wordQuery->orderByDesc('created_at')->first();
            }
        }

        if (!$project) {
            return ['project' => null, 'error' => [
                'found'   => false,
                'message' => "Project \"{$query}\" tidak ditemukan di sistem.",
            ]];
        }

        if (is_array($scopedProjectIds) && !in_array($project->id, $scopedProjectIds, true)) {
            return ['project' => null, 'error' => $this->accessDeniedMessage()];
        }

        return ['project' => $project, 'error' => null];
    }

    private function accessDeniedMessage(): array
    {
        return [
            'found'   => false,
            'message' => 'Mohon maaf, Anda tidak dapat mengakses informasi tersebut karena keterbatasan hak akses (credential perusahaan). Silakan hubungi admin/atasan Anda jika memerlukan akses ke data ini.',
        ];
    }

    private function periodRange(?string $period): ?array
    {
        if (blank($period)) {
            return null;
        }
        try {
            $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            return [$start->copy(), $start->copy()->endOfMonth()];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getBudget(?string $projectQuery, ?string $period, ?array $scopedProjectIds): array
    {
        $resolved = $this->resolveProject($projectQuery, $scopedProjectIds);
        if ($resolved['error']) {
            return $resolved['error'];
        }
        $project = $resolved['project'];
        if (!$project) {
            return ['found' => false, 'message' => 'Sebutkan nama atau kode Project yang dimaksud.'];
        }

        return [
            'found'    => true,
            'project'  => ['code' => $project->code, 'name' => $project->name],
            'budget'   => $project->budgetPlanTotal(),
            'po_value' => (int) ($project->value ?? 0),
        ];
    }

    public function getRealization(?string $projectQuery, ?string $period, ?array $scopedProjectIds): array
    {
        $resolved = $this->resolveProject($projectQuery, $scopedProjectIds);
        if ($resolved['error']) {
            return $resolved['error'];
        }
        $project = $resolved['project'];
        if (!$project) {
            return ['found' => false, 'message' => 'Sebutkan nama atau kode Project yang dimaksud.'];
        }

        $budget      = $project->budgetPlanTotal();
        $realization = $project->costRealizationTotal();

        return [
            'found'       => true,
            'project'     => ['code' => $project->code, 'name' => $project->name],
            'budget'      => $budget,
            'realization' => $realization,
            'percentage'  => $budget > 0 ? round(($realization / $budget) * 100, 1) : 0,
        ];
    }

    public function getInvoice(?string $projectQuery, ?string $customer, ?string $status, ?string $period, ?array $scopedProjectIds): array
    {
        $query = Invoice::with('po:id,code,name');

        if (is_array($scopedProjectIds)) {
            $query->whereHas('po', fn ($q) => $q->whereIn('id', $scopedProjectIds ?: [0]));
        }

        if (filled($projectQuery)) {
            $resolved = $this->resolveProject($projectQuery, $scopedProjectIds);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $query->where('po_id', $resolved['project']->id);
        }

        if (filled($customer)) {
            $query->whereHas('po.customer', fn ($q) => $q->where('name', 'like', "%{$customer}%"));
        }

        if (filled($status)) {
            $query->where('status', strtoupper($status));
        }

        if ($range = $this->periodRange($period)) {
            $query->whereBetween('invoice_date', $range);
        }

        $invoices = $query->orderByDesc('invoice_date')->limit(20)->get();

        return [
            'found'           => true,
            'count'           => $invoices->count(),
            'total_value'     => (int) $invoices->sum('captured_total'),
            'total_received'  => (int) $invoices->sum(fn ($i) => $i->totalReceived()),
            'invoices'        => $invoices->map(fn ($i) => [
                'code'     => $i->code,
                'project'  => $i->po->name ?? '-',
                'status'   => $i->display_status,
                'value'    => (int) $i->captured_total,
                'due_date' => optional($i->due_date)->format('Y-m-d'),
            ])->all(),
        ];
    }

    /**
     * Rincian per-baris (item/harga satuan/qty/potongan) SATU invoice —
     * beda dengan getInvoice() yang cuma daftar ringkas banyak invoice.
     */
    public function getInvoiceDetail(string $invoiceNumber, ?array $scopedProjectIds): array
    {
        $invoice = Invoice::with(['po:id,code,name', 'details'])->where('code', $invoiceNumber)->first();
        if (!$invoice) {
            return ['found' => false, 'message' => "Invoice \"{$invoiceNumber}\" tidak ditemukan di sistem."];
        }
        if (is_array($scopedProjectIds) && !in_array($invoice->po_id, $scopedProjectIds, true)) {
            return $this->accessDeniedMessage();
        }

        return [
            'found'       => true,
            'invoice'     => $invoice->code,
            'project'     => $invoice->po->name ?? '-',
            'status'      => $invoice->display_status,
            'subtotal'    => (int) $invoice->subtotal,
            'tax_amount'  => (int) $invoice->tax_amount,
            'total'       => (int) $invoice->captured_total,
            'received'    => $invoice->totalReceived(),
            'outstanding' => $invoice->remainingBalance(),
            'items'       => $invoice->details->map(fn ($d) => [
                'description' => $d->description,
                'unit_price'  => (int) $d->unit_price,
                'qty'         => (int) $d->qty,
                'deduction'   => (int) $d->deduction,
                'total'       => (int) $d->total,
            ])->values()->all(),
        ];
    }

    public function getPayment(?string $projectQuery, ?string $invoiceNumber, ?string $period, ?array $scopedProjectIds): array
    {
        if (filled($invoiceNumber)) {
            $invoiceQuery = Invoice::with('po:id,code,name')->where('code', $invoiceNumber);
            $invoice = $invoiceQuery->first();
            if (!$invoice) {
                return ['found' => false, 'message' => "Invoice \"{$invoiceNumber}\" tidak ditemukan di sistem."];
            }
            if (is_array($scopedProjectIds) && !in_array($invoice->po_id, $scopedProjectIds, true)) {
                return $this->accessDeniedMessage();
            }

            return [
                'found'       => true,
                'invoice'     => $invoice->code,
                'project'     => ['name' => $invoice->po->name ?? '-'],
                'value'       => (int) $invoice->captured_total,
                'received'    => $invoice->totalReceived(),
                'outstanding' => $invoice->remainingBalance(),
                'status'      => $invoice->display_status,
            ];
        }

        $resolved = $this->resolveProject($projectQuery, $scopedProjectIds);
        if ($resolved['error']) {
            return $resolved['error'];
        }
        $project = $resolved['project'];
        if (!$project) {
            return ['found' => false, 'message' => 'Sebutkan nama/kode Project atau nomor invoice yang dimaksud.'];
        }

        $received = $project->paymentTotal();
        $poValue  = (int) ($project->value ?? 0);

        return [
            'found'       => true,
            'project'     => ['code' => $project->code, 'name' => $project->name],
            'po_value'    => $poValue,
            'received'    => $received,
            'outstanding' => max(0, $poValue - $received),
        ];
    }

    /**
     * Rincian per-item pengeluaran (Cost Request) untuk sebuah Project —
     * beda dengan getRealization() yang cuma agregat total.
     */
    public function getProjectCost(?string $projectQuery, ?string $category, ?string $period, ?array $scopedProjectIds): array
    {
        $resolved = $this->resolveProject($projectQuery, $scopedProjectIds);
        if ($resolved['error']) {
            return $resolved['error'];
        }
        $project = $resolved['project'];
        if (!$project) {
            return ['found' => false, 'message' => 'Sebutkan nama atau kode Project yang dimaksud.'];
        }

        $query = BudgetRequest::with('type:id,name')
            ->whereHas('budgetPlan', fn ($q) => $q->where('project_id', $project->id))
            ->whereNotIn('status', ['DRAFT', 'REJECTED']);

        if (filled($category)) {
            $query->whereHas('type', fn ($q) => $q->where('name', 'like', "%{$category}%"));
        }

        if ($range = $this->periodRange($period)) {
            $query->whereBetween('created_at', $range);
        }

        $items = $query->orderByDesc('created_at')->limit(30)->get();

        return [
            'found'       => true,
            'project'     => ['code' => $project->code, 'name' => $project->name],
            'count'       => $items->count(),
            'total_value' => (int) $items->sum('amount'),
            'items'       => $items->map(fn ($r) => [
                'cr_id'       => $r->id,
                'code'        => $r->code,
                'description' => $r->description,
                'category'    => $r->type->name ?? '-',
                'amount'      => (int) $r->amount,
                'status'      => $r->status_label,
            ])->all(),
        ];
    }

    /**
     * Cari item pengeluaran spesifik berdasarkan kata kunci bebas di kolom
     * notes/description baris Cost Request (RequestDetail) DAN realisasinya
     * (BudgetRequestRealization) — beda dari getProjectCost() yang butuh
     * nama Project dulu, ini bisa lintas Project MAUPUN lintas Cost Center
     * (overhead/payroll) berdasarkan isi catatan transaksi.
     *
     * Akses per-baris dicek lewat BudgetRequest::isAccessibleBy($user) —
     * method yang SAMA PERSIS dipakai halaman Cost Request — supaya aturan
     * Project/Access Group/Payroll/Overhead konsisten di seluruh aplikasi,
     * tidak diduplikasi jadi aturan baru khusus AI yang bisa "meleset".
     *
     * @param bool $projectOnly Kalau true, buang hasil berbasis Cost Center
     *             (overhead/payroll) SEKALIPUN isAccessibleBy() mengizinkan —
     *             dipakai untuk role yang sengaja dibatasi cuma boleh lihat
     *             data berbasis Project/PO (Head, Manager).
     */
    public function searchExpenseItems(string $keyword, ?string $projectQuery, ?array $scopedProjectIds, User $user, bool $projectOnly = false): array
    {
        $project = null;
        if (filled($projectQuery)) {
            $resolved = $this->resolveProject($projectQuery, $scopedProjectIds);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $project = $resolved['project'];
        }

        // Kata kunci dipecah per-kata. TAHAP 1 (presisi): semua kata WAJIB
        // muncul (AND) — supaya "angsuran byd" tetap cocok dengan "ANGSURAN
        // MOBIL BYD" (ada kata sisipan). TAHAP 2 (fallback kalau tahap 1
        // kosong): salah satu kata SAJA cukup (OR), diranking berdasarkan
        // jumlah kata yang cocok — supaya sinonim/paraphrase user (mis.
        // "tiket bis" untuk data yang tercatat sebagai "akomodasi") tetap
        // ketemu selama ada kata kunci lain yang match (mis. nama tempat).
        $words = collect(preg_split('/\s+/', trim($keyword)))->filter()->values();

        $buildMatcher = function ($query, bool $matchAll) use ($words) {
            foreach ($words as $word) {
                $clause = fn ($wq) => $wq->where('description', 'like', "%{$word}%")
                    ->orWhere('notes', 'like', "%{$word}%")
                    ->orWhereHas('budgetRequest', fn ($bq) => $bq->where('description', 'like', "%{$word}%")->orWhere('code', 'like', "%{$word}%"));

                $matchAll ? $query->where($clause) : $query->orWhere($clause);
            }
        };

        $isAccessible = fn ($row) => $row->budgetRequest
            && $row->budgetRequest->isAccessibleBy($user)
            && (!$projectOnly || $row->budgetRequest->budgetPlan->project_id !== null);

        $scoreText = fn ($text) => $words->filter(fn ($w) => str_contains(mb_strtolower($text), mb_strtolower($w)))->count();

        $fetch = function (string $model, bool $matchAll) use ($buildMatcher, $project, $isAccessible) {
            $query = $model::with($model === RequestDetail::class
                ? ['budgetRequest.budgetPlan.project', 'budgetRequest.budgetPlan.costCenter', 'budgetRequest.applicant:id,username']
                : ['requestDetail', 'budgetRequest.budgetPlan.project', 'budgetRequest.budgetPlan.costCenter', 'budgetRequest.applicant:id,username'])
                ->where(fn ($q) => $buildMatcher($q, $matchAll));
            if ($project) {
                $query->whereHas('budgetRequest.budgetPlan', fn ($q) => $q->where('project_id', $project->id));
            }
            return $query->orderByDesc('id')->limit($matchAll ? 60 : 150)->get()->filter($isAccessible);
        };

        $requestItems     = $fetch(RequestDetail::class, true)->take(20);
        $realizationItems = $fetch(BudgetRequestRealization::class, true)->take(20);
        $usedFallback      = false;

        // Fallback TAHAP 2: hanya dijalankan kalau tahap 1 (AND) kosong DAN
        // ada lebih dari 1 kata kunci (kalau cuma 1 kata, AND == OR, percuma diulang).
        // Ambang batas MINIMAL 2 kata kunci cocok (bukan cuma 1) — supaya tidak
        // muncul hasil yang cuma kebetulan menyebut SATU kata umum (mis. cuma
        // "Aceh" tanpa "Jakarta"-nya), yang sering kali sebenarnya tidak relevan.
        if ($requestItems->isEmpty() && $realizationItems->isEmpty() && $words->count() > 1) {
            $usedFallback = true;
            $minScore     = min(2, $words->count());

            $rank = fn ($rows, callable $text) => $rows
                ->map(fn ($row) => ['row' => $row, 'score' => $scoreText($text($row))])
                ->filter(fn ($r) => $r['score'] >= $minScore)
                ->sortByDesc('score')
                ->pluck('row')
                ->values();

            $requestItems = $rank(
                $fetch(RequestDetail::class, false),
                fn ($d) => $d->description . ' ' . $d->notes . ' ' . ($d->budgetRequest->description ?? '')
            )->take(20);

            $realizationItems = $rank(
                $fetch(BudgetRequestRealization::class, false),
                fn ($r) => $r->notes . ' ' . ($r->budgetRequest->description ?? '')
            )->take(20);
        }

        if ($requestItems->isEmpty() && $realizationItems->isEmpty()) {
            return [
                'found'   => false,
                'message' => "Tidak ditemukan item pengeluaran dengan kata kunci \"{$keyword}\"" . ($project ? " di project \"{$project->name}\"" : '') . '.',
            ];
        }

        return [
            'found'   => true,
            'keyword' => $keyword,
            // 'presisi': semua kata kunci cocok, hasil dapat dipercaya langsung.
            // 'longgar': fallback, cuma sebagian kata kunci cocok — item BISA JADI
            // tidak benar-benar relevan (kebetulan menyebut sebagian kata saja),
            // AI WAJIB evaluasi dulu sebelum menyampaikan sebagai jawaban pasti.
            'match_type' => $usedFallback ? 'longgar' : 'presisi',
            'request_items' => $requestItems->map(fn ($d) => [
                'cr_id'          => $d->budgetRequest->id ?? null,
                'cr_code'        => $d->budgetRequest->code ?? '-',
                'cr_title'       => $d->budgetRequest->description ?? '-',
                'source'         => $d->budgetRequest->budgetPlan->project->name ?? ($d->budgetRequest->budgetPlan->costCenter->name ?? '-'),
                'description'    => $d->description,
                'notes'          => $d->notes,
                'amount'         => (int) $d->total,
                'cr_status'      => $d->budgetRequest->status_label ?? '-',
                'submitted_date' => optional($d->budgetRequest?->submitted_at ?? $d->budgetRequest?->created_at)->format('Y-m-d'),
            ])->values()->all(),
            'realization_items' => $realizationItems->map(fn ($r) => [
                'cr_id'       => $r->budgetRequest->id ?? null,
                'cr_code'     => $r->budgetRequest->code ?? '-',
                'cr_title'    => $r->budgetRequest->description ?? '-',
                'source'      => $r->budgetRequest->budgetPlan->project->name ?? ($r->budgetRequest->budgetPlan->costCenter->name ?? '-'),
                'description' => $r->description ?? optional($r->requestDetail)->description,
                'notes'       => $r->notes,
                'amount'      => (int) $r->total,
                'realized_date' => optional($r->created_at)->format('Y-m-d'),
            ])->values()->all(),
        ];
    }

    public function getProjectSummary(?string $projectQuery, ?array $scopedProjectIds): array
    {
        $resolved = $this->resolveProject($projectQuery, $scopedProjectIds);
        if ($resolved['error']) {
            return $resolved['error'];
        }
        $project = $resolved['project'];
        if (!$project) {
            return ['found' => false, 'message' => 'Sebutkan nama atau kode Project yang dimaksud.'];
        }

        $budget      = $project->budgetPlanTotal();
        $realization = $project->costRealizationTotal();
        $received    = $project->paymentTotal();
        $poValue     = (int) ($project->value ?? 0);

        return [
            'found'                   => true,
            'project'                 => [
                'code'     => $project->code,
                'name'     => $project->name,
                'status'   => $project->status_label,
                'customer' => $project->customer->name ?? '-',
            ],
            'po_value'                => $poValue,
            'budget'                  => $budget,
            'realization'             => $realization,
            'realization_percentage'  => $budget > 0 ? round(($realization / $budget) * 100, 1) : 0,
            'received'                => $received,
            'outstanding'             => max(0, $poValue - $received),
            'gross_profit'            => $project->grossProfit(),
            'gross_margin'            => $project->grossMargin(),
        ];
    }

    /**
     * Daftar Project/PO yang bisa diakses user — dipakai untuk pertanyaan
     * "project apa saja yang bisa aku akses?", dan sebagai referensi supaya
     * AI bisa bedakan "data tidak ada" vs "project di luar akses user"
     * daripada langsung menyimpulkan "tidak ditemukan".
     */
    public function listAccessibleProjects(?array $scopedProjectIds): array
    {
        if (is_array($scopedProjectIds) && empty($scopedProjectIds)) {
            return [
                'found'   => true,
                'count'   => 0,
                'message' => 'User ini belum memiliki akses ke Project/PO mana pun — Access Group-nya kosong atau belum diatur. Hubungi admin untuk pengaturan Access Group.',
                'projects' => [],
            ];
        }

        $query = Project::query()->select('id', 'code', 'name', 'status');
        if (is_array($scopedProjectIds)) {
            $query->whereIn('id', $scopedProjectIds);
        }

        $limit    = 50;
        $projects = $query->orderByDesc('created_at')->limit($limit)->get();

        return [
            'found'       => true,
            'count'       => $projects->count(),
            'truncated'   => $projects->count() >= $limit,
            'unrestricted' => !is_array($scopedProjectIds),
            'projects'    => $projects->map(fn ($p) => [
                'code'   => $p->code,
                'name'   => $p->name,
                'status' => $p->status_label,
            ])->all(),
        ];
    }

    /**
     * Status Purchase Order (kontrak ke customer) — beda dari getBudget()
     * yang soal anggaran internal. Reuse method yang sudah dipakai halaman
     * Purchase Order (Project::workProgressStatus(), dst), bukan kalkulasi baru.
     *
     * @param bool $hideNominal Kalau true, nominal per No. PO disembunyikan
     *             (role yang tidak boleh lihat nilai kontrak PO — sama seperti
     *             po_value pada getBudget()).
     */
    public function getPurchaseOrder(?string $projectQuery, ?array $scopedProjectIds, bool $hideNominal = false): array
    {
        $resolved = $this->resolveProject($projectQuery, $scopedProjectIds);
        if ($resolved['error']) {
            return $resolved['error'];
        }
        $project = $resolved['project'];
        if (!$project) {
            return ['found' => false, 'message' => 'Sebutkan nama atau kode Project yang dimaksud.'];
        }

        $poNumbers = $project->poNumbers()->get();

        return [
            'found'            => true,
            'project'          => [
                'code'      => $project->code,
                'name'      => $project->name,
                'status'    => $project->status_label,
                'financing' => $project->financing_label,
            ],
            'work_progress'    => Project::progressBadge($project->workProgressStatus())['label'],
            'billing_progress' => Project::progressBadge($project->billingProgressStatus())['label'],
            'payment_progress' => Project::progressBadge($project->paymentProgressStatus())['label'],
            'po_numbers'       => $poNumbers->map(fn ($p) => array_filter([
                'po_number'    => $p->po_number,
                'nominal'      => $hideNominal ? null : (int) $p->nominal,
                'status'       => $p->status_label,
                'release_date' => optional($p->release_date)->format('Y-m-d'),
                'bast_date'    => optional($p->tanggal_bast)->format('Y-m-d'),
            ], fn ($v) => $v !== null))->values()->all(),
        ];
    }

    /** Terima format YYYY-MM-DD atau YYYY-MM, kembalikan null kalau tidak valid/kosong. */
    private function parseFlexibleDate(?string $value, bool $endOfPeriod = false): ?Carbon
    {
        if (blank($value)) {
            return null;
        }
        try {
            if (preg_match('/^\d{4}-\d{2}$/', $value)) {
                $date = Carbon::createFromFormat('Y-m', $value);
                return $endOfPeriod ? $date->endOfMonth() : $date->startOfMonth();
            }
            $date = Carbon::parse($value);
            return $endOfPeriod ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Arus kas PERUSAHAAN (bukan per-project) — kas masuk dari CashReceipt,
     * kas keluar dari DailyTransaction berstatus PAID. Reuse sumber data yang
     * sama dengan chart Cash Receipt vs Cash Out di Finance Summary Executive.
     */
    public function getCashflow(?string $periodStart, ?string $periodEnd): array
    {
        $start = $this->parseFlexibleDate($periodStart) ?? now()->startOfYear();
        $end   = $this->parseFlexibleDate($periodEnd, true) ?? now()->endOfDay();

        // Jaga-jaga kalau AI terbalik mengirim start/end (mis. "bulan ini"
        // ditafsirkan jadi tanggal akhir bulan sebagai start) — daripada diam-diam
        // mengembalikan Rp0 karena BETWEEN dengan batas terbalik, tukar saja.
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $cashInReceipts      = CashReceipt::whereBetween('received_date', [$start, $end])->orderByDesc('received_date')->limit(30)->get();
        $cashOutTransactions = DailyTransaction::where('status', 'PAID')->whereBetween('date', [$start, $end])->orderByDesc('date')->limit(30)->get();

        $cashIn  = (int) CashReceipt::whereBetween('received_date', [$start, $end])->sum('amount');
        $cashOut = (int) DailyTransaction::where('status', 'PAID')->whereBetween('date', [$start, $end])->sum('captured_amount');

        return [
            'found'        => true,
            'period_start' => $start->format('Y-m-d'),
            'period_end'   => $end->format('Y-m-d'),
            'cash_in'      => $cashIn,
            'cash_out'     => $cashOut,
            'net_cashflow' => $cashIn - $cashOut,
            // Rincian per-transaksi (dibatasi 30 baris terbaru) — dipakai kalau
            // user minta "rincian"/"apa saja" untuk kas masuk/keluar, bukan cuma total.
            'cash_in_items'  => $cashInReceipts->map(fn ($r) => [
                'date'   => optional($r->received_date)->format('Y-m-d'),
                'amount' => (int) $r->amount,
            ])->all(),
            'cash_out_items' => $cashOutTransactions->map(fn ($t) => [
                'transaction_id' => $t->id,
                // SENGAJA bukan "cr_code"/"code" polos — ini kode BATCH Daily
                // Transaction (Treasury), BUKAN kode Cost Request. Jangan
                // ditambahi prefix "CR-" apa pun, itu bukan format CR.
                'batch_code'  => $t->code,
                'date'        => optional($t->date)->format('Y-m-d'),
                'description' => $t->description,
                'amount'      => (int) $t->captured_amount,
            ])->all(),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\Employee;
use App\Models\BudgetPlan;
use App\Models\Project;
use App\Models\User;
use App\Services\NotifyService;

class PayrollRunController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | KONFIGURASI
    |--------------------------------------------------------------------------
    | Payroll bersifat rahasia. Yang boleh mengakses modul ini:
    |   hr, finance_manager, approver (CEO), superadmin.
    | Privasi nilai gaji ditegakkan lebih lanjut di tahap 5c.
    */
    private const PAYROLL_ROLES = ['superadmin', 'approver', 'hr', 'finance_manager', 'guest'];

    /*
    | Hak per aksi workflow:
    |   submit  : HR
    |   verify  : Finance Manager
    |   approve : CEO (approver)
    |   reject  : Finance Manager atau CEO
    | superadmin selalu boleh.
    */
    private const SUBMIT_ROLES  = ['superadmin', 'hr'];
    private const VERIFY_ROLES  = ['superadmin', 'finance_manager'];
    private const APPROVE_ROLES = ['superadmin', 'approver'];
    private const REJECT_ROLES  = ['superadmin', 'finance_manager', 'approver'];

    /** Yang mengeksekusi & menandai pembayaran payroll */
    private const PAY_ROLES     = ['superadmin', 'finance_manager'];

    private function role(): string
    {
        return strtolower(Auth::user()->role->name ?? '');
    }

    private function guardAccess(): void
    {
        $role = strtolower(Auth::user()->role->name ?? '');
        abort_unless(in_array($role, self::PAYROLL_ROLES), 403,
            'Anda tidak memiliki akses ke modul Payroll.');
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $this->guardAccess();

        $runs = PayrollRun::with(['budgetPlan.costCenter', 'lines'])
            ->latest()
            ->get();

        $summary = [
            'total'     => $runs->count(),
            'draft'     => $runs->where('status', 'DRAFT')->count(),
            'submitted' => $runs->where('status', 'SUBMITTED')->count(),
            'verified'  => $runs->where('status', 'VERIFIED')->count(),
            'approved'  => $runs->where('status', 'APPROVED')->count(),
            'paid'      => $runs->where('status', 'PAID')->count(),
            'closed'    => $runs->where('status', 'CLOSED')->count(),
            'rejected'  => $runs->where('status', 'REJECTED')->count(),
        ];

        return view('payroll-run.index', compact('runs', 'summary'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $this->guardAccess();

        return view('payroll-run.create', $this->formData());
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $this->guardAccess();

        $validated = $this->validateRun($request);

        DB::transaction(function () use ($request, $validated) {
            $run = PayrollRun::create([
                'code'           => PayrollRun::generateCode(),
                'period'         => $validated['period'],
                'description'    => strtoupper(trim($validated['description'] ?? '')) ?: null,
                'budget_plan_id' => $validated['budget_plan_id'] ?? null,
                'status'         => 'DRAFT',
                'total'          => 0,
                'notes'          => $request->notes,
                'input_by'       => Auth::id(),
            ]);

            $this->syncLines($run, $request);
            $run->recalcTotal();
        });

        return redirect('/payroll-run')
            ->with('success', 'Payroll run berhasil dibuat.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $this->guardAccess();

        $run = PayrollRun::with([
            'budgetPlan.costCenter',
            'lines.employee',
            'lines.bank',
            'lines.chargeProject',
            'inputBy', 'submittedBy', 'verifiedBy', 'approvedBy',
        ])->findOrFail($id);

        $perms = $this->permissions($run);

        return view('payroll-run.show', compact('run', 'perms'));
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $this->guardAccess();

        $run = PayrollRun::with(['lines.employee'])->findOrFail($id);

        if (!$run->isEditable()) {
            return redirect('/payroll-run')
                ->with('error', 'Payroll run hanya dapat diedit saat berstatus DRAFT.');
        }

        return view('payroll-run.edit', array_merge(['run' => $run], $this->formData()));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $this->guardAccess();

        $run = PayrollRun::findOrFail($id);

        if (!$run->isEditable()) {
            return redirect('/payroll-run')
                ->with('error', 'Payroll run hanya dapat diedit saat berstatus DRAFT.');
        }

        $validated = $this->validateRun($request);

        DB::transaction(function () use ($request, $validated, $run) {
            $run->update([
                'period'         => $validated['period'],
                'description'    => strtoupper(trim($validated['description'] ?? '')) ?: null,
                'budget_plan_id' => $validated['budget_plan_id'] ?? null,
                'notes'          => $request->notes,
            ]);

            // Delete-all-then-recreate (pola child-row TESYS)
            $run->lines()->delete();
            $this->syncLines($run, $request);
            $run->recalcTotal();
        });

        return redirect('/payroll-run')
            ->with('success', 'Payroll run berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW: submit -> verify -> approve  (+ reject)
    |--------------------------------------------------------------------------
    */

    public function submit($id)
    {
        $this->guardAccess();
        $run = PayrollRun::findOrFail($id);

        abort_unless(in_array($this->role(), self::SUBMIT_ROLES), 403,
            'Hanya HR yang dapat submit payroll.');

        if ($run->status !== 'DRAFT') {
            return back()->with('error', 'Payroll hanya bisa disubmit dari status DRAFT.');
        }
        if ($run->lines()->count() === 0) {
            return back()->with('error', 'Payroll belum memiliki karyawan.');
        }

        $run->update([
            'status'       => 'SUBMITTED',
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
            'reject_notes' => null,
        ]);

        NotifyService::send(
            $this->usersWithRole('finance_manager'),
            'Payroll Run menunggu verifikasi',
            Auth::user()->name . ' mengajukan Payroll Run "' . $run->code . '" untuk diverifikasi.',
            route('payroll-run.index'),
            'workflow'
        );

        return redirect('/payroll-run/' . $run->id)
            ->with('success', 'Payroll berhasil disubmit. Menunggu verifikasi Finance Manager.');
    }

    /** Role — penerima notifikasi tiap tahap workflow payroll run. */
    private function usersWithRole(string ...$roles)
    {
        return User::whereHas('role', fn ($r) => $r->whereRaw(
            'LOWER(name) IN (' . implode(',', array_fill(0, count($roles), '?')) . ')',
            array_map('strtolower', $roles)
        ))->where('status', 'ACTIVE')->get();
    }

    public function verify($id)
    {
        $this->guardAccess();
        $run = PayrollRun::findOrFail($id);

        abort_unless(in_array($this->role(), self::VERIFY_ROLES), 403,
            'Hanya Finance Manager yang dapat memverifikasi payroll.');

        if ($run->status !== 'SUBMITTED') {
            return back()->with('error', 'Payroll hanya bisa diverifikasi dari status SUBMITTED.');
        }

        $run->update([
            'status'      => 'VERIFIED',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        NotifyService::send(
            $this->usersWithRole('approver'),
            'Payroll Run menunggu persetujuan',
            Auth::user()->name . ' memverifikasi Payroll Run "' . $run->code . '" — menunggu persetujuan.',
            route('payroll-run.index'),
            'workflow'
        );

        return redirect('/payroll-run/' . $run->id)
            ->with('success', 'Payroll terverifikasi. Menunggu persetujuan CEO.');
    }

    public function approve($id)
    {
        $this->guardAccess();
        $run = PayrollRun::findOrFail($id);

        abort_unless(in_array($this->role(), self::APPROVE_ROLES), 403,
            'Hanya CEO yang dapat menyetujui payroll.');

        if ($run->status !== 'VERIFIED') {
            return back()->with('error', 'Payroll hanya bisa disetujui dari status VERIFIED.');
        }

        $run->update([
            'status'      => 'APPROVED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        if ($run->submittedBy) {
            NotifyService::send(
                [$run->submittedBy],
                'Payroll Run disetujui',
                'Payroll Run "' . $run->code . '" telah disetujui dan siap diproses pembayaran.',
                route('payroll-run.index'),
                'workflow'
            );
        }

        // Catatan: pembentukan batch Daily Transaction dikerjakan di tahap 5d.
        return redirect('/payroll-run/' . $run->id)
            ->with('success', 'Payroll disetujui. Siap diproses pembayaran.');
    }

    public function reject(Request $request, $id)
    {
        $this->guardAccess();
        $run = PayrollRun::findOrFail($id);

        abort_unless(in_array($this->role(), self::REJECT_ROLES), 403,
            'Anda tidak berhak menolak payroll.');

        if (!in_array($run->status, ['SUBMITTED', 'VERIFIED'])) {
            return back()->with('error', 'Payroll hanya bisa ditolak saat SUBMITTED atau VERIFIED.');
        }

        $request->validate(['reject_notes' => 'nullable|string|max:1000']);

        $run->update([
            'status'       => 'REJECTED',
            'reject_notes' => $request->reject_notes,
        ]);

        if ($run->submittedBy) {
            NotifyService::send(
                [$run->submittedBy],
                'Payroll Run ditolak',
                'Payroll Run "' . $run->code . '" ditolak' . ($request->reject_notes ? ': ' . $request->reject_notes : '.'),
                route('payroll-run.index'),
                'workflow'
            );
        }

        return redirect('/payroll-run/' . $run->id)
            ->with('success', 'Payroll ditolak.');
    }

    /** Kembalikan payroll yang REJECTED ke DRAFT agar bisa diperbaiki */
    public function reopen($id)
    {
        $this->guardAccess();
        $run = PayrollRun::findOrFail($id);

        abort_unless(in_array($this->role(), self::SUBMIT_ROLES), 403,
            'Hanya HR yang dapat membuka kembali payroll.');

        if ($run->status !== 'REJECTED') {
            return back()->with('error', 'Hanya payroll REJECTED yang bisa dibuka kembali.');
        }

        $run->update([
            'status'       => 'DRAFT',
            'submitted_by' => null, 'submitted_at' => null,
            'verified_by'  => null, 'verified_at'  => null,
            'approved_by'  => null, 'approved_at'  => null,
        ]);

        return redirect('/payroll-run/' . $run->id)
            ->with('success', 'Payroll dikembalikan ke DRAFT untuk diperbaiki.');
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT BANK — langsung dari payroll_run_lines (opsi B)
    |--------------------------------------------------------------------------
    | Tidak membuat record Daily Transaction. Data gaji tidak pernah masuk
    | tabel finance, jadi tak bisa dilihat finance staff.
    | Format 10 kolom SAMA dengan export Daily Transaction.
    */

    public function export($id)
    {
        $this->guardAccess();

        $run = PayrollRun::with(['lines.bank', 'lines.employee'])->findOrFail($id);

        // Hanya boleh export setelah disetujui
        if (!in_array($run->status, ['APPROVED', 'PAID', 'CLOSED'])) {
            return back()->with('error', 'Export hanya tersedia untuk payroll yang sudah APPROVED.');
        }

        $filename = 'payroll_' . $run->code . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ];

        $columns = [
            'Transfer Method',
            'Account Number',
            'Currency',
            'Transfer Amount',
            'Beneficiary',
            'Bank',
            'Kode Bank',
            'Reference',
            'Remark',
            'Email',
        ];

        $callback = function () use ($run, $columns) {
            $out = fopen('php://output', 'w');

            // BOM agar Excel membaca UTF-8 dengan benar
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, $columns);

            foreach ($run->lines as $line) {
                $bank = $line->bank;

                fputcsv($out, [
                    $bank->default_method ?? '',            // Transfer Method
                    $line->account_number ?? '',            // Account Number (snapshot)
                    'IDR',                                   // Currency
                    (int) ($line->net_amount ?? 0),         // Transfer Amount
                    $line->employee_name ?? '',             // Beneficiary (snapshot nama)
                    $bank->name ?? '',                      // Bank
                    $bank->code ?? '',                      // Kode Bank
                    $run->code ?? '',                       // Reference (kode payroll run)
                    $run->period_label ?? '',               // Remark (periode gaji)
                    optional($line->employee)->email ?? '', // Email (best-effort dari master)
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /*
    |--------------------------------------------------------------------------
    | PEMBAYARAN: APPROVED -> PAID -> CLOSED
    |--------------------------------------------------------------------------
    */

    public function markPaid($id)
    {
        $this->guardAccess();
        $run = PayrollRun::findOrFail($id);

        abort_unless(in_array($this->role(), self::PAY_ROLES), 403,
            'Anda tidak berhak menandai pembayaran payroll.');

        if ($run->status !== 'APPROVED') {
            return back()->with('error', 'Hanya payroll APPROVED yang bisa ditandai PAID.');
        }

        $run->update(['status' => 'PAID']);

        return redirect('/payroll-run/' . $run->id)
            ->with('success', 'Payroll ditandai sudah dibayar.');
    }

    public function close($id)
    {
        $this->guardAccess();
        $run = PayrollRun::findOrFail($id);

        abort_unless(in_array($this->role(), self::PAY_ROLES), 403,
            'Anda tidak berhak menutup payroll.');

        if ($run->status !== 'PAID') {
            return back()->with('error', 'Hanya payroll PAID yang bisa ditutup.');
        }

        $run->update(['status' => 'CLOSED']);

        return redirect('/payroll-run/' . $run->id)
            ->with('success', 'Payroll ditutup (CLOSED).');
    }

    /** Flag hak aksi untuk view */
    private function permissions(PayrollRun $run): array
    {
        $role = $this->role();
        return [
            'can_submit'  => $run->status === 'DRAFT'      && in_array($role, self::SUBMIT_ROLES),
            'can_verify'  => $run->status === 'SUBMITTED'  && in_array($role, self::VERIFY_ROLES),
            'can_approve' => $run->status === 'VERIFIED'   && in_array($role, self::APPROVE_ROLES),
            'can_reject'  => in_array($run->status, ['SUBMITTED', 'VERIFIED']) && in_array($role, self::REJECT_ROLES),
            'can_reopen'  => $run->status === 'REJECTED'   && in_array($role, self::SUBMIT_ROLES),
            'can_export'  => in_array($run->status, ['APPROVED', 'PAID', 'CLOSED']),
            'can_pay'     => $run->status === 'APPROVED'   && in_array($role, self::PAY_ROLES),
            'can_close'   => $run->status === 'PAID'       && in_array($role, self::PAY_ROLES),
        ];
    }

    public function destroy($id)
    {
        $this->guardAccess();

        $run = PayrollRun::findOrFail($id);

        if (!$run->isEditable()) {
            return redirect('/payroll-run')
                ->with('error', 'Hanya payroll run berstatus DRAFT yang dapat dihapus.');
        }

        $run->lines()->delete();
        $run->delete();

        return redirect('/payroll-run')
            ->with('success', 'Payroll run berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /** Data bersama untuk form create/edit */
    private function formData(): array
    {
        // Budget payroll yang ACTIVE
        $budgetPlans = BudgetPlan::where('status', 'ACTIVE')
            ->whereHas('costCenter', fn ($c) => $c->where('type', 'PAYROLL')->where('status', 'ACTIVE'))
            ->with('costCenter')
            ->orderBy('code')
            ->get();

        // Karyawan aktif + data untuk auto-isi & snapshot
        $employees = Employee::active()
            ->with('bank')
            ->orderBy('name')
            ->get()
            ->map(fn ($e) => [
                'id'             => $e->id,
                'name'           => $e->name,
                'position'       => $e->ptkp_status,   // sementara: tak ada kolom jabatan khusus
                'default_salary' => (int) $e->default_salary,
                'bank_id'        => $e->bank_id,
                'bank_name'      => $e->bank->name ?? '',
                'account_name'   => $e->account_name,
                'account_number' => $e->account_number,
            ])
            ->values();

        // Proyek untuk pembebanan (charge_type = PROJECT)
        $projects = Project::orderBy('name')->get(['id', 'code', 'name']);

        return compact('budgetPlans', 'employees', 'projects');
    }

    private function validateRun(Request $request): array
    {
        return $request->validate([
            'period'         => 'required|date_format:Y-m',
            'description'    => 'nullable|string|max:255',
            'budget_plan_id' => 'nullable|exists:budget_plans,id',
            'notes'          => 'nullable|string',
            'lines'          => 'required|array|min:1',
            'lines.*.employee_id' => 'required|exists:employees,id',
            'lines.*.net_amount'  => 'required',
            'lines.*.charge_type' => 'required|in:OVERHEAD,PROJECT',
            'lines.*.charge_project_id' => 'nullable|exists:projects,id',
        ], [
            'period.date_format' => 'Periode harus dalam format tahun-bulan (YYYY-MM).',
            'lines.required'     => 'Minimal satu baris karyawan.',
        ]);
    }

    /** Buat baris payroll dari input, dengan snapshot data karyawan */
    private function syncLines(PayrollRun $run, Request $request): void
    {
        foreach ((array) $request->input('lines', []) as $row) {
            if (empty($row['employee_id'])) continue;

            $employee = Employee::with('bank')->find($row['employee_id']);
            if (!$employee) continue;

            $chargeType = ($row['charge_type'] ?? 'OVERHEAD') === 'PROJECT' ? 'PROJECT' : 'OVERHEAD';

            $run->lines()->create([
                'employee_id'       => $employee->id,
                'employee_name'     => $employee->name,               // snapshot
                'position'          => $employee->ptkp_status,        // snapshot (sementara)
                'bank_id'           => $employee->bank_id,            // snapshot rekening
                'account_name'      => $employee->account_name,
                'account_number'    => $employee->account_number,
                'net_amount'        => $this->normalizeMoney($row['net_amount'] ?? 0),
                'charge_type'       => $chargeType,
                'charge_project_id' => $chargeType === 'PROJECT' ? ($row['charge_project_id'] ?? null) : null,
                'notes'             => $row['notes'] ?? null,
            ]);
        }
    }

    private function normalizeMoney($value): int
    {
        if ($value === null || $value === '') return 0;
        return (int) preg_replace('/\D/', '', (string) $value);
    }
}
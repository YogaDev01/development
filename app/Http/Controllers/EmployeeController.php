<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Employee;
use App\Models\Bank;

class EmployeeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $employees = Employee::with('bank')
            ->orderBy('name')
            ->get();

        $summary = [
            'total'    => $employees->count(),
            'active'   => $employees->where('status', 'ACTIVE')->count(),
            'inactive' => $employees->where('status', 'INACTIVE')->count(),
        ];

        return view('employee.index', compact('employees', 'summary'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $banks        = Bank::orderBy('name')->get();
        $ptkpStatuses = Employee::PTKP_STATUSES;

        return view('employee.create', compact('banks', 'ptkpStatuses'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $data['name']           = strtoupper(trim($data['name']));
        $data['account_name']   = !empty($data['account_name'])
            ? strtoupper(trim($data['account_name']))
            : null;
        $data['default_salary'] = $this->normalizeMoney($request->input('default_salary'));
        $data['status']         = 'ACTIVE';
        $data['input_by']       = Auth::id();

        Employee::create($data);

        return redirect('/employee')
            ->with('success', 'Karyawan berhasil ditambahkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $employee     = Employee::findOrFail($id);
        $banks        = Bank::orderBy('name')->get();
        $ptkpStatuses = Employee::PTKP_STATUSES;

        return view('employee.edit', compact('employee', 'banks', 'ptkpStatuses'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $this->validateData($request, $employee->id);

        $data['name']           = strtoupper(trim($data['name']));
        $data['account_name']   = !empty($data['account_name'])
            ? strtoupper(trim($data['account_name']))
            : null;
        $data['default_salary'] = $this->normalizeMoney($request->input('default_salary'));

        $employee->update($data);

        return redirect('/employee')
            ->with('success', 'Data karyawan berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $employee = Employee::withCount('payrollLines')->findOrFail($id);

        // Jangan hapus bila sudah pernah masuk payroll — nonaktifkan saja,
        // agar histori payroll tetap utuh.
        if ($employee->payroll_lines_count > 0) {
            return redirect('/employee')->with(
                'error',
                'Karyawan tidak dapat dihapus karena sudah tercatat di '
                . $employee->payroll_lines_count . ' baris payroll. Nonaktifkan saja.'
            );
        }

        $employee->delete();

        return redirect('/employee')
            ->with('success', 'Karyawan berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW JSON (modal detail)
    |--------------------------------------------------------------------------
    */

    public function showJson($id)
    {
        $e = Employee::with(['bank', 'inputBy'])->findOrFail($id);

        return response()->json([
            'id'              => $e->id,
            'employee_number' => $e->employee_number ?? '-',
            'name'            => $e->name,
            'birth_place'     => $e->birth_place ?? '-',
            'birth_date'      => $e->birth_date?->format('d M Y') ?? '-',
            'nik'             => $e->nik ?? '-',
            'gender'          => $e->gender_label,
            'address'         => $e->address ?? '-',
            'domicile'        => $e->domicile ?? '-',
            'phone'           => $e->phone ?? '-',
            'email'           => $e->email ?? '-',
            'npwp'            => $e->npwp ?? '-',
            'ptkp_status'     => $e->ptkp_status ?? '-',
            'bank'            => $e->bank->name ?? '-',
            'account_name'    => $e->account_name ?? '-',
            'account_number'  => $e->account_number ?? '-',
            'default_salary'  => $e->default_salary_formatted,
            'status'          => $e->status,
            'status_color'    => $e->status_color,
            'notes'           => $e->notes ?? '-',
            'input_by'        => $e->inputBy->name ?? '-',
            'created_at'      => $e->created_at?->format('d M Y H:i') ?? '-',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $uniqueNo = 'nullable|string|max:100|unique:employees,employee_number';
        if ($ignoreId) {
            $uniqueNo .= ',' . $ignoreId;
        }

        $ptkpList = implode(',', Employee::PTKP_STATUSES);

        return $request->validate([
            'employee_number' => $uniqueNo,
            'name'            => 'required|string|max:255',
            'birth_place'     => 'nullable|string|max:255',
            'birth_date'      => 'nullable|date',
            'nik'             => 'nullable|string|max:50',
            'gender'          => 'nullable|in:L,P',
            'address'         => 'nullable|string',
            'domicile'        => 'nullable|string',
            'phone'           => 'nullable|string|max:50',
            'email'           => 'nullable|email|max:255',
            'npwp'            => 'nullable|string|max:50',
            'ptkp_status'     => 'nullable|in:' . $ptkpList,
            'bank_id'         => 'nullable|exists:banks,id',
            'account_name'    => 'nullable|string|max:255',
            'account_number'  => 'nullable|string|max:100',
            'default_salary'  => 'nullable',
            'status'          => 'nullable|in:ACTIVE,INACTIVE',
            'notes'           => 'nullable|string',
        ]);
    }

    /** Ubah "Rp 5.000.000" / "5000000" menjadi integer */
    private function normalizeMoney($value): int
    {
        if ($value === null || $value === '') return 0;
        return (int) preg_replace('/\D/', '', (string) $value);
    }
}
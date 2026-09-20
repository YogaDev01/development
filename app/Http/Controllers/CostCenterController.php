<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\CostCenter;
use App\Models\BudgetPlan;

class CostCenterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $costCenters = CostCenter::withCount('budgetPlans')
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $summary = [
            'total'    => $costCenters->count(),
            'overhead' => $costCenters->where('type', 'OVERHEAD')->count(),
            'payroll'  => $costCenters->where('type', 'PAYROLL')->count(),
            'active'   => $costCenters->where('status', 'ACTIVE')->count(),
        ];

        return view('cost-center.index', compact('costCenters', 'summary'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'type'  => 'required|in:' . implode(',', CostCenter::TYPES),
            'notes' => 'nullable|string',
        ]);

        CostCenter::create([
            'code'     => CostCenter::generateNextCode($validated['type']),
            'name'     => strtoupper(trim($validated['name'])),
            'type'     => $validated['type'],
            'status'   => 'ACTIVE',   // cost center baru selalu ACTIVE
            'notes'    => $validated['notes'] ?? null,
            'input_by' => Auth::id(),
        ]);

        return redirect('/cost-center')
            ->with('success', 'Cost center berhasil ditambahkan.');
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $costCenter = CostCenter::findOrFail($id);

        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'status' => 'required|in:ACTIVE,INACTIVE',
            'notes'  => 'nullable|string',
        ]);

        // CATATAN: 'type' sengaja TIDAK bisa diubah setelah dibuat.
        // Mengubah OVERHEAD <-> PAYROLL akan membuat budget plan & kode
        // yang sudah menempel jadi tidak konsisten.
        $costCenter->update([
            'name'   => strtoupper(trim($validated['name'])),
            'status' => $validated['status'],
            'notes'  => $validated['notes'] ?? null,
        ]);

        return redirect('/cost-center')
            ->with('success', 'Cost center berhasil diupdate.');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $costCenter = CostCenter::withCount('budgetPlans')->findOrFail($id);

        // Jangan hapus bila sudah dipakai budget plan — akan menghasilkan
        // budget tanpa sumber dana (dan ditolak guard di model BudgetPlan).
        if ($costCenter->budget_plans_count > 0) {
            return redirect('/cost-center')->with(
                'error',
                'Cost center tidak dapat dihapus karena sudah dipakai '
                . $costCenter->budget_plans_count . ' budget plan. Nonaktifkan saja.'
            );
        }

        $costCenter->delete();

        return redirect('/cost-center')
            ->with('success', 'Cost center berhasil dihapus.');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW JSON (modal detail)
    |--------------------------------------------------------------------------
    */

    public function showJson($id)
    {
        $cc = CostCenter::with(['inputBy'])->findOrFail($id);

        $plans = BudgetPlan::where('cost_center_id', $cc->id)
            ->orderBy('code', 'desc')
            ->get();

        return response()->json([
            'id'           => $cc->id,
            'code'         => $cc->code,
            'name'         => $cc->name,
            'type'         => $cc->type,
            'type_color'   => $cc->type_color,
            'status'       => $cc->status,
            'status_color' => $cc->status_color,
            'notes'        => $cc->notes,
            'input_by'     => $cc->inputBy->name ?? '-',
            'created_at'   => $cc->created_at?->format('d M Y H:i') ?? '-',

            'budget_plans' => $plans->map(fn ($bp) => [
                'code'         => $bp->code,
                'description'  => $bp->description,
                'status'       => $bp->status,
                'status_color' => $bp->status_color,
                'amount'       => (int) ($bp->amount ?? 0),
                'used'         => (int) $bp->usedAmount(),
            ])->values(),
        ]);
    }
}
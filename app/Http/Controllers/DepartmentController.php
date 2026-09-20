<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Department;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::withCount('teams')->orderBy('name')->get();

        $summary = [
            'total'  => $departments->count(),
            'active' => $departments->where('status', 'ACTIVE')->count(),
        ];

        return view('department.index', compact('departments', 'summary'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $code = 'DEPT-' . str_pad((string) (Department::max('id') + 1), 3, '0', STR_PAD_LEFT);

        Department::create([
            'code'   => $code,
            'name'   => strtoupper(trim($validated['name'])),
            'status' => 'ACTIVE',
        ]);

        return redirect('/department')->with('success', 'Department berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $department->update([
            'name'   => strtoupper(trim($validated['name'])),
            'status' => $validated['status'],
        ]);

        return redirect('/department')->with('success', 'Department berhasil diupdate.');
    }

    public function destroy($id)
    {
        $department = Department::withCount('teams')->findOrFail($id);

        if ($department->teams_count > 0) {
            return redirect('/department')->with(
                'error',
                'Department tidak dapat dihapus karena masih dipakai ' . $department->teams_count . ' team. Nonaktifkan saja.'
            );
        }

        $department->delete();

        return redirect('/department')->with('success', 'Department berhasil dihapus.');
    }
}

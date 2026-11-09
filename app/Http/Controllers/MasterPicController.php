<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\MasterPic;
use App\Models\UserActivityLog;

class MasterPicController extends Controller
{
    /**
     * Role yang boleh MENGUBAH & MENGHAPUS PIC. Cermin lapis kedua
     * 'role:superadmin' pada route master-pic.edit-data/update/destroy.
     * Daftar (index) pindah ke PicBeneficiaryController sejak PIC &
     * Beneficiary digabung (2026-11-09).
     */
    public const MANAGE_ROLES = \App\Support\AccessRoles::PIC_MANAGE;

    /**
     * Penjaga kedua — middleware route sudah menghadang lebih dulu; diulang
     * supaya aturan "hanya lihat & tambah" tidak bergantung pada satu baris
     * berkas route (pola yang sama dengan BeneficiaryController).
     */
    private function pastikanBolehMengelola(): void
    {
        abort_unless(Auth::user()?->hasRole(self::MANAGE_ROLES) ?? false, 403, 'Unauthorized - Insufficient permissions');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type'        => 'required|in:' . implode(',', array_keys(MasterPic::TYPES)),
            'pic'         => 'required|string|max:255',
            'position'    => 'nullable|string|max:255',
            'phone'       => 'nullable|string|max:50',
            'email'       => 'nullable|email|max:255',
            // Token bebas huruf/angka; nominal boleh kosong tapi kalau diisi
            // harus angka >= 0. Lihat migrasi 2026_09_25_000010.
            'token'       => 'nullable|string|max:100',
            'nominal'     => 'nullable|numeric|min:0',
            'status'      => 'required|in:ACTIVE,INACTIVE',
            // M-02 — penghubung ke orang sebenarnya (opsional).
            'user_id'     => 'nullable|exists:users,id',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $pic = MasterPic::create([
            'type'        => $request->type,
            'pic'         => strtoupper($request->pic),
            'position'    => $request->position,
            'phone'       => $request->phone,
            'email'       => $request->email,
            'token'       => $request->token ?: null,
            'nominal'     => $request->filled('nominal') ? $request->nominal : null,
            'status'      => $request->status,
            'user_id'     => $request->user_id ?: null,
            'employee_id' => $request->employee_id ?: null,
            'input_by' => Auth::id(),
        ]);

        UserActivityLog::record('master_data', 'create', 'Menambah PIC ' . $pic->pic, $pic);

        return redirect('/pic-beneficiary')
            ->with('success', 'PIC berhasil ditambahkan');
    }

    /**
     * --------------------------------------------------------------------------
     * AJAX — Get PIC data for edit modal
     * GET /master-pic/{id}/edit-data
     * --------------------------------------------------------------------------
     */
    public function getEditData($id)
    {
        $this->pastikanBolehMengelola();

        $pic = MasterPic::findOrFail($id);

        return response()->json([
            'id'       => $pic->id,
            'type'     => $pic->type,
            'pic'      => $pic->pic,
            'position' => $pic->position,
            'phone'    => $pic->phone,
            'email'       => $pic->email,
            'token'       => $pic->token,
            'nominal'     => $pic->nominal,
            'status'      => $pic->status,
            'user_id'     => $pic->user_id,
            'employee_id' => $pic->employee_id,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $this->pastikanBolehMengelola();

        $request->validate([
            'type'        => 'required|in:' . implode(',', array_keys(MasterPic::TYPES)),
            'pic'         => 'required|string|max:255',
            'position'    => 'nullable|string|max:255',
            'phone'       => 'nullable|string|max:50',
            'email'       => 'nullable|email|max:255',
            // Token bebas huruf/angka; nominal boleh kosong tapi kalau diisi
            // harus angka >= 0. Lihat migrasi 2026_09_25_000010.
            'token'       => 'nullable|string|max:100',
            'nominal'     => 'nullable|numeric|min:0',
            'status'      => 'required|in:ACTIVE,INACTIVE',
            // M-02 — penghubung ke orang sebenarnya (opsional).
            'user_id'     => 'nullable|exists:users,id',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $pic = MasterPic::findOrFail($id);

        $pic->update([
            'type'        => $request->type,
            'pic'         => strtoupper($request->pic),
            'position'    => $request->position,
            'phone'       => $request->phone,
            'email'       => $request->email,
            'token'       => $request->token ?: null,
            'nominal'     => $request->filled('nominal') ? $request->nominal : null,
            'status'      => $request->status,
            'user_id'     => $request->user_id ?: null,
            'employee_id' => $request->employee_id ?: null,
        ]);

        UserActivityLog::record('master_data', 'update', 'Mengubah PIC ' . $pic->pic, $pic);

        return redirect('/pic-beneficiary')
            ->with('success', 'PIC berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $this->pastikanBolehMengelola();

        $pic = MasterPic::findOrFail($id);

        if (\Illuminate\Support\Facades\DB::table('customer_pic_links')->where('master_pic_id', $pic->id)->exists()) {
            return back()->with('error', 'PIC tidak bisa dihapus karena sudah dipakai pada Customer.');
        }
        if (\Illuminate\Support\Facades\DB::table('project_pic_links')->where('master_pic_id', $pic->id)->exists()) {
            return back()->with('error', 'PIC tidak bisa dihapus karena sudah dipakai pada Project.');
        }
        if (\App\Models\HomebaseDetail::where('pic_id', $pic->id)->exists()) {
            return back()->with('error', 'PIC tidak bisa dihapus karena sudah terdaftar di Homebase.');
        }
        if (\App\Models\SitePic::where('pic_id', $pic->id)->exists()) {
            return back()->with('error', 'PIC tidak bisa dihapus karena sudah terhubung ke Site.');
        }

        $picName = $pic->pic;
        // Beneficiary-nya tidak ikut terhapus: pic_id ber-nullOnDelete
        // (migrasi 2026_09_20_000001), baris itu kembali menjadi "Tanpa PIC".
        $jumlahBen = \App\Models\Beneficiary::where('pic_id', $pic->id)->count();
        $pic->delete();

        UserActivityLog::record('master_data', 'delete', 'Menghapus PIC ' . $picName);

        return redirect('/pic-beneficiary')
            ->with('success', 'PIC ' . $picName . ' dihapus.'
                . ($jumlahBen > 0 ? ' ' . $jumlahBen . ' beneficiary kini tanpa PIC.' : ''));
    }

    /**
     * --------------------------------------------------------------------------
     * AJAX — Options untuk dropdown picker (dipakai PO, Project, dsb nantinya)
     * GET /master-pic/options-json?type=customer|internal
     * --------------------------------------------------------------------------
     */
    public function optionsJson(Request $request)
    {
        $query = MasterPic::where('status', 'ACTIVE');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $pics = $query->orderBy('pic')->get(['id', 'type', 'pic', 'position', 'phone', 'email']);

        return response()->json($pics);
    }
}

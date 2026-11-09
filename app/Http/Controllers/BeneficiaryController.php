<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Beneficiary;
use App\Models\MasterPic;
use App\Models\UserActivityLog;

class BeneficiaryController extends Controller
{
    /**
     * Role yang boleh MENGUBAH & MENGHAPUS penerima dana.
     *
     * Melihat & menambah ditentukan gerbang 'menu:pic-beneficiary' di
     * routes/web.php; konstanta ini khusus untuk lapis kedua di atasnya.
     * Manager sengaja TIDAK di sini: ia boleh mendaftarkan penerima dana baru,
     * tapi baris lama sudah menempel pada Cost Request yang berjalan.
     *
     * Harus cermin dari middleware 'role:superadmin' pada grup edit/hapus di
     * routes/web.php — dua tempat itu berpasangan. Yang satu menjaga pintunya,
     * yang satu menentukan tombolnya dirender atau tidak.
     */
    /** Sumbernya App\Support\AccessRoles::BENEFICIARY_MANAGE; konstanta ini
     *  dipertahankan karena routes/web.php merujuknya dalam komentar. */
    public const MANAGE_ROLES = \App\Support\AccessRoles::BENEFICIARY_MANAGE;

    /** Boleh mengubah/menghapus baris yang sudah ada? */
    private function canManage(): bool
    {
        return Auth::user()?->hasRole(self::MANAGE_ROLES) ?? false;
    }

    /**
     * Penjaga kedua untuk aksi yang mengubah data lama.
     *
     * Middleware 'role:superadmin' di routes/web.php sudah menghadangnya lebih
     * dulu, dan ini memang mengulang pemeriksaan yang sama. Diulang dengan
     * sengaja: aturan "manager hanya boleh menambah" adalah inti modul ini,
     * jadi ia tidak boleh bergantung pada satu baris di berkas route yang bisa
     * tergeser saat grup di sekitarnya dirapikan.
     */
    private function pastikanBolehMengelola(): void
    {
        abort_unless($this->canManage(), 403, 'Unauthorized - Insufficient permissions');
    }

    /*
     | Daftar (index) pindah ke PicBeneficiaryController sejak Beneficiary &
     | PIC digabung (2026-11-09). Controller ini tinggal menangani tulis
     | beneficiary + pemilih/pemindahan PIC-nya.
     */

    /**
     * Daftar ringkas SELURUH beneficiary untuk pemilih "Kaitkan Beneficiary"
     * di detail PIC (halaman PIC & Beneficiary) — mengaitkan baris yang SUDAH ADA ke PIC yang sedang
     * dibuka. Diambil saat modal itu dibuka (bukan di tiap muat halaman).
     *
     * Sengaja TIDAK mengikuti filter kategori/status/pencarian di layar:
     * beneficiary yang ingin dipindahkan tidak boleh hilang dari pilihan hanya
     * karena sedang tersaring.
     * GET /beneficiaries/picker   (hanya MANAGE_ROLES)
     */
    public function picker()
    {
        $this->pastikanBolehMengelola();

        return response()->json(
            Beneficiary::with('pic:id,pic')
                ->orderBy('nickname')
                ->get(['id', 'nickname', 'complete_name', 'pic_id'])
                ->map(fn ($b) => [
                    'id'            => $b->id,
                    'nickname'      => $b->nickname,
                    'complete_name' => $b->complete_name,
                    'pic_id'        => $b->pic_id,
                    'pic_name'      => $b->pic->pic ?? null,
                ])
        );
    }

    /**
     * Pindahkan satu beneficiary ke PIC lain.
     *
     * Memindahkan lewat modal Edit berarti membuka seluruh formulir hanya
     * untuk mengganti satu kolom — dan setiap kolom lain yang ikut terkirim
     * adalah kesempatan berubah tanpa disengaja. Endpoint ini hanya menyentuh
     * pic_id.
     *
     * pic_id boleh kosong: itu cara melepas satu baris dari PIC-nya tanpa
     * harus menghapus PIC-nya untuk semua orang.
     */
    public function movePic(Request $request, $id)
    {
        $this->pastikanBolehMengelola();

        $request->validate([
            'pic_id' => 'nullable|exists:master_pics,id',
        ]);

        $beneficiary = Beneficiary::findOrFail($id);
        $picId       = $request->pic_id ?: null;

        if ((int) $beneficiary->pic_id === (int) $picId) {
            return back()->with('error', $beneficiary->nickname . ' sudah berada di PIC itu.');
        }

        $beneficiary->update(['pic_id' => $picId]);

        $tujuan = $picId
            ? 'PIC ' . MasterPic::whereKey($picId)->value('pic')
            : 'tanpa PIC';

        return redirect('/pic-beneficiary')->with(
            'success',
            $beneficiary->nickname . ' dipindahkan ke ' . $tujuan . '.'
        );
    }

    /**
     * Kaitkan beberapa beneficiary yang SUDAH ADA ke satu PIC sekaligus.
     *
     * Kebalikan dari movePic(): di sana satu beneficiary dipindah dari sisi
     * baris beneficiary-nya sendiri; di sini beberapa baris sekaligus ditarik
     * masuk dari sisi PIC-nya — dipakai tombol "Kaitkan" di detail PIC.
     *
     * TIDAK membuat baris baru. Yang mengetik nickname baru tetap lewat
     * tombol "Tambah Beneficiary" (store()) — ini murni memindahkan
     * kepemilikan PIC pada baris yang sudah terdaftar.
     */
    public function attachToPic(Request $request, $picId)
    {
        $this->pastikanBolehMengelola();

        $pic = MasterPic::findOrFail($picId);

        $request->validate([
            'beneficiary_ids'   => 'required|array|min:1',
            'beneficiary_ids.*' => 'exists:beneficiaries,id',
        ]);

        $jumlah = Beneficiary::whereIn('id', $request->beneficiary_ids)
            ->update(['pic_id' => $pic->id]);

        return redirect('/pic-beneficiary')->with(
            'success',
            $jumlah . ' beneficiary ditambahkan ke PIC ' . $pic->pic . '.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nickname'       => 'required',
            'complete_name'  => 'required',
            'category'       => 'required|in:INTERNAL,EXTERNAL,BILLING',
            // Kategori INTERNAL wajib punya PIC (penanggung jawab).
            'pic_id'         => 'nullable|required_if:category,INTERNAL|exists:master_pics,id',
            'contact'        => 'nullable',
            'bank_id'        => 'nullable|exists:banks,id',
            'account_number' => 'nullable',
            'status'         => 'required|in:ACTIVE,INACTIVE',
        ], [
            'pic_id.required_if' => 'PIC wajib dipilih untuk beneficiary kategori INTERNAL.',
        ]);

        // Cegah nomor rekening ganda sebelum menyimpan.
        $noRek = trim((string) $request->account_number);
        if ($noRek !== '') {
            $ada = Beneficiary::where('account_number', $noRek)->first();
            if ($ada) {
                return back()->withInput()->with(
                    'error',
                    'Nomor rekening ' . $noRek . ' sudah ada (' . $ada->nickname . '). Data tidak disimpan.'
                );
            }
        }

        $beneficiary = Beneficiary::create([
            'nickname'       => strtoupper($request->nickname),
            'complete_name'  => strtoupper($request->complete_name),
            'category'       => $request->category,
            'pic_id'         => $request->pic_id ?: null,
            'contact'        => $request->contact,
            'bank_id'        => $request->bank_id,
            'account_number' => $request->account_number,
            'status'         => $request->status,
            'input_by'       => Auth::id(),
        ]);

        UserActivityLog::record('master_data', 'create', 'Menambah Beneficiary ' . $beneficiary->nickname, $beneficiary);

        return redirect('/pic-beneficiary')
            ->with('success', 'Beneficiary berhasil ditambahkan');
    }

    /**
     * --------------------------------------------------------------------------
     * AJAX — Get beneficiary data for edit modal
     * GET /beneficiaries/{id}/edit-data
     * --------------------------------------------------------------------------
     */
    public function getEditData($id)
    {
        $this->pastikanBolehMengelola();

        $beneficiary = Beneficiary::with('pic')->findOrFail($id);

        return response()->json([
            'id'             => $beneficiary->id,
            'nickname'       => $beneficiary->nickname,
            'complete_name'  => $beneficiary->complete_name,
            'category'       => $beneficiary->category,
            'pic_id'         => $beneficiary->pic_id,
            'pic_name'       => $beneficiary->pic?->pic,
            'bank_id'        => $beneficiary->bank_id,
            'account_number' => $beneficiary->account_number,
            'contact'        => $beneficiary->contact,
            'status'         => $beneficiary->status,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $this->pastikanBolehMengelola();

        $request->validate([
            'nickname'       => 'required',
            'complete_name'  => 'required',
            'category'       => 'required|in:INTERNAL,EXTERNAL,BILLING',
            'pic_id'         => 'nullable|exists:master_pics,id',
            'contact'        => 'nullable',
            'bank_id'        => 'nullable|exists:banks,id',
            'account_number' => 'nullable',
            'status'         => 'required|in:ACTIVE,INACTIVE',
        ]);

        $beneficiary = Beneficiary::findOrFail($id);

        // Dipotret sebelum update untuk rincian perubahan (credential).
        $sebelum = $beneficiary->getOriginal();

        $beneficiary->update([
            'nickname'       => strtoupper($request->nickname),
            'complete_name'  => strtoupper($request->complete_name),
            'category'       => $request->category,
            'pic_id'         => $request->pic_id ?: null,
            'contact'        => $request->contact,
            'bank_id'        => $request->bank_id,
            'account_number' => $request->account_number,
            'status'         => $request->status,
        ]);

        $changes = UserActivityLog::changeSet($sebelum, $beneficiary, [
            'nickname'       => 'Nama Panggilan',
            'complete_name'  => 'Nama Lengkap',
            'category'       => 'Kategori',
            'account_number' => 'No. Rekening',
            'bank_id'        => 'Bank (ID)',
            'status'         => 'Status',
        ]);

        UserActivityLog::record(
            'master_data',
            'update',
            'Mengubah Beneficiary ' . $beneficiary->nickname,
            $beneficiary,
            null,
            null,
            $changes
        );

        return redirect('/pic-beneficiary')
            ->with('success', 'Beneficiary berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $this->pastikanBolehMengelola();

        $beneficiary = Beneficiary::findOrFail($id);

        // withTrashed(): BudgetRequest memakai SoftDeletes, tapi FK
        // budget_request.beneficiary_id ON DELETE CASCADE bekerja di level MySQL dan
        // tidak mengenal soft delete. Tanpa withTrashed() baris yang "ada di
        // recycle bin" tidak terlihat oleh penjaga ini, penghapusan lolos, dan
        // MySQL menghapusnya permanen beserta seluruh log statusnya.
        if (\App\Models\BudgetRequest::withTrashed()->where('beneficiary_id', $beneficiary->id)->exists()) {
            return back()->with('error', 'Beneficiary tidak bisa dihapus karena sudah dipakai pada Cost Request.');
        }

        $nickname = $beneficiary->nickname;
        $beneficiary->delete();

        UserActivityLog::record('master_data', 'delete', 'Menghapus Beneficiary ' . $nickname);

        return redirect('/pic-beneficiary')
            ->with('success', 'Beneficiary berhasil dihapus');
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\MasterPic;

class MasterPicController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // M-02 — filter & pencarian diproses di server supaya kartu ringkasan
        // di atas tabel selalu menghitung himpunan yang sama dengan isi tabel.
        $type   = $request->query('type');
        $status = $request->query('status');
        $search = trim((string) $request->query('q', ''));
        // M-02 — saringan kerja: tampilkan hanya PIC internal yang belum punya
        // akun User, supaya pemetaannya bisa dikerjakan sampai habis tanpa
        // memilah-milah sendiri dari daftar penuh.
        $unlinked = $request->boolean('unlinked');

        /*
         * Tab utama. Internal ditaruh paling kiri dan menjadi tab bawaan: itu
         * daftar yang paling sering dikerjakan (pemetaan ke akun User ada di
         * situ). 'db' = seluruh jenis, termasuk Customer dan Site Keeper yang
         * tidak punya tab sendiri — di sana filter Type yang memisahkannya.
         */
        $tab = $request->query('tab', 'internal');
        // CATATAN: tab 'external' = semua jenis yang BUKAN internal (Customer,
        // External, Site Keeper), bukan hanya jenis 'external'. Namanya mengikuti
        // yang tertulis di layar; pemisahan per jenis tetap lewat filter Type.
        if (! in_array($tab, ['internal', 'external', 'db'], true)) {
            $tab = 'internal';
        }

        $pics = MasterPic::with(['user:id,username,name', 'employee:id,name,employee_number'])
            ->withCount('beneficiaries')
            ->when(in_array($type, array_keys(MasterPic::TYPES), true), fn ($q) => $q->where('type', $type))
            ->when(in_array($status, ['ACTIVE', 'INACTIVE'], true), fn ($q) => $q->where('status', $status))
            ->when($unlinked, fn ($q) => $q->where('type', 'internal')->whereNull('user_id'))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where(fn ($w) => $w->where('pic', 'like', $like)
                    ->orWhere('position', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('token', 'like', $like)
                    ->orWhereHas('user', fn ($r) => $r->where('username', 'like', $like)->orWhere('name', 'like', $like)));
            })
            ->latest()
            ->get();

        /*
         * Seluruh baris dikirim sekali, lalu tab dipilah di browser — berpindah
         * tab tidak memuat ulang halaman. Karena itu angka per tab ikut dihitung
         * di sini untuk ketiganya sekaligus; kartu ringkasannya tinggal ditukar
         * oleh JS saat tab berganti, tanpa permintaan baru ke server.
         */
        $isiTab = [
            'internal' => $pics->where('type', 'internal'),
            'external' => $pics->where('type', '!=', 'internal'),
            'db'       => $pics,
        ];

        // Berapa PIC internal yang BELUM terhubung ke akun User. Sengaja dihitung
        // dari SELURUH data, bukan dari hasil saringan: angka ini menandai sisa
        // pekerjaan, jadi ia harus tetap turun saat dikerjakan dan tidak boleh
        // ikut mengecil hanya karena daftarnya disaring.
        $unlinked = MasterPic::where('type', 'internal')->whereNull('user_id')->count();

        $summaries = [];
        foreach ($isiTab as $key => $isi) {
            $summaries[$key] = [
                'total'    => $isi->count(),
                'active'   => $isi->where('status', 'ACTIVE')->count(),
                'inactive' => $isi->where('status', 'INACTIVE')->count(),
                'unlinked' => $unlinked,
            ];
        }

        // Badge tab dihitung dari seluruh data, bukan dari hasil saringan: ia
        // menerangkan isi tab yang belum dibuka.
        $semua = MasterPic::selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');
        $tabCounts = [
            'internal' => (int) ($semua['internal'] ?? 0),
            'external' => (int) ($semua->sum() - ($semua['internal'] ?? 0)),
            'db'       => (int) $semua->sum(),
        ];

        $summary = $summaries[$tab];

        $users = \App\Models\User::orderBy('username')->get(['id', 'username', 'name']);
        $employees = \App\Models\Employee::where('status', 'ACTIVE')
            ->orderBy('name')->get(['id', 'name', 'employee_number']);

        $filters = ['type' => $type, 'status' => $status, 'q' => $search, 'unlinked' => $unlinked];

        return view('master-pic.index', compact(
            'pics', 'summary', 'summaries', 'users', 'employees', 'filters', 'tab', 'tabCounts'
        ));
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

        MasterPic::create([
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

        return redirect('/master-pic')
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

        return redirect('/master-pic')
            ->with('success', 'PIC berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $pic = MasterPic::findOrFail($id);

        if (\Illuminate\Support\Facades\DB::table('customer_pic_links')->where('master_pic_id', $pic->id)->exists()) {
            return back()->with('error', 'PIC tidak bisa dihapus karena sudah dipakai pada Customer.');
        }
        if (\Illuminate\Support\Facades\DB::table('project_pic_links')->where('master_pic_id', $pic->id)->exists()) {
            return back()->with('error', 'PIC tidak bisa dihapus karena sudah dipakai pada Project.');
        }

        $pic->delete();

        return redirect('/master-pic')
            ->with('success', 'PIC berhasil dihapus');
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

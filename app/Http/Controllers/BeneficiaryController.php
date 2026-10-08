<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Bank;
use App\Models\Beneficiary;
use App\Models\MasterPic;
use App\Models\UserActivityLog;

class BeneficiaryController extends Controller
{
    /**
     * Role yang boleh MENGUBAH & MENGHAPUS penerima dana.
     *
     * Melihat & menambah ditentukan gerbang 'menu:beneficiaries' di
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

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Filter server-side (MN-02) — kategori & status ikut menentukan baris
        // yang diambil, bukan cuma disembunyikan di DataTable, supaya angka
        // summary di kartu atas selalu sama dengan isi tabel di bawahnya.
        $category = $request->query('category');
        $status   = $request->query('status');
        $search   = trim((string) $request->query('q', ''));

        $beneficiaries = Beneficiary::with(['bank', 'user', 'pic'])
            ->when(in_array($category, Beneficiary::CATEGORIES, true),
                fn ($q) => $q->where('category', $category))
            ->when(in_array($status, ['ACTIVE', 'INACTIVE'], true),
                fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('nickname', 'like', $like)
                      ->orWhere('complete_name', 'like', $like)
                      ->orWhere('account_number', 'like', $like)
                      ->orWhere('contact', 'like', $like)
                      // Cari juga lewat relasi PIC & Bank.
                      ->orWhereHas('pic', fn ($r) => $r->where('pic', 'like', $like))
                      ->orWhereHas('bank', fn ($r) => $r->where('name', 'like', $like));
                });
            })
            ->latest()
            ->get();

        $banks = Bank::orderBy('name')->get();
        $pics  = MasterPic::where('status', 'ACTIVE')->orderBy('pic')->get();

        $summary = [
            'total'    => $beneficiaries->count(),
            'active'   => $beneficiaries->where('status', 'ACTIVE')->count(),
            'inactive' => $beneficiaries->where('status', 'INACTIVE')->count(),
            'internal' => $beneficiaries->where('category', 'INTERNAL')->count(),
            'external' => $beneficiaries->where('category', 'EXTERNAL')->count(),
            'billing'  => $beneficiaries->where('category', 'BILLING')->count(),
        ];

        $filters = ['category' => $category, 'status' => $status, 'q' => $search];

        /*
        | TAB utama — mengikuti pola PIC Master.
        |
        | Internal = kategori INTERNAL; Outsider = semua yang BUKAN internal
        | (EXTERNAL + BILLING, termasuk kategori baru apa pun nanti); Database
        | memuat semuanya. Pemilahan baris dikerjakan di peramban: seluruh
        | baris sudah terkirim sekali, jadi berpindah tab tidak memuat ulang
        | halaman. Nilainya di sini hanya untuk tab yang aktif saat halaman
        | dibuka dan angka di badge-nya.
        */
        $tab = in_array($request->query('tab'), ['internal', 'outsider', 'db'], true)
            ? $request->query('tab')
            : 'internal';

        $tabCounts = [
            'internal' => $beneficiaries->where('category', 'INTERNAL')->count(),
            'outsider' => $beneficiaries->where('category', '!=', 'INTERNAL')->count(),
            'db'       => $beneficiaries->count(),
        ];

        /*
        | Ringkasan per PIC — dipakai saat kata pencarian cocok dengan nama PIC.
        |
        | Mencari "RANI FEBRIANDINI" dulu memuntahkan 18 baris beneficiary yang
        | isinya berulang: kolom PIC-nya sama semua, dan yang dicari sebenarnya
        | orangnya, bukan 18 penerima dana itu satu per satu. Daftar ini
        | membuat hasilnya bisa diciutkan jadi SATU baris per PIC, dengan
        | anggotanya dibuka lewat tombol View.
        |
        | Dibangun dari koleksi yang SUDAH diambil di atas, bukan query baru:
        | dua sumber angka untuk daftar yang sama pasti berselisih begitu
        | filter kategori/status sedang aktif.
        */
        $picGroups = $beneficiaries
            ->filter(fn ($b) => $b->pic !== null)
            ->groupBy('pic_id')
            ->map(function ($rows) {
                $pic = $rows->first()->pic;

                return [
                    'id'       => $pic->id,
                    'pic'      => $pic->pic,
                    'position' => $pic->position,
                    'type'     => $pic->type,
                    'phone'    => $pic->phone,
                    'email'    => $pic->email,
                    'total'    => $rows->count(),
                    'items'    => $rows->map(fn ($b) => [
                        'id'             => $b->id,
                        'nickname'       => $b->nickname,
                        'complete_name'  => $b->complete_name,
                        'category'       => $b->category,
                        'bank'           => $b->bank->name ?? '-',
                        'account_number' => $b->account_number ?: '-',
                        'contact'        => $b->contact ?: '-',
                        'status'         => $b->status,
                    ])->values(),
                ];
            })
            ->sortBy('pic')
            ->values();

        /*
         | Dikirim ke view supaya tombol Edit/Hapus (dan modal Edit) tidak
         | dirender untuk role yang akan ditolak middleware. Ini KENYAMANAN,
         | bukan pengamanan — penjaga sebenarnya ada di routes/web.php dan di
         | update()/destroy() bawah.
         */
        $canManage = $this->canManage();

        /*
         | Daftar ringkas SELURUH beneficiary, untuk pemilih "Tambahkan
         | Beneficiary" di dalam modal Detail PIC — mengaitkan baris yang
         | SUDAH ADA ke PIC yang sedang dibuka, bukan membuat baris baru
         | (itu tugas tombol "Tambah Beneficiary" di toolbar/modal Tambah).
         |
         | Sengaja TIDAK memakai $beneficiaries: koleksi itu mengikuti filter
         | kategori/status/pencarian yang sedang aktif di layar. Memilih
         | beneficiary untuk dikaitkan adalah aksi terpisah dari apa yang
         | sedang disaring — kalau ikut tersaring, beneficiary yang justru
         | ingin dipindahkan bisa hilang dari daftar pilihan hanya karena
         | user sedang melihat kategori EXTERNAL saja.
         */
        $beneficiaryPicker = Beneficiary::with('pic:id,pic')
            ->orderBy('nickname')
            ->get(['id', 'nickname', 'complete_name', 'pic_id'])
            ->map(fn ($b) => [
                'id'            => $b->id,
                'nickname'      => $b->nickname,
                'complete_name' => $b->complete_name,
                'pic_id'        => $b->pic_id,
                'pic_name'      => $b->pic->pic ?? null,
            ]);

        return view('beneficiaries.index', compact(
            'beneficiaries', 'banks', 'pics', 'summary', 'filters', 'canManage', 'picGroups', 'beneficiaryPicker',
            'tab', 'tabCounts'
        ));
    }

    /**
     * Hapus satu PIC beserta seluruh datanya di Master PIC.
     *
     * Beneficiary-nya TIDAK ikut terhapus. Kolom pic_id ber-nullOnDelete
     * (migrasi 2026_09_20_000001), jadi baris yang menunjuk PIC ini otomatis
     * kembali tanpa PIC — penerima dananya sendiri masih melekat pada Cost
     * Request yang berjalan dan tidak boleh hilang hanya karena penanggung
     * jawabnya dihapus.
     *
     * Penjagaan Customer & Project disalin dari MasterPicController::destroy()
     * supaya satu PIC tidak bisa dihapus dari layar Beneficiary padahal modul
     * lain masih memakainya — dua pintu ke penghapusan yang sama harus menolak
     * hal yang sama.
     */
    public function destroyPic($picId)
    {
        $this->pastikanBolehMengelola();

        $pic = MasterPic::findOrFail($picId);

        if (DB::table('customer_pic_links')->where('master_pic_id', $pic->id)->exists()) {
            return back()->with('error', 'PIC tidak bisa dihapus karena sudah dipakai pada Customer.');
        }

        if (DB::table('project_pic_links')->where('master_pic_id', $pic->id)->exists()) {
            return back()->with('error', 'PIC tidak bisa dihapus karena sudah dipakai pada Project.');
        }

        $jumlah = Beneficiary::where('pic_id', $pic->id)->count();
        $nama   = $pic->pic;

        $pic->delete();

        return redirect('/beneficiaries')->with(
            'success',
            'PIC ' . $nama . ' dihapus. ' . $jumlah . ' beneficiary kini tanpa PIC.'
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

        return redirect('/beneficiaries')->with(
            'success',
            $beneficiary->nickname . ' dipindahkan ke ' . $tujuan . '.'
        );
    }

    /**
     * Kaitkan beberapa beneficiary yang SUDAH ADA ke satu PIC sekaligus.
     *
     * Kebalikan dari movePic(): di sana satu beneficiary dipindah dari sisi
     * baris beneficiary-nya sendiri; di sini beberapa baris sekaligus ditarik
     * masuk dari sisi PIC-nya — dipakai tombol "Tambahkan Beneficiary" di
     * dalam modal Detail PIC.
     *
     * TIDAK membuat baris baru. Yang mengetik nickname baru tetap lewat
     * tombol "Tambah Beneficiary" di toolbar (store()) — ini murni memindahkan
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

        return redirect('/beneficiaries')->with(
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
            'pic_id'         => 'nullable|exists:master_pics,id',
            'contact'        => 'nullable',
            'bank_id'        => 'nullable|exists:banks,id',
            'account_number' => 'nullable',
            'status'         => 'required|in:ACTIVE,INACTIVE',
        ]);

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

        return redirect('/beneficiaries')
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

        return redirect('/beneficiaries')
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

        return redirect('/beneficiaries')
            ->with('success', 'Beneficiary berhasil dihapus');
    }
}
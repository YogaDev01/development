<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

use App\Models\Bank;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MasterPic;
use App\Models\User;
use App\Support\AccessRoles;

/**
 * Halaman gabungan "PIC & Beneficiary" (2026-11-09).
 *
 * Menggantikan dua halaman index lama (/beneficiaries & /master-pic). Alurnya
 * PIC -> Beneficiary: satu DataTable berisi seluruh PIC (semua tipe, tanpa
 * tab Internal/External), dan beneficiary milik tiap PIC dibuka sebagai
 * detail (child row). Beneficiary yang belum punya PIC tampil sebagai baris
 * sendiri berlabel "Tanpa PIC" di tabel yang sama.
 *
 * Hanya MEMBACA. Seluruh aksi tulis tetap di BeneficiaryController dan
 * MasterPicController (gerbang & validasinya tidak berubah).
 */
class PicBeneficiaryController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $pics = MasterPic::with('user:id,username,name')
            ->withCount('beneficiaries')
            ->orderBy('pic')
            ->get();

        /*
         | Seluruh beneficiary diambil SEKALI dengan kolom seperlunya, lalu
         | dikelompokkan per PIC di PHP. Detail tiap PIC dirender dari data ini
         | di peramban — membuka detail tidak memicu permintaan baru, dan
         | pencarian nickname/no. rekening bisa menemukan baris PIC-nya.
         | Isinya sama dengan yang dulu tampil di tabel Beneficiary untuk role
         | yang sama (termasuk nomor rekening), jadi tidak ada data baru yang
         | terbuka.
         */
        $beneficiaries = Beneficiary::with('bank:id,name')
            ->orderBy('nickname')
            ->get(['id', 'nickname', 'complete_name', 'category', 'pic_id', 'bank_id', 'account_number', 'contact', 'status']);

        $benByPic = $beneficiaries->whereNotNull('pic_id')
            ->groupBy('pic_id')
            ->map(fn ($items) => $items->map(fn ($b) => [
                'id'             => $b->id,
                'nickname'       => $b->nickname,
                'complete_name'  => $b->complete_name,
                'category'       => $b->category,
                'bank'           => $b->bank->name ?? null,
                'account_number' => $b->account_number,
                'contact'        => $b->contact,
                'status'         => $b->status,
            ])->values());

        $orphans = $beneficiaries->whereNull('pic_id')->values();

        // Pilihan PIC untuk form beneficiary & Pindah PIC: hanya yang aktif
        // (sama dengan halaman Beneficiary lama).
        $picOptions = $pics->where('status', 'ACTIVE')->values();

        $banks     = Bank::orderBy('name')->get(['id', 'name']);
        $users     = User::orderBy('username')->get(['id', 'username', 'name']);
        $employees = Employee::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'employee_number']);

        $counts = [
            'pic'      => $pics->count(),
            'ben'      => $beneficiaries->count(),
            'orphan'   => $orphans->count(),
            // Sisa pekerjaan pemetaan akun: PIC internal tanpa akun User.
            'unlinked' => $pics->where('type', 'internal')->whereNull('user_id')->count(),
        ];

        /*
         | Kenyamanan tampilan, bukan pengamanan — penjaga sebenarnya ada di
         | lapis 'role:superadmin' routes/web.php dan di controller tulis.
         */
        $canManage    = $user?->hasRole(AccessRoles::BENEFICIARY_MANAGE) ?? false;
        $canManagePic = $user?->hasRole(AccessRoles::PIC_MANAGE) ?? false;

        return view('pic-beneficiary.index', compact(
            'pics', 'benByPic', 'orphans', 'picOptions', 'banks', 'users', 'employees',
            'counts', 'canManage', 'canManagePic'
        ));
    }
}

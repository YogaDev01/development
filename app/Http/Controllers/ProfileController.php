<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use App\Models\Role;
use App\Models\ProjectMonitoringLog;
use App\Models\RequestStatusLog;

class ProfileController extends Controller
{
    private const LOG_LIMIT = 50;

    public function show()
    {
        /** @var User $user */
        $user = Auth::user();
        $user->load('role', 'accessGroup.projects.customer');

        $accessibleProjects = $user->accessGroup
            ? $user->accessGroup->projects
            : collect();

        $projectLogs = ProjectMonitoringLog::where('user_id', $user->id)
            ->with('project')
            ->latest()
            ->limit(self::LOG_LIMIT)
            ->get();

        $requestLogs = RequestStatusLog::where('acted_by', $user->id)
            ->with('budgetRequest')
            ->latest('created_at')
            ->limit(self::LOG_LIMIT)
            ->get();

        $team = $user->access_group_id
            ? User::where('access_group_id', $user->access_group_id)
                ->where('id', '!=', $user->id)
                ->with('role')
                ->orderBy('name')
                ->get()
            : collect();

        return view('profile.show', compact(
            'user', 'accessibleProjects', 'projectLogs', 'requestLogs', 'team'
        ));
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'name'             => 'required|string|max:255',
            'phone'            => 'nullable|string|max:30',
            'current_password' => 'nullable|required_with:password|string',
            // Aturan sama dengan UserController (pembuatan/penggantian oleh
            // admin) supaya tidak ada jalur yang lebih longgar: sebelumnya
            // min:6 di kedua tempat, sehingga "123456" lolos.
            'password'         => ['nullable', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'Password saat ini salah.'])->withInput();
            }
        }

        $data = [
            'name'  => $request->name,
            'phone' => $request->phone,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * POST /profile/avatar — foto disimpan per user id, nama file lama
     * (ekstensi apapun) dihapus dulu supaya tidak menumpuk file yatim.
     */
    public function uploadAvatar(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'avatar' => 'required|image|max:2048|mimes:jpg,jpeg,png,webp',
        ]);

        if ($user->avatar) {
            Storage::disk('secure')->delete($user->avatar);
        }

        /*
        | Nama berkas acak, bukan "user_{id}.{ext}". Nama berpola bisa ditebak
        | berurutan — dulu itu berarti foto siapa pun bisa diambil hanya dengan
        | menghitung id. Sekarang aksesnya sudah lewat route ber-otentikasi,
        | tapi nama acak menghilangkan pola tebakan sekaligus.
        */
        $file = $request->file('avatar');
        $path = $file->store('avatars', 'secure');

        $user->update(['avatar' => $path]);

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW AS — superadmin mencoba UI/menu sebagai role lain (preview).
    | Lihat App\Models\User::hasRole()/effectiveRole() untuk mekanismenya.
    |--------------------------------------------------------------------------
    */

    /** POST /view-as — aktifkan preview sebagai role tertentu. */
    public function viewAs(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isRealSuperadmin()) {
            abort(403, 'Hanya superadmin yang bisa memakai View As.');
        }

        $request->validate(['role_id' => 'required|exists:roles,id']);

        $role = Role::findOrFail($request->role_id);

        // View As sebagai 'superadmin' sendiri = sama saja dengan reset.
        if (strtolower(trim($role->name)) === 'superadmin') {
            session()->forget('view_as_role_id');
        } else {
            session(['view_as_role_id' => $role->id]);
        }

        return back()->with('success', 'Sekarang melihat sebagai role: ' . $role->name . '.');
    }

    /** POST /view-as/reset — kembali ke superadmin sungguhan. */
    public function resetViewAs()
    {
        session()->forget('view_as_role_id');

        return back()->with('success', 'Kembali ke tampilan Superadmin.');
    }
}

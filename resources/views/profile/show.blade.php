@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Profil Saya</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item active">Profil Saya</li>
            </ol>
        </div>

        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <!-- HEADER CARD: cover + avatar (ala Facebook) -->
        <div class="card shadow-sm mb-3" style="overflow:hidden;">
            <div style="height:150px;background:var(--surface-3);"></div>

            <div class="px-4 pb-3" style="margin-top:-64px;">
                <div class="d-flex align-items-end flex-wrap" style="gap:16px;">
                    <form action="{{ route('profile.avatar') }}" method="POST" enctype="multipart/form-data" id="frmAvatar">
                        @csrf
                        <div class="position-relative d-inline-block" style="cursor:pointer;" onclick="document.getElementById('avatarInput').click();">
                            <div id="avatarPreviewWrap" style="width:128px;height:128px;background:var(--charcoal);border:5px solid var(--border);border-radius:50%;display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.15);">
                                @if($user->avatar_url)
                                    <img src="{{ $user->avatar_url }}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;" id="avatarPreviewImg">
                                @else
                                    <span style="color:var(--accent);font-size:38px;font-weight:700;" id="avatarPreviewInitials">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                                @endif
                            </div>
                            <div style="position:absolute;bottom:4px;right:4px;width:32px;height:32px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;border:2px solid var(--border);">
                                <i class="fas fa-camera" style="font-size:12px;color:#fff;"></i>
                            </div>
                        </div>
                        <input type="file" name="avatar" id="avatarInput" accept="image/png,image/jpeg,image/webp" style="display:none;">
                    </form>

                    <div class="mb-2">
                        <h4 class="mb-0 font-weight-bold">{{ $user->name }}</h4>
                        <div class="text-muted" style="font-size:12.5px;">{{ $user->email }}</div>
                        <div class="mt-1">
                            <span class="badge badge-warning" style="font-size:10.5px;"><i class="fas fa-user-tag mr-1"></i>{{ Str::upper($user->role->name ?? '-') }}</span>
                            <span class="badge badge-secondary" style="font-size:10.5px;"><i class="fas fa-users mr-1"></i>{{ $user->accessGroup->name ?? '-' }}</span>
                            <span class="badge badge-light border" style="font-size:10.5px;"><i class="fas fa-at mr-1"></i>{{ $user->username ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-top">
                <ul class="nav nav-tabs px-3" id="profileTabs" style="border-bottom:none;">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#paneInfo">
                            <i class="fas fa-info-circle mr-1"></i> Informasi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#paneAccess">
                            <i class="fas fa-key mr-1"></i> Access
                            <span class="badge badge-secondary ml-1">{{ $accessibleProjects->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#paneProjectLog">
                            <i class="fas fa-binoculars mr-1"></i> Project Log
                            <span class="badge badge-secondary ml-1">{{ $projectLogs->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#paneRequestLog">
                            <i class="fas fa-file-invoice-dollar mr-1"></i> Request Log
                            <span class="badge badge-secondary ml-1">{{ $requestLogs->count() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#paneActivityLog">
                            <i class="fas fa-stream mr-1"></i> Activity Log
                            <span class="badge badge-secondary ml-1">{{ $activityLogs->total() }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#paneTeam">
                            <i class="fas fa-user-friends mr-1"></i> Team
                            <span class="badge badge-secondary ml-1">{{ $team->count() }}</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- TAB CONTENT -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="tab-content">

                    <!-- TAB INFORMASI -->
                    <div class="tab-pane fade show active" id="paneInfo">
                        <form action="{{ route('profile.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            <h6 class="font-weight-bold mb-3">Informasi Akun</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-600">Nama <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                               value="{{ old('name', $user->name) }}" required>
                                        @error('name')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="font-weight-600">No. WhatsApp</label>
                                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                               value="{{ old('phone', $user->phone) }}" placeholder="08xxxxxxxxxx">
                                        @error('phone')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label class="font-weight-600">Email</label>
                                        <input type="text" class="form-control" value="{{ $user->email }}" disabled>
                                        <small class="text-muted">Hubungi Superadmin untuk mengubah email.</small>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <h6 class="font-weight-bold mb-1">Ubah Password</h6>
                            <p class="text-muted" style="font-size:12px;">Kosongkan bagian ini jika tidak ingin mengubah password.</p>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-600">Password Saat Ini</label>
                                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" autocomplete="off">
                                        @error('current_password')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="font-weight-600">Password Baru</label>
                                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="off">
                                        @error('password')<small class="text-danger d-block">{{ $message }}</small>@enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label class="font-weight-600">Konfirmasi Password Baru</label>
                                        <input type="password" name="password_confirmation" class="form-control" autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                {{-- Batal = keluar dari Profil Saya, balik ke Dashboard (route '/' tanpa nama). --}}
                                <a href="{{ url('/') }}" class="btn btn-secondary mr-2">
                                    <i class="fas fa-times mr-1"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- TAB ACCESS -->
                    <div class="tab-pane fade" id="paneAccess">
                        <p class="text-muted" style="font-size:12px;">
                            Daftar Purchase Order (Scope of Work) yang dapat diakses melalui Access Group
                            <strong>{{ $user->accessGroup->name ?? '-' }}</strong>.
                        </p>

                        @if($accessibleProjects->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-folder-open d-block mb-2" style="font-size:22px;"></i>
                            @if(!$user->accessGroup)
                                Belum ada Access Group yang ditetapkan untuk akun ini.
                            @else
                                Belum ada Purchase Order pada Access Group ini.
                            @endif
                        </div>
                        @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0" style="font-size:12.5px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" style="width:5%;" class="text-center">No</th>
                                        <th scope="col">Scope of Work</th>
                                        <th scope="col">Customer</th>
                                        <th scope="col" style="width:15%;" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($accessibleProjects as $i => $p)
                                    <tr>
                                        <td class="text-center">{{ $i + 1 }}</td>
                                        <td>
                                            <div style="font-weight:600;">{{ $p->name }}</div>
                                            <small class="text-muted">{{ $p->code }}</small>
                                        </td>
                                        <td>{{ $p->customer->name ?? '-' }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $p->status_color }}">{{ $p->status_label }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>

                    <!-- TAB PROJECT LOG -->
                    <div class="tab-pane fade" id="paneProjectLog">
                        <p class="text-muted" style="font-size:12px;">Aktivitas Anda di Project Monitoring (chat, update milestone, dll).</p>

                        @if($projectLogs->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-binoculars d-block mb-2" style="font-size:22px;"></i>
                            Belum ada aktivitas.
                        </div>
                        @else
                        <div style="max-height:480px;overflow-y:auto;">
                            @foreach($projectLogs as $log)
                            <div class="d-flex py-2" style="border-bottom:1px solid var(--border);">
                                <div class="mr-3 text-center" style="width:28px;flex-shrink:0;">
                                    <i class="fas {{ $log->type === 'SYSTEM' ? 'fa-cog text-secondary' : 'fa-comment text-primary' }}"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:12.5px;">
                                        <a href="{{ route('project-monitoring.show', $log->project_id) }}" target="_blank" rel="noopener" class="font-weight-bold">
                                            {{ $log->project->name ?? '-' }}
                                        </a>
                                    </div>
                                    <div style="font-size:12px;color:var(--text-dim);white-space:pre-line;">{{ $log->message }}</div>
                                    <small class="text-muted">{{ $log->created_at->format('d M Y H:i') }}</small>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    <!-- TAB REQUEST LOG -->
                    <div class="tab-pane fade" id="paneRequestLog">
                        <p class="text-muted" style="font-size:12px;">Riwayat pengajuan dan approval Cost Request yang Anda lakukan.</p>

                        @if($requestLogs->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-file-invoice-dollar d-block mb-2" style="font-size:22px;"></i>
                            Belum ada aktivitas.
                        </div>
                        @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0" style="font-size:12.5px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col">Cost Request</th>
                                        <th scope="col" style="width:20%;">Aksi</th>
                                        <th scope="col">Catatan</th>
                                        <th scope="col" style="width:15%;" class="text-center">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requestLogs as $log)
                                    <tr>
                                        <td>
                                            @if($log->budgetRequest)
                                            <a href="{{ route('budget-request.index') }}?open={{ $log->budgetRequest->id }}" target="_blank" rel="noopener" class="font-weight-bold">
                                                {{ $log->budgetRequest->code }}
                                            </a>
                                            <div class="text-muted" style="font-size:11px;">{{ $log->budgetRequest->description }}</div>
                                            @else
                                            <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td><span class="badge badge-{{ $log->action_color }}">{{ $log->action_label }}</span></td>
                                        <td>{{ $log->notes ?: '-' }}</td>
                                        <td class="text-center">{{ $log->created_at?->format('d M Y H:i') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>

                    <!-- TAB ACTIVITY LOG -->
                    <div class="tab-pane fade" id="paneActivityLog">
                        @include('profile._activity_log')
                    </div>

                    <!-- TAB TEAM -->
                    <div class="tab-pane fade" id="paneTeam">
                        <p class="text-muted" style="font-size:12px;">
                            User lain dengan Access Group yang sama: <strong>{{ $user->accessGroup->name ?? '-' }}</strong>.
                        </p>

                        @if($team->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-user-friends d-block mb-2" style="font-size:22px;"></i>
                            @if(!$user->accessGroup)
                                Belum ada Access Group yang ditetapkan untuk akun ini.
                            @else
                                Tidak ada user lain pada Access Group ini.
                            @endif
                        </div>
                        @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0" style="font-size:12.5px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th scope="col" style="width:5%;" class="text-center">No</th>
                                        <th scope="col">Nama</th>
                                        <th scope="col">Username</th>
                                        <th scope="col">Role</th>
                                        <th scope="col">Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($team as $i => $t)
                                    <tr>
                                        <td class="text-center">{{ $i + 1 }}</td>
                                        <td class="font-weight-600">{{ $t->name }}</td>
                                        <td>{{ $t->username ?? '-' }}</td>
                                        <td><span class="badge badge-secondary">{{ Str::upper($t->role->name ?? '-') }}</span></td>
                                        <td>{{ $t->email }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Setelah submit filter / pindah halaman Activity Log, URL membawa param
// (alog / module / action / q / tanggal). Aktifkan kembali tab Activity Log
// supaya user tidak terlempar balik ke tab Informasi.
(function () {
    var p = new URLSearchParams(window.location.search);
    var keys = ['alog', 'module', 'action', 'date_from', 'date_to', 'q'];
    var onActivity = keys.some(function (k) { return p.has(k); });
    if (onActivity) {
        var trigger = document.querySelector('a[href="#paneActivityLog"]');
        if (trigger && window.jQuery) { window.jQuery(trigger).tab('show'); }
    }
})();

document.getElementById('avatarInput').addEventListener('change', function () {
    if (!this.files || !this.files[0]) return;

    var reader = new FileReader();
    reader.onload = function (e) {
        var wrap = document.getElementById('avatarPreviewWrap');
        wrap.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
    };
    reader.readAsDataURL(this.files[0]);

    document.getElementById('frmAvatar').submit();
});
</script>
@endpush

@extends('layouts.app')

@section('title', 'Keamanan')

@section('content')
{{--
    Konsol Keamanan — Fase 1 Security & Access Control (superadmin saja).
    Variabel: $tab, $summary, $users, $filters, $logs (paginator|null),
    $sessions, $sessionsEnabled, $currentHash, $hasDeviceColumn.
--}}
@php
    use App\Models\UserActivityLog;
    $authActions = ['login', 'logout', 'login_failed', 'lockout', 'revoke_session'];
@endphp
<div class="content">
    <div class="container-fluid">

        <div class="ts-index tw:flex tw:flex-col tw:gap-4 tw:py-4">

        <div class="tw:sr-only">
            <h1>Keamanan</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">User &amp; Access</li>
                <li class="breadcrumb-item active">Keamanan</li>
            </ol>
        </div>

        <x-tw.page-header title="Konsol Keamanan"
            description="Jejak audit, riwayat login, dan sesi aktif seluruh user.">
            <x-slot:actions>
                <x-tw.button href="{{ url('/user') }}" variant="secondary"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>

        {{-- Pesan sukses/gagal ditampilkan layout (tesysFlashSuccess/Error). --}}

        <x-stat-cards :cards="[
            ['User Online', $sessionsEnabled ? $summary['active_users'] : '-', 'primary', 'fas fa-user-check', $sessionsEnabled ? 'sesi belum kedaluwarsa' : 'driver sesi bukan database'],
            ['Login Gagal 24 Jam', $summary['failed_24h'], $summary['failed_24h'] ? 'warning' : 'secondary', 'fas fa-user-times'],
            ['Login Diblokir 24 Jam', $summary['lockout_24h'], $summary['lockout_24h'] ? 'danger' : 'secondary', 'fas fa-lock'],
            ['Perubahan Akses 7 Hari', $summary['access_changes'], 'info', 'fas fa-user-shield'],
        ]" />

        <x-tw.card padding="sm">

            <ul class="nav nav-pills mb-3" role="tablist" style="gap:6px;">
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'audit' ? 'active' : '' }}" href="{{ route('security.index', ['tab' => 'audit']) }}">
                        <i class="fas fa-stream mr-1" aria-hidden="true"></i> Audit Trail
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'login' ? 'active' : '' }}" href="{{ route('security.index', ['tab' => 'login']) }}">
                        <i class="fas fa-sign-in-alt mr-1" aria-hidden="true"></i> Riwayat Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $tab === 'sessions' ? 'active' : '' }}" href="{{ route('security.index', ['tab' => 'sessions']) }}">
                        <i class="fas fa-desktop mr-1" aria-hidden="true"></i> Sesi Aktif
                    </a>
                </li>
            </ul>

            @if($tab !== 'sessions')
                {{-- FILTER (GET, server-side) --}}
                <form method="GET" action="{{ route('security.index') }}" class="form-row align-items-end mb-3">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="col-md-3 col-6 mb-2">
                        <label class="font-weight-600 mb-1" style="font-size:11px;" for="secUser">User</label>
                        <select name="user_id" id="secUser" class="form-control form-control-sm">
                            <option value="">Semua User</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $u->id)>{{ $u->name }} ({{ '@' . $u->username }})</option>
                            @endforeach
                        </select>
                    </div>
                    @if($tab === 'audit')
                    <div class="col-md-2 col-6 mb-2">
                        <label class="font-weight-600 mb-1" style="font-size:11px;" for="secModule">Module</label>
                        <select name="module" id="secModule" class="form-control form-control-sm">
                            <option value="">Semua Module</option>
                            @foreach(UserActivityLog::MODULES as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-md-2 col-6 mb-2">
                        <label class="font-weight-600 mb-1" style="font-size:11px;" for="secAction">Aksi</label>
                        <select name="action" id="secAction" class="form-control form-control-sm">
                            <option value="">Semua Aksi</option>
                            @foreach(UserActivityLog::ACTIONS as $key => $label)
                                @if($tab === 'audit' || in_array($key, $authActions, true))
                                    <option value="{{ $key }}" @selected(($filters['action'] ?? '') === $key)>{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1 col-6 mb-2">
                        <label class="font-weight-600 mb-1" style="font-size:11px;" for="secFrom">Dari</label>
                        <input type="date" name="date_from" id="secFrom" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
                    </div>
                    <div class="col-md-1 col-6 mb-2">
                        <label class="font-weight-600 mb-1" style="font-size:11px;" for="secTo">Sampai</label>
                        <input type="date" name="date_to" id="secTo" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
                    </div>
                    <div class="col-md-1 col-6 mb-2">
                        <label class="font-weight-600 mb-1" style="font-size:11px;" for="secIp">IP</label>
                        <input type="text" name="ip" id="secIp" class="form-control form-control-sm" value="{{ $filters['ip'] ?? '' }}" maxlength="45">
                    </div>
                    <div class="col-md-2 col-12 mb-2">
                        <label class="font-weight-600 mb-1" style="font-size:11px;" for="secQ">Kata Kunci</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="q" id="secQ" class="form-control" placeholder="Deskripsi / referensi" value="{{ $filters['q'] ?? '' }}">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary" aria-label="Cari"><i class="fas fa-search" aria-hidden="true"></i></button>
                                <a href="{{ route('security.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary" title="Reset filter" aria-label="Reset filter"><i class="fas fa-times" aria-hidden="true"></i></a>
                            </div>
                        </div>
                    </div>
                </form>

                @if($logs->isEmpty())
                    <x-empty title="Belum ada catatan" message="Tidak ada aktivitas yang cocok dengan filter saat ini." icon="fas fa-stream" />
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col" style="width:130px;">Waktu</th>
                                    <th scope="col">User</th>
                                    <th scope="col">Aktivitas</th>
                                    <th scope="col" style="width:120px;">IP</th>
                                    @if($hasDeviceColumn)
                                        <th scope="col" style="width:22%;">Perangkat</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                <tr>
                                    <td class="text-nowrap">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                                    <td>
                                        @if($log->actor_name)
                                            <div class="font-weight-bold">{{ $log->actor_name }}</div>
                                            <small class="text-muted">{{ $log->actor_role ?? '-' }}</small>
                                        @else
                                            <span class="text-muted">— tanpa sesi —</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="mb-1">
                                            <span class="badge badge-light border" style="font-size:10px;">{{ $log->module_label }}</span>
                                            <span class="badge badge-{{ $log->action_color }}" style="font-size:10px;">{{ $log->action_label }}</span>
                                            @if($log->reference)
                                                <span class="text-muted ml-1" style="font-size:11px;">{{ $log->reference }}</span>
                                            @endif
                                        </div>
                                        <div>{{ $log->description }}</div>
                                        @if($log->notes)
                                            <div class="text-muted" style="font-size:11.5px;"><i class="fas fa-sticky-note mr-1" aria-hidden="true"></i>{{ $log->notes }}</div>
                                        @endif
                                        @if(!empty($log->changes))
                                            <div class="mt-1" style="font-size:11.5px;">
                                                @foreach($log->changes as $chg)
                                                    <div class="text-muted">
                                                        <span class="font-weight-600">{{ $chg['label'] ?? '-' }}:</span>
                                                        <span class="text-danger">{{ $chg['from'] ?? '-' }}</span>
                                                        <i class="fas fa-long-arrow-alt-right mx-1" aria-hidden="true"></i>
                                                        <span class="text-success">{{ $chg['to'] ?? '-' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $log->ip_address ?? '-' }}</td>
                                    @if($hasDeviceColumn)
                                        <td><small class="text-muted" title="{{ $log->user_agent }}">{{ \Illuminate\Support\Str::limit($log->user_agent ?? '-', 70) }}</small></td>
                                    @endif
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $logs->links() }}</div>
                @endif

            @else
                {{-- SESI AKTIF --}}
                @if(! $sessionsEnabled)
                    <x-empty title="Sesi tidak dapat ditampilkan"
                             message="Driver sesi aplikasi bukan database, jadi daftar sesi tidak tersimpan di tabel sessions."
                             icon="fas fa-desktop" />
                @elseif($sessions->isEmpty())
                    <x-empty title="Tidak ada sesi aktif" message="Belum ada sesi login yang masih berlaku." icon="fas fa-desktop" />
                @else
                    <p class="text-muted" style="font-size:12px;">
                        Mencabut sesi juga mereset "Ingat saya" user tersebut, sehingga perangkat yang memakainya harus login ulang.
                        Sesi yang sedang Anda pakai tidak bisa dicabut dari sini.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm mb-0" style="font-size:12.5px;">
                            <thead class="bg-light">
                                <tr>
                                    <th scope="col">User</th>
                                    <th scope="col" style="width:120px;">IP</th>
                                    <th scope="col">Perangkat</th>
                                    <th scope="col" style="width:140px;">Aktivitas Terakhir</th>
                                    <th scope="col" class="text-center" style="width:200px;"><span class="sr-only">Aksi</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sessions as $s)
                                @php $isMine = hash_equals($currentHash, $s->hash); @endphp
                                <tr>
                                    <td>
                                        <div class="font-weight-bold">{{ $s->user?->name ?? '-' }}</div>
                                        <small class="text-muted">{{ $s->user ? '@' . $s->user->username : '' }} · {{ $s->user?->role?->name ?? '-' }}</small>
                                        @if($isMine)
                                            <span class="badge badge-info ml-1">Sesi ini</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $s->ip_address ?? '-' }}</td>
                                    <td><small class="text-muted" title="{{ $s->user_agent }}">{{ \Illuminate\Support\Str::limit($s->user_agent ?? '-', 90) }}</small></td>
                                    <td class="text-nowrap">{{ $s->last_activity->format('d M Y H:i') }}<br><small class="text-muted">{{ $s->last_activity->diffForHumans() }}</small></td>
                                    <td class="text-center text-nowrap">
                                        @unless($isMine)
                                            <form method="POST" action="{{ route('security.sessions.revoke') }}" class="d-inline"
                                                  data-confirm="Cabut sesi {{ $s->user?->name ?? '' }} dari {{ $s->ip_address ?? 'IP tidak diketahui' }}? User harus login ulang di perangkat itu.">
                                                @csrf
                                                <input type="hidden" name="session_hash" value="{{ $s->hash }}">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Cabut</button>
                                            </form>
                                        @endunless
                                        @if($s->user && (int) $s->user->id !== (int) auth()->id())
                                            <form method="POST" action="{{ route('security.users.logout-all', $s->user->id) }}" class="d-inline"
                                                  data-confirm="Putus SEMUA sesi {{ $s->user->name }} di semua perangkat?">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary btn-sm">Semua perangkat</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif

        </x-tw.card>
        </div>
    </div>
</div>
@endsection

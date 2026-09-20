@extends('layouts.app')

@section('title', 'Team')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Team</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">Team</li>
            </ol>
        </div>

        <x-stat-cards :cards="[
            ['Total Team', $summary['total'], 'primary', 'fas fa-sitemap'],
            ['Active', $summary['active'], 'success', 'fas fa-check-circle'],
            ['Total Member Aktif', $summary['members'], 'info', 'fas fa-users'],
        ]" />

        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header d-flex align-items-center flex-wrap">
                <h3 class="card-title"><i class="fas fa-sitemap mr-1"></i> Daftar Team</h3>
                <div class="ml-auto d-flex flex-wrap" style="gap:8px;">
                    <form method="GET" class="d-flex" style="gap:6px;">
                        <input type="search" name="q" class="form-control form-control-sm"
                               value="{{ $filters['q'] }}" placeholder="Cari team / leader / anggota"
                               style="width:220px;">
                        <select name="department_id" class="form-control form-control-sm" onchange="this.form.submit()" style="width:auto;">
                            <option value="">Semua Department</option>
                            @foreach($departments as $dep)
                            <option value="{{ $dep->id }}" {{ request('department_id') == $dep->id ? 'selected' : '' }}>{{ $dep->name }}</option>
                            @endforeach
                        </select>
                        <select name="status" class="form-control form-control-sm" onchange="this.form.submit()" style="width:auto;">
                            <option value="">Semua Status</option>
                            <option value="ACTIVE" {{ request('status') === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                            <option value="INACTIVE" {{ request('status') === 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
                        @if($filters['q'] !== '' || $filters['department_id'] || $filters['status'])
                        <a href="{{ url('/team') }}" class="btn btn-outline-secondary btn-sm" title="Reset filter">
                            <i class="fas fa-eraser"></i>
                        </a>
                        @endif
                    </form>
                    {{-- Kelola Department superadmin saja — gerbang yang sama
                         ditegakkan di routes/web.php, jadi menyembunyikan tombol
                         ini bukan satu-satunya penjaga. --}}
                    @if(Auth::user()->hasRole('superadmin'))
                    <a href="{{ url('/department') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-building mr-1"></i> Kelola Department
                    </a>
                    @endif
                    @if(Auth::user()->hasRole('superadmin'))
                    <a href="{{ url('/team/create') }}" class="btn btn-success btn-sm">
                        <i class="fas fa-plus mr-1"></i> Tambah Team
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="text-nowrap" style="width:1%;">No</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Kode</th>
                                <th scope="col">Nama</th>
                                <th scope="col" class="text-nowrap">Department</th>
                                <th scope="col" class="text-nowrap">Leader</th>
                                <th scope="col">Anggota</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Member</th>
                                <th scope="col" class="text-nowrap" style="width:1%;">Status</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teams as $team)
                            <tr>
                                <td>{{ $teams->firstItem() + $loop->index }}</td>
                                <td><span class="badge badge-dark px-2 py-1">{{ $team->code }}</span></td>
                                <td style="font-weight:600;">{{ $team->name }}</td>
                                <td>{{ $team->department->name ?? '-' }}</td>
                                <td>{{ strtoupper($team->leader->username ?? '-') }}</td>
                                <td>
                                    {{-- M-02 — relasi Team -> anggota ditampilkan langsung
                                         di listing. Dibatasi 4 nama supaya barisnya tidak
                                         memanjang; selebihnya diringkas jadi hitungan. --}}
                                    @forelse($team->activeMembers->take(4) as $m)
                                        <span class="badge badge-light border px-2 py-1" style="font-size:10px;">
                                            {{ strtoupper($m->user->username ?? '-') }}@if($m->position)<span class="text-muted"> · {{ $m->position }}</span>@endif
                                        </span>
                                    @empty
                                        <span class="text-muted" style="font-size:11px;">Belum ada anggota</span>
                                    @endforelse
                                    @if($team->active_members_count > 4)
                                        <span class="text-muted" style="font-size:10px;">+{{ $team->active_members_count - 4 }} lainnya</span>
                                    @endif
                                </td>
                                <td class="text-center"><span class="badge badge-secondary px-2 py-1">{{ $team->active_members_count }}</span></td>
                                <td><span class="badge badge-{{ $team->status_color }} px-2 py-1">{{ $team->status }}</span></td>
                                <td class="text-center action-column">
                                    @php
                                        $bolehEditTeam  = Auth::user()->hasRole('superadmin') || (int) Auth::id() === (int) $team->leader_id;
                                        $bolehHapusTeam = Auth::user()->hasRole('superadmin');
                                    @endphp
                                    <div class="action-buttons ts-actions">

                                        {{-- AKSI UTAMA — Edit bila berhak, kalau tidak Detail. --}}
                                        @if($bolehEditTeam)
                                        <a href="{{ url('/team/' . $team->id . '/edit') }}"
                                           class="ts-action-primary is-neutral"
                                           title="Edit" aria-label="Edit {{ $team->name }}">
                                            <i class="fas fa-pen" aria-hidden="true"></i><span class="ts-al">Edit</span>
                                        </a>
                                        @else
                                        <a href="{{ url('/team/' . $team->id) }}"
                                           class="ts-action-primary is-neutral"
                                           title="Detail" aria-label="Detail {{ $team->name }}">
                                            <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Detail</span>
                                        </a>
                                        @endif

                                        @if($bolehEditTeam || $bolehHapusTeam)
                                        <button type="button" class="ts-action-more"
                                                aria-haspopup="true" aria-expanded="false"
                                                title="Aksi lain" aria-label="Aksi lain untuk {{ $team->name }}">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="ts-action-menu dropdown-menu" hidden>

                                            @if($bolehEditTeam)
                                            <a href="{{ url('/team/' . $team->id) }}" class="dropdown-item">
                                                <i class="fas fa-eye"></i>Detail
                                            </a>
                                            @endif

                                            @if($bolehHapusTeam)
                                            @if($bolehEditTeam)<div class="dropdown-divider"></div>@endif
                                            <form action="{{ url('/team/' . $team->id) }}" method="POST" class="delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button aria-label="Hapus" type="submit" class="dropdown-item is-danger">
                                                    <i class="fas fa-trash"></i>Hapus
                                                </button>
                                            </form>
                                            @endif

                                        </div>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada team yang cocok dengan filter ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- M-02 — daftar team kini dipaginasi; tautan halaman mempertahankan
                     filter aktif lewat withQueryString() di controller. --}}
                <div class="mt-3">
                    {{ $teams->links() }}
                </div>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    $('.delete-form').submit(function (e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            title: 'Hapus Team?', text: 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#9a3030', cancelButtonColor: '#55595e',
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal'
        }).then(function (r) { if (r.isConfirmed) form.submit(); });
    });
});
</script>
@endpush

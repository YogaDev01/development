@extends('layouts.app')

@section('title', 'Team')

@section('content')

@php $denganInduk = \App\Models\Team::supportsStructure(); @endphp

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

        {{-- Dua tab: Database (daftar team) & Organisasi (ringkasan + struktur
             keseluruhan). Tab aktif diingat lewat hash URL (#organisasi). --}}
        <ul class="nav nav-tabs mb-3" id="teamTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tabDatabase" data-toggle="tab" href="#paneDatabase" role="tab" aria-controls="paneDatabase" aria-selected="true">
                    <i class="fas fa-database mr-1"></i> Database
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tabOrganisasi" data-toggle="tab" href="#paneOrganisasi" role="tab" aria-controls="paneOrganisasi" aria-selected="false">
                    <i class="fas fa-sitemap mr-1"></i> Organisasi
                </a>
            </li>
        </ul>

        <div class="tab-content">
        <div class="tab-pane fade show active" id="paneDatabase" role="tabpanel" aria-labelledby="tabDatabase">
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
                                @if($denganInduk)
                                <th scope="col" class="text-nowrap">Induk</th>
                                @endif
                                <th scope="col" class="text-nowrap">Leader</th>
                                <th scope="col">Anggota</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;">Member</th>
                                <th scope="col" class="text-center text-nowrap" style="width:1%;" title="Jumlah Access Group yang dipasang ke team">Akses PO</th>
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
                                @if($denganInduk)
                                <td>{{ $team->parent->name ?? '-' }}</td>
                                @endif
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
                                <td class="text-center">{{ $team->access_group_links_count ?: '-' }}</td>
                                <td><span class="badge badge-{{ $team->status_color }} px-2 py-1">{{ $team->status }}</span></td>
                                <td class="text-center action-column">
                                    @php
                                        $bolehEditTeam  = Auth::user()->hasRole('superadmin') || (int) Auth::id() === (int) $team->leader_id;
                                        $bolehStatusTeam = Auth::user()->hasRole('superadmin');
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

                                        @if($bolehEditTeam || $bolehStatusTeam)
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

                                            {{-- Team tidak dihapus permanen: riwayat anggotanya
                                                 ikut hilang. Gantinya Aktifkan / Nonaktifkan. --}}
                                            @if($bolehStatusTeam)
                                            @if($bolehEditTeam)<div class="dropdown-divider"></div>@endif
                                            @php $keAktif = $team->status !== 'ACTIVE'; @endphp
                                            <form action="{{ route('team.status', $team->id) }}" method="POST" class="status-form"
                                                  data-team="{{ $team->name }}" data-aktif="{{ $keAktif ? 1 : 0 }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $keAktif ? 'ACTIVE' : 'INACTIVE' }}">
                                                <button type="submit" class="dropdown-item {{ $keAktif ? '' : 'is-danger' }}">
                                                    <i class="fas {{ $keAktif ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>{{ $keAktif ? 'Aktifkan' : 'Nonaktifkan' }}
                                                </button>
                                            </form>
                                            @endif

                                        </div>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="{{ $denganInduk ? 11 : 10 }}" class="text-center text-muted py-4">Tidak ada team yang cocok dengan filter ini.</td></tr>
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
        </div>{{-- /paneDatabase --}}

        <div class="tab-pane fade" id="paneOrganisasi" role="tabpanel" aria-labelledby="tabOrganisasi">
            <div class="card card-primary card-outline shadow-sm">
                <div class="card-header d-flex align-items-center flex-wrap">
                    <h3 class="card-title"><i class="fas fa-sitemap mr-1"></i> Struktur Organisasi</h3>
                    <span class="ml-auto text-muted" style="font-size:11.5px;">Ringkasan &amp; struktur keseluruhan team ACTIVE</span>
                </div>
                <div class="card-body">
                    @include('team._organization')
                </div>
            </div>
        </div>{{-- /paneOrganisasi --}}
        </div>{{-- /tab-content --}}

    </div>
</div>

{{-- Di luar .tab-pane supaya panel tidak ikut tersembunyi saat tab berganti. --}}
<x-drawer id="osPerson" title="Profil Organisasi" width="420px" label="Profil organisasi">
    <div id="osPersonBody"></div>
</x-drawer>

@endsection

@push('scripts')
<script>
$(function () {
    // Tab aktif diingat lewat hash (#organisasi) — juga dipakai redirect
    // dari URL lama /team/structure. Hash anchor node (#team-…) diabaikan.
    if (location.hash === '#organisasi') {
        $('#tabOrganisasi').tab('show');
    }
    $('#teamTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var hash = e.target.id === 'tabOrganisasi' ? '#organisasi' : '';
        history.replaceState(null, '', location.pathname + location.search + hash);
    });

    $('.status-form').submit(function (e) {
        e.preventDefault();
        var form = this;
        var aktif = $(form).data('aktif') == 1;
        var nama = $(form).data('team');
        Swal.fire({
            // titleText (bukan title): nama team diperlakukan sebagai teks, bukan HTML.
            titleText: (aktif ? 'Aktifkan' : 'Nonaktifkan') + ' Team ' + nama + '?',
            text: aktif
                ? 'Team dapat dipakai kembali.'
                : 'Team berhenti dipakai. Anggota dan riwayatnya tetap tersimpan.',
            icon: aktif ? 'question' : 'warning', showCancelButton: true,
            input: 'text', inputPlaceholder: 'Catatan (opsional)',
            confirmButtonColor: aktif ? '#92232b' : '#9a3030', cancelButtonColor: '#55595e',
            confirmButtonText: aktif ? 'Ya, Aktifkan' : 'Ya, Nonaktifkan', cancelButtonText: 'Batal'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            if (r.value) $('<input type="hidden" name="notes">').val(r.value).appendTo(form);
            form.submit();
        });
    });
});
</script>
@endpush

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
                @include('team._database')
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

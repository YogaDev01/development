@extends('layouts.app')

@section('title', 'Team')

@section('content')

@php $denganInduk = \App\Models\Team::supportsStructure(); @endphp

<div class="content">
    <div class="container-fluid">

        {{-- Judul & breadcrumb dipindah shell ke topbar (pola Cost Request). --}}
        <div class="tw:sr-only">
            <h1>Team</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Master</a></li>
                <li class="breadcrumb-item active">Team</li>
            </ol>
        </div>
        <x-tw.page-header class="tw:py-4 tw:mb-4" title="Team & Struktur Organisasi"
            description="Daftar team, anggota aktif, dan struktur organisasi keseluruhan.">
            @if(Auth::user()->hasRole('superadmin'))
            {{-- Kelola Department & Tambah Team superadmin saja — gerbang yang sama
                 ditegakkan di routes/web.php, jadi menyembunyikan tombol ini bukan
                 satu-satunya penjaga. --}}
            <x-slot:actions>
                <x-tw.button variant="secondary" href="{{ url('/department') }}"><i class="fas fa-building" aria-hidden="true"></i> Kelola Department</x-tw.button>
                <x-tw.button href="{{ url('/team/create') }}"><i class="fas fa-plus" aria-hidden="true"></i> Tambah Team</x-tw.button>
            </x-slot:actions>
            @endif
        </x-tw.page-header>

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
        {{-- Kartu tunggal toolbar + tabel (pola Cost Request). --}}
        <x-tw.card padding="sm">
            <form method="GET" class="tw:mb-3 tw:flex tw:flex-wrap tw:items-center tw:gap-2">
                <div class="input-group input-group-sm" style="width:260px;">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span></div>
                    <input type="search" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="Cari team / leader / anggota" aria-label="Cari team">
                </div>
                <select name="department_id" class="form-control form-control-sm" onchange="this.form.submit()" style="width:auto;" aria-label="Department">
                    <option value="">Semua Department</option>
                    @foreach($departments as $dep)
                    <option value="{{ $dep->id }}" {{ request('department_id') == $dep->id ? 'selected' : '' }}>{{ $dep->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()" style="width:auto;" aria-label="Status">
                    <option value="">Semua Status</option>
                    <option value="ACTIVE" {{ request('status') === 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                    <option value="INACTIVE" {{ request('status') === 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
                </select>
                <x-tw.button type="submit" variant="secondary" size="sm"><i class="fas fa-filter" aria-hidden="true"></i> Filter</x-tw.button>
                @if($filters['q'] !== '' || $filters['department_id'] || $filters['status'])
                <x-tw.button variant="ghost" size="sm" href="{{ url('/team') }}" title="Clear Filter" aria-label="Clear Filter"><i class="fas fa-eraser" aria-hidden="true"></i></x-tw.button>
                @endif
            </form>
            @include('team._database')
        </x-tw.card>
        </div>{{-- /paneDatabase --}}

        <div class="tab-pane fade" id="paneOrganisasi" role="tabpanel" aria-labelledby="tabOrganisasi">
            <x-tw.card padding="sm">
                <x-slot:header>Struktur Organisasi <span class="tw:text-text-dim" style="font-weight:400;">· ringkasan &amp; struktur keseluruhan team ACTIVE</span></x-slot:header>
                @include('team._organization')
            </x-tw.card>
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

@push('styles')
{{-- Utilitas tw: (x-tw.*) dimuat per halaman, sama seperti Cost Request. --}}
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush

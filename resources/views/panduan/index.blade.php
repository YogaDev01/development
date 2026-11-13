@extends('layouts.app')

@section('title', 'Panduan Penggunaan')

@section('content')

<div class="content">
    <div class="container-fluid">

        {{-- Judul & breadcrumb dipindah shell ke topbar (pola Cost Request). --}}
        <div class="tw:sr-only">
            <h1>Panduan Penggunaan</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Help</a></li>
                <li class="breadcrumb-item active">Panduan</li>
            </ol>
        </div>
        <x-tw.page-header class="tw:py-4 tw:mb-4" title="Panduan Penggunaan TESYS"
            description="Cara memakai setiap modul; pilih topik di daftar samping atau kartu di bawah.">
        </x-tw.page-header>

        <div class="pnd-layout">

            @include('panduan._nav', ['activeSlug' => null])

            <div class="pnd-content">
                <p class="pnd-lead">
                    Panduan ini menjelaskan cara menggunakan setiap modul di lingkup
                    <strong>Finance</strong> dan <strong>Project Monitoring</strong>.
                    Pilih topik di daftar samping atau melalui kartu di bawah. Ketersediaan
                    menu mengikuti hak akses (role) masing-masing pengguna.
                </p>

                @foreach($groups as $group)
                    <div class="pnd-index-group">
                        <h2><i class="fas {{ $group['icon'] }}"></i> {{ $group['label'] }}</h2>
                        <div class="pnd-cards">
                            @foreach($group['topics'] as $t)
                                <a href="{{ route('panduan.show', $t['slug']) }}" class="pnd-card">
                                    <span class="pnd-card-ico"><i class="fas {{ $t['icon'] }}"></i></span>
                                    <span class="pnd-card-txt">
                                        <strong>{{ $t['title'] }}</strong>
                                        <span>{{ $t['desc'] }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

        </div>

    </div>
</div>

@endsection

@push('styles')
    @include('panduan._styles')
@endpush

@push('styles')
{{-- Utilitas tw: (x-tw.*) dimuat per halaman, sama seperti Cost Request. --}}
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush

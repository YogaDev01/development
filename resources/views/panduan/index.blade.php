@extends('layouts.app')

@section('title', 'Panduan Penggunaan')

@section('content')

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);"><i class="fas fa-question-circle mr-2"></i>Panduan Penggunaan Tesys</h1>
        </div>

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

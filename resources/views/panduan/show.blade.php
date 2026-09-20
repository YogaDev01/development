@extends('layouts.app')

@section('title', 'Panduan — ' . $topic['title'])

@section('content')

@php
    $flat = [];
    foreach ($groups as $g) {
        foreach ($g['topics'] as $t) { $flat[] = $t; }
    }
    $idx  = collect($flat)->search(fn($t) => $t['slug'] === $topic['slug']);
    $prev = $idx > 0 ? $flat[$idx - 1] : null;
    $next = $idx !== false && $idx < count($flat) - 1 ? $flat[$idx + 1] : null;
@endphp

<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div class="d-flex align-items-center">
                <a href="{{ route('panduan.index') }}" class="btn btn-secondary btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">
                    <i class="fas {{ $topic['icon'] }} mr-2"></i>{{ $topic['title'] }}
                </h1>
            </div>
        </div>

        <div class="pnd-layout">

            @include('panduan._nav', ['activeSlug' => $topic['slug']])

            <div class="pnd-content">
                <div class="pnd-breadcrumb">
                    <a href="{{ route('panduan.index') }}">Panduan</a>
                    <span class="mx-1">/</span> {{ $groupLabel }}
                    <span class="mx-1">/</span> {{ $topic['title'] }}
                </div>

                @include('panduan.topics.' . $topic['slug'])

                <div class="pnd-pager">
                    @if($prev)
                        <a href="{{ route('panduan.show', $prev['slug']) }}">&larr; {{ $prev['title'] }}</a>
                    @else
                        <span class="disabled">&nbsp;</span>
                    @endif
                    @if($next)
                        <a href="{{ route('panduan.show', $next['slug']) }}">{{ $next['title'] }} &rarr;</a>
                    @else
                        <span class="disabled">&nbsp;</span>
                    @endif
                </div>
            </div>

        </div>

    </div>
</div>

@endsection

@push('styles')
    @include('panduan._styles')
@endpush

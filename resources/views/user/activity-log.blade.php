@extends('layouts.app')

@section('title', 'Activity Log — ' . $user->name)

@section('content')
<div class="content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">Activity Log</h1>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="{{ url('/user') }}">User &amp; Access</a></li>
                <li class="breadcrumb-item active">Activity Log</li>
            </ol>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body d-flex align-items-center flex-wrap" style="gap:12px;">
                <div style="width:48px;height:48px;background:var(--charcoal);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    @if($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    @else
                        <span style="color:var(--accent);font-size:16px;font-weight:700;">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                    @endif
                </div>
                <div>
                    <div class="font-weight-bold">{{ $user->name }}</div>
                    <div class="text-muted" style="font-size:12px;">
                        {{ $user->email }}
                        <span class="badge badge-warning ml-1">{{ Str::upper($user->role->name ?? '-') }}</span>
                    </div>
                </div>
                <a href="{{ url('/user') }}" class="btn btn-secondary btn-sm ml-auto">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali
                </a>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                @include('profile._activity_log')
            </div>
        </div>
    </div>
</div>
@endsection

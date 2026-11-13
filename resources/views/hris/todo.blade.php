@extends('layouts.app')

@section('title', 'Todo List HRIS')

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <h4 class="mb-1">Todo List HRIS</h4>
        <div class="text-muted small">Tugas aktif Anda di Letter Management, Manpower Request, dan Recruitment. {{ $total }} tugas.</div>
    </div>

    @if (! array_filter($access))
        <div class="alert alert-secondary">Anda belum memiliki akses ke modul HRIS mana pun, jadi belum ada tugas yang bisa ditampilkan.</div>
    @elseif ($total === 0)
        <div class="card"><div class="card-body text-center text-muted py-5">Tidak ada tugas aktif. Semua beres.</div></div>
    @endif

    @foreach ($groups as $group => $items)
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center"><b>{{ $group }}</b><span class="badge badge-secondary ml-2">{{ $items->count() }}</span></div>
            <div class="list-group list-group-flush">
                @foreach ($items as $t)
                    <a href="{{ $t['url'] }}" class="list-group-item list-group-item-action d-flex align-items-center" style="gap:10px">
                        <span class="badge badge-{{ $t['badge'] }}">&nbsp;</span>
                        <span class="flex-grow-1">
                            <span class="font-weight-bold">{{ $t['title'] }}</span>
                            <span class="d-block small text-muted">{{ $t['sub'] }}</span>
                        </span>
                        <span class="small text-muted text-nowrap">{{ $t['since']?->diffForHumans() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
@endsection

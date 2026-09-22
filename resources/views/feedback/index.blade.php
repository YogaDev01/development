@extends('layouts.app')

@section('title', 'General Feedback')

@section('content')

<div class="content">
    <div class="container-fluid">

        @if(session('success'))
        <div class="alert alert-success rounded-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger rounded-0 shadow-sm">{{ session('error') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
            <div>
                <h1 class="m-0 font-weight-bold" style="font-size:20px;font-weight:700;color:var(--text);">General Feedback</h1>
                <small class="text-muted">Riwayat submission Anda kepada CEO.</small>
            </div>
            <ol class="breadcrumb m-0 p-0 bg-transparent" style="font-size:12px;">
                <li class="breadcrumb-item"><a href="#">General</a></li>
                <li class="breadcrumb-item active">General Feedback</li>
            </ol>
        </div>

        <div class="card card-primary card-outline shadow-sm mt-3">
            <div class="card-header d-flex align-items-center flex-wrap" style="gap:8px;">
                <h3 class="card-title"><i class="fas fa-comment-dots mr-1"></i> Riwayat Submission Saya</h3>
                <div class="ml-auto d-flex" style="gap:8px;">
                    <a href="{{ route('feedback.create') }}" class="btn btn-success btn-sm">
                        <i class="fas fa-plus mr-1"></i> Buat Submission
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" style="width:12%;">Kategori</th>
                                <th scope="col">Judul</th>
                                <th scope="col" style="width:10%;">Bersedia Diskusi</th>
                                <th scope="col" style="width:12%;">Status</th>
                                <th scope="col" style="width:12%;">Tanggal</th>
                                <th scope="col" style="width:8%;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($submissions as $s)
                            <tr>
                                <td><span class="badge badge-{{ $s->category_color }} px-2 py-1">{{ $s->category_label }}</span></td>
                                <td>{{ $s->title }}</td>
                                <td>
                                    @if($s->willing_to_discuss)
                                    <span class="badge badge-success px-2 py-1">Ya</span>
                                    @else
                                    <span class="badge badge-light border px-2 py-1">Tidak</span>
                                    @endif
                                </td>
                                <td><span class="badge badge-{{ $s->status_color }} px-2 py-1">{{ $s->status_label }}</span></td>
                                <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-center action-column">
                                    <div class="action-buttons ts-actions">
                                        <a href="{{ route('feedback.show', $s->id) }}"
                                           class="ts-action-primary is-neutral"
                                           title="Detail" aria-label="Detail masukan">
                                            <i class="fas fa-eye" aria-hidden="true"></i><span class="ts-al">Detail</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty title="Anda belum pernah membuat submission." icon="fas fa-comment-slash" compact />
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@extends('layouts.app')

@section('title', 'General Feedback')

@section('content')

@php
    // Ringkasan dihitung dari $submissions yang sudah dimuat controller (tanpa query tambahan).
    $total      = $submissions->count();
    $menunggu   = $submissions->whereIn('status', ['SUBMITTED', 'REVIEWED'])->count();
    $ditanggapi = $submissions->where('status', 'RESPONDED')->count();
    $selesai    = $submissions->where('status', 'CLOSED')->count();
@endphp

<div class="content">
    <div class="container-fluid">
        <div class="tw:mx-auto tw:flex tw:max-w-[1200px] tw:flex-col tw:gap-5 tw:py-4">

            {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar lalu
                 menghapusnya dari konten (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
            <div class="tw:sr-only">
                <h1>General Feedback</h1>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">General</a></li>
                    <li class="breadcrumb-item active">General Feedback</li>
                </ol>
            </div>

            @if(session('success'))
            <div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>
            @endif
            @if(session('error'))
            <div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>
            @endif

            <x-tw.page-header eyebrow="General · Feedback" title="Riwayat Submission"
                description="Masukan, kritik, dan saran Anda kepada CEO beserta perkembangan statusnya.">
                <x-slot:actions>
                    {{-- Navigasi = tautan (x-tw.button href). --}}
                    <x-tw.button href="{{ route('feedback.create') }}" class="tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Buat Submission</x-tw.button>
                </x-slot:actions>
            </x-tw.page-header>

            <div class="tw:grid tw:grid-cols-2 tw:gap-3 tw:lg:grid-cols-4">
                <x-tw.stat label="Total" :value="$total" tone="accent" hint="Semua submission Anda" />
                <x-tw.stat label="Menunggu" :value="$menunggu" tone="neutral" hint="Submitted dan Reviewed" />
                <x-tw.stat label="Ditanggapi" :value="$ditanggapi" tone="progress" hint="Sudah dibalas CEO" />
                <x-tw.stat label="Selesai" :value="$selesai" tone="positive" hint="Ditandai Closed" />
            </div>

            <x-tw.card padding="none" class="tw:overflow-hidden">
                <x-slot:header>
                    <div class="tw:flex tw:items-center tw:gap-2">
                        <span role="heading" aria-level="2">Daftar Submission</span>
                        <x-tw.badge tone="neutral">{{ $total }} submission</x-tw.badge>
                    </div>
                </x-slot:header>

                @if($submissions->isEmpty())
                <div class="tw:flex tw:flex-col tw:items-center tw:gap-4 tw:px-card tw:py-10 tw:text-center">
                    <div class="tw:flex tw:h-12 tw:w-12 tw:items-center tw:justify-center tw:rounded-md tw:border tw:border-border tw:bg-surface-2 tw:text-text-dim">
                        <i class="fas fa-comment-slash" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="tw:text-sub tw:font-section tw:text-text">Belum ada submission</div>
                        <div class="tw:mt-1 tw:text-body tw:text-text-dim">Anda belum pernah membuat submission. Masukan Anda kepada CEO akan tercatat di sini.</div>
                    </div>
                    <x-tw.button href="{{ route('feedback.create') }}" size="sm" class="tw:max-sm:min-h-12"><i class="fas fa-plus" aria-hidden="true"></i> Buat Submission</x-tw.button>
                </div>
                @else
                <x-tw.table caps>
                    <x-slot:head>
                        <tr>
                            <th scope="col">Judul</th>
                            <th scope="col">Kategori</th>
                            <th scope="col" class="tw:whitespace-nowrap">Bersedia Diskusi</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="tw:block tw:text-right">Aksi</span></th>
                        </tr>
                    </x-slot:head>

                    @foreach($submissions as $s)
                    <tr class="tw:hover:bg-surface-2">
                        <td class="tw:w-full">
                            <div class="tw:text-body tw:font-label tw:leading-tight tw:text-text">{{ $s->title }}</div>
                            <div class="tw:mt-1 tw:text-caption tw:text-text-dim">Dikirim {{ $s->created_at->format('d/m/Y H:i') }}</div>
                        </td>
                        <td><x-tw.badge dot :tone="$s->category_color">{{ $s->category_label }}</x-tw.badge></td>
                        <td>
                            @if($s->willing_to_discuss)
                            <x-tw.badge tone="positive">Ya</x-tw.badge>
                            @else
                            <x-tw.badge tone="neutral">Tidak</x-tw.badge>
                            @endif
                        </td>
                        <td><x-tw.badge dot :tone="$s->status_color">{{ $s->status_label }}</x-tw.badge></td>
                        <td class="tw:text-right">
                            <x-tw.button href="{{ route('feedback.show', $s->id) }}" variant="ghost" size="sm"
                                class="tw:max-sm:min-h-12" aria-label="Lihat detail submission: {{ $s->title }}">
                                <i class="fas fa-eye" aria-hidden="true"></i> Lihat
                            </x-tw.button>
                        </td>
                    </tr>
                    @endforeach
                </x-tw.table>
                @endif
            </x-tw.card>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush

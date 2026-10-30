@extends('layouts.app')

@section('title', 'Detail Submission')

@section('content')

@php
    // Tujuan & nama aksesibel tombol kembali (pembaca berwenang non-pemilik datang dari Inbox).
    $keInbox = $authorized && !$isOwner;
@endphp

<div class="content">
    <div class="container-fluid">
        <div class="tw:mx-auto tw:flex tw:max-w-[1200px] tw:flex-col tw:gap-5 tw:py-4">

            {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar lalu
                 menghapusnya dari konten (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
            <div class="tw:sr-only">
                <h1>{{ $submission->title }}</h1>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">General</a></li>
                    <li class="breadcrumb-item">{{ $keInbox ? 'Feedback Inbox' : 'General Feedback' }}</li>
                    <li class="breadcrumb-item active">Detail Submission</li>
                </ol>
            </div>

            @if(session('success'))
            <div class="alert alert-success rounded-0 shadow-sm mb-0">{{ session('success') }}</div>
            @endif
            @if(session('error'))
            <div class="alert alert-danger rounded-0 shadow-sm mb-0">{{ session('error') }}</div>
            @endif

            <div class="tw:flex tw:flex-col tw:gap-3">
                <x-tw.page-header eyebrow="General · Feedback · Detail" :title="$submission->title">
                    <x-slot:actions>
                        {{-- Tombol kembali = tautan (x-tw.button href); nama aksesibel lewat aria-label. --}}
                        <x-tw.button href="{{ $keInbox ? route('feedback.inbox') : route('feedback.index') }}" variant="secondary" class="tw:max-sm:min-h-12"
                            aria-label="{{ $keInbox ? 'Kembali ke Feedback Inbox' : 'Kembali ke daftar General Feedback' }}">
                            <i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali
                        </x-tw.button>
                    </x-slot:actions>
                </x-tw.page-header>

                <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-x-3 tw:gap-y-2 tw:text-data tw:text-text-dim">
                    <x-tw.badge dot size="md" :tone="$submission->status_color">{{ $submission->status_label }}</x-tw.badge>
                    <x-tw.badge dot size="md" :tone="$submission->category_color">{{ $submission->category_label }}</x-tw.badge>
                    <span>Dikirim {{ $submission->created_at->format('d/m/Y H:i') }}@if($authorized) oleh <span class="tw:font-label tw:text-text">{{ $submission->submitter->name ?? '-' }}</span>@endif</span>
                </div>
            </div>

            <div class="tw:grid tw:grid-cols-1 tw:items-start tw:gap-5 tw:lg:grid-cols-3">

                <div class="tw:flex tw:min-w-0 tw:flex-col tw:gap-5 tw:lg:col-span-2">

                    <x-tw.card>
                        <x-slot:header><span role="heading" aria-level="2"><i class="fas fa-comment-dots tw:mr-2 tw:text-text-dim" aria-hidden="true"></i>Isi Submission</span></x-slot:header>
                        @if($submission->category === 'CRITICISM')
                        <div class="tw:flex tw:flex-col tw:gap-5">
                            <div>
                                <div class="tw:mb-1 tw:text-caption tw:font-label tw:uppercase tw:tracking-micro tw:text-text-dim">Kritik</div>
                                <div class="tw:whitespace-pre-line tw:text-sub tw:leading-body tw:text-text">{{ $submission->criticism_text }}</div>
                            </div>
                            <div class="tw:border-t tw:border-border tw:pt-5">
                                <div class="tw:mb-1 tw:text-caption tw:font-label tw:uppercase tw:tracking-micro tw:text-text-dim">Saran Perbaikan</div>
                                <div class="tw:whitespace-pre-line tw:text-sub tw:leading-body tw:text-text">{{ $submission->suggestion_text }}</div>
                            </div>
                        </div>
                        @else
                        <div class="tw:whitespace-pre-line tw:text-sub tw:leading-body tw:text-text">{{ $submission->message }}</div>
                        @endif
                    </x-tw.card>

                    @if($submission->response_text)
                    <x-tw.card>
                        <x-slot:header><span role="heading" aria-level="2"><i class="fas fa-reply tw:mr-2 tw:text-accent-text" aria-hidden="true"></i>Tanggapan CEO</span></x-slot:header>
                        <div class="tw:whitespace-pre-line tw:text-sub tw:leading-body tw:text-text">{{ $submission->response_text }}</div>
                        <x-slot:footer>
                            Oleh {{ $submission->respondedBy->name ?? '-' }} &middot; {{ $submission->responded_at?->format('d/m/Y H:i') }}
                        </x-slot:footer>
                    </x-tw.card>
                    @endif
                </div>

                <div class="tw:flex tw:min-w-0 tw:flex-col tw:gap-5">

                    <x-tw.card>
                        <x-slot:header><span role="heading" aria-level="2"><i class="fas fa-info-circle tw:mr-2 tw:text-text-dim" aria-hidden="true"></i>Informasi</span></x-slot:header>
                        {{-- Baris key-value dari div (bukan tabel): th legacy ikut aturan tabel/kartu mobile. --}}
                        <div class="tw:flex tw:flex-col tw:text-data tw:leading-body">
                            @php $baris = 'tw:flex tw:min-h-8 tw:items-center tw:justify-between tw:gap-4 tw:border-b tw:border-border tw:py-2 tw:last:border-b-0'; @endphp
                            @if($authorized)
                            <div class="{{ $baris }}">
                                <span class="tw:text-text-dim">Pengirim</span>
                                <span class="tw:min-w-0 tw:text-right tw:font-label tw:text-text">{{ $submission->submitter->name ?? '-' }}</span>
                            </div>
                            @endif
                            <div class="{{ $baris }}">
                                <span class="tw:text-text-dim">Kategori</span>
                                <x-tw.badge dot :tone="$submission->category_color">{{ $submission->category_label }}</x-tw.badge>
                            </div>
                            <div class="{{ $baris }}">
                                <span class="tw:text-text-dim">Status</span>
                                <x-tw.badge dot :tone="$submission->status_color">{{ $submission->status_label }}</x-tw.badge>
                            </div>
                            <div class="{{ $baris }}">
                                <span class="tw:text-text-dim">Bersedia Diskusi</span>
                                <span class="tw:flex tw:min-w-0 tw:flex-wrap tw:items-center tw:justify-end tw:gap-2 tw:text-right">
                                    @if($submission->willing_to_discuss)
                                        <x-tw.badge tone="positive">Ya</x-tw.badge><span class="tw:text-text">{{ $submission->whatsapp_number }}</span>
                                    @else
                                        <x-tw.badge tone="neutral">Tidak</x-tw.badge>
                                    @endif
                                </span>
                            </div>
                            <div class="{{ $baris }}">
                                <span class="tw:text-text-dim">Tanggal Submit</span>
                                <span class="tw:text-right tw:text-text">{{ $submission->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            @if($submission->reviewed_at)
                            <div class="{{ $baris }}">
                                <span class="tw:text-text-dim">Direview</span>
                                <span class="tw:text-right tw:text-text">{{ $submission->reviewed_at->format('d/m/Y H:i') }}</span>
                            </div>
                            @endif
                            @if($submission->responded_at)
                            <div class="{{ $baris }}">
                                <span class="tw:text-text-dim">Ditanggapi</span>
                                <span class="tw:text-right tw:text-text">{{ $submission->responded_at->format('d/m/Y H:i') }}</span>
                            </div>
                            @endif
                            @if($submission->closed_at)
                            <div class="{{ $baris }}">
                                <span class="tw:text-text-dim">Ditutup</span>
                                <span class="tw:text-right tw:text-text">{{ $submission->closed_at->format('d/m/Y H:i') }}</span>
                            </div>
                            @endif
                        </div>
                    </x-tw.card>

                    @if($authorized && $submission->status !== 'CLOSED')
                    <x-tw.card>
                        <x-slot:header><span role="heading" aria-level="2"><i class="fas fa-reply tw:mr-2 tw:text-text-dim" aria-hidden="true"></i>Beri Tanggapan</span></x-slot:header>
                        <form action="{{ route('feedback.respond', $submission->id) }}" method="POST">
                            @csrf
                            <x-tw.textarea name="response_text" id="inpResponse" label="Tanggapan" rows="5" required
                                :value="$submission->response_text" :error="$errors->first('response_text')"
                                placeholder="Tuliskan tanggapan Anda kepada karyawan ini..." />
                            <div class="tw:flex tw:justify-end">
                                <x-tw.button type="submit" class="tw:max-sm:min-h-12 tw:max-sm:w-full"><i class="fas fa-paper-plane" aria-hidden="true"></i> Kirim Tanggapan</x-tw.button>
                            </div>
                        </form>
                    </x-tw.card>
                    @endif

                    @if($authorized && $submission->status === 'RESPONDED')
                    <x-tw.card>
                        <div class="tw:flex tw:flex-col tw:gap-3">
                            <div class="tw:text-data tw:leading-body tw:text-text-dim">Kalau tanggapan sudah cukup dan tidak ada tindak lanjut lagi, tandai submission ini selesai.</div>
                            <form action="{{ route('feedback.close', $submission->id) }}" method="POST" class="frmCloseFeedback">
                                @csrf
                                <x-tw.button type="submit" variant="secondary" class="tw:w-full tw:max-sm:min-h-12"><i class="fas fa-check-double" aria-hidden="true"></i> Tandai Selesai (Closed)</x-tw.button>
                            </form>
                        </div>
                    </x-tw.card>
                    @endif

                    @include('feedback._disclaimer', ['compact' => true])
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    $(document).on('submit', '.frmCloseFeedback', function (e) {
        e.preventDefault(); var form = this;
        Swal.fire({ title:'Tandai Selesai?', text:'Submission ini akan ditandai Closed.', icon:'question', showCancelButton:true, confirmButtonColor:'var(--success)', cancelButtonColor:'#55595e', confirmButtonText:'Ya, Tandai Selesai', cancelButtonText:'Batal' })
        .then(function(r){ if(r.isConfirmed) form.submit(); });
    });
});
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush

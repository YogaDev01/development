@extends('layouts.app')

@section('title', 'Buat Submission')

@section('content')

<div class="content">
    <div class="container-fluid">
        <div class="tw:mx-auto tw:flex tw:max-w-[1200px] tw:flex-col tw:gap-5 tw:py-4">

            {{-- Judul & breadcrumb topbar: shell memindahkan <h1> dan .breadcrumb pertama ke topbar lalu
                 menghapusnya dari konten (tesys-shell.js hoistPageTitle); disembunyikan secara visual. --}}
            <div class="tw:sr-only">
                <h1>Buat Submission — General Feedback</h1>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="#">General</a></li>
                    <li class="breadcrumb-item">General Feedback</li>
                    <li class="breadcrumb-item active">Buat Submission</li>
                </ol>
            </div>

            <x-tw.page-header eyebrow="General · Feedback" title="Buat Submission"
                description="Sampaikan kritik, saran, ide, atau concern Anda langsung kepada CEO.">
                <x-slot:actions>
                    {{-- Navigasi = tautan (x-tw.button href). --}}
                    <x-tw.button href="{{ route('feedback.index') }}" variant="secondary" class="tw:max-sm:min-h-12"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali ke daftar</x-tw.button>
                </x-slot:actions>
            </x-tw.page-header>

            @if($errors->any())
            <div role="alert" class="tw:rounded-md tw:border tw:border-danger tw:bg-danger-bg tw:p-3 tw:text-data tw:leading-body tw:text-danger">
                <div class="tw:font-label">Periksa kembali isian Anda:</div>
                <div class="tw:mt-1 tw:flex tw:flex-col tw:gap-1">
                    @foreach($errors->all() as $e)
                    <div class="tw:flex tw:gap-2"><span aria-hidden="true">&bull;</span><span>{{ $e }}</span></div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="tw:grid tw:grid-cols-1 tw:items-start tw:gap-5 tw:lg:grid-cols-3">

                <form action="{{ route('feedback.store') }}" method="POST" id="frmFeedback" class="tw:min-w-0 tw:lg:col-span-2">
                    @csrf

                    <x-tw.card padding="none" class="tw:overflow-hidden">

                        {{-- Bagian 1 --}}
                        <div class="tw:border-b tw:border-border tw:p-card">
                            <div class="tw:mb-4">
                                <div role="heading" aria-level="2" class="tw:text-sub tw:font-section tw:leading-tight tw:text-text">Kategori &amp; judul</div>
                                <div class="tw:mt-1 tw:text-caption tw:text-text-dim">Pilih jenis masukan dan beri ringkasan singkat.</div>
                            </div>

                            <x-tw.select name="category" id="selCategory" label="Kategori" placeholder="-- Pilih Kategori --" required
                                :error="$errors->first('category')" class="tw:max-sm:h-12">
                                <option value="SUGGESTION">Suggestion — Saran</option>
                                <option value="CRITICISM">Criticism — Kritik (wajib disertai saran perbaikan)</option>
                                <option value="CONCERN">Concern — Kekhawatiran/Keresahan</option>
                                <option value="IDEA">Idea — Ide/Gagasan</option>
                                <option value="OTHER">Other — Lainnya</option>
                            </x-tw.select>

                            <x-tw.input name="title" label="Judul" :value="old('title')" required maxlength="150"
                                        :error="$errors->first('title')"
                                        placeholder="Ringkasan singkat submission Anda..." class="tw:max-sm:h-12" />
                        </div>

                        {{-- Bagian 2 --}}
                        <div class="tw:border-b tw:border-border tw:p-card">
                            <div class="tw:mb-4">
                                <div role="heading" aria-level="2" class="tw:text-sub tw:font-section tw:leading-tight tw:text-text">Isi masukan</div>
                                <div class="tw:mt-1 tw:text-caption tw:text-text-dim">Tuliskan selengkap mungkin agar mudah dipahami dan ditindaklanjuti.</div>
                            </div>

                            <!-- MESSAGE TUNGGAL (semua kategori kecuali Criticism) -->
                            <div id="wrapMessage">
                                <x-tw.textarea name="message" id="inpMessage" label="Isi Masukan" rows="6" required
                                    :error="$errors->first('message')"
                                    placeholder="Tuliskan saran, ide, concern, atau masukan Anda secara lengkap..." />
                            </div>

                            <!-- CRITICISM: WAJIB DUA BAGIAN -->
                            <div id="wrapCriticism" style="display:none;">
                                <div class="tw:mb-4 tw:flex tw:gap-2 tw:rounded-md tw:bg-warning-bg tw:p-3 tw:text-caption tw:leading-body tw:text-warning">
                                    <i class="fas fa-info-circle tw:mt-0.5" aria-hidden="true"></i>
                                    <span>Kritik harus disertai saran perbaikan — keduanya wajib diisi, tidak boleh hanya salah satu.</span>
                                </div>
                                <x-tw.textarea name="criticism_text" id="inpCriticism" label="Kritik" rows="5" required
                                    :error="$errors->first('criticism_text')"
                                    placeholder="Apa yang menurut Anda perlu dikritisi?" />
                                <x-tw.textarea name="suggestion_text" id="inpSuggestion" label="Saran Perbaikan" rows="5" required
                                    :error="$errors->first('suggestion_text')"
                                    placeholder="Menurut Anda, bagaimana sebaiknya hal ini diperbaiki?" />
                            </div>
                        </div>

                        {{-- Bagian 3 --}}
                        <div class="tw:p-card">
                            <div class="tw:mb-4">
                                <div role="heading" aria-level="2" class="tw:text-sub tw:font-section tw:leading-tight tw:text-text">Tindak lanjut</div>
                                <div class="tw:mt-1 tw:text-caption tw:text-text-dim">Opsional. Centang bila Anda terbuka untuk diskusi langsung.</div>
                            </div>

                            {{-- Baris centang seluruhnya klik-able (label[for] + area ::after), tinggi >= 48px. --}}
                            <div class="tw:relative tw:mb-3 tw:flex tw:items-start tw:gap-3 tw:rounded-md tw:border tw:border-border tw:bg-surface tw:px-3 tw:pt-3 tw:pb-1 tw:hover:bg-surface-2 tw:has-checked:border-accent-text tw:has-checked:bg-surface-2 tw:has-focus-visible:outline-2 tw:has-focus-visible:outline-offset-2 tw:has-focus-visible:outline-accent-text">
                                <input type="checkbox" name="willing_to_discuss" value="1" id="chkWilling"
                                       class="tw:mt-0.5 tw:h-5 tw:w-5 tw:shrink-0 tw:cursor-pointer tw:accent-accent">
                                {{-- Teks di <span>: label legacy menetapkan warna/ukuran pada elemen label itu sendiri. --}}
                                <label for="chkWilling" class="tw:min-w-0 tw:flex-1 tw:cursor-pointer tw:after:absolute tw:after:inset-0 tw:after:content-['']">
                                    <span class="tw:text-control tw:font-label tw:leading-body tw:text-text">Saya bersedia berdiskusi lebih lanjut dengan CEO mengenai hal ini</span>
                                </label>
                            </div>

                            {{-- #wrapWhatsapp tetap pembungkus yang ditoggle JS (komponen membuat .form-group sendiri di dalamnya). --}}
                            <div id="wrapWhatsapp" style="display:none;">
                                <x-tw.input name="whatsapp_number" id="inpWhatsapp" label="No. WhatsApp" required placeholder="08xxxxxxxxxx"
                                            :error="$errors->first('whatsapp_number')"
                                            hint="Akan dihubungi oleh CEO lewat nomor ini." class="tw:max-sm:h-12" />
                            </div>
                        </div>

                        <x-slot:footer>
                            <div class="tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-3">
                                <span class="tw:max-sm:hidden">Kolom bertanda <span class="tw:text-danger" aria-hidden="true">*</span> wajib diisi.</span>
                                <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2 tw:max-sm:w-full tw:max-sm:flex-col-reverse tw:max-sm:[&>*]:w-full">
                                    <x-tw.button href="{{ route('feedback.index') }}" variant="secondary" class="tw:max-sm:min-h-12">Batal</x-tw.button>
                                    <x-tw.button type="submit" class="tw:max-sm:min-h-12"><i class="fas fa-paper-plane" aria-hidden="true"></i> Kirim Submission</x-tw.button>
                                </div>
                            </div>
                        </x-slot:footer>
                    </x-tw.card>
                </form>

                <div class="tw:min-w-0 tw:lg:sticky tw:lg:top-4">
                    @include('feedback._disclaimer')
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    function toggleCategory() {
        var isCriticism = $('#selCategory').val() === 'CRITICISM';
        $('#wrapMessage').toggle(!isCriticism);
        $('#wrapCriticism').toggle(isCriticism);
        $('#inpMessage').prop('required', !isCriticism);
        $('#inpCriticism').prop('required', isCriticism);
        $('#inpSuggestion').prop('required', isCriticism);
    }
    $('#selCategory').on('change', toggleCategory);
    toggleCategory();

    function toggleWhatsapp() {
        var willing = $('#chkWilling').is(':checked');
        $('#wrapWhatsapp').toggle(willing);
        $('#inpWhatsapp').prop('required', willing);
    }
    $('#chkWilling').on('change', toggleWhatsapp);
    toggleWhatsapp();
});
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="@aset('css/tesys-tw.css')">
@endpush

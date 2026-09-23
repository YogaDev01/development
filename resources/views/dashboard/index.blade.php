@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

{{--
    Dashboard TESYS — sementara dipangkas hanya berisi sapaan dan info versi.

    Blok "Perlu Tindakan" (TodoListController::actionQueues) dan "Aktivitas
    Terbaru" dicabut 23 Sep 2026: membangun ulang seluruh worklist setiap kali
    beranda dibuka ikut menyumbang lonjakan CPU di hosting. Antrean kerja
    tetap lengkap di halaman Todo List dan badge lonceng navbar.
--}}

@php
    $clCurrent  = config('changelog.current');
    $clReleases = config('changelog.releases', []);
    $clLatest   = $clReleases[0] ?? null;

    // Popup "What's New" otomatis tampil tiap dashboard dibuka SELAMA user
    // belum mencentang "jangan tampilkan lagi" untuk versi saat ini.
    $showChangelogPopup = $clLatest && Auth::user()->dismissed_changelog_version !== $clCurrent;

    // Butir teratas rilis terakhir — ringkasan, bukan seluruh catatan rilis.
    $clItems = collect($clLatest['sections'] ?? [])->flatMap(fn ($s) => $s['items'] ?? []);
    $clTampil = $clItems->take(3);
    $clSisa   = max(0, $clItems->count() - $clTampil->count());
@endphp

<div class="ts-dash">

    <div class="ts-dash-head">
        <h2 id="dashGreeting">Halo, {{ ucfirst(Auth::user()->name ?: Auth::user()->username) }}</h2>
        <p id="dashSub">{{ now()->translatedFormat('l, d F Y') }}</p>
    </div>

    <div style="max-width:720px;">
        {{-- What's New — ringkasan saja. Daftar lengkap ada di modal. --}}
        @if($clLatest)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">What&rsquo;s New</h3>
                <span class="ts-version-badge">v{{ $clCurrent }}</span>
            </div>
            <div class="card-body">
                <div class="ts-release-head">
                    <span class="ts-release-title">{{ $clLatest['title'] }}</span>
                    <span class="ts-release-date">{{ \Carbon\Carbon::parse($clLatest['date'])->translatedFormat('d F Y') }}</span>
                </div>

                @if(!empty($clLatest['summary']))
                    <p class="ts-release-summary">{{ \Illuminate\Support\Str::limit($clLatest['summary'], 180) }}</p>
                @endif

                @if($clTampil->isNotEmpty())
                    <ul class="ts-release-list">
                        @foreach($clTampil as $item)
                            <li>{{ \Illuminate\Support\Str::limit($item, 90) }}</li>
                        @endforeach
                    </ul>
                    @if($clSisa > 0)
                        <p class="ts-release-more">dan {{ $clSisa }} perubahan lain</p>
                    @endif
                @endif

                <div class="ts-release-foot">
                    <button type="button" class="btn btn-light btn-sm" data-toggle="modal" data-target="#changelogWhatsNewModal">
                        Lihat detail perubahan
                    </button>
                    @if(count($clReleases) > 1)
                        <button type="button" class="btn btn-light btn-sm" data-toggle="modal" data-target="#changelogModal">
                            Riwayat versi
                        </button>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@if($clLatest && count($clReleases) > 1)
<div class="modal fade" id="changelogModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Riwayat Versi TESYS</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                @foreach($clReleases as $rel)
                    <div class="ts-release">
                        <div class="ts-release-head">
                            <span class="ts-version-badge">v{{ $rel['version'] }}</span>
                            <span class="ts-release-title">{{ $rel['title'] }}</span>
                            <span class="ts-release-date">{{ \Carbon\Carbon::parse($rel['date'])->translatedFormat('d F Y') }}</span>
                        </div>
                        @if(!empty($rel['summary']))
                            <p class="ts-release-summary">{{ $rel['summary'] }}</p>
                        @endif
                        @foreach($rel['sections'] ?? [] as $section)
                            <div class="ts-release-section">
                                <span class="ts-release-tag ts-release-tag-{{ $section['type'] }}">{{ $section['label'] }}</span>
                                <ul class="ts-release-list">
                                    @foreach($section['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

@if($clLatest)
{{-- WHAT'S NEW — popup otomatis tiap dashboard dibuka (kecuali sudah
     dicentang "jangan tampilkan lagi"), sekaligus dipakai tombol
     "Lihat detail perubahan" di kartu ringkasan. --}}
<div class="modal fade" id="changelogWhatsNewModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static"
     data-autoshow="{{ $showChangelogPopup ? 1 : 0 }}">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Terbaru TESYS</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="ts-release">
                    <div class="ts-release-head">
                        <span class="ts-version-badge">v{{ $clLatest['version'] }}</span>
                        <span class="ts-release-title">{{ $clLatest['title'] }}</span>
                        <span class="ts-release-date">{{ \Carbon\Carbon::parse($clLatest['date'])->translatedFormat('d F Y') }}</span>
                    </div>
                    @if(!empty($clLatest['summary']))
                        <p class="ts-release-summary">{{ $clLatest['summary'] }}</p>
                    @endif
                    @foreach($clLatest['sections'] ?? [] as $section)
                        <div class="ts-release-section">
                            <span class="ts-release-tag ts-release-tag-{{ $section['type'] }}">{{ $section['label'] }}</span>
                            <ul class="ts-release-list">
                                @foreach($section['items'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer d-flex align-items-center">
                <div class="custom-control custom-checkbox mr-auto">
                    <input type="checkbox" class="custom-control-input" id="clDontShowAgain">
                    <label class="custom-control-label" for="clDontShowAgain" style="font-size:12.5px;">
                        Jangan tampilkan ini lagi pada update saat ini
                    </label>
                </div>
                <button type="button" class="btn btn-primary" id="btnClPopupOk">OK</button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
$(function () {
    // Nilainya dari atribut data-autoshow (diisi Blade), supaya script ini
    // tetap JavaScript murni.
    var $popup = $('#changelogWhatsNewModal');
    if ($popup.data('autoshow') === 1) {
        $popup.modal('show');
    }

    $('#btnClPopupOk').on('click', function () {
        if ($('#clDontShowAgain').is(':checked')) {
            $.post("{{ route('changelog.dismiss') }}");
        }
        $('#changelogWhatsNewModal').modal('hide');
    });
});
</script>
@endpush

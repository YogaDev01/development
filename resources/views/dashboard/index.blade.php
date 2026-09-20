@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

{{--
    Dashboard TESYS — menjawab satu pertanyaan: "apa yang harus saya kerjakan
    sekarang?"

    Urutannya sengaja: sapaan → angka yang bisa ditindaklanjuti → antrean kerja
    → aktivitas → catatan rilis. Catatan rilis ditaruh paling akhir dan hanya
    ringkasannya; daftar lengkapnya ada di modal, supaya kabar fitur tidak
    mengalahkan pekerjaan yang menunggu.

    Sumber data TIDAK berubah: $actionRequired berasal dari
    TodoListController::actionQueues() (sama persis dengan halaman Todo List &
    bell), $activity dari DashboardService::activityFeed().
--}}

@php
    $clCurrent  = config('changelog.current');
    $clReleases = config('changelog.releases', []);
    $clLatest   = $clReleases[0] ?? null;

    // Popup "What's New" otomatis tampil tiap dashboard dibuka SELAMA user
    // belum mencentang "jangan tampilkan lagi" untuk versi saat ini — begitu
    // ada rilis baru, dismissed_changelog_version lama tidak match lagi
    // dengan $clCurrent, jadi otomatis muncul lagi.
    $showChangelogPopup = $clLatest && Auth::user()->dismissed_changelog_version !== $clCurrent;

    $gagal   = $actionRequired['gagal'];
    $total   = $actionRequired['total'];
    $queues  = $actionRequired['queues'];

    // Nada KPI mengikuti keadaan, bukan selera: ada yang menunggu = merah.
    $toneTotal = $total > 0 ? 'danger' : 'success';

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

    {{-- ---------------------------------------------------------------
         1. Angka yang bisa ditindaklanjuti.
         Semuanya berasal dari antrean yang sama dengan daftar di bawah —
         tidak ada perhitungan baru, hanya ringkasannya.
         --------------------------------------------------------------- --}}
    <x-stat-cards :cards="[
        ['Perlu tindakan Anda', $gagal ? '—' : $total, $toneTotal, 'fas fa-bolt',
            $gagal ? 'Antrean gagal dimuat' : ($total > 0 ? 'Dokumen menunggu Anda' : 'Semua sudah ditangani')],
        ['Menunggu terlama', $gagal ? '—' : $actionRequired['maxAging'], $total > 0 ? 'warning' : 'secondary', 'fas fa-hourglass-half',
            $total > 0 ? 'Sejak dokumen masuk antrean' : 'Tidak ada yang tertahan'],
        ['Selesai hari ini', $gagal ? '—' : $actionRequired['completedToday'], 'success', 'fas fa-check',
            'Ditindaklanjuti oleh Anda'],
        ['Jenis antrean', $gagal ? '—' : count($queues), 'secondary', 'fas fa-layer-group',
            'Tahap kerja yang sedang aktif'],
    ]" />

    {{-- ---------------------------------------------------------------
         2. Perlu Tindakan — komponen utama halaman ini.
         --------------------------------------------------------------- --}}
    <div class="card" id="dashAction">
        <div class="card-header">
            <h3 class="card-title">Perlu Tindakan</h3>
            @if(!$gagal && $total > 0)
                <span class="ts-status ts-status-danger ts-status-sm">{{ $total }} menunggu</span>
            @endif
            @if(!$gagal && $total > 0)
                <div class="card-tools ts-queue-foot">
                    <span>Menunggu terlama <strong>{{ $actionRequired['maxAging'] }}</strong></span>
                    <span>Selesai hari ini <strong>{{ $actionRequired['completedToday'] }}</strong></span>
                </div>
            @endif
        </div>

        <div class="card-body">
            @if($gagal)
                <x-empty icon="fas fa-exclamation-triangle"
                         title="Antrean tugas gagal dimuat"
                         message="Muat ulang halaman ini, atau buka Todo List langsung untuk melihat daftar lengkapnya.">
                    @if(Route::has('todo-list.index'))
                        <x-slot:action>
                            <a href="{{ route('todo-list.index') }}" class="btn btn-light btn-sm">Buka Todo List</a>
                        </x-slot:action>
                    @endif
                </x-empty>
            @else
                <div class="ts-queue">
                    @forelse($queues as $q)
                        @php
                            $tone = ['attention' => 'attention', 'progress' => 'progress', 'danger' => 'danger'][$q['tone']] ?? 'progress';
                            // Tanpa akses menu Todo List barisnya tetap tampil tetapi
                            // tidak bisa diklik — lebih jujur daripada tautan yang 403.
                            $tautan = $q['url'] ? 'href="' . e($q['url']) . '"' : '';
                        @endphp
                        <a {!! $tautan !!} class="ts-queue-row">
                            <span class="ts-queue-ic ts-tone-{{ $tone }}"><i class="{{ $q['icon'] }}"></i></span>
                            <span class="ts-queue-main">
                                <span class="ts-queue-label">{{ $q['label'] }}</span>
                                <span class="ts-queue-meta">Menunggu {{ $q['aging'] }}</span>
                            </span>
                            <span class="ts-queue-count">{{ $q['count'] }}</span>
                            <span class="ts-queue-cta">Tinjau <i class="fas fa-arrow-right"></i></span>
                        </a>
                    @empty
                        <x-empty icon="fas fa-check-circle"
                                 title="Tidak ada yang menunggu tindakan Anda"
                                 message="Semua dokumen di tahap Anda sudah ditindaklanjuti." />
                    @endforelse
                </div>
            @endif
        </div>
    </div>

    <div class="ts-dash-grid">

        {{-- ------------------------------------------------------------
             3. Aktivitas terbaru.
             ------------------------------------------------------------ --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">Aktivitas Terbaru</h3></div>
            <div class="card-body">
                @forelse($activity as $item)
                    @php
                        $toneFeed = $item['category'] === 'mention' ? 'purple'
                            : ($item['category'] === 'milestone' ? 'attention' : 'progress');
                    @endphp
                    <a href="{{ $item['url'] ?? '#' }}" class="ts-feed-row">
                        <span class="ts-feed-dot ts-tone-{{ $toneFeed }}" style="background:currentColor;"></span>
                        <span class="ts-feed-text">{{ $item['message'] }}</span>
                        <span class="ts-feed-time">{{ $item['created_at'] }}</span>
                    </a>
                @empty
                    <x-empty compact icon="fas fa-inbox"
                             title="Belum ada aktivitas"
                             message="Perubahan dokumen yang menyangkut Anda akan muncul di sini." />
                @endforelse
            </div>
        </div>

        {{-- ------------------------------------------------------------
             4. What's New — ringkasan saja. Daftar lengkap ada di modal.
             ------------------------------------------------------------ --}}
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
<div class="modal fade" id="changelogWhatsNewModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
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
    @if($showChangelogPopup)
    $('#changelogWhatsNewModal').modal('show');
    @endif

    $('#btnClPopupOk').on('click', function () {
        if ($('#clDontShowAgain').is(':checked')) {
            $.post("{{ route('changelog.dismiss') }}");
        }
        $('#changelogWhatsNewModal').modal('hide');
    });
});
</script>
@endpush

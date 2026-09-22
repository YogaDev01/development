@extends('layouts.app')

@section('title', $app['label'])

@section('content')
{{--
    Halaman aplikasi yang modulnya belum dibangun.

    Tujuannya satu: halaman ini harus terbaca "memang belum ada", bukan
    "rusak" atau "kosong". Karena itu ia menyebutkan apa yang sedang
    disiapkan (config/apps.php -> 'modules'), menegaskan mana yang sudah
    berjalan, dan memberi satu jalan keluar yang jelas.
--}}
<div class="ts-soon">

    <span class="ts-soon-mark" aria-hidden="true"><i class="{{ $app['icon'] }}"></i></span>

    <h2 class="ts-soon-title">Modul {{ $app['label'] }} sedang dalam pengembangan</h2>

    <p class="ts-soon-desc">{{ $app['desc'] ?? 'Modul ini sedang disiapkan.' }}</p>

    @if(!empty($app['modules']))
        <div class="ts-soon-plan">
            <p class="ts-soon-plan-label">Yang sedang disiapkan</p>
            <div class="ts-soon-chips">
                @foreach($app['modules'] as $modul)
                    <span class="ts-soon-chip">{{ $modul }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <p class="ts-soon-note">
        Data acuan yang sudah tersedia untuk {{ $app['label'] }} bisa dibuka lewat
        <strong>Master Data</strong> di menu sebelah kiri. Bagian di atas menyusul
        begitu modulnya siap.
    </p>

    <a href="{{ url('/') }}" class="ts-action-primary is-neutral">
        <i class="fas fa-arrow-left"></i><span class="ts-al">Kembali ke Finance</span>
    </a>

</div>
@endsection

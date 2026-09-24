{{--
    Tesys UI — layout baru (modern minimalist SaaS ERP).

    Dibuat KOMPATIBEL dengan blade lama: semua asset AdminLTE/Bootstrap/DataTables/
    TomSelect/flatpickr tetap dimuat, jadi DataTables, modal Bootstrap, select2-like,
    dan seluruh @push('scripts') di halaman existing tetap berfungsi.
    CSS baru dimuat PALING AKHIR sehingga tampilannya yang menang (re-skin).

    Cara pakai — cukup ganti satu baris di blade mana pun:
        @extends('layouts.app')       -->  @extends('ui.layouts.app')

    Opsional, agar judul muncul di topbar baru:
        @section('title', 'Budgeting')
        @section('breadcrumb', 'Finance / Budgeting')
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Tesys') — Tesys</title>

    {{-- ===== Asset lama (tetap dipertahankan supaya markup & JS existing jalan) ===== --}}
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
    <link rel="stylesheet" href="{{ asset('plugins/flatpickr/dist/flatpickr.min.css') }}">

    {{-- ===== Design system baru (dimuat terakhir → meng-override tampilan lama) ===== --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui.css') }}?v={{ filemtime(public_path('css/tesys-ui.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/tesys-ui-compat.css') }}?v={{ filemtime(public_path('css/tesys-ui-compat.css')) }}">

    @stack('styles')
</head>
<body class="tesys-ui">

<div class="shell">
    @include('ui.partials.sidebar')

    <div class="main">
        @include('ui.partials.topbar')

        <div class="content">
            @if (session('success'))
                @include('ui.partials.alert', ['type' => 'success', 'message' => session('success')])
            @endif
            @if (session('error'))
                @include('ui.partials.alert', ['type' => 'error', 'message' => session('error')])
            @endif

            @yield('content')
        </div>
    </div>
</div>

@include('ui.partials.logout-modal')

<div class="ts-toast-stack" id="toastStack"></div>

{{-- ===== Script lama (urutan dipertahankan persis seperti layouts/app.blade.php) ===== --}}
<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
<script src="{{ asset('dist/js/adminlte.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('plugins/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('plugins/flatpickr/dist/l10n/id.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
<script src="{{ asset('js/tesys-column-filter.js') }}?v={{ filemtime(public_path('js/tesys-column-filter.js')) }}"></script>
<script src="{{ asset('js/tesys-searchable-select.js') }}?v={{ filemtime(public_path('js/tesys-searchable-select.js')) }}"></script>

{{-- URL untuk bell notifikasi (dipakai tesys-ui.js) --}}
<script>
    @if (Route::has('notifications.fetch'))
        window.TESYS_NOTIF_URL = "{{ route('notifications.fetch') }}";
    @endif
    @if (Route::has('notifications.read-all'))
        window.TESYS_NOTIF_READ_ALL_URL = "{{ route('notifications.read-all') }}";
    @endif
</script>

<script src="{{ asset('js/tesys-ui.js') }}?v={{ filemtime(public_path('js/tesys-ui.js')) }}"></script>

@stack('scripts')
</body>
</html>

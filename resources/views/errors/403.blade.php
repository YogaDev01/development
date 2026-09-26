{{--
    Halaman 403 - akses ditolak.

    Bingkainya dipilih di baris @extends: pengguna yang LOGIN mendapat layout
    aplikasi lengkap - sidebar, navbar, tombol kembali - sehingga penolakan
    punya jalan keluar. Itu pokok masalah yang diperbaiki: sebelumnya setiap
    abort(403, '...') di aplikasi ini tampil di halaman bawaan Laravel tanpa
    navigasi apa pun, jadi satu-satunya jalan keluar adalah tombol Back
    peramban. CheckRole bahkan sudah menambal gejalanya untuk satu kasus
    dengan redirect()->back().

    Pengunjung yang BELUM login mendapat layouts.error yang mandiri, karena
    layouts.app memanggil Auth::user()->effectiveRole() tanpa penjaga null dan
    akan ikut melempar galat.
--}}
@extends(Auth::check() ? 'layouts.app' : 'layouts.error')

@php
    /*
     | Pesan dihitung SEKALI dan dipakai oleh KEDUA bingkai.
     |
     | Sebelum ini @section('pesan') berisi teks statis, sementara hanya
     | blok @include di bawah yang membaca $exception. Akibatnya pesan
     | dari abort(403, 'Anda tidak memiliki akses ke ...') sampai ke
     | pengguna yang login, tapi HILANG untuk yang belum login - padahal
     | pesan itu satu-satunya keterangan berguna di halaman ini.
     */
    $pesanGalat = (isset($exception) && trim((string) $exception->getMessage()) !== '')
            ? $exception->getMessage()
            : 'Anda tidak memiliki hak untuk membuka halaman ini. Hubungi administrator bila seharusnya berhak.';
@endphp

@section('title', 'Akses ditolak')
@section('kode', '403')
@section('judul', 'Akses ditolak')
@section('pesan', $pesanGalat)

@section('aksi')
    @if(Auth::check())
        <a href="{{ url('/') }}" class="tombol tombol-utama">Ke Dashboard</a>
    @else
        <a href="{{ url('/login') }}" class="tombol tombol-utama">Masuk</a>
    @endif
@endsection

@section('content')
    @include('errors._isi', [
        'kode'  => '403',
        'judul' => 'Akses ditolak',
        'pesan' => $pesanGalat,
    ])
@endsection

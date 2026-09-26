{{--
    Halaman 500 - galat internal.

    SENGAJA memakai layouts.error, BUKAN layouts.app - bahkan untuk pengguna
    yang sudah login.

    Alasannya: apa pun yang menyebabkan 500 bisa jadi justru yang dipakai
    layout utama. layouts.app memanggil Auth::user()->effectiveRole(), yang
    menyentuh basis data lewat relasi role dan menu; kalau basis datanya yang
    bermasalah, merender halaman galat di dalam layout itu akan melempar galat
    KEDUA, dan yang tampil ke pengguna justru halaman bawaan Laravel - persis
    yang ingin dihindari.

    Halaman ini tidak memuat berkas CSS apa pun dan tidak menyentuh basis data.
    Satu-satunya tugasnya: selalu berhasil tampil.

    Pesan exception TIDAK ditampilkan di sini walaupun tersedia. Pada
    APP_DEBUG=false Laravel sudah menyembunyikannya, dan menampilkannya berarti
    detail internal (nama tabel, path berkas) bocor ke pengguna.
--}}
@extends('layouts.error')

@section('kode', '500')
@section('judul', 'Terjadi kesalahan pada sistem')
@section('pesan', 'Permintaan Anda tidak dapat diselesaikan. Kesalahan ini sudah dicatat. Coba lagi beberapa saat, dan laporkan ke administrator bila terus terjadi.')

@section('aksi')
    <a href="{{ url('/') }}" class="tombol tombol-utama">Ke Dashboard</a>
    <button type="button" class="tombol" onclick="location.reload()">Coba lagi</button>
@endsection

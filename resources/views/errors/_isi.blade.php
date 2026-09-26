{{--
    Isi halaman galat, dipakai bersama oleh 403/404/419/429/500.

    Dirender DUA kali dengan bingkai berbeda:
      - di dalam layouts.app (pengguna yang login, lengkap dengan sidebar &
        navbar sehingga penolakan tidak menjadi jalan buntu),
      - di dalam layouts.error (pengunjung belum login, atau halaman 500 yang
        tidak boleh bergantung pada apa pun).

    Variabel yang diharapkan: $kode, $judul, $pesan. $aksi opsional (HTML).
--}}
@php
    $tampilLogin = ! Auth::check();
@endphp

<div style="max-width:520px;margin:48px auto;text-align:center;padding:0 16px;">
    <p style="font-size:46px;font-weight:800;letter-spacing:-1px;line-height:1;margin:0 0 10px;color:var(--accent,#2563eb);">
        {{ $kode }}
    </p>

    <h1 style="font-size:17px;font-weight:700;margin:0 0 8px;color:var(--text,#16181d);">
        {{ $judul }}
    </h1>

    <p style="font-size:13.5px;line-height:1.6;color:var(--muted,#6b7280);margin:0 0 22px;">
        {{ $pesan }}
    </p>

    <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
        @if($tampilLogin)
            <a href="{{ url('/login') }}" class="btn btn-primary btn-sm">Masuk</a>
        @else
            <a href="{{ url('/') }}" class="btn btn-primary btn-sm">Ke Dashboard</a>
            {{-- history.back() disediakan karena sebagian penolakan terjadi
                 setelah beberapa langkah navigasi, dan kembali satu langkah
                 lebih berguna daripada melompat ke dashboard. --}}
            <button type="button" class="btn btn-secondary btn-sm" onclick="history.back()">Kembali</button>
        @endif
    </div>
</div>

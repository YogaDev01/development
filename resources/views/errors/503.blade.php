{{--
    Halaman 503 — dipakai Laravel saat aplikasi dalam mode pemeliharaan
    (php artisan down) dan untuk respons 503 lainnya.

    Isinya TIDAK ditulis ulang di sini. Ia dibaca langsung dari
    public/maintenance/index.html supaya hanya ada SATU berkas yang perlu
    disunting: berkas statis itu tetap bisa dibuka sendiri (/maintenance/)
    dan tetap tampil walaupun PHP mati sama sekali, sementara view ini
    menyalurkan isi yang sama ke jalur Laravel.

    Catatan: "php artisan down --render=errors::503" MENGGAMBAR view ini
    sekali lalu menyimpan hasilnya di storage/framework/maintenance.php,
    jadi halamannya tetap terlayani meski kerangka aplikasi tidak bisa
    dinyalakan. Karena itu menyunting berkas statisnya setelah sistem
    diturunkan tidak akan terlihat — turunkan ulang (up lalu down) kalau
    teksnya perlu diubah di tengah pemeliharaan.
--}}
@php
    $berkas = public_path('maintenance/index.html');
@endphp

@if (is_file($berkas))
    {!! file_get_contents($berkas) !!}
@else
    {{-- Jaring pengaman: kalau berkas statisnya hilang, jangan sampai yang
         tampil justru jejak galat. Satu baris jujur sudah cukup. --}}
    <!DOCTYPE html>
    <html lang="id"><head><meta charset="utf-8">
    <title>Sedang Pemeliharaan — TESYS</title></head>
    <body style="font-family:system-ui,sans-serif;background:#140a0d;color:#fff;
                 display:flex;align-items:center;justify-content:center;
                 height:100vh;margin:0;text-align:center">
        <div>
            <h1 style="font-size:20px;font-weight:600;margin:0 0 8px">
                Sistem sedang dalam pemeliharaan
            </h1>
            <p style="margin:0;font-size:13px;color:rgba(255,255,255,.62)">
                Silakan coba beberapa saat lagi.
            </p>
        </div>
    </body></html>
@endif

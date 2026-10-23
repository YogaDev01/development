{{--
    Kerangka halaman galat yang MANDIRI.

    Kenapa tidak selalu memakai layouts.app: layout utama memanggil
    Auth::user()->effectiveRole() tanpa penjaga null, jadi ia melempar galat
    untuk pengunjung yang belum login. Kalau halaman 404 sendiri yang error,
    Laravel jatuh ke halaman bawaannya - tepat keadaan yang ingin dihindari.

    Dipakai:
      - selalu untuk 500 (apa pun yang rusak bisa jadi justru layout/basis
        datanya sendiri, jadi halaman galat tidak boleh bergantung pada apa pun),
      - untuk 403/404/419/429 saat pengunjung belum login.

    Yang login mendapat layouts.app lengkap dengan sidebar & navbar, supaya
    penolakan tidak menjadi jalan buntu - itulah pokok masalahnya.

    Gaya di sini sengaja INLINE dan tidak memuat berkas CSS apa pun: satu-satunya
    tugas halaman ini adalah selalu berhasil tampil.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('kode', 'Galat') — TESYS</title>
    <link rel="icon" href="{{ asset('dist/img/favicon16.ico') }}" type="image/x-icon">
    @include('layouts.partials.pwa-head')
    <style>
        :root {
            --bg: #f6f7f9;
            --surface: #ffffff;
            --text: #16181d;
            --muted: #6b7280;
            --border: #e5e7eb;
            --accent: #2563eb;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1115;
                --surface: #171a20;
                --text: #e9eaee;
                --muted: #9ca3af;
                --border: #262a33;
                --accent: #60a5fa;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        .kotak {
            width: 100%;
            max-width: 480px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 32px 28px;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 24px rgba(0,0,0,.06);
        }
        .kode {
            font-size: 46px;
            font-weight: 800;
            letter-spacing: -1px;
            line-height: 1;
            margin: 0 0 10px;
            color: var(--accent);
        }
        .judul { font-size: 17px; font-weight: 700; margin: 0 0 8px; }
        .pesan { font-size: 13.5px; line-height: 1.6; color: var(--muted); margin: 0 0 22px; }
        .aksi { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .tombol {
            display: inline-block;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text);
            background: transparent;
            cursor: pointer;
        }
        .tombol-utama {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="kotak">
        <p class="kode">@yield('kode')</p>
        <h1 class="judul">@yield('judul')</h1>
        <p class="pesan">@yield('pesan')</p>
        <div class="aksi">@yield('aksi')</div>
    </div>
</body>
</html>

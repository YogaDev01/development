{{-- Metadata PWA (Add to Home Screen). Manifest & ikon statis di public/;
     service worker (public/sw.js) hanya melayani halaman "tidak ada koneksi"
     dan TIDAK menyimpan halaman/data aplikasi. --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#180d11">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="TESYS">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<link rel="apple-touch-icon" href="{{ asset('dist/img/pwa/apple-touch-icon-180.png') }}">

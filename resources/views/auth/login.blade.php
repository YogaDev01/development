{{--
    Halaman Login — TESYS (Terex Enterprise System).

    Susunan: satu kartu, dua panel.
      - Panel gelap  : ekosistem heksagon (dekorasi + pesan brand).
      - Panel putih  : identitas perusahaan + form masuk.

    Field, action, dan alur autentikasi TIDAK diubah:
    POST /login dengan username, password, remember.
    Versi paling lama ada di resources/views/auth/login-legacy.blade.php.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk — TESYS</title>

    <link rel="icon" href="{{ asset('dist/img/favicon16.ico') }}" type="image/x-icon">
    @include('layouts.partials.pwa-head')

    {{-- Tema dipasang SEBELUM satu piksel pun digambar.

         Kalau pemasangannya ditunda sampai skrip di kaki halaman jalan,
         halaman sempat tampil dengan tema bawaan lalu berkedip ke tema
         pilihan — dan kedipan itu paling terlihat justru di layar gelap.
         Skrip ini sengaja kecil, sinkron, dan tanpa ketergantungan. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('tesys-theme');
                document.documentElement.setAttribute(
                    'data-theme', t === 'dark' ? 'dark' : 'light');
            } catch (e) {
                /* Mode penyamaran atau penyimpanan diblokir: pakai bawaan. */
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="@aset('css/tesys-ui-tokens.css')">

    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }

        /*
        |--------------------------------------------------------------------
        | DUA TEMA
        |--------------------------------------------------------------------
        | Seluruh warna halaman ini dipusatkan jadi token supaya pergantian
        | tema cukup menukar satu blok nilai, bukan memburu warna yang
        | tersebar di puluhan aturan.
        |
        | Penamaannya mengikuti penamaan Anda: tema burgundy yang sekarang
        | disebut "light", dan "dark" adalah tema baru yang lebih dalam.
        | Keduanya sama-sama gelap — bedanya SUHU dan KEDALAMAN, bukan
        | terang-gelap:
        |
        |   light  = burgundy hangat, ada warna di dalam hitamnya
        |   dark   = arang netral yang hampir tanpa arah warna, satu
        |            tingkat lebih dalam, dengan burgundy disisakan hanya
        |            untuk tombol dan cahaya latar yang jauh lebih redup
        |
        | Yang TIDAK ikut berubah: --accent. Merah merek harus sama di
        | kedua tema, kalau tidak, identitasnya jadi bergantung pada
        | preferensi tampilan.
        */
        :root,
        html[data-theme="light"] {
            --lg-bg-1:          #1d1114;
            --lg-bg-2:          #140a0d;
            --lg-bg-3:          #0b0608;
            --lg-glow-a:        rgba(146, 35, 43, .13);
            --lg-glow-b:        rgba(168, 139, 90, .06);
            --lg-glow-c:        rgba(146, 35, 43, .07);
            --lg-curve:         #ffffff;
            --lg-card:          #1e1215;
            --lg-card-border:   rgba(255, 255, 255, .08);
            --lg-input:         rgba(255, 255, 255, .055);
            --lg-input-hover:   rgba(255, 255, 255, .22);
            --lg-input-focus:   rgba(255, 255, 255, .085);
            --lg-input-border:  rgba(255, 255, 255, .14);
            --lg-autofill:      #241519;
            --lg-deco-glow:     rgba(146, 35, 43, .17);
        }

        html[data-theme="dark"] {
            --lg-bg-1:          #101013;
            --lg-bg-2:          #0a0a0c;
            --lg-bg-3:          #050506;
            --lg-glow-a:        rgba(146, 35, 43, .085);
            --lg-glow-b:        rgba(168, 139, 90, .035);
            --lg-glow-c:        rgba(146, 35, 43, .045);
            --lg-curve:         #ffffff;
            --lg-card:          #141417;
            --lg-card-border:   rgba(255, 255, 255, .07);
            --lg-input:         rgba(255, 255, 255, .045);
            --lg-input-hover:   rgba(255, 255, 255, .19);
            --lg-input-focus:   rgba(255, 255, 255, .072);
            --lg-input-border:  rgba(255, 255, 255, .12);
            --lg-autofill:      #17171b;
            --lg-deco-glow:     rgba(146, 35, 43, .11);
        }

        body {
            /*
              Warna teks sekunder khusus halaman ini. Token global --text-faint
              (#8b8b96) hanya ~3,5:1 di atas putih — cukup untuk label dekoratif,
              tetapi tidak untuk subjudul/placeholder/footer. Nilai di bawah ini
              semuanya >= 4,5:1 sesuai WCAG AA, tanpa mengubah token global yang
              dipakai seluruh aplikasi.
            */
            /* Nilai lama (#5B5B66 dkk) dihitung untuk teks GELAP di atas
               kertas putih. Formulirnya kini berdiri langsung di atas latar
               nyaris hitam, jadi arah kontrasnya berbalik: yang dibutuhkan
               putih yang diredam, bukan abu yang digelapkan.

               Kontras di atas #140A0D — .62 = 8,0:1, .52 = 5,9:1,
               .46 = 4,8:1. Ketiganya lolos AA, dan yang paling redup pun
               hanya dipakai untuk placeholder. */
            --ink-soft: rgba(255, 255, 255, .62);
            --ink-mute: rgba(255, 255, 255, .52);
            --ink-hint: rgba(255, 255, 255, .46);

            font-family: "Inter", -apple-system, "Segoe UI", sans-serif;
            font-size: 13.5px;
            color: var(--text);
            background: var(--chrome);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 36px 20px;
            position: relative;
            overflow-x: hidden;
        }

        /*
        |--------------------------------------------------------------------
        | LATAR
        |--------------------------------------------------------------------
        */
        .bg-wash {
            position: fixed;
            inset: 0;
            z-index: 0;
            /* Nadanya mengikuti sidebar aplikasi (#180D11), bukan merah
               sendiri. Halaman login adalah layar pertama yang dilihat
               orang; kalau warnanya tidak ada di dalam aplikasi, ia jadi
               janji yang tidak ditepati satu klik kemudian.

               Cahaya merahnya juga diturunkan: nilai lama memakai #D71920
               (merah terang dari palet sebelumnya) pada .16 — di layar ia
               terbaca sebagai lampu, bukan sebagai latar. Diganti burgundy
               merek pada .10, cukup untuk memberi arah cahaya dari sudut
               kiri atas tanpa menyala. */
            background: linear-gradient(155deg, var(--lg-bg-1) 0%, var(--lg-bg-2) 54%, var(--lg-bg-3) 100%);
        }

        /*
          Lapisan cahaya yang bergerak. Sebelumnya kedua cahaya sudut ini
          ditanam langsung di .bg-wash sebagai gradien diam; dipisah ke
          lapisannya sendiri supaya bisa digerakkan tanpa menggambar ulang
          latar dasarnya.

          Geraknya sengaja nyaris tidak kelihatan kalau diperhatikan
          langsung: 48 detik satu putaran, geser belasan piksel, skala
          berubah 4%. Yang dicari bukan animasi yang terlihat, melainkan
          layar yang tidak terasa mati — perbedaannya baru terasa kalau
          dimatikan. Animasi yang sampai menarik mata di halaman masuk
          justru akan bersaing dengan kolom isian.

          Hanya transform & opacity yang dianimasikan, jadi peramban
          mengerjakannya di GPU tanpa menghitung ulang tata letak.
        */
        .bg-glow {
            position: fixed;
            inset: -12%;
            z-index: 0;
            pointer-events: none;
            background:
                radial-gradient(880px 560px at 12% 8%,  var(--lg-glow-a), transparent 60%),
                radial-gradient(760px 520px at 92% 94%, var(--lg-glow-b), transparent 62%);
            animation: glow-drift 26s ease-in-out infinite;
            will-change: transform;
        }
        /* Jangkauannya dinaikkan tiga kali lipat (22px -> 70px) dan
           putarannya dipercepat dari 48 ke 26 detik. Skalanya juga naik
           4% -> 9%, karena cahaya sebesar layar yang hanya berubah 4%
           tidak pernah benar-benar terlihat bergerak — yang terbaca cuma
           perpindahannya. Tetap ease-in-out: percepatan dan perlambatan di
           tiap ujung yang membuatnya terbaca sebagai aliran, bukan sebagai
           benda yang digeser. */
        @keyframes glow-drift {
            0%   { transform: translate3d(0, 0, 0) scale(1); }
            50%  { transform: translate3d(-70px, 44px, 0) scale(1.09); }
            100% { transform: translate3d(0, 0, 0) scale(1); }
        }

        /* Lapisan kedua, arah berlawanan dan periode berbeda (62 detik)
           supaya keduanya tidak pernah jatuh pada pola yang sama — itu yang
           membuat geraknya terbaca sebagai napas, bukan sebagai putaran. */
        .bg-glow::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(620px 620px at 72% 22%, var(--lg-glow-c), transparent 64%);
            animation: glow-drift-b 35s ease-in-out infinite;
            will-change: transform, opacity;
        }
        @keyframes glow-drift-b {
            0%   { transform: translate3d(0, 0, 0) scale(1);    opacity: .6; }
            50%  { transform: translate3d(84px, -58px, 0) scale(1.12); opacity: 1; }
            100% { transform: translate3d(0, 0, 0) scale(1);    opacity: .6; }
        }

        /* Cahaya pengikut kursor dilepas.

           Ia bekerja persis seperti yang dirancang — tertinggal di belakang
           kursor, padam saat diam — tapi di halaman masuk itu justru
           masalahnya: satu-satunya hal yang bergerak mengikuti orang, tepat
           saat orang sedang mencari kolom isian. Latar yang bernapas pelan
           sudah memberi kesan "hidup" tanpa pernah menuntut perhatian.

           Lapisan .bg-glow di atas tetap ada dan tetap bergerak; yang hilang
           hanya yang bereaksi terhadap tetikus. */

        .bg-curves {
            position: fixed;
            inset: 0;
            z-index: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        /* Tinta gelap di atas kertas -> cahaya di atas gelap. Opasitasnya
           diturunkan hampir separuh: garis terang di latar gelap terbaca
           jauh lebih kuat daripada garis gelap di latar terang, dan latar
           yang ramai akan berebut perhatian dengan kartunya. */
        .bg-curves path { fill: none; stroke: #ffffff; stroke-width: 1.1; vector-effect: non-scaling-stroke; }
        /* Opasitas dinaikkan sekitar setengah dan periodenya dipendekkan.
           Garis-garis ini yang paling terbaca sebagai "gerak" karena ia
           bentuk, bukan kabut — jadi ia yang paling banyak menentukan apakah
           latar terasa hidup atau diam. */
        .bg-curves .c1 { opacity: .075; animation: drift-a 17s ease-in-out infinite; }
        .bg-curves .c2 { opacity: .058; animation: drift-b 21s ease-in-out infinite; }
        .bg-curves .c3 { opacity: .044; animation: drift-a 25s ease-in-out infinite; }
        .bg-curves .c4 { opacity: .21; stroke: var(--accent); animation: drift-a 19s ease-in-out infinite reverse; }
        .bg-curves .c5 { opacity: .13; stroke: var(--gold); animation: drift-b 23s ease-in-out infinite; }

        @keyframes drift-a {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50%      { transform: translate3d(-54px, -30px, 0); }
        }
        @keyframes drift-b {
            0%, 100% { transform: translate3d(0, 0, 0); }
            50%      { transform: translate3d(48px, 34px, 0); }
        }

        /*
        |--------------------------------------------------------------------
        | KARTU
        |--------------------------------------------------------------------
        */
        /*
          Tidak ada kartu lagi.

          Sebelumnya seluruh isi halaman duduk di dalam satu kotak putih
          berbayang yang mengambang di tengah. Kotak itu punya satu masalah
          yang tidak bisa diperbaiki dengan menyetel warna atau bayangannya:
          ia MEMBINGKAI. Layar masuk jadi terbaca sebagai dialog — sesuatu
          yang muncul di atas aplikasi — padahal ia justru pintu masuknya.

          Sekarang kelas ini tinggal jadi kerangka tata letak: dua kolom,
          tanpa latar, tanpa tepi, tanpa bayangan. Latar halaman yang
          bernapas itulah yang jadi permukaannya, dan isi berdiri langsung
          di atasnya.

          max-width tetap ada bukan untuk membuat kotak, melainkan karena
          baris teks yang melar sampai 2500px tidak bisa dibaca. Latarnya
          sendiri tetap memenuhi layar.
        */
        /*
          Kerangka dua kolom. Yang berubah di sini komposisinya.

          Sebelumnya lebarnya dibatasi 1180px dan jaraknya 64px, jadi di layar
          lebar keduanya berkumpul di sekitar sumbu tengah dan menyisakan dua
          bidang kosong yang lebar di kiri dan kanan. Terbaca seperti kartu
          lama yang dihapus latarnya, bukan seperti halaman.

          Sekarang kerangkanya dibiarkan melebar sampai 1400px dengan jarak
          yang ikut tumbuh mengikuti lebar layar (clamp). Hiasannya bergerak
          ke kiri, formulirnya ke kanan, dan keduanya punya ruang napas
          masing-masing — bukan dua blok yang berdempetan di tengah.
        */
        .auth-card {
            position: relative;
            z-index: 1;
            width: 100%;
            /* 1320 -> 1180. Karena kerangkanya dipusatkan dan kedua
               bloknya didorong ke tepi masing-masing lewat space-between,
               mengecilkan lebar ini menarik KEDUANYA ke tengah dengan jarak
               yang sama — kartu bergeser ke dalam tanpa merusak keseimbangan
               kiri-kanan yang baru dicapai. */
            max-width: 1180px;
            display: grid;
            /* Kolom kiri "auto", bukan "1fr".

               Dengan 1fr, kolom kiri merebut semua sisa ruang lalu
               MEMUSATKAN anyaman heksagon di dalamnya. Akibatnya anyaman itu
               berhenti jauh dari tepi kiri sementara kartu tetap menempel ke
               tepi kanan — dan seluruh komposisi terbaca miring ke kanan
               meskipun kerangkanya sendiri sudah di tengah.

               Dengan auto + space-between, tiap blok menyusut seukuran isinya
               lalu didorong ke tepinya masing-masing. Karena kerangkanya
               dipusatkan, jarak ke tepi kiri dan tepi kanan jadi sama. */
            grid-template-columns: auto minmax(360px, 436px);
            justify-content: space-between;
            align-items: center;
            gap: clamp(40px, 5vw, 96px);
            background: none;
            border: none;
            border-radius: 0;
            box-shadow: none;
            overflow: visible;
            animation: card-in .5s cubic-bezier(.16, 1, .3, 1) backwards;
        }

        @keyframes card-in {
            from { opacity: 0; transform: translateY(14px) scale(.99); }
            to   { opacity: 1; transform: none; }
        }

        /*
        |--------------------------------------------------------------------
        | PANEL GELAP — ekosistem heksagon
        |--------------------------------------------------------------------
        */
        /* Lambang di kolom kiri. Lebarnya yang dikunci, bukan tingginya:
           wordmark ini sangat memanjang (rasio ~5,5:1), jadi mengunci tinggi
           akan membuatnya melar melewati kolomnya di layar sempit. */
        .deco-logo {
            position: relative;
            z-index: 1;
            width: min(420px, 100%);
            height: auto;
            display: block;
        }

        /* Keterangan di bawahnya. Huruf kapital renggang dan nada redup:
           ia melengkapi lambang, bukan bersaing dengannya. */
        .deco-cap {
            position: relative;
            z-index: 1;
            margin: 0;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: .28em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .52);
            /* Dipusatkan TERHADAP LAMBANG, bukan terhadap kolomnya.

               Kalau perataannya diserahkan ke kolom (align-items: center di
               .auth-deco), lambangnya ikut bergeser ke tengah track yang
               melar dan keseimbangan kiri-kanan halaman rusak lagi. Dengan
               memberi keterangan ini LEBAR YANG SAMA dengan lambang lalu
               memusatkan teksnya di dalam lebar itu, hasilnya sama persis
               tanpa satu pun elemen berpindah tempat. */
            width: min(420px, 100%);
            text-align: center;
        }

        /* Isi kolom kiri dirata KIRI, bukan dipusatkan.

           Track kolom ini melar menyerap sisa ruang kerangka (643px untuk
           lambang selebar 416px). Selama isinya dipusatkan, lambang berhenti
           113px lebih jauh dari tepi kiri daripada kartu dari tepi kanan —
           komposisinya terbaca miring meskipun kerangkanya sudah di tengah.

           Menyetel track ke max-content sempat dicoba supaya ia menyusut
           seukuran isi; itu JUSTRU meledak. Pada track max-content lebarnya
           tak tentu, sehingga min(420px, 100%) di lambang jatuh ke lebar asli
           berkasnya — 2254px. Merapatkan isinya ke kiri menyelesaikan hal
           yang sama tanpa menyentuh cara track diukur. */
        .auth-deco {
            /* Tanpa gradasi sendiri. Dulu panel ini butuh arah cahayanya
               sendiri karena ia satu bidang di dalam kartu; sekarang ia
               berdiri di atas latar halaman, dan memberinya warna sendiri
               justru akan menghidupkan kembali kotak yang baru dibubarkan. */
            background: none;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 18px;
            isolation: isolate;
        }
        /* Ubin heksagon dilepas. Ia pola dengan TEPI LURUS; selama ada
           kartu, tepi itu tersembunyi oleh batas kartunya. Tanpa kartu,
           yang tersisa adalah persegi panjang bertekstur di tengah layar —
           persis bentuk yang baru saja kita hilangkan. Tekstur latar sudah
           dikerjakan oleh garis lengkung .bg-curves yang memenuhi layar. */
        .deco-grid { display: none; }
        .deco-glow {
            position: absolute; z-index: -1;
            width: 430px; height: 430px; border-radius: 50%;
            /* .40 membuat lambangnya seperti menyala sendiri. Dengan .24
               ia tetap punya kedalaman, tapi yang menarik mata kembali
               bentuk heksagonnya — bukan sinarnya. */
            background: radial-gradient(circle, var(--lg-deco-glow), transparent 68%);
            filter: blur(10px);
            animation: glow-breathe 9s ease-in-out infinite;
        }
        @keyframes glow-breathe {
            0%, 100% { opacity: .58; transform: scale(1); }
            50%      { opacity: .76; transform: scale(1.04); }
        }

        /*
        |--------------------------------------------------------------------
        | SARANG HEKSAGON — enam fungsi bisnis mengelilingi TESYS
        |--------------------------------------------------------------------
        */
        /* Gaya anyaman heksagon (.hive/.hex/.hive-cap beserta enam animasi
           melayangnya) dihapus bersama markup-nya — lihat catatan di
           <aside class="auth-deco">. Kolom kiri sekarang hanya berisi satu
           lambang dan satu baris keterangan. */


        /*
        |--------------------------------------------------------------------
        | PANEL PUTIH — identitas + form
        |--------------------------------------------------------------------
        */
        /*
          Kartu formulir.

          Halamannya tetap penuh — yang dibingkai hanya formulirnya, bukan
          seluruh isi layar. Bedanya dengan versi dua langkah lalu: dulu SATU
          kartu membungkus hiasan DAN formulir sekaligus, jadi seluruh halaman
          terbaca sebagai dialog. Sekarang hiasannya berdiri bebas di atas
          latar, dan kartu ini cuma menandai "di sini tempat Anda mengetik".

          Isiannya pekat, bukan tembus pandang. Kartu kaca yang meneruskan
          cahaya latar yang bergerak akan membuat teks di atasnya ikut
          berubah-ubah kontras — dan itu glassmorphism, yang memang dihindari
          sistem ini. #1E1215 satu langkah lebih terang daripada latar
          halaman: cukup untuk terbaca terangkat, tanpa jadi bidang terang.
        */
        .auth-form {
            background: var(--lg-card);
            border: 1px solid var(--lg-card-border);
            border-radius: 14px;
            padding: 40px 38px 36px;
            box-shadow:
                0 2px 6px rgba(0, 0, 0, .30),
                0 26px 64px rgba(0, 0, 0, .46);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /*
          Kepala & kaki halaman. Keduanya diposisikan absolut terhadap
          halaman, bukan ikut di dalam kerangka dua kolom — supaya mereka
          menempel ke sudut layar dan tidak bergeser mengikuti tinggi
          formulir. Inilah yang membuat halaman terbaca sebagai HALAMAN,
          bukan sebagai dialog yang kebetulan memenuhi layar.
        */

        /* Sudut kiri atas: ikon produk berdampingan dengan lambang induk.
           Garis pemisah tipis di antara keduanya menyatakan "dua hal berbeda
           yang berdampingan", bukan satu lambang gabungan. */
        .page-marks {
            position: absolute;
            top: 34px;
            left: 44px;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 14px;
            animation: fade-up .5s cubic-bezier(.16, 1, .3, 1) .1s backwards;
        }
        .page-marks img { width: auto; display: block; }
        /* Tautan lambang. Peredupan ringan saat diam lalu penuh saat
           disentuh tetikus — cukup untuk memberi tahu "ini bisa diklik"
           tanpa menambah garis bawah atau kotak apa pun di sudut halaman. */
        .page-marks .mark-link {
            display: block;
            line-height: 0;
            opacity: .86;
            border-radius: 6px;
            transition: opacity .18s ease, transform .18s ease;
        }
        .page-marks .mark-link:hover { opacity: 1; transform: translateY(-1px); }
        .page-marks .mark-link:focus-visible {
            outline: none;
            opacity: 1;
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        /* Ikon TESYS dibuat lebih besar: ia lambang produknya sendiri,
           sedangkan Hanasta berdiri sebagai induk di sebelahnya. Tinggi
           yang berbeda inilah yang menyatakan urutan itu — tanpa perlu
           satu kata pun. */
        .page-marks .mark-ts { height: 36px; }
        .page-marks .mark-hanasta { height: 30px; }
        .page-marks .mark-sep {
            width: 1px;
            height: 22px;
            background: rgba(255, 255, 255, .16);
            flex: none;
        }

        /* Sudut KIRI bawah, dengan tepi kiri yang sama persis dengan
           lambang di sudut kiri atas (44px). Dua keterangan sistem itu jadi
           berdiri di satu garis tegak lurus yang sama — halaman punya satu
           tepi kiri, bukan dua. */
        /*
          Tombol pengalih tema, sudut kanan atas.

          Ikonnya menunjukkan tema yang akan DITUJU, bukan yang sedang
          aktif — pola yang sama dipakai hampir semua aplikasi, dan ia yang
          menjawab pertanyaan sebenarnya di kepala orang: "kalau saya klik
          ini, saya dapat apa?"
        */
        .theme-toggle {
            position: absolute;
            top: 34px;
            right: 44px;
            z-index: 3;
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 1px solid var(--lg-card-border);
            border-radius: 9px;
            background: var(--lg-card);
            color: rgba(255, 255, 255, .62);
            font-size: 13px;
            cursor: pointer;
            transition: background .18s ease, border-color .18s ease,
                        color .18s ease, transform .14s ease;
            animation: fade-up .5s cubic-bezier(.16, 1, .3, 1) .15s backwards;
        }
        .theme-toggle:hover {
            color: #fff;
            border-color: rgba(255, 255, 255, .2);
            transform: translateY(-1px);
        }
        .theme-toggle:focus-visible {
            outline: none;
            color: #fff;
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        /* Hanya satu ikon yang tampil, ditentukan oleh tema yang aktif. */
        .theme-toggle .ic-to-dark { display: none; }
        html[data-theme="light"] .theme-toggle .ic-to-dark { display: block; }
        html[data-theme="light"] .theme-toggle .ic-to-light { display: none; }
        html[data-theme="dark"] .theme-toggle .ic-to-dark { display: none; }
        html[data-theme="dark"] .theme-toggle .ic-to-light { display: block; }

        .page-foot {
            position: absolute;
            bottom: 28px;
            left: 44px;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 3px;
            text-align: left;
            animation: fade-up .5s cubic-bezier(.16, 1, .3, 1) .3s backwards;
        }
        .page-foot .foot-copy {
            font-size: 11.5px;
            color: var(--ink-mute);
        }
        .page-foot .foot-version {
            font-size: 11px;
            letter-spacing: .06em;
            color: rgba(255, 255, 255, .34);
            font-variant-numeric: tabular-nums;
        }

        .auth-head { margin-bottom: 26px; }
        /* Ukuran dan bobotnya mengikuti skala judul halaman di dalam
           aplikasi (24-28px, bobot 600). Halaman masuk adalah hal pertama
           yang dilihat, jadi ia yang menetapkan nada — kalau di sini
           berbeda, keduanya terbaca seperti dua produk. */
        .auth-head h1 {
            font-family: var(--font-display);
            font-size: 27px; font-weight: 600; margin: 0 0 6px; letter-spacing: -.01em;
            line-height: 1.2;
        }
        .auth-head p { margin: 0; font-size: 13px; color: var(--ink-soft); }

        .auth-field { margin-bottom: 16px; }
        .auth-field label {
            display: block; font-size: 12px; font-weight: 600;
            color: var(--text); margin-bottom: 7px;
        }

        /* Ikon field dipusatkan lewat kotak setinggi input, bukan translate
           manual — tetap presisi berapa pun tinggi field-nya. */
        .auth-input-wrap { position: relative; }
        .auth-input-wrap > .fld-ic {
            position: absolute; left: 0; top: 0; bottom: 0;
            width: 40px; display: flex; align-items: center; justify-content: center;
            color: var(--ink-hint); font-size: 13px; pointer-events: none;
            transition: color .18s ease;
        }
        .auth-input {
            width: 100%; height: 46px;
            border: 1px solid var(--border); border-radius: 10px;
            padding: 0 14px 0 40px; font-size: 13.5px; font-family: inherit;
            background: #fcfcfd; color: var(--text);
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
        }
        .auth-input::placeholder { color: var(--ink-hint); }
        .auth-input:hover:not(:focus) { border-color: var(--border-strong); }
        .auth-input:focus {
            outline: none; background: var(--surface);
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        .auth-input-wrap:focus-within > .fld-ic { color: var(--accent); }
        .auth-input.is-invalid { border-color: var(--danger); background: #fef7f7; }
        .auth-err { display: block; margin-top: 6px; font-size: 11.5px; color: var(--danger); }

        .auth-toggle {
            position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
            width: 36px; height: 36px; border: none; background: none; cursor: pointer;
            color: var(--ink-hint); border-radius: 8px; font-size: 13px;
            display: flex; align-items: center; justify-content: center;
            transition: background .16s ease, color .16s ease;
        }
        .auth-toggle:hover { background: var(--surface-2); color: var(--text); }
        .auth-toggle:focus-visible {
            outline: none; color: var(--text);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        .auth-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 18px 0 22px; }
        .auth-check { display: flex; align-items: center; gap: 9px; font-size: 12.5px; color: var(--ink-soft); cursor: pointer; }
        .auth-check input { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; margin: 0; flex: none; }
        .auth-check input:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

        .auth-btn {
            position: relative;
            /* Kilau di bawah dipotong oleh tepi tombol. */
            overflow: hidden;
            width: 100%; height: 48px;
            border: 1px solid var(--accent); background: var(--accent); color: #fff;
            border-radius: 10px;
            font-family: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            box-shadow: 0 1px 2px rgba(200, 39, 46, .16), 0 6px 16px rgba(200, 39, 46, .18);
            transition: background .18s ease, border-color .18s ease, box-shadow .2s ease, transform .12s ease;
        }
        .auth-btn:hover {
            background: var(--accent-dark); border-color: var(--accent-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(200, 39, 46, .18), 0 10px 22px rgba(200, 39, 46, .22);
        }
        .auth-btn:active {
            transform: translateY(0) scale(.995);
            box-shadow: 0 1px 2px rgba(200, 39, 46, .2);
        }
        .auth-btn:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px #fff, 0 0 0 5px var(--accent);
        }
        .auth-btn i { font-size: 12px; transition: transform .2s ease; }
        .auth-btn:hover i { transform: translateX(2px); }

        /*
          Kilau berkala pada tombol utama.

          Satu berkas cahaya miring menyapu tombol dari kiri ke kanan. Yang
          menentukan apakah ia terbaca "mahal" atau "murahan" bukan kilaunya,
          melainkan JEDANYA: sapuannya sendiri hanya 1,1 detik, sisanya —
          hampir lima detik — tombol diam sepenuhnya. Kilau yang berulang
          tanpa jeda akan terbaca seperti iklan.

          Dikerjakan oleh ::before, bukan latar tombol, supaya keadaan hover
          dan :active tetap bisa mengubah warna tombol tanpa mengganggu
          sapuannya. pointer-events dimatikan agar ia tidak pernah menelan
          klik. Hanya transform yang dianimasikan.
        */
        .auth-btn::before {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: -60%;
            width: 45%;
            pointer-events: none;
            background: linear-gradient(
                100deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, .26) 50%,
                rgba(255, 255, 255, 0) 100%
            );
            transform: translate3d(0, 0, 0) skewX(-18deg);
            animation: btn-sheen 5.6s ease-in-out infinite;
            will-change: transform;
        }
        @keyframes btn-sheen {
            /* Sapuan terjadi di 20% pertama siklus; 80% sisanya diam di
               luar bidang tombol. */
            0%        { transform: translate3d(0, 0, 0) skewX(-18deg); }
            20%, 100% { transform: translate3d(360%, 0, 0) skewX(-18deg); }
        }

        /* Saat sedang mengirim, kilau dimatikan — di situ yang harus
           terbaca hanya pemintalnya. */
        .auth-btn.is-busy::before { display: none; }

        /* Keadaan sedang mengirim — murni tampilan; pengiriman form tetap jalan. */
        .auth-btn.is-busy { pointer-events: none; opacity: .82; }
        .auth-btn.is-busy .btn-label,
        .auth-btn.is-busy i { visibility: hidden; }
        .auth-btn .btn-spin {
            display: none;
            position: absolute; width: 17px; height: 17px;
            border: 2px solid rgba(255, 255, 255, .35);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        .auth-btn.is-busy .btn-spin { display: block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Alert */
        .auth-alert {
            border-radius: 10px; padding: 12px 14px; font-size: 12.5px;
            margin-bottom: 18px; display: flex; gap: 10px; align-items: flex-start; line-height: 1.55;
        }
        .auth-alert i { margin-top: 2px; font-size: 13px; }
        .auth-alert-success { background: var(--success-bg); color: var(--success); }
        .auth-alert-danger  { background: var(--danger-bg);  color: var(--danger); }
        .auth-alert ul { margin: 5px 0 0; padding-left: 17px; }

        /*
        |--------------------------------------------------------------------
        | KENDALI FORM DI ATAS LATAR GELAP
        |--------------------------------------------------------------------
        | Sampai kartunya dibubarkan, formulir ini duduk di atas kertas
        | terang, jadi seluruh kendalinya digambar dengan logika "tinta gelap
        | di atas kertas": isian nyaris putih, garis tepi abu muda, teks
        | hampir hitam.
        |
        | Sekarang dasarnya berbalik. Yang ditulis ulang bukan warnanya satu
        | per satu, melainkan PERANNYA:
        |
        |   - isian bukan lagi "kertas", melainkan CEKUNGAN — bidang yang
        |     sedikit lebih gelap dari sekitarnya, dinyatakan lewat putih
        |     transparan tipis, bukan warna tetap. Dengan begitu ia ikut
        |     menyesuaikan diri saat cahaya latar yang bernapas itu lewat.
        |   - garis tepi bukan lagi pembatas, melainkan TEPI YANG DITERANGI.
        |   - keadaan fokus tetap burgundy: satu-satunya isyarat berwarna di
        |     seluruh formulir, jadi ia tidak mungkin tertukar dengan apa pun.
        */
        .auth-head h1 { color: #fff; }

        .auth-field label { color: rgba(255, 255, 255, .88); }

        .auth-input {
            background: var(--lg-input);
            border-color: var(--lg-input-border);
            color: #fff;
        }
        .auth-input::placeholder { color: var(--ink-hint); }
        .auth-input:hover { border-color: var(--lg-input-hover); }
        .auth-input:focus {
            background: var(--lg-input-focus);
            border-color: var(--accent-soft);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        .auth-input.is-invalid {
            border-color: var(--danger);
            background: rgba(154, 48, 48, .16);
        }

        /* Isian yang diisi otomatis oleh peramban. Tanpa ini Chrome menimpa
           latarnya dengan biru muda bawaannya dan teks putih jadi hilang;
           inset shadow adalah satu-satunya cara menahannya. */
        .auth-input:-webkit-autofill,
        .auth-input:-webkit-autofill:hover,
        .auth-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #fff;
            -webkit-box-shadow: 0 0 0 1000px var(--lg-autofill) inset;
            caret-color: #fff;
            transition: background-color 9999s ease-in-out 0s;
        }

        .auth-input-wrap > .fld-ic { color: var(--ink-hint); }

        .auth-toggle { color: var(--ink-hint); }
        .auth-toggle:hover { background: rgba(255, 255, 255, .08); color: #fff; }
        .auth-toggle:focus-visible { color: #fff; }

        .auth-check { color: var(--ink-soft); }

        /* Pemberitahuan: isian burgundy/hijau pekat dari palet terang akan
           menyala di sini, jadi keduanya dibuat sebagai kaca berwarna —
           latar transparan tipis, teks nada terang dari keluarga yang sama. */
        .auth-alert-success {
            background: rgba(71, 112, 90, .20);
            color: #a9cdb8;
        }
        .auth-alert-danger {
            background: rgba(154, 48, 48, .20);
            color: #e6a8a8;
        }

        /* Cincin fokus tombol memakai #fff sebagai lapis dalam — di atas
           kertas itu memisahkan tombol dari cincinnya, di atas latar gelap
           ia justru jadi kilatan putih. Diganti warna latarnya sendiri. */
        .auth-btn:focus-visible {
            box-shadow: 0 0 0 3px var(--lg-bg-2), 0 0 0 5px var(--accent-soft);
        }

        /* Isi panel form muncul berurutan dari atas ke bawah. */
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: none; }
        }
        .stagger > * { animation: fade-up .45s cubic-bezier(.16, 1, .3, 1) backwards; }
        .stagger > *:nth-child(1) { animation-delay: .16s; }
        .stagger > *:nth-child(2) { animation-delay: .23s; }
        .stagger > *:nth-child(3) { animation-delay: .30s; }
        .stagger > *:nth-child(4) { animation-delay: .37s; }

        /*
        |--------------------------------------------------------------------
        | RESPONSIF
        | Form selalu jadi prioritas: di bawah 880px ia naik ke atas dan panel
        | dekorasi turun, sehingga lebar form tidak pernah terjepit.
        |--------------------------------------------------------------------
        */
        @media (max-width: 1080px) {
            .auth-card { max-width: 900px; }
            .auth-card { gap: 44px; }
        }

        /*
        |--------------------------------------------------------------------
        | LAYAR SEMPIT
        |--------------------------------------------------------------------
        | Tiga hal yang berubah, dan alasannya masing-masing:
        |
        | 1. SATU KOLOM, FORMULIR DULU. Hiasan turun ke bawah formulir. Di
        |    layar tinggi-sempit, apa pun yang ditaruh di atas kolom isian
        |    berarti orang harus menggulir melewatinya untuk mulai bekerja.
        |
        | 2. SUDUT-SUDUTNYA DILEPAS DARI SUDUT. Empat blok yang ditempel ke
        |    sudut layar bekerja di monitor lebar; di ponsel keempatnya
        |    berebut ruang dengan formulir dan saling bertabrakan. Lambang
        |    dan kaki halaman dikembalikan ke aliran normal — mengalir
        |    bersama isinya, bukan mengapung di atasnya.
        |
        | 3. SASARAN SENTUH DIPERBESAR. Tinggi kolom isian dan tombol
        |    dinaikkan ke 50px, di atas ambang 44px yang dipakai sebagai
        |    batas nyaman untuk jempol.
        */
        @media (max-width: 880px) {
            /* body adalah flex container. Di desktop arahnya BARIS dan
               ketiga blok (lambang, kerangka, kaki) tidak pernah bertemu
               karena dua di antaranya diposisikan absolut.

               Begitu keduanya dikembalikan ke aliran normal, arah baris itu
               akan menjajarkan ketiganya bersebelahan. Jadi arahnya harus
               ikut diputar ke KOLOM — tanpa itu, order: 1/2/3 di bawah tidak
               berarti apa-apa. */
            body {
                flex-direction: column;
                align-items: stretch;
                justify-content: flex-start;
                padding: 26px 20px 30px;
                min-height: 100%;
            }

            /* Lambang & kaki halaman keluar dari posisi absolut supaya tinggi
               halaman dihitung dari isinya — ini yang membuat halaman bisa
               digulir dengan benar di ponsel pendek. */
            .page-marks,
            .page-foot {
                position: static;
                animation: none;
            }
            /* Pengalih tema tetap mengambang di sudut — ia satu tombol kecil
               dan tidak pernah berebut ruang dengan isi. */
            .theme-toggle { top: 22px; right: 20px; width: 36px; height: 36px; }

            .auth-card {
                order: 2;
                grid-template-columns: 1fr;
                width: 100%;
                max-width: 460px;
                margin: 0 auto;
                gap: 30px;
                align-items: stretch;
            }

            .page-marks { order: 1; justify-content: flex-start; gap: 12px; margin-bottom: 26px; }
            .page-marks .mark-ts { height: 38px; }
            .page-marks .mark-hanasta { height: 25px; }

            .auth-form { order: 1; padding: 30px 24px 28px; }
            .auth-deco { order: 2; gap: 14px; align-items: center; }

            .deco-logo { width: min(300px, 78%); margin: 0 auto; }
            .deco-cap {
                width: 100%;
                font-size: 10.5px;
                letter-spacing: .2em;
            }
            .deco-glow { width: 300px; height: 300px; }

            .page-foot {
                order: 3;
                align-items: center;
                text-align: center;
                margin-top: 26px;
            }

            .auth-head h1 { font-size: 24px; }

            /* Sasaran sentuh. */
            .auth-input { height: 50px; }
            .auth-btn { height: 50px; }
            .auth-toggle { width: 42px; height: 42px; }
        }

        @media (max-width: 420px) {
            body { padding: 20px 14px 26px; }
            .auth-card { gap: 26px; }
            .auth-form { padding: 26px 18px 24px; }
            .auth-head h1 { font-size: 22px; }
            .page-marks .mark-ts { height: 34px; }
            .page-marks .mark-hanasta { height: 22px; }
            .deco-logo { width: min(260px, 84%); }
            .deco-cap { font-size: 10px; letter-spacing: .16em; }
        }

        /* Layar pendek & melintang (ponsel diputar): hiasan dilepas sama
           sekali. Di tinggi 480px, lambang dan keterangannya mendorong
           tombol Masuk keluar dari layar — dan hiasan tidak pernah lebih
           penting daripada tombol yang dicari orang. */
        @media (max-height: 520px) and (max-width: 880px) {
            .auth-deco { display: none; }
            .auth-card { gap: 16px; }
            /* Semua jarak vertikal dipangkas. Formulir masuk memang tidak
               akan pernah muat utuh di tinggi 420px — yang bisa diusahakan
               adalah menyisakan gulir sesedikit mungkin, dan memastikan yang
               terpotong itu kaki halaman, bukan tombolnya. */
            body { padding: 14px 20px 18px; }
            .page-marks { margin-bottom: 14px; }
            .page-marks .mark-ts { height: 30px; }
            .page-marks .mark-hanasta { height: 20px; }
            .auth-form { padding: 20px 22px 20px; }
            .auth-head { margin-bottom: 16px; }
            .auth-head h1 { font-size: 20px; }
            .auth-field { margin-bottom: 11px; }
            .auth-row { margin: 12px 0 14px; }
            .page-foot { margin-top: 14px; }
        }

        /* Pengguna yang mematikan animasi di sistem tetap mendapat tampilan
           akhir yang sama, tanpa gerakan apa pun. */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation: none !important;
                transition-duration: .01ms !important;
            }
        }

        /*
        |--------------------------------------------------------------------
        | LUPA SANDI — tautan + modal
        |--------------------------------------------------------------------
        */
        .auth-forgot {
            font-size: 12.5px; color: var(--accent-soft); text-decoration: none;
            transition: color .16s ease;
        }
        .auth-forgot:hover { color: #fff; text-decoration: underline; }

        .lupa-overlay {
            position: fixed; inset: 0; z-index: 50;
            background: rgba(0, 0, 0, .62);
            display: none; align-items: center; justify-content: center; padding: 20px;
        }
        .lupa-overlay.show { display: flex; }
        .lupa-modal {
            width: 100%; max-width: 420px;
            background: var(--lg-card); border: 1px solid var(--lg-card-border);
            border-radius: 14px; padding: 26px 24px 22px;
            box-shadow: 0 26px 64px rgba(0, 0, 0, .5);
            animation: card-in .35s cubic-bezier(.16, 1, .3, 1) backwards;
        }
        .lupa-modal h2 {
            font-family: var(--font-display); font-size: 19px; font-weight: 600;
            color: #fff; margin: 0 0 4px;
        }
        .lupa-modal .lupa-sub { font-size: 12.5px; color: var(--ink-soft); margin: 0 0 18px; }
        .lupa-close {
            position: absolute; top: 14px; right: 16px; background: none; border: none;
            color: var(--ink-mute); font-size: 22px; cursor: pointer; line-height: 1;
        }
        .lupa-close:hover { color: #fff; }
        .lupa-status { font-size: 11.5px; margin-top: 6px; display: block; min-height: 15px; }
        .lupa-status.ok { color: #a9cdb8; }
        .lupa-status.err { color: #e6a8a8; }
        .lupa-step2 { display: none; border-top: 1px solid var(--lg-card-border); margin-top: 18px; padding-top: 16px; }
        .lupa-step2.show { display: block; }
        .auth-btn.is-disabled { opacity: .5; pointer-events: none; }
        .auth-btn.secondary {
            background: transparent; color: #fff; border-color: var(--lg-input-border);
            box-shadow: none;
        }
        .auth-btn.secondary:hover { background: var(--lg-input); border-color: var(--lg-input-hover); }
    </style>
</head>

<body>

    <div class="bg-wash"></div>
    <div class="bg-glow" aria-hidden="true"></div>

{{-- Garis lengkung latar — kesan aliran tanpa berkas gambar tambahan. --}}
<svg class="bg-curves" viewBox="0 0 1440 900" preserveAspectRatio="none" aria-hidden="true">
    <path class="c1" d="M-80 690 C 220 548, 420 772, 700 630 S 1180 458, 1520 590"/>
    <path class="c2" d="M-80 752 C 250 612, 470 834, 748 694 S 1210 524, 1520 654"/>
    <path class="c3" d="M-80 814 C 280 676, 500 896, 790 756 S 1240 588, 1520 718"/>
    <path class="c4" d="M-80 236 C 260 116, 520 316, 820 196 S 1240 46, 1520 156"/>
    <path class="c5" d="M-80 168 C 240 54, 500 250, 800 130 S 1230 -16, 1520 92"/>
</svg>

{{-- Kepala halaman. Lambangnya memakai TesysLogo.png — wordmark PUTIH
     dengan swoosh merah, memang disiapkan untuk latar gelap. terexonly.png
     yang dipakai sebelumnya berwarna hitam; ia benar selama formulirnya
     masih berdiri di atas kertas putih, tapi akan lenyap sama sekali begitu
     kartunya dibubarkan. --}}
{{-- Sudut kiri atas: lambang grup. hanasta.png dirender hanya bila
     berkasnya memang ada, jadi halaman tetap utuh sampai berkas itu
     ditaruh di public/dist/img/. --}}
{{-- Pengalih tema. Ditaruh sebelum isi lain supaya urutan Tab
     menjangkaunya lebih dulu, sebelum kolom isian. --}}
<button type="button" class="theme-toggle" id="themeToggle"
        aria-label="Ganti tema" title="Ganti tema">
    <i class="fas fa-moon ic-to-dark" aria-hidden="true"></i>
    <i class="fas fa-sun ic-to-light" aria-hidden="true"></i>
</button>

<div class="page-marks">
    {{-- Keduanya membuka situs luar, jadi rel="noopener" dipasang: tanpa itu
         halaman tujuan bisa menjangkau halaman ini lewat window.opener. Di
         layar masuk, halaman yang bisa dijangkau pihak lain adalah halaman
         yang berisi kolom sandi. --}}
    <a class="mark-link" href="https://terex.id" target="_blank" rel="noopener noreferrer"
       title="Terex — terex.id">
        <img class="mark-ts" src="{{ asset('dist/img/ts_icon.png') }}" alt="Terex">
    </a>
    @if (file_exists(public_path('dist/img/hanasta.png')))
        <span class="mark-sep" aria-hidden="true"></span>
        <a class="mark-link" href="https://hanasta.id" target="_blank" rel="noopener noreferrer"
           title="Hanasta — hanasta.id">
            <img class="mark-hanasta" src="{{ asset('dist/img/hanasta.png') }}" alt="Hanasta">
        </a>
    @endif
</div>

<div class="auth-card">

    {{-- ===================== Panel ekosistem ===================== --}}
    <aside class="auth-deco">

        {{-- Anyaman heksagon enam modul dilepas. Ia menjelaskan CAKUPAN
             sistem — sesuatu yang berguna di halaman pemasaran, tapi di
             layar masuk ia hanya menunda orang yang sudah tahu mau ke mana.
             Satu lambang menyatakan hal yang sama dalam sekali lihat. --}}
        <div class="deco-glow" aria-hidden="true"></div>

        <img class="deco-logo" src="{{ asset('dist/img/tesys.png') }}"
             alt="TEREX SYSTEM">

        <p class="deco-cap">Internal Management Platform</p>

    </aside>

    {{-- ===================== Panel form ===================== --}}
    <main class="auth-form stagger">

        <div class="auth-head">
            <h1>Selamat datang</h1>
            <p>Masuk untuk melanjutkan.</p>
        </div>

        <div>
            <div aria-live="polite">
                @if (session('success'))
                    <div class="auth-alert auth-alert-success">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="auth-alert auth-alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                        <div>
                            @if ($errors->has('login'))
                                {{ $errors->first('login') }}
                            @else
                                <strong>Periksa kembali isian Anda</strong>
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <form action="{{ url('/login') }}" method="POST" novalidate id="loginForm">
                @csrf

                <div class="auth-field">
                    <label for="username">Username</label>
                    <div class="auth-input-wrap">
                        <span class="fld-ic"><i class="fas fa-user" aria-hidden="true"></i></span>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="auth-input @error('username') is-invalid @enderror"
                            placeholder="Masukkan username"
                            value="{{ old('username') }}"
                            autocomplete="username"
                            @error('username') aria-invalid="true" aria-describedby="username-error" @enderror
                            required
                            autofocus>
                    </div>
                    @error('username')
                        <small class="auth-err" id="username-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <div class="auth-input-wrap">
                        <span class="fld-ic"><i class="fas fa-lock" aria-hidden="true"></i></span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="auth-input @error('password') is-invalid @enderror"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            style="padding-right:46px;"
                            @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                            required>
                        <button type="button" class="auth-toggle" id="togglePwd" aria-label="Tampilkan password" aria-pressed="false">
                            <i class="fas fa-eye" id="togglePwdIcon" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('password')
                        <small class="auth-err" id="password-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="auth-row">
                    <label class="auth-check" for="remember">
                        <input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        Ingat saya
                    </label>
                    <a href="#" class="auth-forgot" id="lupaSandiLink">Lupa Sandi?</a>
                </div>

                <button type="submit" class="auth-btn" id="loginBtn">
                    <span class="btn-spin" aria-hidden="true"></span>
                    <span class="btn-label">Masuk</span>
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>
        </div>



    </main>

</div>

{{-- ===================== MODAL LUPA SANDI ===================== --}}
<div class="lupa-overlay" id="lupaOverlay" role="dialog" aria-modal="true" aria-labelledby="lupaTitle">
    <div class="lupa-modal" style="position:relative;">
        <button type="button" class="lupa-close" id="lupaClose" aria-label="Tutup">&times;</button>
        <h2 id="lupaTitle">Lupa Sandi</h2>
        <p class="lupa-sub">Masukkan Username &amp; Email terdaftar Anda. Bila cocok, kami kirim kode verifikasi ke email.</p>

        {{-- STEP 1: identitas --}}
        <div class="auth-field">
            <label for="lupaUser">Username</label>
            <div class="auth-input-wrap">
                <span class="fld-ic"><i class="fas fa-user" aria-hidden="true"></i></span>
                <input type="text" id="lupaUser" class="auth-input" placeholder="Username" autocomplete="username">
            </div>
        </div>
        <div class="auth-field" style="margin-bottom:10px;">
            <label for="lupaEmail">Email</label>
            <div class="auth-input-wrap">
                <span class="fld-ic"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                <input type="email" id="lupaEmail" class="auth-input" placeholder="email@terdaftar" autocomplete="email">
            </div>
            <small class="lupa-status" id="lupaStatus"></small>
        </div>

        <button type="button" class="auth-btn is-disabled" id="lupaSendBtn">
            <span class="btn-spin" aria-hidden="true"></span>
            <span class="btn-label">Kirim Kode ke Email</span>
        </button>

        {{-- STEP 2: kode + password baru --}}
        <div class="lupa-step2" id="lupaStep2">
            <div class="auth-field">
                <label for="lupaCode">Kode Verifikasi</label>
                <div class="auth-input-wrap">
                    <span class="fld-ic"><i class="fas fa-key" aria-hidden="true"></i></span>
                    <input type="text" id="lupaCode" class="auth-input" inputmode="numeric" placeholder="6 digit kode dari email" autocomplete="one-time-code">
                </div>
            </div>
            <div class="auth-field">
                <label for="lupaPass">Password Baru</label>
                <div class="auth-input-wrap">
                    <span class="fld-ic"><i class="fas fa-lock" aria-hidden="true"></i></span>
                    <input type="password" id="lupaPass" class="auth-input" placeholder="Min. 10 karakter, huruf & angka" autocomplete="new-password">
                </div>
            </div>
            <div class="auth-field" style="margin-bottom:12px;">
                <label for="lupaPass2">Konfirmasi Password Baru</label>
                <div class="auth-input-wrap">
                    <span class="fld-ic"><i class="fas fa-lock" aria-hidden="true"></i></span>
                    <input type="password" id="lupaPass2" class="auth-input" placeholder="Ulangi password baru" autocomplete="new-password">
                </div>
            </div>
            <small class="lupa-status" id="lupaResetStatus"></small>
            <button type="button" class="auth-btn" id="lupaResetBtn" style="margin-top:8px;">
                <span class="btn-spin" aria-hidden="true"></span>
                <span class="btn-label">Reset Password</span>
            </button>
        </div>
    </div>
</div>

{{-- Kaki halaman. Di luar kerangka dua kolom supaya ia menempel ke tepi
     bawah layar, bukan ikut terdorong oleh tinggi formulir. --}}
{{-- Hak cipta dan versi ditumpuk di sudut kanan bawah. Keduanya
     keterangan sistem, bukan navigasi — menaruhnya berjauhan di dua sudut
     membuat mata harus menyapu lebar layar untuk membaca dua baris yang
     sebenarnya satu kelompok. --}}
<footer class="page-foot">
    <span class="foot-copy">&copy; {{ date('Y') }} TEREX SYSTEM. SELURUH HAK CIPTA DILINDUNGI.</span>
    <span class="foot-version">v{{ config('changelog.current') }}</span>
</footer>

<script>
    (function () {
        // Tampilkan/sembunyikan password.
        var btn = document.getElementById('togglePwd');
        var pwd = document.getElementById('password');
        var ico = document.getElementById('togglePwdIcon');

        if (btn && pwd) {
            btn.addEventListener('click', function () {
                var show = pwd.type === 'password';
                pwd.type = show ? 'text' : 'password';
                ico.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                pwd.focus();
            });
        }

        // Keadaan "sedang mengirim" pada tombol. Murni tampilan: tombol tidak
        // pernah di-disable dan handler ini tidak memanggil preventDefault,
        // sehingga pengiriman form serta alur login tidak berubah sama sekali.
        var form = document.getElementById('loginForm');
        var submitBtn = document.getElementById('loginBtn');

        if (form && submitBtn) {
            form.addEventListener('submit', function () {
                submitBtn.classList.add('is-busy');
                submitBtn.setAttribute('aria-busy', 'true');
            });
        }
    })();

    /* =================================================================
       PENGALIH TEMA
       -----------------------------------------------------------------
       Menukar atribut data-theme di <html> dan menyimpannya. Seluruh
       warna halaman dibaca dari token yang menempel pada atribut itu,
       jadi pergantiannya cukup satu baris — tidak ada satu pun kelas
       yang perlu disentuh.

       Penyimpanannya dibungkus try/catch: di jendela penyamaran, akses
       localStorage bisa melempar. Kalau itu terjadi, temanya tetap
       berganti untuk kunjungan ini, hanya tidak diingat.
       ================================================================= */
    (function () {
        'use strict';

        var tombol = document.getElementById('themeToggle');
        if (!tombol) return;

        var akar = document.documentElement;

        function terapkan(tema) {
            akar.setAttribute('data-theme', tema);
            tombol.setAttribute('aria-label',
                tema === 'dark' ? 'Ganti ke tema terang' : 'Ganti ke tema gelap');
            tombol.setAttribute('title', tombol.getAttribute('aria-label'));
            try { localStorage.setItem('tesys-theme', tema); } catch (e) {}
        }

        /* Label awal diselaraskan dengan tema yang sudah dipasang di <head>. */
        terapkan(akar.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

        tombol.addEventListener('click', function () {
            terapkan(akar.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });
    })();

    /* =================================================================
       LUPA SANDI — alur reset password tamu.
       1) isi username+email -> dicek cocok (enable tombol Kirim Kode)
       2) kirim kode ke email
       3) isi kode + password baru -> reset
       ================================================================= */
    (function () {
        var overlay = document.getElementById('lupaOverlay');
        var link    = document.getElementById('lupaSandiLink');
        if (!overlay || !link) return;

        var CSRF = document.querySelector('meta[name="csrf-token"]').content;
        var elUser   = document.getElementById('lupaUser');
        var elEmail  = document.getElementById('lupaEmail');
        var elStatus = document.getElementById('lupaStatus');
        var sendBtn  = document.getElementById('lupaSendBtn');
        var step2    = document.getElementById('lupaStep2');
        var elCode   = document.getElementById('lupaCode');
        var elPass   = document.getElementById('lupaPass');
        var elPass2  = document.getElementById('lupaPass2');
        var resetSt  = document.getElementById('lupaResetStatus');
        var resetBtn = document.getElementById('lupaResetBtn');

        var matched = false, verifyTimer = null, cooldown = 0, cdTimer = null;

        function setStatus(el, msg, kind) {
            el.textContent = msg || '';
            el.className = 'lupa-status' + (kind ? ' ' + kind : '');
        }
        function busy(btn, on) {
            btn.classList.toggle('is-busy', on);
            btn.setAttribute('aria-busy', on ? 'true' : 'false');
        }
        function post(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body)
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, status: r.status, d: d }; }); });
        }
        function payload() { return { username: elUser.value.trim(), email: elEmail.value.trim() }; }

        function open() { overlay.classList.add('show'); setTimeout(function(){ elUser.focus(); }, 50); }
        function close() { overlay.classList.remove('show'); }

        link.addEventListener('click', function (e) { e.preventDefault(); open(); });
        document.getElementById('lupaClose').addEventListener('click', close);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

        // Cek kecocokan username+email (debounce) -> enable tombol Kirim Kode.
        function verify() {
            matched = false;
            sendBtn.classList.add('is-disabled');
            var p = payload();
            if (!p.username || !p.email || p.email.indexOf('@') < 0) { setStatus(elStatus, ''); return; }
            setStatus(elStatus, 'Memeriksa…');
            post('{{ route('forgot.verify') }}', p).then(function (res) {
                if (!payload().username) return;
                if (res.d && res.d.match) {
                    matched = true;
                    sendBtn.classList.remove('is-disabled');
                    setStatus(elStatus, 'Data cocok. Anda bisa mengirim kode.', 'ok');
                } else {
                    setStatus(elStatus, 'Username & email tidak cocok.', 'err');
                }
            }).catch(function () { setStatus(elStatus, 'Gagal memeriksa. Coba lagi.', 'err'); });
        }
        [elUser, elEmail].forEach(function (el) {
            el.addEventListener('input', function () {
                clearTimeout(verifyTimer);
                verifyTimer = setTimeout(verify, 450);
            });
        });

        // Kirim kode.
        function tickCd() {
            if (cooldown <= 0) { sendBtn.querySelector('.btn-label').textContent = 'Kirim Ulang Kode'; sendBtn.classList.remove('is-disabled'); clearInterval(cdTimer); return; }
            sendBtn.querySelector('.btn-label').textContent = 'Kirim ulang (' + cooldown + ')';
            cooldown--;
        }
        sendBtn.addEventListener('click', function () {
            if (!matched) return;
            busy(sendBtn, true);
            post('{{ route('forgot.send') }}', payload()).then(function (res) {
                busy(sendBtn, false);
                if (res.ok && res.d.ok) {
                    setStatus(elStatus, res.d.message, 'ok');
                    step2.classList.add('show');
                    elCode.focus();
                    sendBtn.classList.add('is-disabled');
                    cooldown = 60; cdTimer = setInterval(tickCd, 1000); tickCd();
                } else {
                    setStatus(elStatus, (res.d && res.d.message) || 'Gagal mengirim kode.', 'err');
                }
            }).catch(function () { busy(sendBtn, false); setStatus(elStatus, 'Gagal menghubungi server.', 'err'); });
        });

        // Reset password.
        resetBtn.addEventListener('click', function () {
            setStatus(resetSt, '');
            if (elPass.value !== elPass2.value) { setStatus(resetSt, 'Konfirmasi password tidak sama.', 'err'); return; }
            busy(resetBtn, true);
            post('{{ route('forgot.reset') }}', {
                username: elUser.value.trim(), email: elEmail.value.trim(),
                code: elCode.value.trim(),
                password: elPass.value, password_confirmation: elPass2.value
            }).then(function (res) {
                busy(resetBtn, false);
                if (res.ok && res.d.ok) {
                    setStatus(resetSt, res.d.message + ' Mengarahkan ke login…', 'ok');
                    setTimeout(function () { window.location.href = '{{ url('/login') }}'; }, 1600);
                } else {
                    var msg = 'Gagal mereset password.';
                    if (res.d && res.d.errors) { msg = Object.values(res.d.errors)[0][0]; }
                    else if (res.d && res.d.message) { msg = res.d.message; }
                    setStatus(resetSt, msg, 'err');
                }
            }).catch(function () { busy(resetBtn, false); setStatus(resetSt, 'Gagal menghubungi server.', 'err'); });
        });
    })();

</script>

<script src="@aset('js/tesys-pwa.js')"></script>
</body>
</html>

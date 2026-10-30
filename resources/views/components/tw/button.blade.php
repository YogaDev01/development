{{--
    <x-tw.button> — tombol Tailwind (prefix tw), visual penuh tanpa kelas .btn.

    Props:
      variant  : primary | secondary | danger | ghost   (default primary)
      size     : sm | md                                (default md)
      type     : button | submit | reset                (default button)
      loading  : true -> disabled + aria-busy="true" (tanpa animasi)
      disabled : true -> disabled
      href     : string tak kosong -> dirender sebagai <a href> (tautan semantik:
                 ctrl/middle-klik, salin tautan, peran "link" bagi pembaca layar);
                 varian/ukuran/visual sama dengan <button>; prop type diabaikan.
                 target="_blank" tanpa rel otomatis diberi rel="noopener noreferrer".
                 disabled/loading pada tautan: TANPA href (tak bisa diaktifkan),
                 aria-disabled="true" role="link" tabindex="-1" (loading juga
                 aria-busy="true"), opacity 45% dan kursor not-allowed/progress.
                 Peringatan: <a> tanpa href tetap menerima event klik JS (beda dengan
                 <button disabled>); jangan memasang onclick/data-toggle pada tautan
                 disabled. Nilai href di-escape; pemanggil bertanggung jawab atas skema
                 (jangan teruskan input pengguna mentah).

    Atribut lain (id, name, data-*, aria-*, title, form, target, rel, download, ...)
    diteruskan ke <button> atau <a>. Tidak ada handler JS di sini: perilaku
    (data-toggle, onclick, submit) tetap milik pemanggil. Konten tombol = slot; ikon + teks boleh,
    uppercase/truncate taruh di <span> dalam slot (reboot BS
    button{text-transform:none;overflow:visible}).

    Dipakai untuk tombol baru dan toolbar Cost Request (index); aksi baris tabel
    tetap legacy (ts-action-*). Jangan campur dengan .btn.

    Kelas ditulis LITERAL penuh (dipindai Tailwind) — jangan dirangkai.
    Jangan menulis token berawalan "tw" + titik dua di komentar: tes aset
    memeriksa setiap token yang tertulis utuh di berkas ini.

    Sufiks ! yang ada — SEMUANYA terbukti perlu lewat ukur CDP (docs/ui/tailwind-evidence,
    butir G3-bang: nilai bentuk tanpa ! berbeda dari token; aturan legacy tak
    berlayer menang atas utilitas berlayer biasa):
      rounded-sm!   : reboot BS button{border-radius:0} (istirahat) dan
                      :focus-visible{border-radius:4px} (tesys-ui.css:57)
      outline-accent-text! (hanya warna, saat fokus papan ketik) :
                      :focus-visible{outline:2px solid var(--accent)} (tesys-ui.css:57)
                      mengunci warna outline ke accent-text (kontras >= 3:1 di terang & gelap,
                      --accent gelap hanya 2,06:1). Lebar/gaya/offset outline sengaja tanpa !:
                      nilainya sama dengan legacy, jadi berlaku di dalam maupun di luar legacy.
      text-control!, text-data!, leading-tight! (HANYA <button>) : reboot BS
                      button,input,select,...{font-size:inherit;line-height:inherit}
                      (ukur: pada <a> tidak perlu, jadi tautan memakai bentuk tanpa !)
      disabled:cursor-not-allowed! (atau disabled:cursor-progress! saat loading;
                      keduanya tidak pernah bersamaan; HANYA <button>) : button{cursor:pointer}
                      (tesys-ui.css:55). Tautan disabled memakai cursor-not-allowed/progress
                      tanpa ! (terukur tidak dikalahkan legacy).
    Khusus <a> (terukur G3-bang/G2-token-tautan; hanya warna, bukan dekorasi/ukuran):
      bg-*! (latar) : reboot BS a{background-color:transparent} mengalahkan bg-* berlayer
      text-*! (warna teks), hover:bg-*! dan hover:text-*! :
                      a{color:inherit} (tesys-ui.css:54), body.tesys-ui a / a:hover
                      (tesys-ui-compat.css:365-366), BS a / a:hover. Warna dasar diberi !
                      sehingga hover legacy tidak mengubahnya (tautan disabled pun tetap).
      Tidak perlu !: text-decoration (tetap none saat hover/fokus), display, ukuran, tepi,
      bayangan, kursor.

    Indikator fokus = outline (bukan ring box-shadow): ring tidak dilukis pada
    forced-colors (Windows High Contrast) sehingga fokus hilang (ukur: butir
    G4-fokus-forced-colors), dan ring berwarna sama dengan isi tombol primer
    menyatu dengan isinya; outline offset 2px menyisakan celah transparan.

    Teks tombol primer memakai on-accent (putih): --chrome-text abu-abu
    (#d2ccce) gagal 4,5:1 pada aksen praset Teal/Forest/Rust (3,85-3,93).

    Catatan ukur: padding vertikal bawaan UA button (1px) tetap ada tetapi tidak
    berpengaruh (min-h-* + items-center); target sentuh 34/30px (deviasi dari 44px,
    sama dengan kontrol legacy; WCAG 2.2 AA 2.5.8 minimum 24px terpenuhi).
--}}
@props([
    'variant'  => 'primary',
    'size'     => 'md',
    'type'     => 'button',
    'loading'  => false,
    'disabled' => false,
    'href'     => null,
])

@php
    $tautan = is_string($href) && $href !== '';

    // Bagian bersama <button> dan <a>.
    $dasar = 'tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:border tw:rounded-sm! tw:font-label tw:whitespace-nowrap tw:select-none tw:transition tw:focus-visible:outline-2 tw:focus-visible:outline-offset-2 tw:focus-visible:outline-accent-text!';

    $varian = [
        'primary'   => 'tw:bg-accent tw:border-accent tw:text-on-accent tw:shadow-accent',
        'secondary' => 'tw:bg-surface tw:border-border tw:text-text',
        'danger'    => 'tw:bg-danger-bg tw:border-danger tw:text-danger',
        'ghost'     => 'tw:bg-transparent tw:border-transparent tw:text-text-dim',
    ];

    $hoverTombol = [
        'primary'   => 'tw:enabled:hover:bg-accent-dark tw:enabled:hover:border-accent-dark',
        'secondary' => 'tw:enabled:hover:bg-surface-2 tw:enabled:hover:border-border-strong',
        'danger'    => 'tw:enabled:hover:bg-danger tw:enabled:hover:text-surface',
        'ghost'     => 'tw:enabled:hover:bg-surface-2 tw:enabled:hover:text-text',
    ];

    // <a>: latar dan warna teks dikalahkan aturan legacy a{background-color:transparent},
    // a{color:inherit}, a:hover{color} (lihat daftar ! di atas), jadi diberi !.
    $varianTautan = [
        'primary'   => 'tw:bg-accent! tw:border-accent tw:text-on-accent! tw:shadow-accent',
        'secondary' => 'tw:bg-surface! tw:border-border tw:text-text!',
        'danger'    => 'tw:bg-danger-bg! tw:border-danger tw:text-danger!',
        'ghost'     => 'tw:bg-transparent tw:border-transparent tw:text-text-dim!',
    ];

    $hoverTautan = [
        'primary'   => 'tw:hover:bg-accent-dark! tw:hover:border-accent-dark',
        'secondary' => 'tw:hover:bg-surface-2! tw:hover:border-border-strong',
        'danger'    => 'tw:hover:bg-danger! tw:hover:text-surface!',
        'ghost'     => 'tw:hover:bg-surface-2! tw:hover:text-text!',
    ];

    $ukuran = [
        'sm' => 'tw:min-h-ctl-sm tw:px-3 tw:text-data! tw:leading-tight!',
        'md' => 'tw:min-h-ctl tw:px-4 tw:text-control! tw:leading-tight!',
    ];

    // <a> tidak terkena reboot BS button{font-size:inherit;line-height:inherit}, jadi tanpa !.
    $ukuranTautan = [
        'sm' => 'tw:min-h-ctl-sm tw:px-3 tw:text-data tw:leading-tight',
        'md' => 'tw:min-h-ctl tw:px-4 tw:text-control tw:leading-tight',
    ];

    $mati = (bool) $disabled || (bool) $loading;
    $v = isset($varian[$variant]) ? $variant : 'primary';
    $u = isset($ukuran[$size]) ? $size : 'md';

    if ($tautan) {
        $kelas = $dasar . ' ' . $varianTautan[$v] . ' ' . ($mati ? '' : $hoverTautan[$v] . ' ') . $ukuranTautan[$u]
            . ($mati ? ($loading ? ' tw:opacity-45 tw:cursor-progress' : ' tw:opacity-45 tw:cursor-not-allowed') : '');
        $tambahan = ['class' => trim($kelas)];
        if ($mati) {
            $tambahan['aria-disabled'] = 'true';
            $tambahan['role'] = 'link';
            $tambahan['tabindex'] = '-1';
        } else {
            $tambahan['href'] = $href;
            if (strtolower((string) $attributes->get('target')) === '_blank') {
                $tambahan['rel'] = 'noopener noreferrer';
            }
        }
        if ($loading) {
            $tambahan['aria-busy'] = 'true';
        }
    } else {
        $type = in_array($type, ['button', 'submit', 'reset'], true) ? $type : 'button';
        $tambahan = [
            'class' => trim(
                $dasar . ' tw:disabled:opacity-45 '
                . $varian[$v] . ' ' . $hoverTombol[$v] . ' '
                . $ukuran[$u] . ' '
                . ($loading ? 'tw:disabled:cursor-progress!' : 'tw:disabled:cursor-not-allowed!')
            ),
            'type'  => $type,
        ];
        if ($mati) {
            $tambahan['disabled'] = true;
        }
        if ($loading) {
            $tambahan['aria-busy'] = 'true';
        }
    }
@endphp

@if ($tautan)<a {{ $attributes->merge($tambahan) }}>{{ $slot }}</a>@else<button {{ $attributes->merge($tambahan) }}>{{ $slot }}</button>@endif

{{--
    Galeri komponen Tailwind (x-tw.*) untuk pengukuran browser — BUKAN halaman
    aplikasi. Dirender oleh scripts/tailwind-harness/render.php (Blade asli,
    tanpa boot aplikasi) menjadi HTML statis di tools/tailwind/uji/.

    Data dari render.php:
      $varian : 'tw'       -> CSS legacy urut layout asli + tesys-tw.css produksi di AKHIR
                'baseline' -> CSS legacy urut layout asli, TANPA tesys-tw.css
                'awal'     -> tesys-tw.css dimuat SEBELUM tesys-ui.css (meniru @stack('styles'))
      $rel    : jalur relatif dari berkas keluaran ke folder public/

    Berkas ini sengaja berada di luar resources/views (tidak dipindai Tailwind,
    tidak memuat kelas utilitas sendiri) — semua kelas tw hanya berasal dari
    komponen.
--}}
@php
    $cssAwal = [
        'plugins/fontawesome-free/css/all.min.css',
        'plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
        'plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
        'css/bootstrap-4.6.1.css',
        'plugins/tom-select/tom-select.css',
        'plugins/flatpickr/dist/flatpickr.min.css',
        'css/tesys-ui-base.css',
    ];
    $cssTesys = [
        'css/tesys-ui.css',
        'css/tesys-ui-tokens.css',
        'css/tesys-ui-components.css',
        'css/tesys-ui-compat.css',
        'css/tesys-ui-pages.css',
        'css/tesys-ui-shell.css',
        'css/tesys-ui-motion.css',
        'css/tesys-ui-luxe.css',
        'css/tesys-ui-mobile.css',
        'css/tesys-ui-datatable.css',
        'css/tesys-ui-refine.css',
    ];
    $dataTabel = [
        ['CR-0001', 'Pengadaan alat tulis kantor', 'Disetujui', 'Rp 1.250.000'],
        ['CR-0002', 'Perjalanan dinas Surabaya', 'Menunggu', 'Rp 8.400.000'],
        ['CR-0003', 'Sewa kendaraan operasional', 'Ditolak', 'Rp 15.000.000'],
        ['CR-0004', 'Pembayaran vendor jaringan', 'Selesai', 'Rp 42.750.000'],
    ];
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Galeri x-tw ({{ $varian }})</title>

    {{-- Tema dari ?tema=gelap — sebelum CSS, meniru app.blade.php:36-45. --}}
    @verbatim
    <script>
        (function () {
            var q = new URLSearchParams(location.search);
            document.documentElement.setAttribute('data-theme', q.get('tema') === 'gelap' ? 'dark' : 'light');
        })();
    </script>
    @endverbatim

    @foreach ($cssAwal as $css)
        <link rel="stylesheet" href="{{ $rel }}/{{ $css }}">
    @endforeach

    @if ($varian === 'awal')
        {{-- Posisi @stack('styles'): sesudah tesys-ui-base, sebelum tesys-ui.css. --}}
        <link rel="stylesheet" href="{{ $rel }}/css/tesys-tw.css" id="tesys-tw-css">
    @endif

    @foreach ($cssTesys as $css)
        <link rel="stylesheet" href="{{ $rel }}/{{ $css }}">
    @endforeach

    @if ($varian === 'tw')
        <link rel="stylesheet" href="{{ $rel }}/css/tesys-tw.css" id="tesys-tw-css">
    @endif

    <style id="galeri-style">
        .galeri-judul { margin: 18px 0 8px; font-size: var(--fs-section); font-weight: var(--fw-section); color: var(--text-dim); border-bottom: 1px solid var(--border); padding-bottom: 4px; }
        .galeri-baris { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; margin-bottom: 12px; }
        .galeri-statis { position: static; display: block; float: none; }
        .galeri-lebar { width: 100%; max-width: 360px; }
    </style>

    {{-- Aksen kustom dari ?aksen=%23rrggbb — salinan app.blade.php:200-263 (isi
         skrip tidak boleh diproses Blade). Elemen style id tesys-accent-style
         diletakkan paling akhir di head, seperti di layout. --}}
    @verbatim
    <script>
        (function () {
            var DEFAULT = '#92232b';
            function css(hex) {
                return ':root{'
                    + '--accent:' + hex + ';'
                    + '--accent-dark:color-mix(in srgb,' + hex + ' 82%, #000);'
                    + '--accent-soft:color-mix(in srgb,' + hex + ' 52%, #fff);'
                    + '--accent-tint:color-mix(in srgb,' + hex + ' 10%, #fff);'
                    + '--accent-tint-2:color-mix(in srgb,' + hex + ' 22%, #fff);'
                    + '--accent-text:color-mix(in srgb,' + hex + ' 88%, #000);'
                    + '--accent-ring:color-mix(in srgb,' + hex + ' 22%, transparent);'
                    + '--accent-on-chrome:color-mix(in srgb,' + hex + ' 60%, #fff);'
                    + '--chrome-active:color-mix(in srgb,' + hex + ' 30%, #000);'
                    + '--amber:' + hex + ';'
                    + '--amber-dk:color-mix(in srgb,' + hex + ' 82%, #000);'
                    + '--amber-tint:color-mix(in srgb,' + hex + ' 10%, #fff);'
                    + '--amber-border:color-mix(in srgb,' + hex + ' 22%, #fff);'
                    + '--chrome:color-mix(in srgb,' + hex + ' 20%, #000);'
                    + '--chrome-2:color-mix(in srgb,' + hex + ' 28%, #000);'
                    + '--chrome-3:color-mix(in srgb,' + hex + ' 34%, #000);'
                    + '--chrome-border:color-mix(in srgb,' + hex + ' 24%, #000);'
                    + '--topbar:color-mix(in srgb,' + hex + ' 30%, #000);'
                    + '--topbar-2:color-mix(in srgb,' + hex + ' 38%, #000);'
                    + '--topbar-3:color-mix(in srgb,' + hex + ' 46%, #000);'
                    + '}'
                    + 'html[data-theme="dark"]{'
                    + '--accent-soft:color-mix(in srgb,' + hex + ' 68%, #000);'
                    + '--accent-tint:color-mix(in srgb,' + hex + ' 24%, #000);'
                    + '--accent-tint-2:color-mix(in srgb,' + hex + ' 36%, #000);'
                    + '--accent-text:color-mix(in srgb,' + hex + ' 55%, #fff);'
                    + '--accent-on-chrome:color-mix(in srgb,' + hex + ' 60%, #fff);'
                    + '--chrome-active:color-mix(in srgb,' + hex + ' 34%, #000);'
                    + '--amber-tint:color-mix(in srgb,' + hex + ' 24%, #000);'
                    + '--amber-border:color-mix(in srgb,' + hex + ' 36%, #000);'
                    + '--chrome:color-mix(in srgb,' + hex + ' 15%, #000);'
                    + '--chrome-2:color-mix(in srgb,' + hex + ' 23%, #000);'
                    + '--chrome-3:color-mix(in srgb,' + hex + ' 29%, #000);'
                    + '--chrome-border:color-mix(in srgb,' + hex + ' 19%, #000);'
                    + '--topbar:color-mix(in srgb,' + hex + ' 24%, #000);'
                    + '--topbar-2:color-mix(in srgb,' + hex + ' 32%, #000);'
                    + '--topbar-3:color-mix(in srgb,' + hex + ' 40%, #000);'
                    + '}';
            }
            function apply(hex) {
                var el = document.getElementById('tesys-accent-style');
                if (!hex || hex.toLowerCase() === DEFAULT) {
                    if (el) el.textContent = '';
                    return;
                }
                if (!el) {
                    el = document.createElement('style');
                    el.id = 'tesys-accent-style';
                    document.head.appendChild(el);
                }
                el.textContent = css(hex);
            }
            window.TesysAccent = { apply: apply, DEFAULT: DEFAULT };
            var aksen = new URLSearchParams(location.search).get('aksen');
            if (aksen && /^#[0-9a-fA-F]{6}$/.test(aksen)) apply(aksen);
        })();
    </script>
    @endverbatim
</head>

<body class="tesys-ui" data-galeri="{{ $varian }}">
{{-- Seperti app.blade.php:55-65: di layar <= 900px laci sidebar tertutup (sidebar-off);
     tanpa kelas ini selubung gelap .shell::after menutupi halaman dan menghalangi ketukan. --}}
@verbatim
<script>if (window.matchMedia('(max-width: 900px)').matches) document.body.classList.add('sidebar-off');</script>
@endverbatim
<div class="shell" id="galeri-shell">
<div class="main" id="galeri-main">
<div class="content" id="galeri-content">

    {{-- ================= TOMBOL ================= --}}
    <div class="galeri-judul">x-tw.button</div>
    <div class="galeri-baris" id="g-button">
        @foreach (['primary', 'secondary', 'danger', 'ghost'] as $v)
            @foreach (['md', 'sm'] as $u)
                <x-tw.button :variant="$v" :size="$u" id="tw-btn-{{ $v }}-{{ $u }}">{{ ucfirst($v) }} {{ $u }}</x-tw.button>
            @endforeach
            <x-tw.button :variant="$v" :disabled="true" id="tw-btn-{{ $v }}-disabled">{{ ucfirst($v) }} nonaktif</x-tw.button>
            <x-tw.button :variant="$v" :loading="true" id="tw-btn-{{ $v }}-loading">{{ ucfirst($v) }} memuat</x-tw.button>
        @endforeach
        <x-tw.button type="submit" form="tw-form-uji" id="tw-btn-submit" data-uji="submit" title="Tombol submit">Kirim form</x-tw.button>
    </div>

    {{-- ================= TAUTAN SEBAGAI TOMBOL (x-tw.button href) ================= --}}
    <div class="galeri-judul">x-tw.button href (tautan)</div>
    <div class="galeri-baris" id="g-link">
        @foreach (['primary', 'secondary', 'danger', 'ghost'] as $v)
            @foreach (['md', 'sm'] as $u)
                <x-tw.button href="#tw-lnk-tujuan" :variant="$v" :size="$u" id="tw-lnk-{{ $v }}-{{ $u }}">{{ ucfirst($v) }} {{ $u }}</x-tw.button>
                <x-tw.button href="#tw-lnk-tujuan" :variant="$v" :size="$u" :disabled="true" id="tw-lnk-{{ $v }}-{{ $u }}-disabled">{{ ucfirst($v) }} nonaktif</x-tw.button>
                <x-tw.button href="#tw-lnk-tujuan" :variant="$v" :size="$u" :loading="true" id="tw-lnk-{{ $v }}-{{ $u }}-loading">{{ ucfirst($v) }} memuat</x-tw.button>
                <x-tw.button href="#tw-lnk-tujuan" :variant="$v" :size="$u" id="tw-lnk-{{ $v }}-{{ $u }}-ikon"><i class="fas fa-download" aria-hidden="true"></i><span>Unduh</span></x-tw.button>
                <x-tw.button href="https://example.invalid/uji" target="_blank" :variant="$v" :size="$u" id="tw-lnk-{{ $v }}-{{ $u }}-blank">Tab baru</x-tw.button>
            @endforeach
        @endforeach
        {{-- Tujuan tautan internal dan pembanding legacy (a.btn) di baris yang sama. --}}
        <span id="tw-lnk-tujuan" tabindex="-1">Tujuan tautan uji</span>
        <a href="#tw-lnk-tujuan" class="btn btn-primary" id="lg-a-btn">legacy a.btn</a>
    </div>

    {{-- ================= INPUT & SELECT ================= --}}
    <div class="galeri-judul">x-tw.input / x-tw.select</div>
    <form id="tw-form-uji" action="#" onsubmit="return false" novalidate>
        <div class="galeri-baris" id="g-form">
            <div class="galeri-lebar">
                <x-tw.input label="Nama pemohon" name="pemohon" id="tw-in-normal" placeholder="Ketik nama" />
                <x-tw.input label="Jumlah" name="jumlah" id="tw-in-hint" type="number" value="1500000" hint="Isi dalam rupiah, tanpa titik." :required="true" />
                <x-tw.input label="Email" name="email" id="tw-in-error" type="email" value="salah" error="Format email tidak valid." />
                <x-tw.input label="Kode (nonaktif)" name="kode" id="tw-in-disabled" value="CR-0001" :disabled="true" />
                <x-tw.input label="Referensi (baca saja)" name="referensi" id="tw-in-readonly" value="REF-77" readonly />
                <x-tw.input label="Larik" name="details[0][nama]" value="Baris pertama" />
                <x-tw.input name="tanpa_label" id="tw-in-nolabel" placeholder="Tanpa label" aria-label="Tanpa label" />
            </div>
            <div class="galeri-lebar">
                <x-tw.select label="Jenis pengajuan" name="jenis" id="tw-sel-normal" placeholder="Pilih jenis">
                    <option value="ca">Cash Advance</option>
                    <option value="cp" selected>Cost Payment</option>
                    <option value="rb">Reimbursement</option>
                </x-tw.select>
                <x-tw.select label="Divisi" name="divisi" id="tw-sel-hint" hint="Pilih divisi pemohon." :required="true">
                    <option value="1">Keuangan</option>
                    <option value="2">Operasional</option>
                </x-tw.select>
                <x-tw.select label="Cabang" name="cabang" id="tw-sel-error" error="Cabang wajib dipilih." placeholder="Pilih cabang">
                    <option value="1">Jakarta</option>
                </x-tw.select>
                <x-tw.select label="Mata uang (nonaktif)" name="mata_uang" id="tw-sel-disabled" :disabled="true">
                    <option value="idr">IDR</option>
                </x-tw.select>
            </div>
            <div class="galeri-lebar">
                {{-- x-tw.textarea (F4-b): normal, hint+required, galat, nonaktif. --}}
                <x-tw.textarea label="Catatan" name="catatan" id="tw-ta-normal" placeholder="Tulis catatan" />
                <x-tw.textarea label="Alasan" name="alasan" id="tw-ta-hint" rows="3" value="Isi awal alasan" hint="Jelaskan singkat." :required="true" />
                <x-tw.textarea label="Uraian" name="uraian" id="tw-ta-error" error="Uraian wajib diisi." />
                <x-tw.textarea label="Keterangan (nonaktif)" name="keterangan" id="tw-ta-disabled" value="Terkunci" :disabled="true" />
            </div>
            <div class="galeri-lebar">
                {{-- Pembanding legacy di form-group yang sama. --}}
                <div class="form-group">
                    <label for="lg-in-normal">Legacy form-control</label>
                    <input type="text" class="form-control" id="lg-in-normal" placeholder="Legacy">
                </div>
                <div class="form-group">
                    <label for="lg-sel-normal">Legacy custom-select</label>
                    <select class="custom-select" id="lg-sel-normal"><option>Satu</option><option>Dua</option></select>
                </div>
            </div>
        </div>
    </form>

    {{-- ================= BADGE ================= --}}
    <div class="galeri-judul">x-tw.badge</div>
    <div class="galeri-baris" id="g-badge">
        @foreach (['neutral', 'attention', 'progress', 'positive', 'done', 'danger'] as $t)
            <x-tw.badge :tone="$t" size="sm" id="tw-badge-{{ $t }}-sm">{{ $t }} sm</x-tw.badge>
            <x-tw.badge :tone="$t" size="md" id="tw-badge-{{ $t }}-md">{{ $t }} md</x-tw.badge>
        @endforeach
        @foreach (['primary', 'secondary', 'success', 'warning', 'info', 'dark', 'light'] as $t)
            <x-tw.badge :tone="$t" id="tw-badge-alias-{{ $t }}">alias {{ $t }}</x-tw.badge>
        @endforeach
        <x-tw.badge tone="tidak-dikenal" id="tw-badge-unknown" title="Nada tak dikenal jatuh ke neutral">tak dikenal</x-tw.badge>
        @foreach (['neutral', 'attention', 'progress', 'positive', 'done', 'danger'] as $t)
            <x-tw.badge :tone="$t" dot id="tw-badge-dot-{{ $t }}">{{ $t }} dot</x-tw.badge>
        @endforeach
    </div>

    {{-- ================= KARTU ================= --}}
    <div class="galeri-judul">x-tw.card</div>
    <div class="galeri-baris" id="g-card">
        <x-tw.card id="tw-card-md" class="galeri-lebar">
            <x-slot:header>Judul kartu (md)</x-slot:header>
            Isi kartu dengan padding normal.
            <x-slot:footer>Keterangan kaki kartu</x-slot:footer>
        </x-tw.card>
        <x-tw.card padding="sm" id="tw-card-sm" class="galeri-lebar">
            <x-slot:header>Judul kartu (sm)</x-slot:header>
            Isi kartu dengan padding kecil.
        </x-tw.card>
        <x-tw.card padding="none" id="tw-card-none" class="galeri-lebar">
            <x-slot:header>Tanpa padding isi</x-slot:header>
            <div style="padding:8px">Isi diatur pemanggil.</div>
        </x-tw.card>
        <x-tw.card id="tw-card-polos" class="galeri-lebar">Kartu tanpa header/footer.</x-tw.card>
        <div class="card galeri-lebar" id="lg-card"><div class="card-header">Legacy .card</div><div class="card-body">Isi kartu legacy.</div></div>
    </div>

    {{-- ================= TABEL ================= --}}
    <div class="galeri-judul">x-tw.table</div>
    <div id="g-table">
        <x-tw.table id="tw-tabel">
            <x-slot:head><tr><th>No</th><th>Kode</th><th>Uraian</th><th>Status</th><th class="text-right">Nilai</th><th>Aksi</th></tr></x-slot:head>
            @foreach ($dataTabel as $i => $b)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $b[0] }}</td>
                    <td>{{ $b[1] }}</td>
                    <td>{{ $b[2] }}</td>
                    <td class="text-right">{{ $b[3] }}</td>
                    <td><x-tw.button variant="secondary" size="sm" title="Lihat {{ $b[0] }}">Lihat</x-tw.button></td>
                </tr>
            @endforeach
            <x-slot:foot><tr><td colspan="4">Total</td><td class="text-right">Rp 67.400.000</td><td></td></tr></x-slot:foot>
        </x-tw.table>

        <div class="galeri-judul">x-tw.table :dense, :card-mode="false"</div>
        <x-tw.table id="tw-tabel-rapat" :dense="true" :card-mode="false">
            <x-slot:head><tr><th>No</th><th>Kode</th><th>Uraian</th></tr></x-slot:head>
            @foreach ($dataTabel as $i => $b)
                <tr><td>{{ $i + 1 }}</td><td>{{ $b[0] }}</td><td>{{ $b[1] }}</td></tr>
            @endforeach
        </x-tw.table>

        <div class="galeri-judul">x-tw.table caps (header kapital), tanpa mode kartu</div>
        <x-tw.table id="tw-tabel-kapital" caps :card-mode="false">
            <x-slot:head><tr><th>Kode</th><th>Uraian</th><th>Status</th></tr></x-slot:head>
            @foreach ($dataTabel as $b)
                <tr><td>{{ $b[0] }}</td><td>{{ $b[1] }}</td><td><x-tw.badge dot tone="positive">{{ $b[2] }}</x-tw.badge></td></tr>
            @endforeach
        </x-tw.table>

        <div class="galeri-judul">x-tw.page-header + x-tw.stat</div>
        <x-tw.page-header id="tw-page-header" eyebrow="Modul · Contoh" title="Judul kepala halaman" description="Satu kalimat penjelas halaman.">
            <x-slot:actions>
                <x-tw.button variant="secondary" size="sm">Sekunder</x-tw.button>
                <x-tw.button size="sm">Primer</x-tw.button>
            </x-slot:actions>
        </x-tw.page-header>
        <div class="tw:grid tw:grid-cols-2 tw:gap-3 tw:lg:grid-cols-4" id="tw-stat-grid">
            <x-tw.stat label="Total" :value="22" tone="accent" hint="Semua data" />
            <x-tw.stat label="Menunggu" :value="3" tone="neutral" />
            <x-tw.stat label="Diproses" :value="1" tone="progress" />
            <x-tw.stat label="Selesai" :value="18" tone="positive" />
        </div>

        <div class="galeri-judul">x-tw.table + DataTables (aktif dengan ?dt=1)</div>
        <x-tw.table id="tw-tabel-dt">
            <x-slot:head><tr><th>No</th><th>Kode</th><th>Uraian</th><th>Status</th><th>Nilai</th></tr></x-slot:head>
            @foreach ($dataTabel as $i => $b)
                <tr><td>{{ $i + 1 }}</td><td>{{ $b[0] }}</td><td>{{ $b[1] }}</td><td>{{ $b[2] }}</td><td class="text-right">{{ $b[3] }}</td></tr>
            @endforeach
        </x-tw.table>

        <div class="galeri-judul">Tabel legacy (table.table)</div>
        <div class="table-responsive">
            <table class="table table-hover" id="lg-tabel">
                <thead><tr><th>No</th><th>Kode</th><th>Uraian</th><th>Status</th><th>Nilai</th></tr></thead>
                <tbody>
                    @foreach ($dataTabel as $i => $b)
                        <tr><td>{{ $i + 1 }}</td><td>{{ $b[0] }}</td><td>{{ $b[1] }}</td><td>{{ $b[2] }}</td><td class="text-right">{{ $b[3] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================= MODAL ================= --}}
    <div class="galeri-judul">x-tw.modal</div>
    <div class="galeri-baris" id="g-modal">
        <button type="button" class="btn btn-primary" id="lg-buka-modal-tw" data-toggle="modal" data-target="#tw-modal-uji">Buka modal tw (normal)</button>
        <button type="button" class="btn btn-secondary" id="lg-buka-modal-sm" data-toggle="modal" data-target="#tw-modal-sm">Buka modal tw sm</button>
        <button type="button" class="btn btn-secondary" id="lg-buka-modal-lg" data-toggle="modal" data-target="#tw-modal-lg">Buka modal tw lg</button>
        <button type="button" class="btn btn-secondary" id="lg-buka-modal-xl" data-toggle="modal" data-target="#tw-modal-xl">Buka modal tw xl</button>
        <button type="button" class="btn btn-secondary" id="lg-buka-modal-tinggi" data-toggle="modal" data-target="#tw-modal-tinggi">Buka modal tw tinggi</button>
        <button type="button" class="btn btn-outline-secondary" id="lg-buka-modal-legacy" data-toggle="modal" data-target="#lg-modal">Buka modal legacy</button>
        <x-tw.button variant="secondary" id="tw-buka-modal" data-toggle="modal" data-target="#tw-modal-uji">Buka modal tw (tombol x-tw.button)</x-tw.button>
    </div>

    <x-tw.modal id="tw-modal-uji">
        <x-slot:title>Judul modal uji</x-slot:title>
        <x-tw.input label="Nama di modal" name="modal_nama" id="tw-modal-in" placeholder="Ketik di sini" />
        <x-tw.select label="Pilihan di modal" name="modal_pilihan" id="tw-modal-sel">
            <option value="1">Satu</option>
            <option value="2">Dua</option>
        </x-tw.select>
        {{-- Label tanpa for= di form-group: dihubungkan tesys-ui.js saat shown.bs.modal. --}}
        <div class="form-group">
            <label>Label tanpa for (legacy)</label>
            <input type="text" id="tw-modal-in-legacy" class="form-control">
        </div>
        <x-slot:footer>
            <x-tw.button variant="ghost" data-dismiss="modal" id="tw-modal-batal">Batal</x-tw.button>
            <x-tw.button variant="primary" id="tw-modal-simpan">Simpan</x-tw.button>
        </x-slot:footer>
    </x-tw.modal>

    <x-tw.modal id="tw-modal-sm" size="sm">
        <x-slot:title>Modal kecil</x-slot:title>
        Konfirmasi singkat.
    </x-tw.modal>
    <x-tw.modal id="tw-modal-lg" size="lg">
        <x-slot:title>Modal besar</x-slot:title>
        Isi modal besar.
        <x-slot:footer><x-tw.button variant="secondary" data-dismiss="modal">Tutup</x-tw.button></x-slot:footer>
    </x-tw.modal>
    <x-tw.modal id="tw-modal-xl" size="xl">
        <x-slot:title>Modal ekstra besar</x-slot:title>
        Isi modal ekstra besar.
    </x-tw.modal>
    <x-tw.modal id="tw-modal-tinggi">
        <x-slot:title>Modal berisi panjang</x-slot:title>
        @for ($n = 1; $n <= 40; $n++)
            <div>Baris isi nomor {{ $n }} untuk menguji gulir internal.</div>
        @endfor
        <x-slot:footer>
            <x-tw.button variant="secondary" data-dismiss="modal">Tutup</x-tw.button>
            <x-tw.button variant="primary" id="tw-modal-tinggi-simpan">Simpan</x-tw.button>
        </x-slot:footer>
    </x-tw.modal>

    <div class="modal fade" id="lg-modal" tabindex="-1" role="dialog" aria-hidden="true" aria-labelledby="lg-modal-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lg-modal-title">Modal legacy</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">Isi modal legacy.</div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button></div>
            </div>
        </div>
    </div>

    {{-- ================= ELEMEN LEGACY KANONIK (uji isolasi) ================= --}}
    <div class="galeri-judul">Elemen legacy kanonik (uji isolasi)</div>
    <div id="g-legacy">
        <div class="galeri-baris">
            <button type="button" class="btn btn-primary" id="lg-btn-primary">btn-primary</button>
            <button type="button" class="btn btn-secondary" id="lg-btn-secondary">btn-secondary</button>
            <button type="button" class="btn btn-danger" id="lg-btn-danger">btn-danger</button>
            <button type="button" class="btn btn-primary" id="lg-btn-disabled" disabled>btn disabled</button>
            <span class="badge badge-success" id="lg-badge">badge-success</span>
            <a href="#" id="lg-a">tautan legacy</a>
        </div>
        <h1 id="lg-h1">Judul h1 legacy</h1>
        <div class="form-group">
            <label for="lg-label-in" id="lg-label">Label legacy</label>
            <input type="text" class="form-control is-invalid" id="lg-label-in" value="invalid">
            <div class="invalid-feedback" style="display:block">Pesan invalid legacy</div>
        </div>
        <ul class="nav nav-tabs" id="lg-tabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" href="#lg-tab-1" data-toggle="tab" role="tab">Tab satu</a></li>
            <li class="nav-item"><a class="nav-link" href="#lg-tab-2" data-toggle="tab" role="tab">Tab dua</a></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="lg-tab-1" role="tabpanel">Isi tab satu</div>
            <div class="tab-pane fade" id="lg-tab-2" role="tabpanel">Isi tab dua</div>
        </div>
        <div class="dropdown-menu show galeri-statis" id="lg-dropdown">
            <a class="dropdown-item" href="#">Item satu</a>
            <a class="dropdown-item active" href="#">Item aktif</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="#">Item tiga</a>
        </div>
    </div>

</div>
</div>
</div>

<script src="{{ $rel }}/plugins/jquery/jquery.min.js"></script>
<script src="{{ $rel }}/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="{{ $rel }}/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="{{ $rel }}/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="{{ $rel }}/js/tesys-table.js"></script>
<script src="{{ $rel }}/js/tesys-ui.js"></script>
@verbatim
<script>
    (function () {
        var q = new URLSearchParams(location.search);
        // ?bungkus=polos : lepas .shell/.main/.content (halaman mengalir biasa).
        if (q.get('bungkus') === 'polos') {
            ['galeri-shell', 'galeri-main', 'galeri-content'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.className = '';
            });
        }
        // ?dt=1 : inisialisasi DataTables pada #tw-tabel-dt (x-tw.table) dan #lg-tabel
        // (pembanding legacy; sama-sama kena mode kartu tesys-table.js di <= 640px).
        if (q.get('dt') === '1' && window.jQuery && jQuery.fn.DataTable) {
            jQuery(function () { jQuery('#tw-tabel-dt, #lg-tabel').DataTable({ paging: false, searching: false, info: false }); });
        }
        // ?tw=0 : lepas tesys-tw.css setelah dimuat (uji lepas-pasang tanpa membangun ulang).
        if (q.get('tw') === '0') {
            var l = document.getElementById('tesys-tw-css');
            if (l && l.parentNode) l.parentNode.removeChild(l);
        }
        document.documentElement.setAttribute('data-galeri-siap', '1');
    })();
</script>
@endverbatim
</body>
</html>

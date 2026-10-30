<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Penjaga statis Tailwind (docs/ui/tailwind.md): hanya membaca berkas,
 * TANPA database dan TANPA boot aplikasi.
 *
 * Yang dijaga: artefak PRODUKSI (public/css/tesys-tw.css) tetap terisolasi
 * (semua selektor ber-prefix tw:, tanpa reset global, tanpa palet bawaan),
 * tetap kecil, bebas dari safelist uji dan kelas satuan viewport yang dilarang,
 * pemetaan token menunjuk token yang benar-benar ada, safelist uji hidup di
 * masukan terpisah (resources/tailwind/tesys-tw.uji.css), setiap kelas tw: yang
 * dipakai komponen/JS benar-benar ada di artefak, dan Tailwind belum dimuat di
 * layout/Blade mana pun.
 */
class TailwindAssetTest extends TestCase
{
    private const BATAS_UKURAN = 51200; // 50 KB, anggaran awal (minified)

    /** Satuan viewport yang dilarang di komponen (html{zoom:.8} di shell.css membuatnya meleset). */
    private const KELAS_TERLARANG = 'h-screen|w-screen|h-dvh|min-h-screen';

    /** Kelas safelist uji yang tidak dipakai komponen; tidak boleh bocor ke artefak produksi. */
    private const PENANDA_SAFELIST = [
        'tw:col-span-2',
        'tw:bg-purple-bg',
        'tw:bg-orange-bg',
        'tw:text-purple',
    ];

    private function akar(string $path): string
    {
        return dirname(__DIR__, 2).'/'.$path;
    }

    private function baca(string $path): string
    {
        $isi = file_get_contents($this->akar($path));
        $this->assertNotFalse($isi, "Tidak bisa membaca {$path}");

        return $isi;
    }

    private function bacaTanpaKomentar(string $path): string
    {
        return preg_replace('#/\*.*?\*/#s', '', $this->baca($path));
    }

    /**
     * Pecah daftar selektor pada koma TINGKAT-ATAS (koma di dalam (), [] atau yang di-escape
     * dengan backslash tidak memecah). Tailwind menggabungkan selektor berdeklarasi sama
     * dengan koma, mis. varian [&_td] dan [&_th] dalam satu aturan.
     *
     * @return list<string>
     */
    private function pecahSelektor(string $daftar): array
    {
        $hasil = [];
        $kedalaman = 0;
        $kini = '';
        $n = strlen($daftar);
        for ($i = 0; $i < $n; $i++) {
            $c = $daftar[$i];
            if ($c === '\\') {
                $kini .= $c.($daftar[$i + 1] ?? '');
                $i++;

                continue;
            }
            if ($c === '(' || $c === '[') {
                $kedalaman++;
            } elseif ($c === ')' || $c === ']') {
                $kedalaman--;
            }
            if ($c === ',' && $kedalaman === 0) {
                $hasil[] = trim($kini);
                $kini = '';
            } else {
                $kini .= $c;
            }
        }
        if (trim($kini) !== '') {
            $hasil[] = trim($kini);
        }

        return $hasil;
    }

    /**
     * Himpunan nama kelas UTUH (sudah di-unescape) yang menjadi kelas utama sebuah selektor
     * di sebuah CSS, dari SETIAP selektor dalam daftar koma. Pencocokan memakai keanggotaan
     * himpunan, bukan awalan: salah ketik seperti text-thh! tidak lolos.
     *
     * @return array<string, true>
     */
    private function kelasDiArtefak(string $css): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/([^{};]+)\{/', $css, $pembuka);

        $himpunan = [];
        foreach ($pembuka[1] as $daftar) {
            $daftar = trim($daftar);
            if ($daftar === '' || $daftar[0] === '@') {
                continue;
            }
            foreach ($this->pecahSelektor($daftar) as $selektor) {
                if (preg_match('/^\.((?:\\\\.|[A-Za-z0-9_-])+)/s', $selektor, $k)) {
                    $himpunan[preg_replace('/\\\\(.)/s', '$1', $k[1])] = true;
                }
            }
        }

        return $himpunan;
    }

    /**
     * Token tw:… yang tertulis UTUH di sebuah teks sumber (kelas dinamis tidak ikut:
     * token yang berakhir "-" atau ":" dibuang karena Tailwind pun tak menghasilkannya).
     * Token berakhir di pemisah sebenarnya (spasi, kutip, backtick, kurung kurawal, "<", ";"),
     * sehingga karakter varian arbitrer & , = * > + ~ ( ) [ ] # % ! . / : @ ikut dalam token
     * (mis. tw:min-sm:[&_th]:text-th!).
     *
     * @return list<string>
     */
    private function tokenTw(string $teks): array
    {
        preg_match_all('/(?<![A-Za-z0-9_-])tw:[^\s"\'`{}<;]+/', $teks, $cocok);

        return array_values(array_unique(array_filter(
            $cocok[0],
            fn (string $t) => ! str_ends_with($t, '-') && ! str_ends_with($t, ':')
        )));
    }

    /** @return list<string> daftar berkas (path absolut) dengan akhiran tertentu di bawah sebuah folder. */
    private function berkasDi(string $folder, string $akhiran): array
    {
        $dir = $this->akar($folder);
        if (! is_dir($dir)) {
            return [];
        }

        $hasil = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $berkas) {
            if ($berkas->isFile() && str_ends_with($berkas->getFilename(), $akhiran)) {
                $hasil[] = $berkas->getPathname();
            }
        }
        sort($hasil);

        return $hasil;
    }

    public function test_hasil_build_ada_dan_di_bawah_anggaran_ukuran(): void
    {
        $css = $this->baca('public/css/tesys-tw.css');

        // Artefak boleh sangat kecil (belum ada kelas tw: terpakai), tetapi tidak boleh kosong.
        $this->assertNotSame('', trim($css));
        $this->assertLessThanOrEqual(self::BATAS_UKURAN, strlen($css), 'tesys-tw.css melewati anggaran 50 KB.');
        $this->assertStringContainsString('tailwindcss v4.3.3', $css, 'Versi Tailwind di banner berubah dari yang dipatok.');
    }

    public function test_semua_selektor_berprefix_tw_dan_tanpa_reset_global(): void
    {
        // Banner /*! … */ dibuang dulu supaya tidak ikut terbaca sebagai pembuka blok pertama.
        $css = $this->bacaTanpaKomentar('public/css/tesys-tw.css');

        // Ambil setiap pembuka blok: teks sebelum "{" sejak "}", "{" atau ";" terakhir.
        preg_match_all('/([^{};]+)\{/', $css, $cocok);

        $pengecualian = [];
        foreach ($cocok[1] as $pembuka) {
            $pembuka = trim($pembuka);
            if ($pembuka === '' || $pembuka[0] === '@') {
                continue; // @layer / @media / @supports / @property
            }
            if ($pembuka === '*,:before,:after,::backdrop') {
                $pengecualian[] = $pembuka; // dinilai di bawah (cadangan --tw-*)

                continue;
            }
            // SETIAP selektor dalam daftar koma harus ber-prefix .tw\: (bukan hanya yang pertama).
            foreach ($this->pecahSelektor($pembuka) as $selektor) {
                if (! str_starts_with($selektor, '.tw\\:')) {
                    $pengecualian[] = $selektor;
                }
            }
        }

        // Satu-satunya pengecualian yang diizinkan: cadangan @property untuk peramban lama,
        // di dalam @layer properties + @supports; hanya mendeklarasikan --tw-*. Blok ini hanya
        // muncul bila ada utilitas yang memakai --tw-* (artefak yang nyaris kosong tidak memilikinya).
        $this->assertEqualsCanonicalizing(
            [],
            array_diff($pengecualian, ['*,:before,:after,::backdrop']),
            'Ada selektor di luar .tw\\: selain cadangan --tw-*.'
        );
        $this->assertLessThanOrEqual(1, count($pengecualian), 'Blok cadangan universal --tw-* muncul lebih dari sekali.');

        if (preg_match('/@layer properties\{@supports[^{]*\{\*,:before,:after,::backdrop\{([^}]*)\}/', $css, $blok)) {
            foreach (array_filter(explode(';', $blok[1])) as $deklarasi) {
                $this->assertStringStartsWith('--tw-', $deklarasi, 'Cadangan universal hanya boleh mengisi --tw-*.');
            }
        } else {
            $this->assertSame([], $pengecualian, 'Selektor universal ada tetapi bukan blok cadangan @layer properties.');
        }
    }

    public function test_tanpa_palet_bawaan_dan_tanpa_hex_baru(): void
    {
        $css = $this->baca('public/css/tesys-tw.css');

        $this->assertStringNotContainsString(':root', $css);
        $this->assertStringNotContainsString(':host', $css);
        $this->assertStringNotContainsString('oklch(', $css, 'Palet warna bawaan Tailwind ikut terbawa.');
        $this->assertStringNotContainsString('--tw-color-', $css);
        $this->assertStringNotContainsString('-webkit-text-size-adjust', $css, 'Preflight ikut terbawa.');

        preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $hex);
        // #0000 / #fff = nilai internal Tailwind untuk bayangan/cincin kosong (bukan warna tampilan).
        $this->assertSame([], array_values(array_diff(array_unique($hex[0]), ['#0000', '#fff'])), 'Ada hex warna baru di hasil build.');
    }

    public function test_artefak_produksi_bebas_safelist_dan_kelas_terlarang(): void
    {
        $css = $this->baca('public/css/tesys-tw.css');

        $this->assertDoesNotMatchRegularExpression(
            '/\\\\:('.self::KELAS_TERLARANG.')(?![\w-])/',
            $css,
            'Artefak produksi memuat kelas satuan viewport yang dilarang (*-screen / h-dvh).'
        );

        // Penanda safelist uji hanya diperiksa bila tidak dipakai sungguhan di sumber terpindai.
        $sumber = '';
        foreach ($this->berkasDi('resources/views', '.php') as $berkas) {
            $sumber .= file_get_contents($berkas);
        }
        foreach (glob($this->akar('public/js/tesys-*.js')) as $berkas) {
            $sumber .= file_get_contents($berkas);
        }

        $ada = $this->kelasDiArtefak($css);
        $diperiksa = 0;
        foreach (self::PENANDA_SAFELIST as $token) {
            if (preg_match('/'.preg_quote($token, '/').'(?![\w-])/', $sumber)) {
                continue; // dipakai sungguhan di sumber; wajar ada di artefak
            }
            $diperiksa++;
            $this->assertArrayNotHasKey($token, $ada, "Kelas safelist uji {$token} bocor ke artefak produksi.");
        }
        if ($diperiksa === 0) {
            $this->markTestSkipped('Semua penanda safelist uji ternyata dipakai di sumber terpindai.');
        }
    }

    public function test_masukan_produksi_menolak_preflight_dan_memakai_prefix(): void
    {
        $in = $this->bacaTanpaKomentar('resources/tailwind/tesys-tw.css');

        $this->assertStringContainsString('tailwindcss/theme.css', $in);
        $this->assertStringContainsString('tailwindcss/utilities.css', $in);
        $this->assertStringNotContainsString('preflight', $in);
        $this->assertDoesNotMatchRegularExpression('/@import\s+"tailwindcss"/', $in, 'Impor penuh membawa preflight.');
        $this->assertSame(2, preg_match_all('/prefix\(tw\)/', $in), 'Kedua impor harus ber-prefix(tw).');
        $this->assertSame(2, preg_match_all('/source\(none\)/', $in), 'Pemindaian otomatis harus mati (daftar putih @source).');
        $this->assertStringContainsString('--*: initial;', $in);
        $this->assertStringContainsString('@source "../views";', $in);
        $this->assertStringContainsString('@source "../../public/js/tesys-*.js";', $in);

        // Safelist hanya milik masukan uji.
        $this->assertDoesNotMatchRegularExpression('/@source\s+inline\s*\(/', $in, 'Masukan produksi tidak boleh memuat @source inline (safelist).');
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])('.self::KELAS_TERLARANG.')(?![\w-])/', $in);
    }

    public function test_masukan_uji_mengimpor_produksi_dan_memuat_safelist(): void
    {
        $uji = $this->bacaTanpaKomentar('resources/tailwind/tesys-tw.uji.css');

        $this->assertStringContainsString('@import "./tesys-tw.css";', $uji, 'Masukan uji harus mengimpor masukan produksi apa adanya.');
        $this->assertMatchesRegularExpression('/@source\s+inline\s*\(/', $uji, 'Masukan uji harus memuat safelist.');
        $this->assertStringContainsString('h-screen', $uji, 'Safelist uji memuat satuan viewport untuk uji zoom (hanya di artefak uji).');

        // Artefak uji tidak boleh pernah berada di public/.
        $this->assertSame([], glob($this->akar('public/css/*.uji.css')) ?: [], 'Artefak uji ditemukan di public/css.');
        $this->assertFileDoesNotExist($this->akar('public/css/tesys-tw.uji.css'));
    }

    public function test_pemetaan_token_menunjuk_token_yang_ada(): void
    {
        $in = $this->bacaTanpaKomentar('resources/tailwind/tesys-tw.css');
        preg_match('/@theme inline\s*\{(.*?)\n\}/s', $in, $tema);
        $this->assertNotEmpty($tema, 'Blok @theme inline tidak ditemukan.');

        preg_match_all('/var\(--([a-z0-9-]+)\)/', $tema[1], $rujukan);
        $dirujuk = array_unique($rujukan[1]);
        $this->assertNotEmpty($dirujuk);

        $sumber = '';
        foreach (glob($this->akar('public/css/tesys-ui*.css')) as $berkas) {
            $sumber .= file_get_contents($berkas);
        }

        foreach ($dirujuk as $token) {
            $this->assertMatchesRegularExpression(
                '/--'.preg_quote($token, '/').'\s*:/',
                $sumber,
                "Token --{$token} dirujuk tesys-tw tetapi tidak didefinisikan di public/css/tesys-ui*.css."
            );
        }
    }

    public function test_kelas_tw_di_komponen_dan_js_ada_di_artefak_produksi(): void
    {
        // Kondisional: aktif setelah ada komponen resources/views/components/tw/, view Feedback (pilot F3), view Cost Request (M1) atau JS yang memakai tw:.
        $sumber = [];
        foreach ($this->berkasDi('resources/views/components/tw', '.blade.php') as $berkas) {
            $sumber[$berkas] = file_get_contents($berkas);
        }
        foreach (glob($this->akar('public/js/tesys-*.js')) as $berkas) {
            $sumber[$berkas] = file_get_contents($berkas);
        }
        foreach ($this->berkasDi('resources/views/feedback', '.blade.php') as $berkas) {
            $sumber[$berkas] = file_get_contents($berkas);
        }
        foreach ($this->berkasDi('resources/views/budget-request', '.blade.php') as $berkas) {
            $sumber[$berkas] = file_get_contents($berkas);
        }

        $token = [];
        foreach ($sumber as $berkas => $teks) {
            foreach ($this->tokenTw($teks) as $t) {
                $token[$t] = basename($berkas);
            }
        }
        if ($token === []) {
            $this->markTestSkipped('Belum ada kelas tw: tertulis utuh di komponen/JS; artefak produksi memang boleh nyaris kosong.');
        }

        // Keanggotaan himpunan nama kelas UTUH (bukan awalan): salah ketik gagal.
        $ada = $this->kelasDiArtefak($this->baca('public/css/tesys-tw.css'));
        foreach ($token as $t => $asal) {
            $this->assertArrayHasKey(
                $t,
                $ada,
                "Kelas {$t} (ditulis di {$asal}) tidak ada di public/css/tesys-tw.css — bangun ulang dengan scripts/build-tailwind.ps1 atau perbaiki penulisannya."
            );
        }
    }

    public function test_pencocokan_kelas_artefak_utuh_dan_menjangkau_seluruh_daftar_selektor(): void
    {
        // CSS tiruan berformat sama dengan keluaran Tailwind (selektor di-escape, daftar koma).
        $css = '@layer utilities{'
            .'.tw\:min-sm\:\[\&\:not\(\.dataTable\)_td\]\:px-cell-x\!:not(.dataTable) td,.tw\:min-sm\:\[\&\:not\(\.dataTable\)_th\]\:px-cell-x\!:not(.dataTable) th{padding-inline:1px!important}'
            .'.tw\:min-sm\:\[\&_th\]\:text-th\! th{font-size:1px!important}'
            .'.tw\:w-1\/2{width:50%}'
            .'}';
        $ada = $this->kelasDiArtefak($css);

        // Kelas di bagian KEDUA daftar koma ikut terbaca.
        $this->assertArrayHasKey('tw:min-sm:[&:not(.dataTable)_td]:px-cell-x!', $ada);
        $this->assertArrayHasKey('tw:min-sm:[&:not(.dataTable)_th]:px-cell-x!', $ada);
        $this->assertArrayHasKey('tw:min-sm:[&_th]:text-th!', $ada);
        $this->assertArrayHasKey('tw:w-1/2', $ada);
        // Salah ketik dan awalan terpotong GAGAL.
        $this->assertArrayNotHasKey('tw:min-sm:[&_th]:text-thh!', $ada);
        $this->assertArrayNotHasKey('tw:min-sm:[&_th]:text-th', $ada);
        $this->assertArrayNotHasKey('tw:min-sm:[', $ada);

        // Token varian arbitrer tertangkap utuh sampai pemisah sebenarnya.
        $this->assertSame(
            ['tw:min-sm:[&_th]:text-th!', 'tw:min-sm:[&>x,&_y]:p-1'],
            $this->tokenTw('class="tw:min-sm:[&_th]:text-th! tw:min-sm:[&>x,&_y]:p-1" dinamis tw:min-'),
        );
    }

    public function test_versi_aset_tidak_memeriksa_keberadaan_berkas(): void
    {
        // AC-7: berkas CSS yang hilang harus berupa satu 404, bukan layar putih.
        // Itu hanya benar selama AssetVersion::url() tidak memanggil filemtime()/file_exists().
        $sumber = $this->baca('app/Support/AssetVersion.php');
        $kode = preg_replace('#/\*.*?\*/#s', '', $sumber);

        $this->assertStringNotContainsString('filemtime(', $kode);
        $this->assertStringNotContainsString('file_exists(', $kode);
        $this->assertStringContainsString("'?v='", $kode);
    }

    public function test_tesys_tw_hanya_dimuat_di_view_feedback_dan_cost_request_satu_kali_per_halaman_utama(): void
    {
        // Penjaga pilot F3 + M1 + M2 + M3 + M4: hanya resources/views/feedback/ serta halaman utama budget-request/index,
        // budget-request/create, budget-request/edit, dan budget-request/realize yang boleh memuat tesys-tw.css; layout, partial, dan view lain tidak.
        $feedback = str_replace('\\', '/', $this->akar('resources/views/feedback')).'/';
        $costRequestIndex = str_replace('\\', '/', $this->akar('resources/views/budget-request/index.blade.php'));
        $costRequestCreate = str_replace('\\', '/', $this->akar('resources/views/budget-request/create.blade.php'));
        $costRequestEdit = str_replace('\\', '/', $this->akar('resources/views/budget-request/edit.blade.php'));
        $costRequestRealize = str_replace('\\', '/', $this->akar('resources/views/budget-request/realize.blade.php'));
        $dir =new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->akar('resources/views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($dir as $berkas) {
            if (! $berkas->isFile() || ! str_ends_with($berkas->getFilename(), '.php')) {
                continue;
            }
            $jalur = str_replace('\\', '/', $berkas->getPathname());
            $jumlah = substr_count(file_get_contents($berkas->getPathname()), 'tesys-tw');
            if (str_starts_with($jalur, $feedback) || $jalur === $costRequestIndex || $jalur === $costRequestCreate || $jalur === $costRequestEdit || $jalur === $costRequestRealize) {
                continue;
            }
            $this->assertSame(0, $jumlah, 'tesys-tw dimuat di '.$jalur.' (di luar view Feedback, budget-request/index, budget-request/create, budget-request/edit, dan budget-request/realize; hanya itu yang boleh).');
        }

        // Halaman utama Feedback: persis satu pemuatan per halaman (di @push('styles'), bukan di layout).
        foreach (['index', 'create', 'show', 'inbox'] as $halaman) {
            $teks = file_get_contents($this->akar('resources/views/feedback/'.$halaman.'.blade.php'));
            $this->assertSame(1, substr_count($teks, 'tesys-tw'), "feedback/{$halaman}.blade.php harus memuat tesys-tw persis satu kali.");
            $this->assertStringContainsString("@aset('css/tesys-tw.css')", $teks);
        }

        // Partial tidak boleh memuatnya sendiri (akan terduplikasi di tiap halaman yang menyertakannya).
        $this->assertStringNotContainsString('tesys-tw', file_get_contents($this->akar('resources/views/feedback/_disclaimer.blade.php')));

        // Cost Request (M1): index memuatnya persis satu kali; partial tab (di-include/dimuat AJAX) tidak boleh.
        $indeksCr = file_get_contents($this->akar('resources/views/budget-request/index.blade.php'));
        $this->assertSame(1, substr_count($indeksCr, 'tesys-tw'), 'budget-request/index.blade.php harus memuat tesys-tw persis satu kali.');
        $this->assertStringContainsString("@aset('css/tesys-tw.css')", $indeksCr);
        foreach (['_toolbar', '_table', '_rows', '_rows_db'] as $partial) {
            $this->assertStringNotContainsString('tesys-tw', file_get_contents($this->akar('resources/views/budget-request/'.$partial.'.blade.php')), "budget-request/{$partial} tidak boleh memuat tesys-tw sendiri.");
        }

        // Cost Request (M2): create memuatnya persis satu kali, lewat @aset (versi aset mengikuti AssetVersion).
        $buatCr = file_get_contents($this->akar('resources/views/budget-request/create.blade.php'));
        $this->assertSame(1, substr_count($buatCr, 'tesys-tw'), 'budget-request/create.blade.php harus memuat tesys-tw persis satu kali.');
        $this->assertStringContainsString("@aset('css/tesys-tw.css')", $buatCr);

        // Cost Request (M3): edit memuatnya persis satu kali, lewat @aset (sama dengan create).
        $ubahCr = file_get_contents($this->akar('resources/views/budget-request/edit.blade.php'));
        $this->assertSame(1, substr_count($ubahCr, 'tesys-tw'), 'budget-request/edit.blade.php harus memuat tesys-tw persis satu kali.');
        $this->assertStringContainsString("@aset('css/tesys-tw.css')", $ubahCr);

        // Cost Request (M4): realize memuatnya persis satu kali, lewat @aset; partial baris (_realize_row, di-include
        // dan dipakai sebagai <template>) tidak boleh memuatnya sendiri.
        $realisasiCr = file_get_contents($this->akar('resources/views/budget-request/realize.blade.php'));
        $this->assertSame(1, substr_count($realisasiCr, 'tesys-tw'), 'budget-request/realize.blade.php harus memuat tesys-tw persis satu kali.');
        $this->assertStringContainsString("@aset('css/tesys-tw.css')", $realisasiCr);
        $this->assertStringNotContainsString('tesys-tw', file_get_contents($this->akar('resources/views/budget-request/_realize_row.blade.php')), 'budget-request/_realize_row tidak boleh memuat tesys-tw sendiri.');

        $this->assertStringContainsString('/tools/tailwind/', $this->baca('.gitignore'));
    }

    public function test_tabel_cost_request_m5a_hanya_varian_lebar_dan_kelas_bang_sesuai_daftar_terukur(): void
    {
        // M5a: gaya tabel Index Cost Request (th/td/tfoot, kolom beku, baris NOB) = SATU string kelas di <table>
        // (_table.blade.php, $gayaTabel), bukan kelas per sel. Dua hal dijaga:
        //  1. semua kelasnya varian lebar (tw:min-sm:), sehingga mode kartu mobile (<= 640px) tidak tersentuh;
        //  2. kelas ber-! persis daftar terukur di docs/ui/tailwind-components.md (tidak boleh menumpuk diam-diam).
        $tabel = $this->baca('resources/views/budget-request/_table.blade.php');
        $this->assertSame(1, preg_match('/\$gayaTabel\s*=\s*implode\(\' \', \[(.*?)\]\);/s', $tabel, $blok), '$gayaTabel tidak ditemukan di _table.blade.php.');

        $token = $this->tokenTw($blok[1]);
        $this->assertNotSame([], $token);
        foreach ($token as $t) {
            $this->assertStringStartsWith('tw:min-sm:', $t, "Kelas {$t} di \$gayaTabel bukan varian lebar (min-sm): mode kartu mobile tidak boleh disentuh M5a.");
        }

        $bang = array_values(array_filter($token, fn (string $t) => str_ends_with($t, '!')));
        sort($bang);
        $diizinkan = [
            'tw:min-sm:[&_:is(th,td).cr-col-check]:px-0!',
            'tw:min-sm:[&_td:not(.cr-col-check)]:px-2!',
            'tw:min-sm:[&_tfoot_th:not(.cr-foot-label)]:text-text!',
            'tw:min-sm:[&_tfoot_th]:text-data!',
            'tw:min-sm:[&_th:is(.cr-col-check,.cr-col-code,.action-column)]:sticky!',
            'tw:min-sm:[&_thead_th:not(.cr-col-check)]:pl-2!',
        ];
        sort($diizinkan);
        $this->assertSame($diizinkan, $bang, 'Kelas ber-! di tabel Cost Request berubah: ukur dulu (lihat docs/ui/tailwind-components.md) lalu perbarui daftar ini.');

        // Lebar minimum Description 17rem hanya di jendela >= 1440px; di bawahnya hanya lantai 9rem
        // (17rem di 1366px membuat Status CA tertutup kolom Action beku pada gulir 0).
        $this->assertContains('tw:min-sm:min-[1440px]:[&_td.cr-col-desc]:min-w-[17rem]', $token);
        $this->assertNotContains('tw:min-sm:[&_td.cr-col-desc]:min-w-[17rem]', $token);
        $this->assertContains('tw:min-sm:[&_td.cr-col-desc]:min-w-[9rem]', $token);

        // Kelas hook yang dipakai JS/CSS legacy tetap ada di markup tabel dan baris.
        foreach (['cr-tbl', 'cr-col-code', 'cr-col-desc', 'action-column', 'cr-col-check', 'cr-check-all', 'cr-foot-label'] as $hook) {
            $this->assertStringContainsString($hook, $tabel, "Hook {$hook} hilang dari _table.blade.php.");
        }
        $this->assertStringContainsString('cr-check-row', $this->baca('resources/views/budget-request/_rows.blade.php'));
    }

    public function test_kartu_mobile_cost_request_m5b_varian_sempit_kelas_bang_terukur_dan_label_server(): void
    {
        // M5b: mode kartu mobile tabel Index Cost Request = SATU string kelas di <table> ($gayaKartu), bukan kelas per sel.
        //  1. kartu lama tesys-table.js dimatikan (data-ts-card="off");
        //  2. semua kelasnya varian sempit (tw:max-sm:), kecuali 4 kelas min-sm yang mengembalikan nilai M5a
        //     setelah aturan "kolom pertama beku" datatable.css ikut berlaku karena data-ts-card="off";
        //  3. kelas ber-! persis daftar terukur di docs/ui/tailwind-components.md;
        //  4. label field ditulis server (data-lbl), bukan data-label yang dihapus tesys-table.js.
        $tabel = $this->baca('resources/views/budget-request/_table.blade.php');
        $this->assertStringContainsString('data-ts-card="off"', $tabel);
        $this->assertSame(1, preg_match('/\$gayaKartu\s*=\s*implode\(\' \', \[(.*?)\]\);/s', $tabel, $blok), '$gayaKartu tidak ditemukan di _table.blade.php.');

        $token = $this->tokenTw($blok[1]);
        $this->assertNotSame([], $token);
        $pemulihLebar = [
            'tw:min-sm:[&>thead>tr>th:first-child,&>tbody>tr>td:first-child:not([colspan])]:[z-index:var(--crk-z,3)]!',
            'tw:min-sm:[&>thead>tr>th]:[--crk-z:4]',
            'tw:min-sm:[&>tbody>tr:not(:has(:checked))>td:first-child:not([colspan])]:[box-shadow:var(--crk-sh,none)]!',
            'tw:min-sm:[&_td.cr-col-code,&>tbody>tr:is(.row-nob,.row-nob-item)>td:first-child]:[--crk-sh:var(--tw-inset-shadow),var(--tw-inset-ring-shadow),var(--tw-ring-offset-shadow),var(--tw-ring-shadow),var(--tw-shadow)]',
        ];
        foreach ($token as $t) {
            if (in_array($t, $pemulihLebar, true)) {
                continue;
            }
            $this->assertStringStartsWith('tw:max-sm:', $t, "Kelas {$t} di \$gayaKartu bukan varian sempit (max-sm): desktop tidak boleh berubah.");
        }

        $bang = array_values(array_filter($token, fn (string $t) => str_ends_with($t, '!')));
        sort($bang);
        $diizinkan = [
            'tw:max-sm:[&>:is(tbody,tfoot)>tr>*]:[border-width:var(--crk-b,0_0_1px)]!',
            'tw:max-sm:[&>:is(tbody,tfoot)>tr>*]:[padding:var(--crk-p,6px_0)]!',
            'tw:max-sm:[&_td.cr-col-check]:shadow-none!',
            'tw:max-sm:[&_th[data-lbl]]:text-text!',
            'tw:min-sm:[&>tbody>tr:not(:has(:checked))>td:first-child:not([colspan])]:[box-shadow:var(--crk-sh,none)]!',
            'tw:min-sm:[&>thead>tr>th:first-child,&>tbody>tr>td:first-child:not([colspan])]:[z-index:var(--crk-z,3)]!',
        ];
        sort($diizinkan);
        $this->assertSame($diizinkan, $bang, 'Kelas ber-! kartu mobile Cost Request berubah: ukur dulu (lihat docs/ui/tailwind-components.md) lalu perbarui daftar ini.');

        // Label field dari server: tab CA/CP dan DB, plus penanda status; tfoot berlabel untuk blok ringkasan.
        $baris = $this->baca('resources/views/budget-request/_rows.blade.php');
        $barisDb = $this->baca('resources/views/budget-request/_rows_db.blade.php');
        foreach (['Description', 'Tanggal', 'Tanggal Paid', 'Requestor', 'PIC', 'Request', 'Settlement', 'Refund', 'Expense Claim'] as $label) {
            $this->assertStringContainsString('data-lbl="'.$label.'"', $baris, "Label {$label} hilang dari _rows.blade.php.");
        }
        foreach (['Description', 'Jenis', 'Tanggal', 'Tanggal Paid', 'Requestor', 'PIC', 'Cost', 'Close Date'] as $label) {
            $this->assertStringContainsString('data-lbl="'.$label.'"', $barisDb, "Label {$label} hilang dari _rows_db.blade.php.");
        }
        $this->assertStringContainsString('data-card="status"', $baris);
        $this->assertStringContainsString('data-card="status"', $barisDb);
        $this->assertStringNotContainsString(' data-label="', $baris.$barisDb, 'data-label pada sel dihapus tesys-table.js: pakai data-lbl.');
        // Requestor/PIC tidak boleh ber-text-nowrap (BS !important): nama panjang di kartu 390px harus membungkus, bukan meluap.
        $this->assertContains('tw:max-sm:[&_td[data-lbl]]:[overflow-wrap:anywhere]', $token);
        $this->assertStringNotContainsString('class="text-nowrap" data-lbl="Requestor"', $baris.$barisDb);
        $this->assertStringNotContainsString('class="text-nowrap" data-lbl="PIC"', $baris.$barisDb);
        $this->assertSame(6, substr_count($tabel, ' data-lbl="'), 'tfoot Cost Request: 6 sel total berlabel (CA 4 + CP 1 + DB 1).');
    }
}

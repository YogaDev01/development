<?php

/**
 * Render galeri komponen Tailwind (x-tw.*) ke HTML statis untuk pengukuran browser.
 *
 * Berdiri sendiri: memakai paket Illuminate dari vendor/ (Container, Filesystem,
 * Events, View, BladeCompiler) lewat vendor/autoload.php — TANPA boot aplikasi
 * Laravel, TANPA database, TANPA membaca .env/config, TANPA artisan.
 *
 * Keluaran (folder ter-ignore git, bukan public/):
 *   tools/tailwind/uji/galeri-tw.html        legacy urut layout + tesys-tw.css produksi di AKHIR
 *   tools/tailwind/uji/galeri-baseline.html  legacy urut layout, tanpa tesys-tw.css
 *   tools/tailwind/uji/galeri-tw-awal.html   tesys-tw.css dimuat SEBELUM tesys-ui.css
 *   tools/tailwind/uji/galeri.html           salinan galeri-tw.html (nama bawaan)
 * Cache Blade terkompilasi: tools/tailwind/uji/.cache-blade/
 *
 * Jalankan (dari akar proyek):
 *   <php.exe> scripts/tailwind-harness/render.php
 * Kode keluar 0 = semua varian terender tanpa galat/peringatan.
 */

declare(strict_types=1);

// Hanya untuk CLI; jangan pernah terlayani lewat web server.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo "Skrip ini hanya untuk CLI.\n";
    exit(1);
}

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

// Peringatan/notice/deprecation dianggap galat: render yang "bersih" wajib bersih.
set_error_handler(static function (int $no, string $pesan, string $berkas, int $baris): bool {
    throw new ErrorException($pesan, 0, $no, $berkas, $baris);
});

$akar = dirname(__DIR__, 2);
$autoload = $akar . '/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "vendor/autoload.php tidak ada; jalankan dari salinan proyek yang memiliki vendor/.\n");
    exit(1);
}
require $autoload;

$keluaran = $akar . '/tools/tailwind/uji';
$cache = $keluaran . '/.cache-blade';

// Jangan pernah menulis ke bawah public/ (artefak uji tidak boleh dipublikasikan).
$realKeluaran = str_replace('\\', '/', $keluaran);
if (str_contains($realKeluaran . '/', '/public/')) {
    fwrite(STDERR, "Folder keluaran berada di bawah public/: ditolak.\n");
    exit(1);
}
foreach ([$keluaran, $cache] as $dir) {
    if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
        fwrite(STDERR, "Gagal membuat folder {$dir}\n");
        exit(1);
    }
}

// Cache terkompilasi lama dibuang supaya sintaks Blade selalu dikompilasi ulang.
$files = new Filesystem();
foreach (glob($cache . '/*.php') ?: [] as $lama) {
    unlink($lama);
}

$container = new Container();
Container::setInstance($container);

$events = new Dispatcher($container);
$blade = new BladeCompiler($files, $cache);

$resolver = new EngineResolver();
$resolver->register('blade', static fn () => new CompilerEngine($blade, $files));
$resolver->register('php', static fn () => new PhpEngine($files));

// Komponen anonim <x-tw.*> -> resources/views/components/tw/*.blade.php lewat
// view finder (components.tw.<nama>); galeri.blade.php ada di folder skrip ini.
$finder = new FileViewFinder($files, [__DIR__, $akar . '/resources/views']);
$factory = new Factory($resolver, $finder, $events);
$factory->setContainer($container);

$container->instance('view', $factory);
$container->instance('blade.compiler', $blade);
$container->instance(Illuminate\Contracts\View\Factory::class, $factory);
$container->instance(Factory::class, $factory);
// ComponentTagCompiler::guessClassName() meminta namespace aplikasi untuk mencari
// kelas komponen berbasis class; stub ini cukup (tidak ada kelas yang cocok, jadi
// komponen jatuh ke komponen anonim). Bukan aplikasi Laravel.
$container->instance(Illuminate\Contracts\Foundation\Application::class, new class {
    public function getNamespace(): string
    {
        return 'Harness\\';
    }
});

// Varian => [berkas keluaran, jalur relatif folder public/ dari berkas keluaran].
$rel = '../../../public';
$varian = [
    'tw'       => 'galeri-tw.html',
    'baseline' => 'galeri-baseline.html',
    'awal'     => 'galeri-tw-awal.html',
];

$hasil = [];
foreach ($varian as $nama => $berkas) {
    $html = $factory->make('galeri', ['varian' => $nama, 'rel' => $rel])->render();

    if (str_contains($html, '<x-') || str_contains($html, '@verbatim') || str_contains($html, '{{')) {
        throw new RuntimeException("Sisa sintaks Blade/komponen yang tidak terkompilasi pada varian {$nama}.");
    }

    file_put_contents($keluaran . '/' . $berkas, $html);
    $hasil[] = sprintf('%-22s %7d B', $berkas, strlen($html));

    if ($nama === 'tw') {
        file_put_contents($keluaran . '/galeri.html', $html);
        $hasil[] = sprintf('%-22s %7d B', 'galeri.html', strlen($html));
    }
}

echo implode(PHP_EOL, $hasil), PHP_EOL, "OK", PHP_EOL;

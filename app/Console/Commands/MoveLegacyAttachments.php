<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pindahkan lampiran lama dari disk "public" (storage/app/public) ke disk
 * privat "secure" (storage/app/private).
 *
 * Sejak lampiran dilayani lewat SecureFileController, semua unggahan baru
 * masuk ke disk privat. Berkas yang diunggah sebelum itu masih tinggal di
 * disk public dengan path yang sama persis di basis data, sehingga cukup
 * dipindah apa adanya — tidak ada kolom yang perlu diubah.
 *
 * Aman diulang: berkas yang sudah ada di disk privat dilewati, dan tanpa
 * --jalankan perintah ini hanya melaporkan apa yang AKAN dipindah.
 */
class MoveLegacyAttachments extends Command
{
    protected $signature = 'files:pindah-lampiran-lama {--jalankan : Benar-benar memindahkan (tanpa ini hanya laporan)}';

    protected $description = 'Pindahkan lampiran lama dari storage/app/public ke storage/app/private';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $secure = Storage::disk('secure');
        $jalankan = (bool) $this->option('jalankan');

        $dipindah = 0;
        $sudahAda = 0;
        $gagal    = 0;
        $perFolder = [];

        foreach ($public->allFiles() as $path) {
            // .gitignore dan berkas tersembunyi lain bukan lampiran.
            if (str_starts_with(basename($path), '.')) {
                continue;
            }

            $folder = explode('/', $path)[0];

            if ($secure->exists($path)) {
                $sudahAda++;
                continue;
            }

            $perFolder[$folder] = ($perFolder[$folder] ?? 0) + 1;

            if (! $jalankan) {
                $dipindah++;
                continue;
            }

            // Salin dulu, hapus sumbernya hanya bila salinannya utuh.
            $stream = $public->readStream($path);
            $ok = $stream && $secure->writeStream($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($ok && $secure->size($path) === $public->size($path)) {
                $public->delete($path);
                $dipindah++;
            } else {
                $gagal++;
                $this->error('Gagal: ' . $path);
            }
        }

        foreach ($perFolder as $folder => $jumlah) {
            $this->line(sprintf('  %-40s %d berkas', $folder, $jumlah));
        }

        $this->info(($jalankan ? 'Dipindah: ' : 'Akan dipindah: ') . $dipindah
            . " | sudah ada di privat (dilewati): {$sudahAda} | gagal: {$gagal}");

        if (! $jalankan && $dipindah > 0) {
            $this->comment('Jalankan ulang dengan --jalankan untuk memindahkan.');
        }

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}

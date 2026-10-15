<?php

namespace App\Support;

use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Riwayat satu Invoice sebagai daftar peristiwa siap tampil.
 *
 * KENAPA DIPINDAH KE SINI
 *
 * Sebelumnya ±95 baris PHP ini tinggal di dalam
 * resources/views/invoice-cash-receipt/show.blade.php: membangun koleksi,
 * membaca relasi, memformat mata uang, DAN menyusun potongan HTML sebagai
 * string yang lalu dikeluarkan dengan {!! !!}.
 *
 * Semuanya sudah di-escape dengan benar, jadi ini bukan lubang keamanan. Tiga
 * hal lain yang jadi masalah:
 *
 *   1. Tidak bisa diuji tanpa merender view. Urutan peristiwa dan perhitungan
 *      PPh adalah logika bisnis; ia layak punya uji sendiri.
 *   2. Relasi dibaca di dalam view ($invoice->cashReceipts, $cr->sourceFund,
 *      $cr->inputBy). Kalau eager load di controller berubah, halaman ini
 *      diam-diam menjadi N+1 tanpa ada yang menyadarinya.
 *   3. HTML sebagai string PHP melewati seluruh pemeriksaan Blade, dan
 *      membuat satu-satunya {!! !!} yang tersisa di halaman ini ada karena
 *      keterbatasan bentuk, bukan kebutuhan.
 *
 * Yang dikembalikan sekarang adalah DATA. Tidak ada HTML di kelas ini, jadi
 * view bisa merendernya dengan Blade biasa dan {!! !!} tidak diperlukan lagi.
 *
 * Bentuk tiap peristiwa:
 *   badge  string  label lencana, mis. 'PAYMENT + SETTLE'
 *   color  string  varian warna Bootstrap
 *   actor  string  nama pelaku
 *   sort   Carbon  dipakai mengurutkan
 *   label  string  tanggal terformat
 *   lines  array   baris keterangan tambahan; tiap baris:
 *                    ['teks' => string]                      -> teks biasa
 *                    ['teks' => string, 'tautan' => [url,label]] -> ada tautan
 */
final class InvoiceTimeline
{
    /** @return Collection<int, array<string, mixed>> */
    public static function untuk(Invoice $invoice): Collection
    {
        $peristiwa = collect();

        $peristiwa->push(self::baris(
            'DRAFT', 'secondary',
            $invoice->createdBy->name ?? '-',
            $invoice->created_at,
            $invoice->created_at?->format('d/m/Y H:i')
        ));

        /*
        | Tahap workflow yang bentuknya seragam: ada stempel waktu, ada
        | pelakunya, tanpa keterangan tambahan. Didaftar sebagai tabel supaya
        | menambah tahap baru berarti menambah satu baris, bukan menyalin blok
        | if enam kali seperti sebelumnya.
        */
        $tahap = [
            ['submitted_at', 'submittedBy', 'SUBMIT',  'warning'],
            ['verified_at',  'verifiedBy',  'VERIFY',  'info'],
            ['approved_at',  'approvedBy',  'APPROVE', 'primary'],
            ['sent_at',      'sentBy',      'SENT',    'indigo'],
        ];

        /*
        | Catatan & lampiran yang ditulis di Surat Jalan ikut masuk ke log
        | Invoice (permintaan Collection: setiap log yang menyangkut invoice ini
        | harus terbaca dari halaman invoice, tanpa membuka modul Surat Jalan).
        | Log Surat Jalan tidak ditambahkan sebagai peristiwa tersendiri supaya
        | tidak menggandakan SENT/RECEIVED yang sudah dimiliki invoice; yang
        | dipinjam hanya isinya.
        */
        $logSj = self::logSuratJalan($invoice);

        foreach ($tahap as [$kolom, $relasi, $badge, $warna]) {
            if (! $invoice->{$kolom}) {
                continue;
            }

            $peristiwa->push(self::baris(
                $badge, $warna,
                $invoice->{$relasi}->name ?? '-',
                $invoice->{$kolom},
                $invoice->{$kolom}->format('d/m/Y H:i'),
                $logSj[$badge] ?? []
            ));
        }

        // RECEIVED — kalau lewat Surat Jalan, tautkan ke dokumennya.
        if ($invoice->received_at) {
            $dn    = $invoice->deliveryNote;
            $lines = [];

            if ($dn) {
                $lines[] = [
                    'teks'   => 'Via Surat Jalan',
                    'ikon'   => 'fas fa-truck',
                    'tautan' => [
                        'url'   => route('invoice-cash-receipt.delivery-note', [$invoice->id, $dn->id]),
                        'label' => $dn->code,
                        'modal' => true,
                    ],
                ];
            }

            $lines = array_merge($lines, $logSj['RECEIVED'] ?? []);

            $peristiwa->push(self::baris(
                'RECEIVED', 'purple',
                $invoice->receivedBy->name ?? '-',
                $invoice->received_at,
                $invoice->received_at->format('d/m/Y H:i'),
                $lines
            ));
        }

        // Pembayaran — satu peristiwa per Cash Receipt.
        foreach ($invoice->cashReceipts as $cr) {
            $lines = [];

            $lines[] = ['teks' => self::rupiah($cr->amount) . ' diterima'
                . ($cr->sourceFund ? ' via ' . $cr->sourceFund->name : '')];

            if ($cr->withholding_tax > 0) {
                $lines[] = ['teks' => 'PPh Ps.4(2) di-settle: ' . self::rupiah($cr->withholding_tax)];
            }

            if ($cr->notes) {
                $lines[] = ['teks' => $cr->notes];
            }

            $peristiwa->push(self::baris(
                $cr->withholding_tax > 0 ? 'PAYMENT + SETTLE' : 'PAYMENT',
                'success',
                $cr->inputBy->name ?? '-',
                $cr->created_at,
                $cr->received_date
                    ? $cr->received_date->format('d/m/Y')
                    : $cr->created_at->format('d/m/Y'),
                $lines
            ));
        }

        // Komentar bebas dari tombol aksi di daftar invoice.
        foreach ($invoice->comments as $km) {
            $peristiwa->push(self::baris(
                'KOMENTAR', 'light',
                $km->user->name ?? '-',
                $km->created_at,
                $km->created_at?->format('d/m/Y H:i'),
                [['teks' => $km->comment, 'ikon' => 'fas fa-comment']]
            ));
        }

        if ($invoice->cancelled_at) {
            $peristiwa->push(self::baris(
                'CANCELLED', 'dark',
                $invoice->cancelledBy->name ?? '-',
                $invoice->cancelled_at,
                $invoice->cancelled_at->format('d/m/Y H:i'),
                $invoice->cancel_reason ? [['teks' => $invoice->cancel_reason]] : []
            ));
        }

        return $peristiwa->sortBy('sort')->values();
    }

    /**
     * Relasi yang harus di-eager-load pemanggil supaya timeline ini tidak
     * memicu N+1.
     *
     * Disediakan di sini, bukan diingat-ingat di controller: daftar relasi yang
     * dibutuhkan adalah urusan kelas ini, dan menaruhnya di tempat lain berarti
     * menambah peristiwa baru bisa diam-diam menambah query per baris.
     *
     * @return array<int, string>
     */
    public static function relasiDibutuhkan(): array
    {
        return [
            'createdBy', 'submittedBy', 'verifiedBy', 'approvedBy', 'sentBy',
            'receivedBy', 'cancelledBy',
            'deliveryNote', 'deliveryNote.logs.attachments',
            'cashReceipts.sourceFund', 'cashReceipts.inputBy', 'comments.user',
        ];
    }


    /**
     * Catatan & lampiran dari log Surat Jalan, dikelompokkan per tahap invoice
     * yang sepadan ('SENT' / 'RECEIVED').
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function logSuratJalan(Invoice $invoice): array
    {
        $dn = $invoice->deliveryNote;

        if (! $dn) {
            return [];
        }

        $hasil = [];

        foreach ($dn->logs as $log) {
            // CREATED tidak punya padanan di alur invoice, jadi dilewati.
            if (! in_array($log->action, ['SENT', 'RECEIVED'], true)) {
                continue;
            }

            if ($log->notes) {
                $hasil[$log->action][] = ['teks' => $log->notes, 'ikon' => 'fas fa-truck'];
            }

            foreach ($log->attachments as $att) {
                $hasil[$log->action][] = [
                    'teks'   => '',
                    'ikon'   => 'fas fa-paperclip',
                    'tautan' => [
                        'url'   => '/storage/' . $att->file_path,
                        'label' => $att->title ?: 'Lampiran',
                    ],
                ];
            }
        }

        return $hasil;
    }

    /** @param array<int, array<string, mixed>> $lines */
    private static function baris(
        string $badge,
        string $warna,
        string $pelaku,
        $urut,
        ?string $label,
        array $lines = []
    ): array {
        return [
            'badge' => $badge,
            'color' => $warna,
            'actor' => $pelaku,
            'sort'  => $urut,
            'label' => $label ?? '-',
            'lines' => $lines,
        ];
    }

    private static function rupiah($nilai): string
    {
        return number_format((float) ($nilai ?: 0), 0, ',', '.');
    }
}

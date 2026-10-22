<?php

namespace App\Services;

use App\Models\TroubleTicket;
use Illuminate\Support\Collection;

/**
 * Menggambar ringkasan detail Trouble Ticket sebagai PNG.
 *
 * Dipakai saat plan & biaya disetujui: berkasnya dilampirkan sebagai
 * "Lampiran Tambahan / Pendukung" pada Cost Request draft yang terbentuk,
 * yang untuk tipe Cash Advance memang wajib ada.
 *
 * Tata letaknya meniru modal Info tiket — kepala berisi nomor tiket & chip,
 * dua kolom (kiri Identitas/Gangguan/Resolution, kanan Rincian Biaya Pengajuan), label
 * abu-abu, garis tipis antar baris — TANPA bagian SLA (keputusan 20 Okt
 * 2026): SLA ukuran kerja internal, bukan keterangan pengeluaran.
 *
 * Digambar dengan ekstensi GD bawaan PHP; tidak ada paket baru. Teks memakai
 * TTF (lihat fontPath()); tanpa TTF, GD hanya punya font bitmap monospace
 * yang membuat hasilnya jauh dari tampilan layar, jadi berkasnya digambar
 * tanpa dua kolom dan pembaca diberi tahu lewat ukurannya yang lebih kasar.
 */
class TicketDetailImage
{
    private const LEBAR = 1240;

    private const MARGIN = 36;

    /** Awal kolom kanan. */
    private const KANAN = 560;

    private const BARIS = 30;

    /** @var \GdImage */
    private $img;

    private array $warna = [];

    private ?string $font = null;

    private ?string $fontTebal = null;

    /**
     * @param  Collection<int, \App\Models\TroubleTicketRequestItem>  $planCosts  baris biaya pengajuan
     * @return string Isi berkas PNG
     */
    public static function render(TroubleTicket $ticket, Collection $planCosts): string
    {
        return (new self())->gambar($ticket, $planCosts);
    }

    private function gambar(TroubleTicket $t, Collection $planCosts): string
    {
        [$this->font, $this->fontTebal] = $this->fontPath();

        /* Kanvas sengaja dibuat berlebih lalu DIPOTONG seukuran isi di akhir.
           Menghitung tinggi di muka pernah dicoba dan meleset — jarak antar
           bagian tidak seragam, jadi rumusnya memotong tabel biaya. */
        $tinggi = 1400 + $planCosts->count() * self::BARIS;
        $this->img = imagecreatetruecolor(self::LEBAR, $tinggi);

        $this->warna = [
            'bg'      => imagecolorallocate($this->img, 255, 255, 255),
            'teks'    => imagecolorallocate($this->img, 33, 37, 41),
            'label'   => imagecolorallocate($this->img, 108, 117, 125),
            'judul'   => imagecolorallocate($this->img, 33, 37, 41),
            'aksen'   => imagecolorallocate($this->img, 139, 0, 0),
            'garis'   => imagecolorallocate($this->img, 233, 236, 239),
            'chip_bg' => imagecolorallocate($this->img, 243, 244, 246),
        ];

        imagefilledrectangle($this->img, 0, 0, self::LEBAR, $tinggi, $this->warna['bg']);

        $y = $this->kepala($t);

        // Dua kolom digambar terpisah; tinggi akhir = yang paling panjang.
        $kiri  = $this->kolomKiri($t, $y);
        $kanan = $this->kolomKanan($t, $planCosts, $y);

        return $this->selesai(max($kiri, $kanan), $tinggi);
    }

    /*
    |--------------------------------------------------------------------------
    | BAGIAN
    |--------------------------------------------------------------------------
    */

    /** Nomor tiket + chip status/severity/aging, lalu garis pemisah. */
    private function kepala(TroubleTicket $t): int
    {
        $y = self::MARGIN;

        $this->tulis(self::MARGIN, $y, $t->ticket_number, 'teks', 15, true);
        $y += 34;

        $x = self::MARGIN;
        foreach ([$this->statusLabel($t), (string) $t->severity_label, 'Aging ' . $t->aging_days . ' hari'] as $chip) {
            if ($chip === '') {
                continue;
            }

            $x = $this->chip($x, $y, $chip);
        }

        $y += 30;
        imageline($this->img, self::MARGIN, $y, self::LEBAR - self::MARGIN, $y, $this->warna['garis']);

        return $y + 24;
    }

    private function kolomKiri(TroubleTicket $t, int $y): int
    {
        $y = $this->judul(self::MARGIN, $y, 'IDENTITAS');
        $y = $this->baris(self::MARGIN, $y, 'No. TT', $t->ticket_number, true);
        $y = $this->baris(self::MARGIN, $y, 'Site Name', $t->site?->sitename ?? '-');
        $y = $this->baris(self::MARGIN, $y, 'Site ID', $t->site?->tid ?? '-');
        $y = $this->baris(self::MARGIN, $y, 'Customer', $t->customer?->name ?? '-');
        $y = $this->baris(self::MARGIN, $y, 'Date Open', $t->reported_at?->format('d M Y') ?? '-');
        $y = $this->baris(self::MARGIN, $y, 'Dibuat oleh', $t->creator?->name ?? '-');
        $y = $this->baris(self::MARGIN, $y, 'Teknisi', $t->technician?->name ?? '-');
        $y = $this->baris(self::MARGIN, $y, 'Plan visit', $t->visit_site?->format('d M Y') ?? '-');

        $y = $this->judul(self::MARGIN, $y, 'GANGGUAN');
        $y = $this->baris(self::MARGIN, $y, 'Aging', $t->aging_days . ' hari');
        $y = $this->baris(self::MARGIN, $y, 'Severity', (string) $t->severity_label);
        $y = $this->baris(self::MARGIN, $y, 'RCA', $t->rca_label ?: '-');
        $y = $this->baris(self::MARGIN, $y, 'Sub-RCA', $t->sub_rca_label ?: '-');
        $y = $this->baris(self::MARGIN, $y, 'Keterangan', (string) $t->description);

        $y = $this->judul(self::MARGIN, $y, 'RESOLUTION');
        $y = $this->baris(self::MARGIN, $y, 'RCA', $t->rca ?: '-');
        $y = $this->baris(self::MARGIN, $y, 'Sub-RCA', $t->sub_rca ?: '-');
        $y = $this->baris(self::MARGIN, $y, 'Keterangan', $t->resolution_summary ?: '-');
        $y = $this->baris(self::MARGIN, $y, 'Close by', $t->closer?->name ?: '-');

        return $this->baris(self::MARGIN, $y, 'Date closed', $t->closed_at?->format('d M Y') ?: '-');
    }

    /** @param  Collection<int, \App\Models\TroubleTicketRequestItem>  $rows */
    private function kolomKanan(TroubleTicket $t, Collection $rows, int $y): int
    {
        $x = self::KANAN;
        $kananTepi = self::LEBAR - self::MARGIN;

        $y = $this->judul($x, $y, 'RINCIAN BIAYA PENGAJUAN');
        $y = $this->baris($x, $y, 'Budget Plan', $rows->first()?->request?->budgetPlan?->code ?? $t->budgetPlan?->code ?? '-');
        $y += 8;

        /* Kolom tabel: COA, Description, lalu tiga kolom angka rata kanan.
           Jaraknya dihitung dari tepi kanan supaya angka terpanjang
           ("Rp 300.000") tidak pernah menabrak kolom sebelahnya. */
        $kDesc  = $x + 150;
        $kTotal = $kananTepi;
        $kQty   = $kananTepi - 120;
        $kHarga = $kQty - 60;

        $this->tulis($x, $y, 'COA', 'label', 10);
        $this->tulis($kDesc, $y, 'DESCRIPTION', 'label', 10);
        $this->kanan($kHarga, $y, 'HARGA', 'label', 10);
        $this->kanan($kQty, $y, 'QTY', 'label', 10);
        $this->kanan($kTotal, $y, 'TOTAL', 'label', 10);
        $y += 22;

        foreach ($rows as $r) {
            imageline($this->img, $x, $y, $kananTepi, $y, $this->warna['garis']);
            $y += 10;

            $this->tulis($x, $y, $this->potong((string) ($r->account_name ?: '-'), 9), 'teks');
            $this->tulis($kDesc, $y, $this->potong((string) $r->description, 9), 'teks');
            $this->kanan($kHarga, $y, 'Rp ' . number_format((int) $r->amount, 0, ',', '.'), 'teks');
            $this->kanan($kQty, $y, (string) (int) $r->qty, 'teks');
            $this->kanan($kTotal, $y, 'Rp ' . number_format((int) $r->total, 0, ',', '.'), 'teks');
            $y += self::BARIS - 10;
        }

        imageline($this->img, $x, $y, $kananTepi, $y, $this->warna['garis']);
        $y += 14;
        $this->kanan($kTotal - 130, $y, 'TOTAL PENGAJUAN BIAYA', 'label', 10);
        $this->kanan($kTotal, $y, 'Rp ' . number_format((int) $rows->sum('total'), 0, ',', '.'), 'teks', 12, true);

        return $y + self::BARIS;
    }

    /*
    |--------------------------------------------------------------------------
    | PRIMITIF GAMBAR
    |--------------------------------------------------------------------------
    */

    private function judul(int $x, int $y, string $teks): int
    {
        $this->tulis($x, $y, $teks, 'judul', 10, true);

        return $y + 24;
    }

    /** Satu pasang label/nilai beserta garis tipis di bawahnya. */
    private function baris(int $x, int $y, string $label, string $nilai, bool $tebal = false): int
    {
        $lebarKolom = $x === self::MARGIN ? self::KANAN - self::MARGIN - 40 : self::LEBAR - self::MARGIN - $x;

        $this->tulis($x, $y, $label, 'label');
        $this->tulis($x + 150, $y, $this->potong(trim($nilai) ?: '-', $x === self::MARGIN ? 36 : 30), 'teks', 12, $tebal);

        $y += 20;
        imageline($this->img, $x, $y, $x + $lebarKolom, $y, $this->warna['garis']);

        return $y + 10;
    }

    /** Chip abu-abu; mengembalikan x berikutnya. */
    private function chip(int $x, int $y, string $teks): int
    {
        $lebar = $this->lebarTeks($teks, 10) + 22;

        imagefilledrectangle($this->img, $x, $y - 4, $x + $lebar, $y + 20, $this->warna['chip_bg']);
        $this->tulis($x + 11, $y, $teks, 'teks', 10);

        return $x + $lebar + 8;
    }

    /** Teks rata kanan pada batas $x. */
    private function kanan(int $x, int $y, string $teks, string $warna, int $ukuran = 12, bool $tebal = false): void
    {
        $this->tulis($x - $this->lebarTeks($teks, $ukuran, $tebal), $y, $teks, $warna, $ukuran, $tebal);
    }

    private function tulis(int $x, int $y, string $teks, string $warna, int $ukuran = 12, bool $tebal = false): void
    {
        $font = $tebal ? ($this->fontTebal ?? $this->font) : $this->font;

        if ($font !== null) {
            imagettftext($this->img, $ukuran, 0, $x, $y + $ukuran, $this->warna[$warna], $font, $teks);

            return;
        }

        imagestring($this->img, 4, $x, $y, $this->asciiAman($teks), $this->warna[$warna]);
    }

    private function lebarTeks(string $teks, int $ukuran, bool $tebal = false): int
    {
        $font = $tebal ? ($this->fontTebal ?? $this->font) : $this->font;

        if ($font === null) {
            return imagefontwidth(4) * strlen($teks);
        }

        $kotak = imagettfbbox($ukuran, 0, $font, $teks);

        return (int) abs($kotak[2] - $kotak[0]);
    }

    private function potong(string $teks, int $maks): string
    {
        return mb_strimwidth($teks, 0, $maks * 2, '...');
    }

    /** Label status seperti chip di layar ("Plan", "In Progress", ...). */
    private function statusLabel(TroubleTicket $t): string
    {
        return ucwords(strtolower(str_replace('_', ' ', (string) $t->status)));
    }

    /**
     * Font bitmap bawaan GD hanya mengenal ASCII; karakter di luar itu
     * digantikan agar tidak muncul sebagai kotak kosong.
     */
    private function asciiAman(string $teks): string
    {
        $teks = strtr($teks, ['—' => '-', '–' => '-', '·' => '-', '“' => '"', '”' => '"', '’' => "'"]);

        return (string) preg_replace('/[^\x20-\x7E]/', '?', $teks);
    }

    /** Memotong kanvas tepat di bawah isi, lalu mengeluarkan PNG. */
    private function selesai(int $y, int $tinggi): string
    {
        $potong = imagecrop($this->img, [
            'x' => 0,
            'y' => 0,
            'width' => self::LEBAR,
            'height' => min($tinggi, $y + self::MARGIN),
        ]);

        if ($potong !== false) {
            imagedestroy($this->img);
            $this->img = $potong;
        }

        ob_start();
        imagepng($this->img);
        $png = (string) ob_get_clean();

        imagedestroy($this->img);

        return $png;
    }

    /**
     * [font biasa, font tebal] yang dipakai menulis.
     *
     * Urutannya: berkas di resources/fonts (taruh .ttf di sana untuk
     * memastikan hasil yang sama di semua server), lalu font bawaan sistem
     * yang lazim ada — DejaVu/Liberation di Linux, Arial di Windows. Repo ini
     * hanya memuat font IKON (Font Awesome, Summernote) yang tidak bisa
     * dipakai menulis teks, jadi tidak dicari ke sana.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function fontPath(): array
    {
        $biasa = $this->cari([
            resource_path('fonts/regular.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            'C:/Windows/Fonts/arial.ttf',
        ], resource_path('fonts/*.ttf'));

        $tebal = $this->cari([
            resource_path('fonts/bold.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            'C:/Windows/Fonts/arialbd.ttf',
        ]);

        return [$biasa, $tebal ?? $biasa];
    }

    /** Berkas pertama yang ada & terbaca dari daftar, atau dari pola glob. */
    private function cari(array $kandidat, ?string $glob = null): ?string
    {
        foreach ($kandidat as $file) {
            if (is_file($file) && is_readable($file)) {
                return $file;
            }
        }

        foreach ($glob ? (glob($glob) ?: []) : [] as $file) {
            if (is_readable($file)) {
                return $file;
            }
        }

        return null;
    }
}

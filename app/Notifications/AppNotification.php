<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\FonnteChannel;

/**
 * Notifikasi generik dipakai lintas modul (mention, perubahan status
 * workflow, milestone, dll). Dikirim ke channel database (bell icon)
 * dan, jika user punya nomor WhatsApp & Fonnte terkonfigurasi, ke WhatsApp.
 */
class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /*
    | ShouldQueue: pengiriman dilepas ke antrean, tidak lagi menempel pada
    | request yang memicunya. Sebelumnya setiap approval menunggu panggilan
    | HTTP ke Fonnte selesai - per penerima - sehingga satu klik Approve bisa
    | menggantung beberapa detik hanya karena API pihak ketiga sedang lambat,
    | dan ikut gagal kalau API-nya mati.
    |
    | Konsekuensi operasional: worker WAJIB berjalan
    | ("php artisan queue:work", lihat docs/DEPLOYMENT.md). Tanpa worker,
    | notifikasi menumpuk di tabel jobs dan tidak pernah terkirim.
    */

    /*
    | Kapan notifikasi ini dibuat (unix time). Dipakai untuk menahan pesan
    | BASI saat antrean baru diproses jauh setelahnya — mis. 21–23 Sep 2026
    | worker tidak berjalan dan 1.900+ job menumpuk. Job lama yang dibuat
    | sebelum properti ini ada akan ter-unserialize dengan nilai null, dan
    | diperlakukan basi juga.
    */
    public ?int $dibuatPada = null;

    /** Lewat dari ini, WhatsApp tidak dikirim & notifikasi lonceng masuk sebagai sudah dibaca. */
    public const BATAS_BASI_DETIK = 6 * 3600;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $category = 'general',
        public ?string $whatsappMessage = null,
    ) {
        $this->dibuatPada = time();
    }

    private function basi(): bool
    {
        return $this->dibuatPada === null || time() - $this->dibuatPada > self::BATAS_BASI_DETIK;
    }

    /**
     * Dibaca DatabaseChannel. Notifikasi basi tetap tercatat di riwayat
     * lonceng, tapi tidak menambah angka "belum dibaca".
     */
    public function initialDatabaseReadAtValue($notifiable): ?\Illuminate\Support\Carbon
    {
        return $this->basi() ? now() : null;
    }

    public function via($notifiable): array
    {
        // Preferensi notifikasi per-user (Profil > Settings). Aman-default:
        // kalau notifiable bukan User atau belum pernah menyetel preferensi,
        // wantsNotification() mengembalikan true, jadi perilaku tidak berubah.
        $mauInApp    = ! method_exists($notifiable, 'wantsNotification')
                       || $notifiable->wantsNotification($this->category, 'database');
        $mauWhatsApp = ! method_exists($notifiable, 'wantsNotification')
                       || $notifiable->wantsNotification($this->category, 'whatsapp');

        $channels = [];

        if ($mauInApp) {
            $channels[] = 'database';
        }

        if ($mauWhatsApp
            && config('services.fonnte.enabled')
            && ! blank($notifiable->phone)
            && ! blank(config('services.fonnte.token'))) {
            $channels[] = FonnteChannel::class;
        }

        return $channels;
    }

    public function toArray($notifiable): array
    {
        return [
            'category' => $this->category,
            'title'    => $this->title,
            'message'  => $this->message,
            'url'      => $this->url,
        ];
    }

    public function toFonnte($notifiable): string
    {
        // Pesan kosong = FonnteChannel tidak mengirim apa pun. WhatsApp tentang
        // approval berhari-hari lalu hanya membingungkan penerimanya.
        if ($this->basi()) {
            return '';
        }

        return $this->whatsappMessage ?? ($this->title . "\n" . $this->message);
    }
}

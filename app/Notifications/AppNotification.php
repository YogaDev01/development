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

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $category = 'general',
        public ?string $whatsappMessage = null,
    ) {
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (! blank($notifiable->phone) && ! blank(config('services.fonnte.token'))) {
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
        return $this->whatsappMessage ?? ($this->title . "\n" . $this->message);
    }
}

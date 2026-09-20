<?php

namespace App\Services;

use App\Notifications\AppNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Helper terpusat untuk mengirim notifikasi (in-app + WhatsApp Fonnte)
 * ke satu atau banyak user sekaligus, dipakai dari berbagai controller.
 */
class NotifyService
{
    /**
     * @param  iterable  $recipients  Koleksi/array User
     */
    public static function send(
        iterable $recipients,
        string $title,
        string $message,
        ?string $url = null,
        string $category = 'general',
        ?string $whatsappMessage = null
    ): void {
        $recipients = collect($recipients)->filter()->unique('id')->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new AppNotification($title, $message, $url, $category, $whatsappMessage));
    }
}

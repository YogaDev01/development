<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Channel notifikasi WhatsApp via Fonnte (https://fonnte.com).
 * Notification class harus punya method toFonnte($notifiable) yang
 * mengembalikan teks pesan, dan model penerima harus punya method
 * routeNotificationForFonnte() yang mengembalikan nomor tujuan.
 */
class FonnteChannel
{
    /** Sisakan empat digit terakhir saja untuk keperluan penelusuran. */
    private static function maskNumber(?string $number): string
    {
        $number = (string) $number;

        return strlen($number) <= 4
            ? str_repeat('*', strlen($number))
            : str_repeat('*', strlen($number) - 4) . substr($number, -4);
    }

    public function send($notifiable, Notification $notification): void
    {
        // Dicek lagi di sini (bukan hanya di via()) supaya job WhatsApp yang
        // SUDAH terlanjur antre sebelum saklar dimatikan juga tidak terkirim.
        if (! config('services.fonnte.enabled') || ! method_exists($notification, 'toFonnte')) {
            return;
        }

        $target = $notifiable->routeNotificationFor('fonnte', $notification);
        $token  = config('services.fonnte.token');

        if (blank($target) || blank($token)) {
            return;
        }

        $message = $notification->toFonnte($notifiable);
        if (blank($message)) {
            return;
        }

        try {
            /*
            | timeout(5)/connectTimeout(3): notifikasi dikirim di dalam siklus
            | request (lihat AppNotification). Tanpa batas waktu, Laravel
            | memakai bawaan 30 detik — artinya satu approval bisa menggantung
            | setengah menit per penerima kalau Fonnte lambat. Lima detik cukup
            | untuk panggilan yang sehat; sisanya lebih baik gagal dan dicatat.
            */
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->withHeaders(['Authorization' => $token])
                ->asForm()
                ->post(config('services.fonnte.url'), [
                    'target'  => $target,
                    'message' => $message,
                ]);

            $body = $response->json();

            // Fonnte membalas HTTP 200 walau gagal (status:false di body),
            // jadi respons sukses HTTP tidak cukup — body-nya wajib dicek juga.
            if (! $response->successful() || (is_array($body) && array_key_exists('status', $body) && $body['status'] === false)) {
                Log::warning('Fonnte menolak pengiriman WhatsApp', [
                    // Nomor disamarkan: log aplikasi bukan tempat menyimpan
                    // data pribadi, dan empat digit terakhir sudah cukup untuk
                    // mencocokkan dengan data karyawan saat menelusuri masalah.
                    'target'    => self::maskNumber($target),
                    'http_code' => $response->status(),
                    'response'  => $body ?? $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim notifikasi WhatsApp via Fonnte: ' . $e->getMessage(), [
                'target' => self::maskNumber($target),
            ]);
        }
    }
}

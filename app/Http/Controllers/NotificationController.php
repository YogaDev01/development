<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    /**
     * Polling: badge count + daftar notifikasi terbaru untuk dropdown bell icon.
     */
    public function fetch(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'category'   => $n->data['category'] ?? 'general',
                'title'      => $n->data['title'] ?? '-',
                'message'    => $n->data['message'] ?? '',
                'url'        => self::tautanRelatif($n->data['url'] ?? null),
                'is_read'    => ! is_null($n->read_at),
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        return response()->json([
            'unread_count'  => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    /**
     * Buang skema + host dari tautan notifikasi, sisakan path (+query/fragment).
     *
     * URL notifikasi dibuat dengan route() pada saat aksinya terjadi, jadi
     * host-nya ikut APP_URL mesin yang memproses — mis. `http://127.0.0.1:8000`.
     * Dibuka dari HP, host itu menunjuk ke HP itu sendiri dan peramban berhenti
     * di ERR_CONNECTION_REFUSED. Tautan relatif selalu mendarat di host yang
     * sedang dipakai, dan ikut memperbaiki notifikasi lama yang sudah tersimpan.
     *
     * Tautan ke situs LAIN (host berbeda dari permintaan ini) dibiarkan apa
     * adanya: itu memang tujuan luar, bukan halaman TESYS.
     */
    private static function tautanRelatif(?string $url): ?string
    {
        if (blank($url) || Str::startsWith($url, '/')) {
            return $url;
        }

        $bagian = parse_url($url);
        if (! isset($bagian['host'])) {
            return $url;
        }

        // Host luar (mis. tautan dokumen eksternal) tidak disentuh.
        if ($bagian['host'] !== request()->getHost() && ! in_array($bagian['host'], ['127.0.0.1', 'localhost'], true)) {
            return $url;
        }

        return ($bagian['path'] ?? '/')
            .(isset($bagian['query']) ? '?'.$bagian['query'] : '')
            .(isset($bagian['fragment']) ? '#'.$bagian['fragment'] : '');
    }

    public function markRead(Request $request, string $id)
    {
        /** @var User $user */
        $user = Auth::user();

        $notification = $user->notifications()->where('id', $id)->first();
        $notification?->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $user->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }
}

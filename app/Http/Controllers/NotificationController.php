<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
                'url'        => $n->data['url'] ?? null,
                'is_read'    => ! is_null($n->read_at),
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        return response()->json([
            'unread_count'  => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
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

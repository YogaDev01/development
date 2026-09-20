<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChangelogController extends Controller
{
    /**
     * Tandai versi changelog saat ini sebagai "sudah ditutup" oleh user
     * (checkbox "jangan tampilkan lagi" di modal What's New) — dipanggil
     * lewat AJAX dari dashboard. Menyimpan NOMOR VERSI (bukan boolean),
     * supaya rilis berikutnya otomatis memicu modal lagi.
     */
    public function dismiss(Request $request)
    {
        Auth::user()->update([
            'dismissed_changelog_version' => (string) config('changelog.current'),
        ]);

        return response()->json(['ok' => true]);
    }
}

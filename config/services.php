<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fonnte' => [
        // Saklar notifikasi WhatsApp. Bawaannya MATI (dimatikan 23 Sep 2026);
        // nyalakan lagi dengan FONNTE_ENABLED=true di .env.
        'enabled' => (bool) env('FONNTE_ENABLED', false),
        'token' => env('FONNTE_TOKEN'),
        'url'   => env('FONNTE_API_URL', 'https://api.fonnte.com/send'),
    ],

    'export_api' => [
        'token' => env('EXPORT_API_TOKEN'),
    ],

    /*
    | Google Maps Static API — peta kecil di drawer detail Site.
    | Kunci terlihat di URL gambar (memang begitu cara Static API), jadi WAJIB
    | dibatasi di Google Cloud Console: API = Maps Static API saja, dan HTTP
    | referrer = domain TESYS. Kosong = drawer memakai peta OpenStreetMap.
    */
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_KEY'),
    ],

    /*
    | Kunci OpenAI / Gemini / Tavily DIHAPUS pada 26 Sep 2026, bersama modul
    | AI yang memakainya.
    |
    | Modul itu ternyata tidak terjangkau sama sekali: TesysAiController tidak
    | pernah terpasang di route mana pun, sehingga ketiga service provider AI
    | (OpenAiService, GeminiService, TavilyService), seluruh isi app/Services/Ai
    | dan app/Services/Finance/AiDataService hanya saling merujuk satu sama
    | lain. Membiarkan kuncinya di sini membuat pembaca menyimpulkan ada fitur
    | AI yang aktif dan perlu dikonfigurasi - padahal tidak ada.
    |
    | Kalau modulnya dihidupkan lagi, kembalikan bersama route-nya.
    */

];

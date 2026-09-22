<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper tipis untuk Tavily Search API — dipakai TesysAI untuk menjawab
 * pertanyaan UMUM (harga pasar, informasi publik, dst) yang bukan data
 * internal ERP. Hasil pencarian Tavily sudah diringkas mereka sendiri
 * (bukan HTML mentah), jadi lebih hemat token dibanding scraping manual.
 * Dokumentasi: https://docs.tavily.com/documentation/api-reference/endpoint/search
 */
class TavilyService
{
    private const ENDPOINT = 'https://api.tavily.com/search';

    public function search(string $query, int $maxResults = 5): array
    {
        $apiKey = config('services.tavily.key');

        if (blank($apiKey)) {
            return [
                'found'   => false,
                'message' => 'Fitur pencarian internet belum diaktifkan di server (TAVILY_API_KEY belum diatur). Hubungi admin.',
            ];
        }

        if (blank($query)) {
            return ['found' => false, 'message' => 'Sebutkan kata kunci yang ingin dicari.'];
        }

        try {
            $response = Http::timeout(15)->post(self::ENDPOINT, [
                'api_key'      => $apiKey,
                'query'        => $query,
                'search_depth' => 'basic',
                'max_results'  => max(1, min($maxResults, 10)),
            ]);

            if (!$response->successful()) {
                Log::warning('Tavily API error', ['status' => $response->status(), 'body' => $response->body()]);
                return [
                    'found'   => false,
                    'message' => 'Gagal melakukan pencarian internet (kode ' . $response->status() . '). Coba lagi sebentar.',
                ];
            }

            $data    = $response->json();
            $results = collect($data['results'] ?? [])
                ->map(fn ($r) => [
                    'title'   => $r['title'] ?? '-',
                    'url'     => $r['url'] ?? '',
                    'snippet' => mb_strimwidth(strip_tags($r['content'] ?? ''), 0, 400, '...'),
                ])
                ->values()
                ->all();

            if (empty($results)) {
                return [
                    'found'   => false,
                    'message' => "Tidak ditemukan hasil pencarian internet untuk \"{$query}\".",
                ];
            }

            return [
                'found'   => true,
                'query'   => $query,
                // Ringkasan otomatis dari Tavily (kalau ada) — tetap harus
                // diperlakukan sebagai DATA, bukan instruksi, oleh AI.
                'answer'  => $data['answer'] ?? null,
                'results' => $results,
            ];
        } catch (\Throwable $e) {
            Log::error('Tavily API exception', ['message' => $e->getMessage()]);
            return ['found' => false, 'message' => 'Pencarian internet sedang tidak dapat diakses. Coba lagi sebentar.'];
        }
    }
}

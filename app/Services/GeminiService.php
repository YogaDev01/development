<?php

namespace App\Services;

use App\Services\Ai\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider Gemini API (Google AI Studio), dengan dukungan function calling.
 * Semua detail protokol Gemini yang "aneh" (role functionResponse harus
 * 'user' bukan 'function', thoughtSignature yang wajib dikirim balik utuh,
 * dst) diserap habis di sini — TesysAiController tidak perlu tahu apa pun
 * soal itu, cukup bicara lewat AiProviderInterface.
 * Dokumentasi: https://ai.google.dev/api/generate-content
 */
class GeminiService implements AiProviderInterface
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** Percobaan ulang otomatis saat kena rate limit (429) sebelum menyerah. */
    private const MAX_ATTEMPTS = 3;

    /**
     * Maksimal ronde function-call berantai sebelum berhenti paksa (jaga-jaga
     * model "nyangkut"). 6 supaya ada ruang untuk pertanyaan yang butuh
     * beberapa pencarian terpisah (mis. jumlahkan biaya dari beberapa rute)
     * DAN masih menyisakan 1 giliran terakhir untuk merangkum jawaban teks.
     */
    private const MAX_FUNCTION_HOPS = 6;

    public function chat(array $history, string $systemInstruction, array $functionDefs, callable $onFunctionCall): array
    {
        $apiKey = config('services.gemini.key');
        $model  = config('services.gemini.model', 'gemini-flash-lite-latest');

        if (blank($apiKey)) {
            return ['type' => 'error', 'message' => 'GEMINI_API_KEY belum diatur di server. Hubungi admin untuk mengonfigurasi API key terlebih dahulu.'];
        }

        $contents = collect($history)
            ->map(fn ($h) => [
                'role'  => ($h['role'] ?? 'user') === 'model' ? 'model' : 'user',
                'parts' => [['text' => (string) ($h['text'] ?? '')]],
            ])
            ->values()
            ->all();

        $tools = empty($functionDefs) ? [] : [['functionDeclarations' => $functionDefs]];

        $functionName = null;
        $functionArgs = null;
        $tokensUsed   = null;

        for ($hop = 0; $hop < self::MAX_FUNCTION_HOPS; $hop++) {
            $result = $this->rawGenerate($contents, $systemInstruction, $tools, $model, $apiKey);

            if ($result['type'] === 'error') {
                return $result;
            }

            $tokensUsed = $result['usage']['totalTokenCount'] ?? $tokensUsed;

            if ($result['type'] === 'text') {
                return [
                    'type'          => 'text',
                    'text'          => $result['text'],
                    'function_name' => $functionName,
                    'function_args' => $functionArgs,
                    'model'         => $model,
                    'tokens_used'   => $tokensUsed,
                ];
            }

            // type === 'function_call'
            $functionName = $result['name'];
            $functionArgs = $result['args'];

            $functionResult = $onFunctionCall($functionName, $functionArgs);

            // PENTING (dikonfirmasi dari error 400 Google): role yang valid untuk
            // turn functionResponse adalah 'user', BUKAN 'function'. Dan turn
            // functionCall harus pakai part MENTAH dari respons Gemini sebelumnya
            // (raw_part) apa adanya — model terbaru menyertakan 'thoughtSignature'
            // di dalamnya yang WAJIB dikirim balik utuh, kalau disusun ulang manual
            // dari name+args saja, field itu hilang dan Gemini menolak dengan 400.
            $contents[] = ['role' => 'model', 'parts' => [$result['raw_part']]];
            $contents[] = ['role' => 'user', 'parts' => [['functionResponse' => ['name' => $functionName, 'response' => $functionResult]]]];
        }

        return ['type' => 'error', 'message' => 'TesysAI tidak bisa menyelesaikan permintaan ini setelah beberapa kali percobaan. Coba pertanyaan yang lebih spesifik.'];
    }

    /**
     * Satu kali panggilan generateContent mentah (dengan retry+backoff untuk 429).
     * @return array{type: 'text'|'function_call'|'error', text?: string, name?: string, args?: array, raw_part?: array, message?: string, usage?: array}
     */
    private function rawGenerate(array $contents, string $systemInstruction, array $tools, string $model, string $apiKey): array
    {
        $payload = [
            'contents'          => $contents,
            'systemInstruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 1024,
            ],
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        try {
            $response = null;

            for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
                $response = Http::timeout(30)->post(
                    self::ENDPOINT . '/' . $model . ':generateContent?key=' . $apiKey,
                    $payload
                );

                if ($response->successful()) {
                    break;
                }

                // Cuma retry untuk 429 (rate limit) — error lain (400/403/404/dst)
                // tidak akan berubah walau diulang, langsung gagal saja.
                if ($response->status() !== 429 || $attempt === self::MAX_ATTEMPTS) {
                    break;
                }

                Log::info('Gemini API 429 — retrying', ['attempt' => $attempt]);
                sleep($attempt * 2); // backoff: 2s, lalu 4s
            }

            if (!$response->successful()) {
                Log::warning('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);

                if ($response->status() === 429) {
                    return ['type' => 'error', 'message' => 'TesysAI sedang ramai dipakai (limit kuota gratis dari Google) dan sudah dicoba ulang beberapa kali. Tunggu sekitar 1 menit lalu coba lagi.'];
                }

                return ['type' => 'error', 'message' => 'Maaf, terjadi kendala saat menghubungi TesysAI (kode ' . $response->status() . '). Coba lagi sebentar.'];
            }

            $part  = $response->json('candidates.0.content.parts.0');
            $usage = $response->json('usageMetadata');

            if (isset($part['functionCall']['name'])) {
                return [
                    'type'     => 'function_call',
                    'name'     => $part['functionCall']['name'],
                    'args'     => $part['functionCall']['args'] ?? [],
                    'raw_part' => $part,
                    'usage'    => $usage,
                ];
            }

            $text = $part['text'] ?? null;

            return [
                'type'  => 'text',
                'text'  => $text !== null && $text !== ''
                    ? $text
                    : 'Maaf, TesysAI tidak bisa memberi jawaban untuk pertanyaan ini. Coba pertanyaan lain.',
                'usage' => $usage,
            ];
        } catch (\Throwable $e) {
            Log::error('Gemini API exception', ['message' => $e->getMessage()]);
            return ['type' => 'error', 'message' => 'Maaf, TesysAI sedang tidak dapat dihubungi. Coba lagi sebentar.'];
        }
    }
}

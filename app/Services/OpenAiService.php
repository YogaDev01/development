<?php

namespace App\Services;

use App\Services\Ai\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider OpenAI (Chat Completions API), dengan dukungan function calling
 * (tool use). Format wire OpenAI beda total dari Gemini — assistant message
 * berisi 'tool_calls' (array, ber-id), lalu dibalas dengan message
 * role:'tool' + tool_call_id yang sama. Semua detail itu diserap di sini,
 * TesysAiController tidak perlu tahu bedanya sama sekali (AiProviderInterface).
 * Dokumentasi: https://platform.openai.com/docs/api-reference/chat
 */
class OpenAiService implements AiProviderInterface
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

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
        $apiKey = config('services.openai.key');
        $model  = config('services.openai.model', 'gpt-4o-mini');

        if (blank($apiKey)) {
            return ['type' => 'error', 'message' => 'OPENAI_API_KEY belum diatur di server. Hubungi admin untuk mengonfigurasi API key terlebih dahulu.'];
        }

        $messages = collect($history)
            ->map(fn ($h) => [
                'role'    => ($h['role'] ?? 'user') === 'model' ? 'assistant' : 'user',
                'content' => (string) ($h['text'] ?? ''),
            ])
            ->values()
            ->all();

        array_unshift($messages, ['role' => 'system', 'content' => $systemInstruction]);

        $tools = $this->buildTools($functionDefs);

        $functionName = null;
        $functionArgs = null;
        $tokensUsed   = null;

        for ($hop = 0; $hop < self::MAX_FUNCTION_HOPS; $hop++) {
            $result = $this->rawChat($messages, $tools, $model, $apiKey);

            if ($result['type'] === 'error') {
                return $result;
            }

            $tokensUsed = $result['usage']['total_tokens'] ?? $tokensUsed;

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
            $toolCall     = $result['tool_call'];
            $functionName = $toolCall['function']['name'];
            $functionArgs = json_decode($toolCall['function']['arguments'] ?? '{}', true) ?: [];

            $functionResult = $onFunctionCall($functionName, $functionArgs);

            // Assistant turn (berisi tool_calls) HARUS dikirim balik apa adanya
            // (termasuk 'id'-nya), lalu dibalas dengan message role:'tool' yang
            // tool_call_id-nya SAMA PERSIS — itu cara OpenAI mencocokkan jawaban
            // ke pemanggilan function yang mana.
            $messages[] = [
                'role'       => 'assistant',
                'content'    => null,
                'tool_calls' => [$toolCall],
            ];
            $messages[] = [
                'role'         => 'tool',
                'tool_call_id' => $toolCall['id'],
                'content'      => json_encode($functionResult),
            ];
        }

        return ['type' => 'error', 'message' => 'TesysAI tidak bisa menyelesaikan permintaan ini setelah beberapa kali percobaan. Coba pertanyaan yang lebih spesifik.'];
    }

    private function buildTools(array $functionDefs): array
    {
        return array_map(fn ($f) => [
            'type'     => 'function',
            'function' => [
                'name'        => $f['name'],
                'description' => $f['description'],
                'parameters'  => $this->lowercaseSchemaTypes($f['parameters']),
            ],
        ], $functionDefs);
    }

    /**
     * FunctionRegistry menulis type schema ala Gemini (STRING/OBJECT, huruf besar).
     * OpenAI (standar JSON Schema) pakai huruf kecil (string/object) — dikonversi
     * di sini supaya FunctionRegistry tetap satu sumber definisi untuk semua provider.
     */
    private function lowercaseSchemaTypes(array $schema): array
    {
        if (isset($schema['type'])) {
            $schema['type'] = strtolower($schema['type']);
        }
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $prop) {
                $schema['properties'][$key] = $this->lowercaseSchemaTypes($prop);
            }
        }
        return $schema;
    }

    /**
     * @return array{type: 'text'|'function_call'|'error', text?: string, tool_call?: array, message?: string, usage?: array}
     */
    private function rawChat(array $messages, array $tools, string $model, string $apiKey): array
    {
        $payload = [
            'model'    => $model,
            'messages' => $messages,
        ];

        if (!empty($tools)) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = 'auto';

            // Model reasoning (mis. gpt-5.6-luna) menolak function tools di
            // /v1/chat/completions kecuali reasoning_effort di-nonaktifkan —
            // dikonfirmasi dari error 400 OpenAI: "Function tools with
            // reasoning_effort are not supported ... set reasoning_effort to
            // 'none'". TAPI mematikan reasoning ternyata bikin pemilihan
            // function jadi kurang akurat (selalu pilih function pertama di
            // daftar, terbukti dari testing 2026-08-12). Jadi param ini HANYA
            // dikirim kalau eksplisit di-set lewat OPENAI_REASONING_EFFORT —
            // model klasik non-reasoning (gpt-4o-mini, dst) tidak butuh ini
            // sama sekali dan tool-calling-nya justru lebih akurat tanpanya.
            $reasoningEffort = config('services.openai.reasoning_effort');
            if (filled($reasoningEffort)) {
                $payload['reasoning_effort'] = $reasoningEffort;
            }
        }

        try {
            $response = null;

            for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
                $response = Http::withToken($apiKey)->timeout(30)->post(self::ENDPOINT, $payload);

                if ($response->successful()) {
                    break;
                }

                if ($response->status() !== 429 || $attempt === self::MAX_ATTEMPTS) {
                    break;
                }

                Log::info('OpenAI API 429 — retrying', ['attempt' => $attempt]);
                sleep($attempt * 2);
            }

            if (!$response->successful()) {
                Log::warning('OpenAI API error', ['status' => $response->status(), 'body' => $response->body()]);

                if ($response->status() === 429) {
                    return ['type' => 'error', 'message' => 'TesysAI sedang ramai dipakai (limit kuota dari OpenAI) dan sudah dicoba ulang beberapa kali. Tunggu sekitar 1 menit lalu coba lagi.'];
                }

                return ['type' => 'error', 'message' => 'Maaf, terjadi kendala saat menghubungi TesysAI (kode ' . $response->status() . '). Coba lagi sebentar.'];
            }

            $message = $response->json('choices.0.message');
            $usage   = $response->json('usage');

            if (!empty($message['tool_calls'][0])) {
                return [
                    'type'      => 'function_call',
                    'tool_call' => $message['tool_calls'][0],
                    'usage'     => $usage,
                ];
            }

            $text = $message['content'] ?? null;

            return [
                'type'  => 'text',
                'text'  => $text !== null && $text !== ''
                    ? $text
                    : 'Maaf, TesysAI tidak bisa memberi jawaban untuk pertanyaan ini. Coba pertanyaan lain.',
                'usage' => $usage,
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAI API exception', ['message' => $e->getMessage()]);
            return ['type' => 'error', 'message' => 'Maaf, TesysAI sedang tidak dapat dihubungi. Coba lagi sebentar.'];
        }
    }
}

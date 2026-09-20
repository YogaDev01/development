<?php

namespace App\Services\Ai;

/**
 * Kontrak umum untuk provider AI (Gemini, OpenAI, dst). TesysAiController
 * hanya bicara ke interface ini — tidak tahu format wire (contents/parts
 * ala Gemini vs messages/tool_calls ala OpenAI). Setiap implementasi
 * bertanggung jawab penuh atas detail protokol function-calling-nya
 * sendiri, termasuk multi-turn round-trip saat function dipanggil.
 */
interface AiProviderInterface
{
    /**
     * @param array<int, array{role: 'user'|'model', text: string}> $history
     *        Riwayat percakapan ringkas (bukan format khusus provider).
     * @param string $systemInstruction Instruksi sistem + compact state.
     * @param array<int, array{name: string, description: string, parameters: array}> $functionDefs
     *        Hasil FunctionRegistry::definitions() — provider-neutral.
     * @param callable(string $name, array $args): array $onFunctionCall
     *        Dipanggil provider setiap kali model minta eksekusi function.
     *        HARUS mengembalikan array hasil terstruktur (dari
     *        AiFunctionDispatcher::dispatch()) yang akan dikirim balik ke
     *        model apa adanya oleh provider.
     * @return array{
     *     type: 'text'|'error',
     *     text?: string,
     *     message?: string,
     *     function_name?: string|null,
     *     function_args?: array|null,
     *     model?: string,
     *     tokens_used?: int|null,
     * }
     */
    public function chat(array $history, string $systemInstruction, array $functionDefs, callable $onFunctionCall): array;
}

<?php

namespace App\Services\Ai;

use App\Models\AiConversation;

/**
 * Menyimpan "compact state" percakapan (project/customer/periode yang
 * sedang dibahas), supaya pertanyaan lanjutan seperti "sudah terpakai
 * berapa?" tidak perlu mengirim ulang seluruh riwayat teks ke Gemini
 * untuk mempertahankan konteksnya — cukup beberapa baris ringkas.
 */
class ConversationStateService
{
    private const DEFAULT_STATE = [
        'current_project'  => null,
        'current_customer' => null,
        'current_period'   => null,
        'last_intent'      => null,
    ];

    public function get(AiConversation $conversation): array
    {
        return array_merge(self::DEFAULT_STATE, $conversation->state ?? []);
    }

    /**
     * Update state setelah satu giliran selesai. Project hasil resolusi
     * backend (dari $result) lebih diutamakan daripada apa yang tertulis
     * di $args, karena itu yang benar-benar ditemukan di database.
     */
    public function apply(AiConversation $conversation, ?string $functionName, array $args, array $result): array
    {
        $state = $this->get($conversation);

        if ($functionName) {
            $state['last_intent'] = $functionName;
        }

        if (($result['found'] ?? false) && !empty($result['project']['name'])) {
            $state['current_project'] = $result['project']['name'];
        } elseif (!empty($args['project'])) {
            $state['current_project'] = $args['project'];
        }

        if (!empty($args['customer'])) {
            $state['current_customer'] = $args['customer'];
        }

        if (!empty($args['period'])) {
            $state['current_period'] = $args['period'];
        }

        $conversation->update(['state' => $state]);

        return $state;
    }

    /** Ringkasan state untuk disisipkan ke system prompt (bukan riwayat teks penuh). */
    public function toPromptLine(array $state): string
    {
        $parts = [];
        if (!empty($state['current_project']))  $parts[] = "Project yang sedang dibahas: {$state['current_project']}";
        if (!empty($state['current_customer'])) $parts[] = "Customer yang sedang dibahas: {$state['current_customer']}";
        if (!empty($state['current_period']))   $parts[] = "Periode yang sedang dibahas: {$state['current_period']}";

        return $parts ? implode('. ', $parts) . '.' : '(Belum ada konteks project/customer/periode spesifik pada percakapan ini.)';
    }
}

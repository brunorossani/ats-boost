<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicService
{
    public function complete(string $prompt, ?string $model = null, int $maxTokens = 4096): string
    {
        $response = $this->send([
            ['role' => 'user', 'content' => $prompt],
        ], $model, $maxTokens);

        return trim($this->extractText($response));
    }

    /**
     * Igual que complete(), pero con un `system` prompt separado y un prefill del
     * turno del asistente (útil para forzar que la respuesta empiece con "{" y
     * sea JSON válido). Anthropic no repite el prefill en la respuesta, así que
     * lo volvemos a anteponer antes de devolver el texto.
     */
    public function completeWithSystem(
        string $prompt,
        string $system,
        string $assistantPrefill = '',
        ?string $model = null,
        int $maxTokens = 4096,
    ): string {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        if ($assistantPrefill !== '') {
            $messages[] = ['role' => 'assistant', 'content' => $assistantPrefill];
        }

        $response = $this->send($messages, $model, $maxTokens, $system);

        return $assistantPrefill.$this->extractText($response);
    }

    private function send(array $messages, ?string $model, int $maxTokens, ?string $system = null)
    {
        $payload = [
            'model' => $model ?: config('anthropic.model'),
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];

        if ($system !== null) {
            $payload['system'] = $system;
        }

        $response = Http::withHeaders([
            'x-api-key' => (string) config('anthropic.api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout((int) config('anthropic.request_timeout', 120))
            ->post(rtrim((string) config('anthropic.base_uri'), '/').'/v1/messages', $payload);

        if ($response->failed()) {
            throw new RuntimeException('No se pudo completar la solicitud a Claude.');
        }

        return $response;
    }

    private function extractText($response): string
    {
        $content = $response->json('content.0.text');

        if (! is_string($content)) {
            throw new RuntimeException('Claude devolvió una respuesta inesperada.');
        }

        return $content;
    }
}

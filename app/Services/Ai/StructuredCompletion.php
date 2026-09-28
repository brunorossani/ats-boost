<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Exceptions\ResumeGenerationException;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Único punto de contacto con Claude.
 *
 * Se pide salida estructurada validada contra un esquema, así que lo que
 * vuelve o cumple el contrato o lanza excepción: ninguna capa de arriba
 * parsea nada.
 */
class StructuredCompletion
{
    /**
     * Ejecuta una tarea y devuelve el JSON ya decodificado.
     *
     * @param  non-empty-string  $task  Identificador corto para logs y errores.
     * @return array<string, mixed>
     *
     * @throws ResumeGenerationException
     */
    public function run(
        string $task,
        string $model,
        string $systemPrompt,
        string $userPrompt,
        JsonSchema $schema,
    ): array {
        $attempts = max(1, (int) config('resume.retries', 2) + 1);
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->attempt($model, $systemPrompt, $userPrompt, $schema);
            } catch (ResumeGenerationException $e) {
                // Una negativa del modelo no cambia con reintentos.
                throw $e;
            } catch (Throwable $e) {
                $lastError = $e;

                Log::warning('Fallo la generación estructurada', [
                    'task' => $task,
                    'attempt' => $attempt,
                    'of' => $attempts,
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Distinguir "el modelo devolvió basura" de "la API no contestó"
        // importa: el primer caso es culpa del prompt, el segundo es red.
        throw $lastError instanceof JsonException
            ? ResumeGenerationException::invalidStructure($task)
            : ResumeGenerationException::modelUnavailable($lastError);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException|ResumeGenerationException|Throwable
     */
    protected function attempt(string $model, string $systemPrompt, string $userPrompt, JsonSchema $schema): array
    {
        $withFallbacks = $this->supportsFallbacks($model);

        $message = app(Client::class)->beta->messages->create(
            maxTokens: (int) config('resume.max_tokens', 16000),
            messages: [['role' => 'user', 'content' => $userPrompt]],
            model: $model,
            system: $systemPrompt,
            outputConfig: array_filter([
                'format' => $schema->toOutputFormat(),
                'effort' => $this->effort($model),
            ]),
            fallbacks: $withFallbacks ? 'default' : null,
            betas: $withFallbacks ? ['server-side-fallback-2026-07-01'] : null,
        );

        if ($message->stopReason === 'refusal') {
            throw ResumeGenerationException::refused();
        }

        if ($message->stopReason === 'max_tokens') {
            throw new JsonException('La respuesta se cortó por el límite de tokens.');
        }

        $text = '';

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new JsonException('La respuesta no es un objeto JSON.');
        }

        return $decoded;
    }

    /**
     * Haiku 4.5 no admite `effort`; en los modelos que sí, se usa en lugar de
     * la temperatura (que Claude 5 ya no acepta).
     */
    private function effort(string $model): ?string
    {
        return str_starts_with($model, 'claude-haiku') ? null : config('resume.effort');
    }

    /**
     * Si Opus/Fable declinan por política, la API reintenta sola en el modelo
     * alternativo que corresponda en vez de devolver una negativa.
     */
    private function supportsFallbacks(string $model): bool
    {
        return str_starts_with($model, 'claude-opus-5') || str_starts_with($model, 'claude-fable');
    }
}

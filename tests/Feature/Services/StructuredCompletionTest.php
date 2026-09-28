<?php

use Anthropic\Client;
use App\Exceptions\ResumeGenerationException;
use App\Services\Ai\JsonSchema;
use App\Services\Ai\StructuredCompletion;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

function runCompletion(string $model): array
{
    return app(StructuredCompletion::class)->run(
        task: 'test',
        model: $model,
        systemPrompt: 'Sos un asistente.',
        userPrompt: 'Hola',
        schema: JsonSchema::object('demo', [
            'score' => JsonSchema::integer('De 0 a 100.'),
            'note' => JsonSchema::nullableString('Una nota.'),
        ]),
    );
}

it('pide salida estructurada a Claude y devuelve el JSON decodificado', function (): void {
    fakeChatResponses(['score' => 80, 'note' => null]);

    expect(runCompletion('claude-opus-5'))->toBe(['score' => 80, 'note' => null]);

    $request = sentChatRequests()[0];
    $entry = app('tests.chat-history')[0]['request'];

    expect($request['model'])->toBe('claude-opus-5')
        ->and($request['system'])->toBe('Sos un asistente.')
        ->and($request['output_config']['format']['type'])->toBe('json_schema')
        ->and($request['output_config']['effort'])->toBe('medium')
        ->and($request['fallbacks'])->toBe('default')
        ->and($request)->not->toHaveKey('temperature')
        // Claude no admite minimum/maximum en el esquema.
        ->and($request['output_config']['format']['schema']['properties']['score'])->not->toHaveKey('minimum')
        ->and($entry->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01');
});

it('no manda effort ni fallbacks a Haiku, que no los admite', function (): void {
    fakeChatResponses(['score' => 10, 'note' => 'ok']);

    runCompletion('claude-haiku-4-5');

    $request = sentChatRequests()[0];

    expect($request['output_config'])->not->toHaveKey('effort')
        ->and($request)->not->toHaveKey('fallbacks');
});

it('no reintenta cuando el modelo se niega a responder', function (): void {
    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_refusal', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-4-5',
            'content' => [], 'stop_reason' => 'refusal', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 0],
        ])),
    ]);

    app()->instance(Client::class, new Client(
        apiKey: 'test-key',
        requestOptions: ['transporter' => new GuzzleClient(['handler' => HandlerStack::create($mock)]), 'maxRetries' => 0],
    ));

    expect(fn () => runCompletion('claude-haiku-4-5'))->toThrow(ResumeGenerationException::class);
    expect($mock->count())->toBe(0);
});

it('convierte una caída de la API en un error legible', function (): void {
    fakeChatResponses();

    expect(fn () => runCompletion('claude-haiku-4-5'))
        ->toThrow(ResumeGenerationException::class, 'no está respondiendo');
});

it('manda el workspace solo cuando está configurado', function (): void {
    fakeChatResponses(['score' => 1, 'note' => null], ['score' => 2, 'note' => null]);

    runCompletion('claude-haiku-4-5');
    config(['services.anthropic.workspace_id' => 'wrkspc_test']);
    runCompletion('claude-haiku-4-5');

    $history = app('tests.chat-history');

    expect($history[0]['request']->hasHeader('anthropic-workspace-id'))->toBeFalse()
        ->and($history[1]['request']->getHeaderLine('anthropic-workspace-id'))->toBe('wrkspc_test');
});

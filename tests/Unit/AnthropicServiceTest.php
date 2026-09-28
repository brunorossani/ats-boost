<?php

use App\Services\AnthropicService;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

test('completes a prompt through the Anthropic messages API', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'respuesta de Claude']],
        ]),
    ]);

    $result = app(AnthropicService::class)->complete('Genera un CV');

    expect($result)->toBe('respuesta de Claude');

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('x-api-key')
            && $request->hasHeader('anthropic-version')
            && $request['messages'][0]['content'] === 'Genera un CV';
    });
});

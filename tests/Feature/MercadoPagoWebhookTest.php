<?php

use App\Actions\MercadoPago\SyncSubscription;

function mercadoPagoWebhookHeaders(string $secret, string $dataId, string $requestId, string $ts): array
{
    $manifest = 'id:'.strtolower($dataId).";request-id:{$requestId};ts:{$ts};";
    $v1 = hash_hmac('sha256', $manifest, $secret);

    return [
        'x-signature' => "ts={$ts},v1={$v1}",
        'x-request-id' => $requestId,
    ];
}

test('a webhook with a valid signature is processed', function () {
    config()->set('services.mercadopago.webhook_secret', 'test-secret');

    $this->mock(SyncSubscription::class, function ($mock) {
        $mock->shouldReceive('handle')
            ->once()
            ->with([
                'id' => 'sub_123',
                'type' => 'subscription_preapproval',
            ]);
    });

    $headers = mercadoPagoWebhookHeaders('test-secret', 'sub_123', 'req-1', (string) time());

    $response = $this->postJson('/api/webhooks/mercadopago', [
        'type' => 'subscription_preapproval',
        'data' => ['id' => 'sub_123'],
    ], $headers);

    $response->assertOk();
    $response->assertJson(['ok' => true]);
});

test('a webhook with an invalid signature is rejected', function () {
    config()->set('services.mercadopago.webhook_secret', 'test-secret');

    $this->mock(SyncSubscription::class, function ($mock) {
        $mock->shouldNotReceive('handle');
    });

    $response = $this->postJson('/api/webhooks/mercadopago', [
        'type' => 'subscription_preapproval',
        'data' => ['id' => 'sub_123'],
    ], [
        'x-signature' => 'ts='.time().',v1=firma-invalida',
        'x-request-id' => 'req-1',
    ]);

    $response->assertStatus(401);
});

test('a webhook is rejected when no signature headers are sent at all', function () {
    config()->set('services.mercadopago.webhook_secret', 'test-secret');

    $this->mock(SyncSubscription::class, function ($mock) {
        $mock->shouldNotReceive('handle');
    });

    $response = $this->postJson('/api/webhooks/mercadopago', [
        'type' => 'subscription_preapproval',
        'data' => ['id' => 'sub_123'],
    ]);

    $response->assertStatus(401);
});

test('a webhook is rejected when the webhook secret is not configured (fail-closed)', function () {
    config()->set('services.mercadopago.webhook_secret', '');

    $this->mock(SyncSubscription::class, function ($mock) {
        $mock->shouldNotReceive('handle');
    });

    $headers = mercadoPagoWebhookHeaders('cualquier-secret', 'sub_123', 'req-1', (string) time());

    $response = $this->postJson('/api/webhooks/mercadopago', [
        'type' => 'subscription_preapproval',
        'data' => ['id' => 'sub_123'],
    ], $headers);

    $response->assertStatus(401);
});

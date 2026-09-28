<?php

use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\Http;

test('fails with a useful exception when the access token is missing', function () {
    config()->set('services.mercadopago.access_token', null);

    expect(fn () => new MercadoPagoService())
        ->toThrow(Exception::class, 'Token de acceso de MercadoPago no configurado.');
});

test('getSubscription retries once on a transient failure and then succeeds', function () {
    Http::fake([
        'api.mercadopago.com/preapproval/*' => Http::sequence()
            ->push(['message' => 'error transitorio'], 500)
            ->push(['id' => 'sub_123', 'status' => 'authorized'], 200),
    ]);

    $service = new MercadoPagoService('test-token');

    $result = $service->getSubscription('sub_123');

    expect($result['status'])->toBe('authorized');
    Http::assertSentCount(2);
});

test('createSubscription does NOT retry on failure to avoid creating duplicate subscriptions', function () {
    Http::fake([
        'api.mercadopago.com/preapproval' => Http::response(['message' => 'error'], 500),
    ]);

    $service = new MercadoPagoService('test-token');

    expect(fn () => $service->createSubscription(['payer_email' => 'test@example.com']))
        ->toThrow(Exception::class);

    Http::assertSentCount(1);
});

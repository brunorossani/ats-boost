<?php

use App\Actions\MercadoPago\SyncSubscription;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.mercadopago.webhook_secret' => 'test-secret',
        'services.mercadopago.access_token' => 'test-token',
    ]);

    Http::preventStrayRequests();
});

function mercadoPagoSignature(string $dataId, string $secret = 'test-secret', string $requestId = 'req-1'): array
{
    $ts = (string) time();
    $v1 = hash_hmac('sha256', 'id:'.strtolower($dataId).";request-id:{$requestId};ts:{$ts};", $secret);

    return ['x-signature' => "ts={$ts},v1={$v1}", 'x-request-id' => $requestId];
}

it('procesa un webhook con firma válida', function (): void {
    $this->mock(SyncSubscription::class)->shouldReceive('handle')->once()
        ->with(['id' => 'sub_123', 'type' => 'subscription_preapproval']);

    $this->postJson('/api/webhooks/mercadopago', [
        'type' => 'subscription_preapproval',
        'data' => ['id' => 'sub_123'],
    ], mercadoPagoSignature('sub_123'))
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('valida la firma cuando data.id viene en la URL de notificación', function (): void {
    $this->mock(SyncSubscription::class)->shouldReceive('handle')->once();

    $this->withHeaders(mercadoPagoSignature('ABC123'))
        ->postJson('/api/webhooks/mercadopago?data.id=ABC123&type=subscription_preapproval', [])
        ->assertOk();
});

it('rechaza un webhook con firma inválida', function (): void {
    $this->mock(SyncSubscription::class)->shouldNotReceive('handle');

    $this->postJson('/api/webhooks/mercadopago', [
        'type' => 'subscription_preapproval',
        'data' => ['id' => 'sub_123'],
    ], ['x-signature' => 'ts='.time().',v1=firma-invalida', 'x-request-id' => 'req-1'])
        ->assertStatus(401);
});

it('rechaza un webhook sin headers de firma', function (): void {
    $this->mock(SyncSubscription::class)->shouldNotReceive('handle');

    $this->postJson('/api/webhooks/mercadopago', [
        'type' => 'subscription_preapproval',
        'data' => ['id' => 'sub_123'],
    ])->assertStatus(401);
});

it('rechaza todo si la clave secreta no está configurada', function (): void {
    config(['services.mercadopago.webhook_secret' => '']);
    $this->mock(SyncSubscription::class)->shouldNotReceive('handle');

    $this->postJson('/api/webhooks/mercadopago', [
        'type' => 'subscription_preapproval',
        'data' => ['id' => 'sub_123'],
    ], mercadoPagoSignature('sub_123', 'cualquier-clave'))
        ->assertStatus(401);
});

it('renueva la suscripción cuando llega un cobro recurrente', function (): void {
    $subscriber = Subscriber::factory()->create([
        'mp_subscription_id' => 'pre_1',
        'ends_at' => now()->addDay(),
    ]);
    $nextPayment = now()->addMonth()->startOfSecond();

    Http::fake([
        'api.mercadopago.com/authorized_payments/pay_1' => Http::response(['id' => 'pay_1', 'preapproval_id' => 'pre_1']),
        'api.mercadopago.com/preapproval/pre_1' => Http::response([
            'id' => 'pre_1',
            'preapproval_plan_id' => $subscriber->mp_plan_id,
            'status' => 'authorized',
            'next_payment_date' => $nextPayment->toIso8601String(),
        ]),
    ]);

    $this->postJson('/api/webhooks/mercadopago', [
        'type' => 'subscription_authorized_payment',
        'data' => ['id' => 'pay_1'],
    ], mercadoPagoSignature('pay_1'))->assertOk();

    $subscriber->refresh();

    expect($subscriber->ends_at->equalTo($nextPayment))->toBeTrue()
        ->and($subscriber->status)->toBe('authorized')
        ->and($subscriber->active)->toBeTrue();
});

it('no toca ninguna suscripción si el pago no trae una asociada', function (): void {
    $this->mock(SyncSubscription::class)->shouldNotReceive('handle');

    Http::fake(['api.mercadopago.com/authorized_payments/pay_2' => Http::response(['id' => 'pay_2'])]);

    $this->postJson('/api/webhooks/mercadopago', [
        'type' => 'subscription_authorized_payment',
        'data' => ['id' => 'pay_2'],
    ], mercadoPagoSignature('pay_2'))->assertOk();
});

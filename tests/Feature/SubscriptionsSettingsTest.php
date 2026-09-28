<?php

use App\Actions\MercadoPago\SyncSubscription;
use App\Livewire\Settings\Subscriptions;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\MercadoPagoService;
use Livewire\Livewire;

test('changePlan rejects a plan id outside the configured allowlist', function () {
    config()->set('services.mercadopago.plans', ['monthly' => 'plan_monthly_test']);

    $user = User::factory()->create();

    Subscriber::create([
        'user_id' => $user->id,
        'mp_subscription_id' => 'sub_123',
        'mp_plan_id' => 'plan_monthly_test',
        'status' => 'authorized',
        'active' => true,
        'ends_at' => now()->addMonth(),
    ]);

    $this->mock(MercadoPagoService::class, function ($mock) {
        $mock->shouldReceive('getPlanPrice')->andReturn([
            'amount' => 100,
            'currency' => 'UYU',
            'frequency' => 1,
            'frequency_type' => 'months',
        ]);
    });

    Livewire::actingAs($user)
        ->test(Subscriptions::class)
        ->set('newPlan', 'plan-inventado')
        ->call('changePlan')
        ->assertNoRedirect()
        ->assertDispatched('toast-show');
});

test('cancelSubscription shows an error and leaves the database untouched when Mercado Pago fails', function () {
    // Sin planes configurados: mount() no intenta cargar precios, así el mock
    // de MercadoPagoService solo necesita responder a cancelSubscription().
    config()->set('services.mercadopago.plans', []);

    $user = User::factory()->create();

    $subscriber = Subscriber::create([
        'user_id' => $user->id,
        'mp_subscription_id' => 'sub_123',
        'mp_plan_id' => 'plan_test',
        'status' => 'authorized',
        'active' => true,
        'ends_at' => now()->addMonth(),
    ]);

    $this->mock(MercadoPagoService::class, function ($mock) {
        $mock->shouldReceive('cancelSubscription')
            ->once()
            ->andThrow(new Exception('Mercado Pago no responde'));
    });

    $this->mock(SyncSubscription::class, function ($mock) {
        $mock->shouldNotReceive('handle');
    });

    Livewire::actingAs($user)
        ->test(Subscriptions::class)
        ->call('cancelSubscription')
        ->assertDispatched('toast-show');

    expect($subscriber->fresh()->status)->toBe('authorized');
});

test('the subscriptions page renders even when Mercado Pago cannot be reached', function () {
    // Token vacío: MercadoPagoService tira en el constructor.
    config()->set('services.mercadopago.access_token', '');
    config()->set('services.mercadopago.plans', [
        'weekly' => 'plan_weekly_test',
        'monthly' => 'plan_monthly_test',
        'yearly' => 'plan_yearly_test',
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Subscriptions::class)
        ->assertOk()
        ->assertSet('prices.weekly', null)
        ->assertSet('prices.monthly', null)
        ->assertSet('prices.yearly', null);
});

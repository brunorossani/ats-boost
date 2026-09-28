<?php

use App\Models\User;

beforeEach(function () {
    config()->set('services.mercadopago.plans', [
        'weekly' => 'plan_weekly_test',
        'monthly' => 'plan_monthly_test',
        'yearly' => 'plan_yearly_test',
    ]);
});

test('checkout start redirects to Mercado Pago for an allowed plan id', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('checkout.start', ['variant' => 'plan_monthly_test']));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('plan_monthly_test');
});

test('checkout start rejects a plan id that is not in the configured allowlist', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('checkout.start', ['variant' => 'algun-plan-inventado']));

    $response->assertNotFound();
});

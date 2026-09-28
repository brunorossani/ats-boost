<?php

namespace App\Http\Controllers;

class CheckoutController extends Controller
{
    public function start(string $variant)
    {
        $allowedPlans = array_filter(config('services.mercadopago.plans'));

        if (! in_array($variant, $allowedPlans, true)) {
            abort(404);
        }

        session(['checkout_variant' => $variant]);

        if (! auth()->check()) {
            return redirect()->guest(route('login'));
        }

        $user = auth()->user();

        if ($user->hasActiveSubscription()) {
            return redirect()->route('subscriptions.edit');
        }

        return redirect()->away(
            'https://www.mercadopago.com.uy/subscriptions/checkout?preapproval_plan_id=' . $variant
        );
    }
}

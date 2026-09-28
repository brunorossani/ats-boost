<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\MercadoPagoService;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

class PricingPlans extends Component
{
    public array $prices = [];

    public function mount()
    {
        // Página pública: nunca debe romperse por un problema de MercadoPago
        // (token no configurado, API caída, etc.) — se degrada mostrando el
        // precio como no disponible en vez de tirar un 500 para todo el mundo.
        try {
            $mp = app(MercadoPagoService::class);
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: servicio no disponible', ['error' => $e->getMessage()]);

            $this->prices = array_fill_keys(array_keys(config('services.mercadopago.plans')), null);

            return;
        }

        foreach (config('services.mercadopago.plans') as $key => $planId) {
            try {
                $price = $mp->getPlanPrice($planId);

                $this->prices[$key] = [
                    ...$price,
                    'formatted' => Money::format($price['amount'], $price['currency']),
                ];
            } catch (\Throwable $e) {
                Log::error('Mercado Pago: no se pudo obtener el precio del plan', [
                    'plan' => $key,
                    'error' => $e->getMessage(),
                ]);

                $this->prices[$key] = null;
            }
        }
    }

    public function render()
    {
        return view('livewire.pricing-plans');
    }
}

<?php

namespace App\Livewire\Settings;

use App\Actions\MercadoPago\HandleSubscriptionPlanChange;
use App\Actions\MercadoPago\SyncSubscription;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Livewire\Component;
use App\Support\Money;
use Flux\Flux;

class Subscriptions extends Component
{
    public $subscription;
    public $newPlan;
    public array $prices = [];

    public function mount(Request $request)
    {
        if(session('subscription_required')) {
            Flux::toast(
                heading: 'Plan requerido',
                text: 'Necesitas una suscripción activa.',
                variant: 'warning',
            );

            session()->forget('subscription_required');
        }

        // Sync ONLY when coming back from MercadoPago
        if ($request->filled('preapproval_id')) {

            app(SyncSubscription::class)->handle([
                'id' => $request->preapproval_id,
                'source' => 'back_url',
            ]);

            $oldSubscriptionId = session('plan_change_old_subscription_id');

            if ($oldSubscriptionId) {

                $success = app(HandleSubscriptionPlanChange::class)->handle(
                    Auth::user(),
                    $request->preapproval_id,
                    $oldSubscriptionId
                );

                if ($success) {
                    Flux::toast(
                        heading: 'Plan actualizado',
                        text: 'Tu suscripción ha sido actualizada exitosamente.',
                        variant: 'success',
                    );
                }

                session()->forget('plan_change_old_subscription_id');
                session()->forget('plan_change_new_plan_id');
            }
        }

        $this->loadSubscription();
        $this->loadPrices();
    }

    /**
     * Carga los precios de los planes. Si MercadoPago no está disponible
     * (token no configurado, API caída, etc.) la página igual debe poder
     * renderizarse: cada plan queda en null en vez de tirar un 500.
     */
    protected function loadPrices(): void
    {
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

    protected function loadSubscription()
    {
        $this->subscription = Auth::user()
            ->subscribers()
            ->orderByDesc('ends_at')
            ->first();

        $this->newPlan = $this->subscription?->mp_plan_id;
    }

    public function changePlan()
    {
        if (!$this->subscription) {
            return;
        }

        if ($this->newPlan === $this->subscription->mp_plan_id) {
            return;
        }

        // $newPlan es una propiedad pública de Livewire: puede llegar manipulada
        // desde el cliente sin importar qué opciones renderice el <select>.
        $allowedPlans = array_filter(config('services.mercadopago.plans'));

        if (! in_array($this->newPlan, $allowedPlans, true)) {
            Flux::toast(
                heading: 'Plan inválido',
                text: 'El plan seleccionado no es válido.',
                variant: 'danger',
            );

            return;
        }

        session([
            'plan_change_old_subscription_id' => $this->subscription->mp_subscription_id,
            'plan_change_new_plan_id' => $this->newPlan,
        ]);

        $this->modal('update-suscription')->close();

        return redirect()->away(
            'https://www.mercadopago.com.uy/subscriptions/checkout?preapproval_plan_id=' . $this->newPlan
        );
    }

    public function cancelSubscription()
    {
        if (!$this->subscription) {
            return;
        }

        if ($this->subscription->status === 'cancelled') {
            return;
        }

        try {
            app(MercadoPagoService::class)
                ->cancelSubscription($this->subscription->mp_subscription_id);
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: error al cancelar la suscripción', [
                'subscription_id' => $this->subscription->mp_subscription_id,
                'error' => $e->getMessage(),
            ]);

            Flux::toast(
                heading: 'No se pudo cancelar',
                text: 'Ocurrió un problema al comunicarnos con Mercado Pago. Intenta nuevamente en unos minutos.',
                variant: 'danger',
            );

            return;
        }

        app(SyncSubscription::class)->handle([
            'id' => $this->subscription->mp_subscription_id,
            'source' => 'cancel',
        ]);

        $this->loadSubscription();

        Flux::modals()->close();

        Flux::toast(
            heading: 'Suscripción cancelada',
            text: 'Tu suscripción terminará al final del período de facturación.',
            variant: 'success'
        );
    }

    public function render()
    {
        return view('livewire.settings.subscriptions')
            ->title(__('Suscripciones • ATS Boost'));
    }
}

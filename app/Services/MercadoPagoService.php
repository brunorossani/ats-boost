<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MercadoPagoService
{
    protected string $apiUrl = 'https://api.mercadopago.com';

    public function __construct(
        protected string $accessToken = ''
    ) {
        $this->accessToken = config('services.mercadopago.access_token');

        if (! $this->accessToken) {
            throw new \Exception('Token de acceso de MercadoPago no configurado. Configura MERCADOPAGO_ACCESS_TOKEN en .env');
        }
    }

    public function createSubscription(array $data): array
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(10)
            ->post("{$this->apiUrl}/preapproval", $data);

        if ($response->failed()) {
            throw new \Exception($response->body());
        }

        return $response->json();
    }

    public function getSubscription(string $id): array
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->get("{$this->apiUrl}/preapproval/{$id}")
            ->throw()
            ->json();
    }

    /**
     * Cobro recurrente de una suscripción. Trae `preapproval_id`.
     */
    public function getAuthorizedPayment(string $id): array
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->get("{$this->apiUrl}/authorized_payments/{$id}")
            ->throw()
            ->json();
    }

    public function cancelSubscription(string $id): array
    {
        return $this->updateSubscription($id, [
            'status' => 'cancelled',
            'active' => false,
        ]);
    }

    public function updateSubscription(string $id, array $data): array
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->put("{$this->apiUrl}/preapproval/{$id}", $data)
            ->throw()
            ->json();
    }

    public function getPlan(string $planId): array
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(10)
            ->get("{$this->apiUrl}/preapproval_plan/{$planId}");

        if ($response->failed()) {
            throw new \Exception("No se pudo obtener el plan {$planId}: {$response->body()}");
        }

        return $response->json();
    }

    public function getPlanPrice(string $planId): array
    {
        return Cache::remember("mp_plan_price_{$planId}", now()->addHours(6), function () use ($planId) {
            $plan = $this->getPlan($planId);

            return [
                'amount' => $plan['auto_recurring']['transaction_amount'],
                'currency' => $plan['auto_recurring']['currency_id'],
                'frequency' => $plan['auto_recurring']['frequency'],
                'frequency_type' => $plan['auto_recurring']['frequency_type'],
            ];
        });
    }
}

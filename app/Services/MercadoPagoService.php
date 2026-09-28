<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class MercadoPagoService
{
    protected string $apiUrl = 'https://api.mercadopago.com';

    protected string $accessToken;

    public function __construct(?string $accessToken = null)
    {
        $this->accessToken = $accessToken ?: (string) config('services.mercadopago.access_token', '');

        if (! $this->accessToken) {
            throw new \Exception('Token de acceso de MercadoPago no configurado. Configura MERCADOPAGO_ACCESS_TOKEN en .env');
        }
    }

    /**
     * NO se le agrega retry a propósito: es un POST que crea una suscripción
     * real (cobra dinero). Reintentar un timeout/fallo de red podría crear
     * una suscripción duplicada si el primer intento sí llegó a procesarse
     * del lado de MercadoPago pero la respuesta no volvió a tiempo.
     */
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
            ->retry(2, 200)
            ->get("{$this->apiUrl}/preapproval/{$id}")
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
            ->retry(2, 200)
            ->put("{$this->apiUrl}/preapproval/{$id}", $data)
            ->throw()
            ->json();
    }

    public function getPlan(string $planId): array
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(10)
            ->retry(2, 200)
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

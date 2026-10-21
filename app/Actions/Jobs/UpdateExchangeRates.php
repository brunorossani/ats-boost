<?php

namespace App\Actions\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Actualiza las tasas de cambio desde una API gratuita y confiable.
 *
 * Usa exchangerate-api.com (plan free: 1500 requests/mes, más que suficiente para una vez por día).
 * Alternativa: api.exchangerate.host (también gratuita).
 *
 * Fallback: si la API falla, mantiene las tasas anteriores.
 */
class UpdateExchangeRates
{
    private const API_URL = 'https://api.exchangerate-api.com/v4/latest/USD';

    public function handle(): bool
    {
        try {
            $response = Http::timeout(10)->get(self::API_URL);

            if (! $response->successful()) {
                Log::warning('Failed to fetch exchange rates', ['status' => $response->status()]);

                return false;
            }

            $data = $response->json();
            $rates = $data['rates'] ?? [];

            if (empty($rates)) {
                Log::warning('Exchange rates API returned empty rates');

                return false;
            }

            // Actualizar todas las monedas que tengamos ofertas (más comunes)
            $currencies = ['USD', 'EUR', 'GBP', 'ARS', 'CLP', 'MXN', 'BRL', 'COP', 'UYU', 'CAD', 'AUD', 'INR'];

            foreach ($currencies as $currency) {
                if (! isset($rates[$currency])) {
                    continue;
                }

                // La tasa viene como "1 USD = X currency", necesitamos "1 currency = Y USD"
                $toUsdRate = 1 / $rates[$currency];

                DB::table('exchange_rates')->updateOrInsert(
                    ['currency' => $currency],
                    ['to_usd_rate' => round($toUsdRate, 6), 'updated_at' => now()]
                );
            }

            Log::info('Exchange rates updated successfully', ['currencies' => count($currencies)]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update exchange rates', ['error' => $e->getMessage()]);

            return false;
        }
    }
}

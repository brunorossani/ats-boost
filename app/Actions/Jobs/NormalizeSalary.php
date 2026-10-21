<?php

namespace App\Actions\Jobs;

use Illuminate\Support\Facades\DB;

/**
 * Convierte un salario en cualquier moneda y período a USD por mes.
 *
 * Si el período es null, no se puede normalizar (devuelve null).
 * Si la moneda no está en exchange_rates, intenta con USD = 1.0 como fallback.
 */
class NormalizeSalary
{
    /**
     * @param  float|null  $min
     * @param  float|null  $max
     * @param  string|null  $currency
     * @param  string|null  $period  hour|day|month|year
     * @return array{min_usd_month: float|null, max_usd_month: float|null}
     */
    public function handle(
        ?float $min,
        ?float $max,
        ?string $currency,
        ?string $period
    ): array {
        // Sin período no podemos normalizar
        if ($period === null) {
            return ['min_usd_month' => null, 'max_usd_month' => null];
        }

        // Obtener tasa de cambio (fallback a 1.0 si no existe)
        $rate = $currency
            ? (float) DB::table('exchange_rates')->where('currency', $currency)->value('to_usd_rate') ?: 1.0
            : 1.0;

        // Convertir a meses
        $monthMultiplier = match ($period) {
            'hour' => 160, // ~40h/semana * 4 semanas
            'day' => 22,   // días laborables por mes
            'month' => 1,
            'year' => 1 / 12,
            default => null,
        };

        if ($monthMultiplier === null) {
            return ['min_usd_month' => null, 'max_usd_month' => null];
        }

        return [
            'min_usd_month' => $min ? round($min * $rate * $monthMultiplier, 2) : null,
            'max_usd_month' => $max ? round($max * $rate * $monthMultiplier, 2) : null,
        ];
    }
}

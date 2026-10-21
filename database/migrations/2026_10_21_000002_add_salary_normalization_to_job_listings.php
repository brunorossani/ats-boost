<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 1: agregar normalización de salarios para permitir ordenar por salario
     * y comparar ofertas de diferentes países/monedas.
     */
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            // Período del salario (si la oferta no lo dice, queda null)
            $table->enum('salary_period', ['hour', 'day', 'month', 'year'])->nullable()->after('salary_currency');

            // Salario normalizado a USD por mes
            $table->decimal('salary_min_usd_month', 10, 2)->nullable()->after('salary_period');
            $table->decimal('salary_max_usd_month', 10, 2)->nullable()->after('salary_min_usd_month');

            // Flag para distinguir salarios publicados vs estimados (por ahora siempre false)
            $table->boolean('salary_is_estimated')->default(false)->after('salary_max_usd_month');

            // Índices para ordenar por salario
            $table->index('salary_min_usd_month');
            $table->index('salary_max_usd_month');
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropIndex(['salary_min_usd_month']);
            $table->dropIndex(['salary_max_usd_month']);
            $table->dropColumn([
                'salary_period',
                'salary_min_usd_month',
                'salary_max_usd_month',
                'salary_is_estimated',
            ]);
        });
    }
};

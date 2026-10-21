<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 1: tabla de tasas de cambio para normalizar salarios a USD.
     * Se actualiza una vez por día por comando programado.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->string('currency', 3)->primary();
            $table->decimal('to_usd_rate', 12, 6);
            $table->timestamp('updated_at');
        });

        // Inicializar con USD = 1.0 como fallback
        DB::table('exchange_rates')->insert([
            'currency' => 'USD',
            'to_usd_rate' => 1.0,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};

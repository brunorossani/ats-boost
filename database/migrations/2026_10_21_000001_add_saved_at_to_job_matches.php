<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 0: agregar saved_at para permitir "guardar" ofertas sin cambiar su estado.
     */
    public function up(): void
    {
        Schema::table('job_matches', function (Blueprint $table) {
            $table->timestamp('saved_at')->nullable()->after('applied_at');
            $table->index('saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('job_matches', function (Blueprint $table) {
            $table->dropIndex(['saved_at']);
            $table->dropColumn('saved_at');
        });
    }
};

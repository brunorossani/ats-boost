<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 1: agregar seen_at para marcar "nuevas desde tu última visita".
     */
    public function up(): void
    {
        Schema::table('job_matches', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable()->after('saved_at');
            $table->index('seen_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_feed_visit_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('job_matches', function (Blueprint $table) {
            $table->dropIndex(['seen_at']);
            $table->dropColumn('seen_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_feed_visit_at');
        });
    }
};

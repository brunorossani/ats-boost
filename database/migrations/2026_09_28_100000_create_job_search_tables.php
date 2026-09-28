<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('external_id');
            $table->string('fingerprint', 40)->index();
            $table->string('title');
            $table->string('company');
            $table->string('location')->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->boolean('is_remote')->default(false);
            $table->string('remote_zone')->nullable();
            $table->string('employment_type')->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('salary_currency', 8)->nullable();
            $table->longText('description');
            $table->text('url');
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });

        Schema::create('job_search_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('keywords');
            $table->char('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->boolean('include_remote')->default(true);
            $table->unsignedSmallInteger('max_age_days')->default(3);
            $table->boolean('is_active')->default(true);
            // CV base: se extrae y parsea una sola vez, no en cada oferta.
            $table->string('resume_filename')->nullable();
            $table->longText('resume_text')->nullable();
            $table->json('resume_payload')->nullable();
            $table->json('last_sync_report')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('job_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_search_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('new')->index();
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->json('match_details')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['job_search_profile_id', 'job_listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_matches');
        Schema::dropIfExists('job_search_profiles');
        Schema::dropIfExists('job_listings');
    }
};

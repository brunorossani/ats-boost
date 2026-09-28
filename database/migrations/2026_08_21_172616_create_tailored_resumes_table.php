<?php

use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tailored_resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Resume::class)->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('candidate_name')->nullable();
            $table->text('job_description');
            $table->longText('html');
            $table->string('language', 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tailored_resumes');
    }
};

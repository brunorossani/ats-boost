<?php

namespace App\Models;

use App\Data\ResumeData;
use App\JobSearch\JobQuery;
use App\JobSearch\Regions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una búsqueda guardada: qué puesto, dónde, y con qué CV base se adaptan las ofertas.
 */
class JobSearchProfile extends Model
{
    // Iguales a los defaults de la migración: sin esto un modelo recién creado
    // los tiene en null hasta refrescarlo.
    protected $attributes = [
        'include_remote' => true,
        'max_age_days' => 3,
        'is_active' => true,
    ];

    protected $fillable = [
        'keywords',
        'country_code',
        'city',
        'include_remote',
        'max_age_days',
        'is_active',
        'resume_filename',
        'resume_text',
        'resume_payload',
        'last_sync_report',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'include_remote' => 'boolean',
            'is_active' => 'boolean',
            'resume_payload' => 'array',
            'last_sync_report' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(JobMatch::class);
    }

    public function hasResume(): bool
    {
        return filled($this->resume_text);
    }

    public function baseResume(): ?ResumeData
    {
        return $this->resume_payload ? ResumeData::from($this->resume_payload) : null;
    }

    public function toQuery(int $limit = 50): JobQuery
    {
        return new JobQuery(
            keywords: $this->keywords,
            countryCode: $this->country_code,
            city: $this->city,
            includeRemote: $this->include_remote,
            maxAgeDays: max(1, min($this->max_age_days, (int) config('jobsearch.max_age_days', 3))),
            limit: $limit,
        );
    }

    public function label(): string
    {
        $place = collect([$this->city, Regions::name($this->country_code)])->filter()->implode(', ');

        return $place ?: 'Remoto';
    }
}

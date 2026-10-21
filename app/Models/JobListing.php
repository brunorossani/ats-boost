<?php

namespace App\Models;

use App\JobSearch\JobPosting;
use App\JobSearch\Regions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobListing extends Model
{
    protected $fillable = [
        'source',
        'external_id',
        'fingerprint',
        'title',
        'company',
        'location',
        'country_code',
        'is_remote',
        'remote_zone',
        'employment_type',
        'salary_min',
        'salary_max',
        'salary_currency',
        'salary_period',
        'salary_min_usd_month',
        'salary_max_usd_month',
        'salary_is_estimated',
        'description',
        'url',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_remote' => 'boolean',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'salary_min_usd_month' => 'decimal:2',
            'salary_max_usd_month' => 'decimal:2',
            'salary_is_estimated' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(JobMatch::class);
    }

    public static function fromPosting(JobPosting $posting): self
    {
        return static::updateOrCreate(
            ['source' => $posting->source, 'external_id' => mb_substr($posting->externalId, 0, 255)],
            [
                'fingerprint' => $posting->fingerprint(),
                'title' => mb_substr($posting->title, 0, 255),
                'company' => mb_substr($posting->company ?: '—', 0, 255),
                'location' => $posting->location ? mb_substr($posting->location, 0, 255) : null,
                'country_code' => $posting->countryCode,
                'is_remote' => $posting->isRemote,
                'remote_zone' => $posting->remoteZone ? mb_substr($posting->remoteZone, 0, 255) : null,
                'employment_type' => $posting->employmentType ? mb_substr($posting->employmentType, 0, 255) : null,
                'salary_min' => $posting->salaryMin,
                'salary_max' => $posting->salaryMax,
                'salary_currency' => $posting->salaryCurrency,
                'description' => $posting->description,
                'url' => $posting->url,
                'posted_at' => $posting->postedAt,
            ]
        );
    }

    public function safeUrl(): ?string
    {
        return preg_match('#^https?://#i', (string) $this->url) ? $this->url : null;
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'jsearch' => 'Google Jobs',
            'adzuna' => 'Adzuna',
            'getonbrd' => 'Get on Board',
            'arbeitnow' => 'Arbeitnow',
            'remotive' => 'Remotive',
            'jooble' => 'Jooble',
            'ats' => 'Web de la empresa',
            default => ucfirst($this->source),
        };
    }

    public function locationLabel(): string
    {
        $place = $this->location ?: Regions::name($this->country_code);

        if ($this->is_remote) {
            return $place ? "Remoto · {$place}" : 'Remoto';
        }

        return $place ?: 'Ubicación no informada';
    }

    /**
     * Etiqueta de salario para mostrar en UI: original + normalizado si está disponible.
     */
    public function salaryLabel(): ?string
    {
        if (! $this->salary_min && ! $this->salary_max) {
            return null;
        }

        $parts = [];

        // Salario original
        $original = collect([$this->salary_min, $this->salary_max])
            ->filter()
            ->map(fn ($v) => number_format((float) $v, 0, ',', '.'))
            ->implode(' – ');

        if ($original) {
            $parts[] = ($this->salary_currency ?: '') . ' ' . $original;
            if ($this->salary_period) {
                $parts[0] .= '/' . match ($this->salary_period) {
                    'hour' => 'hora',
                    'day' => 'día',
                    'month' => 'mes',
                    'year' => 'año',
                    default => $this->salary_period,
                };
            }
        }

        // Normalizado (solo si existe y es diferente de original)
        if ($this->salary_min_usd_month || $this->salary_max_usd_month) {
            $normalized = collect([$this->salary_min_usd_month, $this->salary_max_usd_month])
                ->filter()
                ->map(fn ($v) => number_format((float) $v, 0, ',', '.'))
                ->implode(' – ');

            if ($normalized && $this->salary_currency !== 'USD') {
                $parts[] = "≈ USD {$normalized}/mes";
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Texto que se le pasa a la IA para adaptar el CV.
     */
    public function promptText(): string
    {
        return collect([
            "Puesto: {$this->title}",
            "Empresa: {$this->company}",
            'Ubicación: '.$this->locationLabel(),
            $this->employment_type ? "Tipo de contrato: {$this->employment_type}" : null,
            '',
            $this->description,
        ])->filter(fn ($l) => $l !== null)->implode("\n");
    }
}

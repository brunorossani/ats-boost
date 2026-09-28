<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn (string $word): string => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<JobSearchProfile, $this>
     */
    public function jobSearchProfiles(): HasMany
    {
        return $this->hasMany(JobSearchProfile::class);
    }

    /**
     * @return HasMany<JobMatch, $this>
     */
    public function jobMatches(): HasMany
    {
        return $this->hasMany(JobMatch::class);
    }

    /**
     * @return HasMany<Subscriber, $this>
     */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    /**
     * La suscripción vigente, si hay alguna.
     *
     * Se define como relación (y no como método que arma la query a mano)
     * para poder precargarla con `with('subscription')` y no repetir la
     * consulta en el middleware, en la política y en la vista.
     *
     * @return HasOne<Subscriber, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscriber::class)->ofMany(
            ['ends_at' => 'max'],
            fn (Builder $query) => $query->active(),
        );
    }

    public function isSubscribed(): bool
    {
        // relationLoaded evita una consulta por cada llamada dentro de una
        // misma petición: el layout pregunta esto varias veces por render.
        if ($this->relationLoaded('subscription')) {
            return $this->getRelation('subscription') !== null;
        }

        return $this->subscribers()->active()->exists();
    }

    public function onTrial(): bool
    {
        return $this->subscription?->onTrial() ?? false;
    }
}

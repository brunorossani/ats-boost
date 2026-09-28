<?php

namespace App\JobSearch;

final readonly class JobQuery
{
    /**
     * @param  string|null  $countryCode  ISO 3166-1 alfa-2. null = solo remoto, sin país.
     */
    public function __construct(
        public string $keywords,
        public ?string $countryCode = null,
        public ?string $city = null,
        public bool $includeRemote = true,
        public int $maxAgeDays = 3,
        public int $limit = 50,
    ) {}

    public function region(): ?string
    {
        return Regions::regionOf($this->countryCode);
    }

    public function countryName(): ?string
    {
        return Regions::name($this->countryCode);
    }

    public function locationText(): string
    {
        return collect([$this->city, $this->countryName()])->filter()->implode(', ');
    }

    public function remoteOnly(): bool
    {
        return $this->countryCode === null;
    }

    /**
     * @return list<string>
     */
    public function keywordTokens(): array
    {
        return collect(preg_split('/[\s,;\/]+/u', Regions::normalize($this->keywords)))
            ->filter(fn ($t) => mb_strlen($t) >= 2)
            ->values()
            ->all();
    }
}

<?php

namespace App\JobSearch;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class JobPosting
{
    public function __construct(
        public string $source,
        public string $externalId,
        public string $title,
        public string $company,
        public string $url,
        public string $description,
        public ?string $location = null,
        public ?string $countryCode = null,
        public bool $isRemote = false,
        public ?string $remoteZone = null,
        public ?CarbonImmutable $postedAt = null,
        public ?string $employmentType = null,
        public ?float $salaryMin = null,
        public ?float $salaryMax = null,
        public ?string $salaryCurrency = null,
    ) {}

    /**
     * Misma oferta publicada en varios portales => misma huella.
     */
    public function fingerprint(): string
    {
        $normalize = fn (string $s) => Str::of($s)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();

        return sha1(implode('|', [
            $normalize($this->company),
            $normalize($this->title),
            $this->isRemote ? 'remote' : ($this->countryCode ?? ''),
        ]));
    }

    public static function cleanText(?string $html): string
    {
        $text = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<\s*(br|\/p|\/li|\/h\d|\/div)\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\s*li[^>]*>/i', "\n• ", $text);
        $text = strip_tags($text);
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text);

        return Str::limit(trim($text), 15000, '');
    }

    public static function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $value = (int) $value;

                // Algunos portales devuelven milisegundos.
                return CarbonImmutable::createFromTimestampUTC($value > 9_999_999_999 ? intdiv($value, 1000) : $value);
            }

            return CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function money(mixed $value): ?float
    {
        return is_numeric($value) && $value > 0 ? (float) $value : null;
    }
}

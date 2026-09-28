<?php

namespace App\JobSearch;

final class Regions
{
    public const LATAM = 'latam';

    public const NORTH_AMERICA = 'north_america';

    public const EUROPE = 'europe';

    public const APAC = 'apac';

    public const MEA = 'mea';

    /**
     * code => [nombre en español, región, idioma principal, alias para matchear texto libre]
     */
    private const COUNTRIES = [
        'ar' => ['Argentina', self::LATAM, 'es', ['argentina']],
        'bo' => ['Bolivia', self::LATAM, 'es', ['bolivia']],
        'br' => ['Brasil', self::LATAM, 'pt', ['brasil', 'brazil']],
        'cl' => ['Chile', self::LATAM, 'es', ['chile']],
        'co' => ['Colombia', self::LATAM, 'es', ['colombia']],
        'cr' => ['Costa Rica', self::LATAM, 'es', ['costa rica']],
        'do' => ['República Dominicana', self::LATAM, 'es', ['república dominicana', 'dominican republic']],
        'ec' => ['Ecuador', self::LATAM, 'es', ['ecuador']],
        'gt' => ['Guatemala', self::LATAM, 'es', ['guatemala']],
        'mx' => ['México', self::LATAM, 'es', ['méxico', 'mexico']],
        'pa' => ['Panamá', self::LATAM, 'es', ['panamá', 'panama']],
        'pe' => ['Perú', self::LATAM, 'es', ['perú', 'peru']],
        'py' => ['Paraguay', self::LATAM, 'es', ['paraguay']],
        'uy' => ['Uruguay', self::LATAM, 'es', ['uruguay']],
        've' => ['Venezuela', self::LATAM, 'es', ['venezuela']],

        'us' => ['Estados Unidos', self::NORTH_AMERICA, 'en', ['united states', 'usa', 'estados unidos']],
        'ca' => ['Canadá', self::NORTH_AMERICA, 'en', ['canada', 'canadá']],

        'gb' => ['Reino Unido', self::EUROPE, 'en', ['united kingdom', 'uk', 'england', 'reino unido']],
        'ie' => ['Irlanda', self::EUROPE, 'en', ['ireland', 'irlanda']],
        'es' => ['España', self::EUROPE, 'es', ['españa', 'spain']],
        'pt' => ['Portugal', self::EUROPE, 'pt', ['portugal']],
        'fr' => ['Francia', self::EUROPE, 'fr', ['france', 'francia']],
        'de' => ['Alemania', self::EUROPE, 'de', ['germany', 'deutschland', 'alemania']],
        'it' => ['Italia', self::EUROPE, 'it', ['italy', 'italia']],
        'nl' => ['Países Bajos', self::EUROPE, 'nl', ['netherlands', 'nederland', 'países bajos', 'holanda']],
        'be' => ['Bélgica', self::EUROPE, 'nl', ['belgium', 'bélgica', 'belgique']],
        'ch' => ['Suiza', self::EUROPE, 'de', ['switzerland', 'suiza', 'schweiz']],
        'at' => ['Austria', self::EUROPE, 'de', ['austria', 'österreich']],
        'pl' => ['Polonia', self::EUROPE, 'pl', ['poland', 'polonia', 'polska']],
        'se' => ['Suecia', self::EUROPE, 'sv', ['sweden', 'suecia']],
        'dk' => ['Dinamarca', self::EUROPE, 'da', ['denmark', 'dinamarca']],

        'au' => ['Australia', self::APAC, 'en', ['australia']],
        'nz' => ['Nueva Zelanda', self::APAC, 'en', ['new zealand', 'nueva zelanda']],
        'sg' => ['Singapur', self::APAC, 'en', ['singapore', 'singapur']],
        'in' => ['India', self::APAC, 'en', ['india']],

        'za' => ['Sudáfrica', self::MEA, 'en', ['south africa', 'sudáfrica']],
        'ae' => ['Emiratos Árabes Unidos', self::MEA, 'en', ['united arab emirates', 'uae', 'dubai']],
    ];

    /**
     * Palabras que un portal usa para decir "remoto desde esta región".
     */
    private const REMOTE_ZONE_ALIASES = [
        self::LATAM => ['latam', 'latin america', 'latinoamérica', 'latinoamerica', 'south america', 'americas'],
        self::NORTH_AMERICA => ['north america', 'americas', 'us only', 'usa only'],
        self::EUROPE => ['europe', 'emea', 'eu', 'cet', 'european union'],
        self::APAC => ['apac', 'asia'],
        self::MEA => ['emea', 'middle east', 'africa'],
    ];

    private const WORLDWIDE_ALIASES = ['worldwide', 'anywhere', 'global', 'remote', 'remoto', 'any location'];

    public static function all(): array
    {
        return collect(self::COUNTRIES)
            ->map(fn (array $c) => $c[0])
            ->sort()
            ->all();
    }

    public static function exists(?string $code): bool
    {
        return $code !== null && isset(self::COUNTRIES[strtolower($code)]);
    }

    public static function name(?string $code): ?string
    {
        return $code ? (self::COUNTRIES[strtolower($code)][0] ?? null) : null;
    }

    public static function regionOf(?string $code): ?string
    {
        return $code ? (self::COUNTRIES[strtolower($code)][1] ?? null) : null;
    }

    public static function languageOf(?string $code): string
    {
        return $code ? (self::COUNTRIES[strtolower($code)][2] ?? 'en') : 'en';
    }

    /**
     * Resuelve un texto libre ("Buenos Aires, Argentina", "Remote - LATAM") a un código de país.
     */
    public static function detectCountry(?string $text): ?string
    {
        $haystack = self::normalize($text);

        if ($haystack === '') {
            return null;
        }

        foreach (self::COUNTRIES as $code => [, , , $aliases]) {
            foreach ($aliases as $alias) {
                if (preg_match('/\b'.preg_quote($alias, '/').'\b/u', $haystack)) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * ¿Una zona de trabajo remoto ("Worldwide", "LATAM only", "USA") acepta candidatos de este país?
     */
    public static function remoteZoneAccepts(?string $zone, ?string $countryCode): bool
    {
        $zone = self::normalize($zone);

        if ($zone === '' || $countryCode === null) {
            return true;
        }

        if (in_array($zone, self::WORLDWIDE_ALIASES, true) || str_contains($zone, 'worldwide') || str_contains($zone, 'anywhere')) {
            return true;
        }

        if (self::detectCountry($zone) === strtolower($countryCode)) {
            return true;
        }

        foreach (self::REMOTE_ZONE_ALIASES[self::regionOf($countryCode)] ?? [] as $alias) {
            if (preg_match('/\b'.preg_quote($alias, '/').'\b/u', $zone)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convenciones de CV por región, como instrucción para el modelo.
     */
    public static function cvConventions(?string $countryCode, bool $remote = false): string
    {
        $region = self::regionOf($countryCode);
        $country = self::name($countryCode);

        $base = match ($region) {
            self::NORTH_AMERICA => [
                'Formato résumé norteamericano.',
                'Si la oferta está en inglés, usá inglés estadounidense.',
                'No incluyas fecha de nacimiento, edad, estado civil, nacionalidad, documento de identidad ni foto; en contacto, solo ciudad y estado/provincia.',
                'Fechas con formato "Mon YYYY" (por ejemplo "Jan 2024 – Present").',
                'Viñetas que empiecen con verbos de acción y logros cuantificados.',
            ],
            self::EUROPE => [
                'Formato CV europeo.',
                'Si la oferta está en inglés, usá inglés británico (ortografía UK).',
                'Podés mantener nacionalidad o permiso de trabajo en la UE si figura en el CV base; no agregues foto ni fecha de nacimiento.',
                'Expresá el nivel de idiomas con la escala MCER (A1–C2) cuando el CV base lo permita.',
                'Fechas con formato "MM/AAAA" o "Mes AAAA", de forma consistente.',
            ],
            self::LATAM => [
                'Formato de CV latinoamericano.',
                'Usá un español neutro, sin regionalismos.',
                'Podés incluir ciudad y país en el contacto; no agregues foto, DNI ni estado civil.',
                'Fechas con formato "Mes AAAA" (por ejemplo "Ene 2024 – Actualidad").',
            ],
            default => [
                'Formato de CV internacional.',
                'No incluyas datos personales sensibles (fecha de nacimiento, estado civil, documento, foto).',
                'Fechas con formato "Mon YYYY".',
            ],
        };

        $lines = $base;

        if ($country) {
            $lines[] = "El puesto es para {$country}.";
        }

        if ($remote) {
            $lines[] = 'El puesto es remoto: destacá experiencia trabajando en remoto, con equipos distribuidos o en otras zonas horarias si figura en el CV base.';
        }

        return implode("\n", array_map(fn ($l) => "- {$l}", $lines));
    }

    public static function normalize(?string $text): string
    {
        return trim(mb_strtolower((string) $text));
    }
}

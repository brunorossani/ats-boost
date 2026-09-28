<?php

namespace App\Enums;

enum Language: string
{
    case Spanish = 'es';
    case English = 'en';

    /**
     * Detecta el idioma por frecuencia de palabras funcionales.
     *
     * Se resuelve localmente a propósito: la versión anterior gastaba una
     * llamada al modelo (y ~1s de latencia) para elegir entre dos opciones.
     */
    public static function detect(string $text, self $fallback = self::English): self
    {
        $haystack = ' '.mb_strtolower(preg_replace('/\s+/u', ' ', $text)).' ';

        $spanish = [' el ', ' la ', ' los ', ' las ', ' de ', ' del ', ' que ', ' con ', ' para ', ' por ',
            'experiencia', 'educación', 'habilidades', 'empresa', 'trabajo', 'conocimientos', 'requisitos'];

        $english = [' the ', ' and ', ' of ', ' to ', ' for ', ' with ', ' in ', ' is ',
            'experience', 'education', 'skills', 'company', 'requirements', 'responsibilities', 'knowledge'];

        $score = static fn (array $needles): int => array_sum(
            array_map(fn (string $needle): int => mb_substr_count($haystack, $needle), $needles)
        );

        $spanishScore = $score($spanish);
        $englishScore = $score($english);

        if ($spanishScore === $englishScore) {
            return $fallback;
        }

        return $spanishScore > $englishScore ? self::Spanish : self::English;
    }

    public function isSpanish(): bool
    {
        return $this === self::Spanish;
    }

    /**
     * Nombre del idioma tal como debe aparecer en el prompt.
     */
    public function promptName(): string
    {
        return match ($this) {
            self::Spanish => 'Spanish (español)',
            self::English => 'English',
        };
    }

    /**
     * Elige entre dos valores según el idioma. Evita el `if ($lang === 'es')`
     * repetido en cada método de los servicios viejos.
     */
    public function pick(mixed $spanish, mixed $english): mixed
    {
        return $this->isSpanish() ? $spanish : $english;
    }

    /**
     * Locale de Carbon para formatear fechas dentro del PDF.
     */
    public function locale(): string
    {
        return $this->value;
    }
}

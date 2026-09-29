<?php

namespace App\Enums;

enum ResumeTemplate: string
{
    case Claude = 'claude';
    case ClaudePhoto = 'claude-photo';
    case Modern = 'modern';
    case Compact = 'compact';

    public function label(): string
    {
        return match ($this) {
            self::Claude => 'Claude Design',
            self::ClaudePhoto => 'Claude Design (con foto)',
            self::Modern => 'Moderna',
            self::Compact => 'Compacta',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Claude => 'Diseño profesional con encabezado centrado, secciones con bordes y grid de skills.',
            self::ClaudePhoto => 'Diseño profesional con foto en el encabezado, ideal para mercados que la requieren.',
            self::Modern => 'Encabezado centrado, secciones con línea divisoria y fechas alineadas a la derecha.',
            self::Compact => 'La misma estructura con menos aire, pensada para currículums de más de una página.',
        };
    }

    public function view(): string
    {
        return "pdf.resume.{$this->value}";
    }

    /**
     * Escala tipográfica en puntos. La plantilla compacta reduce cuerpo e
     * interlineado sin cambiar la jerarquía visual.
     *
     * @return array<string, float|string>
     */
    public function metrics(): array
    {
        return match ($this) {
            self::Claude, self::ClaudePhoto => [
                'base' => 10.8,
                'name' => 22.5,
                'section' => 12.3,
                'line_height' => 1.24,
                'entry_gap' => 3,
                'section_gap' => 5.5,
                'margin' => '15.2mm', // 0.6in del original
            ],
            self::Modern => [
                'base' => 9.5,
                'name' => 22,
                'section' => 11,
                'line_height' => 1.42,
                'entry_gap' => 9,
                'section_gap' => 14,
                'margin' => '13mm 15mm',
            ],
            self::Compact => [
                'base' => 8.8,
                'name' => 19,
                'section' => 10,
                'line_height' => 1.32,
                'entry_gap' => 6,
                'section_gap' => 10,
                'margin' => '10mm 13mm',
            ],
        };
    }
}

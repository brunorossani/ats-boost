<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Smalot\PdfParser\Parser;

/**
 * El modelo nunca genera HTML: devuelve JSON (ver resources/prompts/cv-tailor-system.md)
 * y resources/views/cv/template.blade.php lo pinta siempre con la misma escala
 * tipográfica, para que ningún CV generado rompa la carilla A4 ni varíe de diseño
 * entre generaciones.
 */
class CvTailorService
{
    private const MAX_GENERATION_ATTEMPTS = 2; // intento inicial + 1 reintento

    public function __construct(private AnthropicService $anthropic) {}

    /**
     * Flujo completo de adaptación de CV.
     *
     * @return array{cv: array, html: string}
     */
    public function tailorResume(string $resumePath, string $jobDescription): array
    {
        $cvText = $this->extractCvText($resumePath);

        $cv = $this->generateTailoredCv($cvText, $jobDescription);
        $cv = $this->ensureFitsOnePage($cv, $cvText, $jobDescription);

        return [
            'cv' => $cv,
            'html' => $this->renderHtml($cv),
            'cvText' => $cvText,
        ];
    }

    /**
     * Descarga el PDF final a partir del $cv ya generado (no vuelve a llamar a la IA).
     */
    public function downloadPdf(array $cv)
    {
        $pdf = $this->renderPdf($cv);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $this->buildFileName($cv['name'] ?? null)
        );
    }

    /**
     * -----------------------------
     * Generación (IA)
     * -----------------------------
     */
    private function generateTailoredCv(string $cvText, string $jobDescription, ?string $extraInstruction = null): array
    {
        $system = $this->systemPrompt();
        $prompt = $this->buildUserPrompt($cvText, $jobDescription, $extraInstruction);

        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            try {
                $raw = $this->anthropic->completeWithSystem(
                    prompt: $prompt,
                    system: $system,
                    assistantPrefill: '{',
                );

                $cv = $this->parseAndValidate($raw);

                return $cv;
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning('CvTailorService: intento de generación de CV falló', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new RuntimeException('No se pudo generar un CV adaptado válido.', previous: $lastError);
    }

    private function systemPrompt(): string
    {
        return trim(file_get_contents(resource_path('prompts/cv-tailor-system.md')));
    }

    private function buildUserPrompt(string $cvText, string $jobDescription, ?string $extraInstruction): string
    {
        $prompt = "--- CV BASE ---\n{$cvText}\n\n--- OFERTA DE TRABAJO ---\n{$jobDescription}";

        if ($extraInstruction) {
            $prompt .= "\n\n--- INSTRUCCIÓN ADICIONAL ---\n{$extraInstruction}";
        }

        return $prompt;
    }

    private function parseAndValidate(string $raw): array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(json)?|```$/im', '', $raw);
        $raw = trim($raw);

        $cv = json_decode($raw, true);

        if (! is_array($cv) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Claude no devolvió JSON válido.');
        }

        if (! $this->matchesSchema($cv)) {
            throw new RuntimeException('El JSON devuelto no respeta el esquema esperado.');
        }

        return $cv;
    }

    /**
     * Validación estructural liviana: alcanza para detectar una respuesta rota o
     * truncada sin tener que instalar una librería de JSON Schema completa.
     */
    private function matchesSchema(array $cv): bool
    {
        if (! is_string($cv['name'] ?? null) || $cv['name'] === '') {
            return false;
        }

        if (! is_string($cv['headline'] ?? null)) {
            return false;
        }

        if (! is_string($cv['lang'] ?? null)) {
            return false;
        }

        if (! is_array($cv['contact'] ?? null)) {
            return false;
        }

        foreach ($cv['contact'] as $item) {
            if (! is_array($item) || ! is_string($item['label'] ?? null)) {
                return false;
            }
        }

        if (! is_array($cv['sections'] ?? null)) {
            return false;
        }

        foreach ($cv['sections'] as $section) {
            if (! $this->matchesSectionSchema($section)) {
                return false;
            }
        }

        return true;
    }

    private function matchesSectionSchema($section): bool
    {
        if (! is_array($section)) {
            return false;
        }

        if (! is_string($section['heading'] ?? null)) {
            return false;
        }

        $type = $section['type'] ?? null;

        if (! in_array($type, ['entries', 'list', 'grid'], true)) {
            return false;
        }

        if (! is_array($section['items'] ?? null)) {
            return false;
        }

        foreach ($section['items'] as $item) {
            if (! is_array($item)) {
                return false;
            }

            $valid = match ($type) {
                'entries' => is_string($item['title'] ?? null) && is_string($item['dates'] ?? null),
                'list' => is_string($item['title'] ?? null) && is_string($item['description'] ?? null),
                'grid' => is_string($item['label'] ?? null) && is_string($item['value'] ?? null),
                default => false,
            };

            if (! $valid) {
                return false;
            }
        }

        return true;
    }

    /**
     * -----------------------------
     * Presupuesto de una carilla
     * -----------------------------
     */
    private function ensureFitsOnePage(array $cv, string $cvText, string $jobDescription): array
    {
        try {
            $pages = $this->renderPdf($cv)->getDomPDF()->getCanvas()->get_page_count();
        } catch (\Throwable $e) {
            Log::warning('CvTailorService: no se pudo verificar la cantidad de páginas', [
                'error' => $e->getMessage(),
            ]);

            return $cv;
        }

        if ($pages <= 1) {
            return $cv;
        }

        Log::info('CvTailorService: el CV generado ocupó más de una carilla, recortando y reintentando', [
            'pages' => $pages,
        ]);

        try {
            return $this->generateTailoredCv(
                $cvText,
                $jobDescription,
                extraInstruction: 'La generación anterior no entró en una sola carilla A4. '
                    .'Recortá un escalón más: menos viñetas por empleo, viñetas más cortas, '
                    .'menos filas de habilidades. Nunca borres las métricas de las viñetas que sí queden.'
            );
        } catch (\Throwable $e) {
            // Si el reintento con presupuesto más chico también falla, nos quedamos
            // con la primera versión válida en vez de dejar al usuario sin nada.
            Log::warning('CvTailorService: el reintento con presupuesto reducido falló, se usa la versión original', [
                'error' => $e->getMessage(),
            ]);

            return $cv;
        }
    }

    /**
     * -----------------------------
     * Render
     * -----------------------------
     */
    private function renderHtml(array $cv): string
    {
        return view('cv.template', ['cv' => $cv])->render();
    }

    private function renderPdf(array $cv)
    {
        return Pdf::loadView('cv.template', ['cv' => $cv])->setPaper('a4', 'portrait');
    }

    /**
     * -----------------------------
     * File helpers
     * -----------------------------
     */
    private function buildFileName(?string $name): string
    {
        $name = $name ?: 'Nombre Desconocido';

        return preg_replace('/[\/\\\\]/', '-', "{$name}.pdf");
    }

    /**
     * -----------------------------
     * CV extraction
     * -----------------------------
     */
    private function extractCvText(string $path): string
    {
        if (! file_exists($path)) {
            throw new RuntimeException('Archivo de CV no encontrado.');
        }

        if (str_ends_with(strtolower($path), '.pdf')) {
            return $this->extractPdf($path);
        }

        return trim(file_get_contents($path));
    }

    private function extractPdf(string $path): string
    {
        try {
            return trim((new Parser)->parseFile($path)->getText());
        } catch (\Throwable) {
            throw new RuntimeException('No se puede leer el PDF del CV.');
        }
    }
}

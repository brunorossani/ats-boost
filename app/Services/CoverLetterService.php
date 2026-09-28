<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use RuntimeException;
use Smalot\PdfParser\Parser;

class CoverLetterService
{
    public function __construct(private AnthropicService $anthropic) {}

    /**
     * Detecta el idioma principal del texto (español, inglés, etc.)
     */
    private function detectLanguage(string $text): string
    {
        // Palabras clave en español
        $spanishKeywords = ['el ', 'la ', 'de ', 'que ', 'experiencia', 'educación', 'habilidades', 'trabajo', 'empresa', 'año', 'descripción'];

        // Palabras clave en inglés
        $englishKeywords = ['the ', 'and ', 'a ', 'to ', 'experience', 'education', 'skills', 'job', 'company', 'year', 'description'];

        // Convertir a minúsculas para análisis
        $textLower = strtolower($text);

        // Contar coincidencias
        $spanishCount = 0;
        $englishCount = 0;

        foreach ($spanishKeywords as $keyword) {
            $spanishCount += substr_count($textLower, $keyword);
        }

        foreach ($englishKeywords as $keyword) {
            $englishCount += substr_count($textLower, $keyword);
        }

        // Retornar idioma con mayor coincidencia
        return $spanishCount > $englishCount ? 'es' : 'en';
    }

    /**
     * Obtiene el prompt dinámico según el idioma
     */
    private function getPromptInferCandidateProfile(string $language, string $cvText): string
    {
        $currentDate = Carbon::now()->format('d/m/Y');

        if ($language === 'es') {
            return "
            Hoy es: {$currentDate}

            Extrae el nombre completo del candidato y su rol profesional principal del CV a continuación.

            REGLAS:
            - Usa SOLO el contenido del CV.
            - NO adivines datos faltantes.
            - Si no está claro, devuelve valores null.
            - Devuelve JSON ESTRICTO.

            FORMATO JSON:
            {
                \"name\": string|null,
                \"role\": string|null
            }

            --- CV ---
            {$cvText}
        ";
        }

        // English version
        return "
            Today is: {$currentDate}

            Extract the full name of the candidate and their main professional role from the CV below.

            RULES:
            - Use ONLY the CV content.
            - Do NOT guess missing data.
            - If unclear, return null values.
            - Return STRICT JSON.

            JSON FORMAT:
            {
                \"name\": string|null,
                \"role\": string|null
            }

            --- CV ---
            {$cvText}
        ";
    }

    /**
     * Obtiene el prompt dinámico para generar carta de presentación según idioma
     */
    private function getPromptGenerateCoverLetter(string $language, string $candidateName, string $candidateRole, string $cvText, string $jobOffer): string
    {
        $currentDate = Carbon::now()->format('d/m/Y');

        if ($language === 'es') {
            return "
            Eres un motor de generación profesional de cartas de presentación.

            Hoy es: {$currentDate}

            Genera una carta de presentación en HTML puro basada en:
            - Perfil del candidato
            - Descripción del trabajo

            ====================
            REGLAS GLOBALES
            ====================

            - Devuelve SOLO HTML válido.
            - NO incluyas <html>, <head>, <body>, ni estilos.
            - Estructura:
              <h1>Candidato Name</h1>
              <h2>Fecha</h2>
              <p>párrafos de la carta...</p>
            - Usa <strong> para énfasis.
            - Los párrafos deben ser naturales y persuasivos.
            - La carta debe dirigirse al empleador.
            - Máximo 4-5 párrafos.

            ====================
            DATOS DEL CANDIDATO
            ====================

            Nombre: {$candidateName}
            Rol: {$candidateRole}
            CV:
            {$cvText}

            ====================
            DESCRIPCIÓN DEL TRABAJO
            ====================

            {$jobOffer}

            Genera la carta ahora:
        ";
        }

        // English version
        return "
            You are a professional cover letter generation engine.

            Today is: {$currentDate}

            Generate a cover letter in pure HTML based on:
            - Candidate profile
            - Job description

            ====================
            GLOBAL RULES
            ====================

            - Return ONLY valid HTML.
            - Do NOT include <html>, <head>, <body>, or styles.
            - Structure:
              <h1>Candidate Name</h1>
              <h2>Date</h2>
              <p>paragraphs of the letter...</p>
            - Use <strong> for emphasis.
            - Paragraphs should be natural and persuasive.
            - The letter should address the employer.
            - Maximum 4-5 paragraphs.

            ====================
            CANDIDATE DATA
            ====================

            Name: {$candidateName}
            Role: {$candidateRole}
            CV:
            {$cvText}

            ====================
            JOB DESCRIPTION
            ====================

            {$jobOffer}

            Generate the letter now:
        ";
    }

    public function generatePdf(string $html): string
    {
        return Pdf::loadHTML("
            <html>
                <head>
                    <style>
                        body {
                            font-family: Calibri, sans-serif;
                            font-size: 12px;
                            line-height: 1.5;
                            margin: 30px;
                        }
                        h1 {
                            font-size: 18px;
                            margin-bottom: 4px;
                        }
                        h2 {
                            font-size: 13px;
                            font-weight: normal;
                            margin-bottom: 16px;
                        }
                        p {
                            margin: 0 0 10px 0;
                        }
                    </style>
                </head>
                <body>
                    {$html}
                </body>
            </html>
        ")->output();
    }

    public function extractCvText(string $path): string
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
            $parser = new Parser;
            $pdf = $parser->parseFile($path);

            return trim($pdf->getText());
        } catch (\Throwable) {
            throw new RuntimeException('No se puede extraer texto del PDF de CV.');
        }
    }

    /**
     * Inferir nombre del candidato y rol profesional del CV usando IA
     */
    public function inferCandidateProfile(string $cvText, string $language = 'en'): array
    {
        $prompt = $this->getPromptInferCandidateProfile($language, $cvText);

        return json_decode(
            $this->anthropic->complete($prompt, maxTokens: 256),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }

    public function buildFileName(
        ?string $name,
        ?string $role,
        ?string $company = null
    ): string {
        $name ??= 'Carta de Presentación';
        $rolePart = $role ? " - {$role}" : '';
        $companyPart = $company ? " - {$company}" : '';

        return preg_replace(
            '/[\/\\\\]/',
            '-',
            "{$name}{$rolePart}{$companyPart}.pdf"
        );
    }

    /**
     * Generar carta de presentación con encabezado inferido
     */
    public function generateCoverLetter(
        string $description,
        string $cvText,
        ?string $company = null
    ): string {
        $language = $this->detectLanguage($description);
        $profile = $this->inferCandidateProfile($cvText, $language);

        $name = $profile['name'] ?? ($language === 'es' ? 'Candidato' : 'Candidate');
        $role = $profile['role'] ?? null;

        $prompt = $this->getPromptGenerateCoverLetter($language, $name, (string) $role, $cvText, $description);

        if ($language === 'es') {
            $placeholderLetter = 'Carta de Presentación';
        } else {
            $placeholderLetter = 'Cover Letter';
        }

        return $this->anthropic->complete($prompt);
    }
}

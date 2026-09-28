<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use RuntimeException;
use Smalot\PdfParser\Parser;

class AnalyzeResumeService
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

    /* =========================
     * API PÚBLICA
     * ========================= */

    public function analyzeResume($resume): int
    {
        $cvText = $this->extractText($resume->getRealPath());

        return $this->analyzeWithAI($cvText);
    }

    public function improveResume($resume, ?string $jobDescription = null): array
    {
        $cvText = $this->extractText($resume->getRealPath());
        // Use job description language if provided, otherwise use CV language
        $language = $jobDescription ? $this->detectLanguage($jobDescription) : $this->detectLanguage($cvText);

        $name = $this->extractNameFromCv($cvText, $language);
        $contactLine = $this->extractContactLine($cvText, $language);

        $bodyHtml = $this->improveWithAI($cvText, $language);

        $placeholderName = $language === 'es' ? 'Insertar nombre aquí' : 'Insert name here';
        $placeholderContact = $language === 'es'
            ? 'Insertar ciudad, país • Insertar correo • Insertar teléfono'
            : 'Insert city, country • Insert email • Insert phone';

        return [
            'html' => $this->buildHeaderHtml(
                $name ?: $placeholderName,
                $contactLine ?: $placeholderContact
            ) . $bodyHtml,
            'name' => $name,
            'cvText' => $cvText,
        ];
    }

    public function downloadPdf(string $html, string $candidateName)
    {
        $pdf = $this->generatePdf($html);

        return response()->streamDownload(
            fn() => print($pdf),
            $this->buildFileName($candidateName)
        );
    }

    /* =========================
     * EXTRACCIÓN DE TEXTO
     * ========================= */

    private function extractText(string $path): string
    {
        if (! file_exists($path)) {
            throw new RuntimeException('CV no encontrado.');
        }

        if (str_ends_with(strtolower($path), '.pdf')) {
            return trim((new Parser)->parseFile($path)->getText());
        }

        return trim(file_get_contents($path));
    }

    /**
     * Obtiene prompt dinámico para análisis de ATS según idioma
     */
    private function getPromptAnalyzeATS(string $language, string $cv): string
    {
        $currentDate = Carbon::now()->format('d/m/Y');

        if ($language === 'es') {
            return "
            Eres un motor de puntuación ATS integrado en un SaaS de optimización de CV.

            Hoy es: {$currentDate}

            IMPORTANTE:
            Esta plataforma tiene un estándar muy estricto de formato y escritura de CV.
            Los CV producidos por el motor de mejora propio de la plataforma se consideran
            casi óptimos por definición.

            Tu trabajo NO es juzgar la perfección general del CV,
            sino medir qué tan de cerca el CV coincide con el estándar de ESTA plataforma.

            Devuelve SOLO un número entre 0 y 100.

            ====================
            ANCLAJE DE PUNTUACIÓN (CRÍTICO)
            ====================

            - Si el CV ya sigue la estructura de la plataforma, reglas de formato
            y estilo de escritura, DEBE puntuarse entre 80 y 100.
            - Solo deducir puntos por desviaciones claras del estándar de la plataforma.
            - NO penalizar por métricas faltantes, nivel de antigüedad,
            o preferencias subjetivas.
            - Asumir que las opciones de redacción son intencionales a menos que sean claramente débiles.

            ====================
            CUMPLIMIENTO DE FORMATO (PESO PRIMARIO)
            ====================

            Recompensa fuertemente lo siguiente:

            - Títulos de sección claramente separados (preferiblemente encabezados)
            - Entradas de Experiencia Laboral estructuradas EXACTAMENTE como:
            1) Título del Trabajo – Empresa (sin fechas, sin descripciones)
            2) Ubicación — Rango de fechas
            3) Puntos de viñeta para logros
            - Entradas de Educación usando el mismo patrón estricto
            - Sin tablas, columnas, iconos, gráficos o diseño decorativo
            - Estructura limpia y segura para ATS

            Un CV que sigue este formato ya debería puntuarse muy alto.

            ====================
            CALIDAD DE CONTENIDO (PESO SECUNDARIO)
            ====================

            Evalúa SOLO problemas obvios:

            - Presencia de verbos de acción en viñetas
            - Fraseología orientada a logros cuando sea posible
            - Lenguaje claro y conciso
            - Orden lógico de secciones

            NO penalizar agresivamente diferencias estilísticas.

            ====================
            LEGIBILIDAD DE ATS (LÍNEA BASE)
            ====================

            - Texto plano o HTML simple
            - Formato consistente
            - Fácil de analizar por sistemas ATS

            ====================
            REGLA FINAL
            ====================

            - Los CV que se ven como si fueron generados por el motor de
            mejora de la plataforma naturalmente deben puntuarse 80–100.
            - Reservar puntuaciones por debajo de 70 SOLO para CV que claramente requieren reestructuración.

            CV:
                {$cv}
            ";
        }

        // English version
        return "
            You are an ATS scoring engine integrated into a CV optimization SaaS.

            IMPORTANT:
            This platform has a very strict standard for CV format and writing.
            CVs produced by the platform's own improvement engine are considered
            nearly optimal by definition.

            Your job is NOT to judge overall CV perfection,
            but to measure how closely the CV matches THIS platform's standard.

            Return ONLY a number between 0 and 100.

            ====================
            SCORE ANCHORING (CRITICAL)
            ====================

            - If the CV already follows the platform structure, formatting rules,
            and writing style, it MUST score between 80 and 100.
            - Only deduct points for clear deviations from the platform standard.
            - Do NOT penalize for missing metrics, seniority level,
            or subjective preferences.
            - Assume writing choices are intentional unless clearly weak.

            ====================
            FORMAT COMPLIANCE (PRIMARY WEIGHT)
            ====================

            Reward the following strongly:

            - Clearly separated section titles (preferably headings)
            - Work Experience entries structured EXACTLY as:
            1) Job Title – Company (no dates, no descriptions)
            2) Location — Date Range
            3) Bullet points for achievements
            - Education entries using the same strict pattern
            - No tables, columns, icons, graphics, or decorative design
            - Clean, ATS-safe structure

            A CV following this format should already score very high.

            ====================
            CONTENT QUALITY (SECONDARY WEIGHT)
            ====================

            Evaluate ONLY obvious issues:

            - Presence of action verbs in bullets
            - Achievement-oriented phrasing where possible
            - Clear, concise language
            - Logical section order

            Do NOT aggressively penalize stylistic differences.

            ====================
            ATS READABILITY (BASELINE)
            ====================

            - Plain text or simple HTML
            - Consistent formatting
            - Easy to parse by ATS systems

            ====================
            FINAL RULE
            ====================

            - CVs that look like they were generated by the platform's
            improvement engine should naturally score 80–100.
            - Reserve scores below 70 ONLY for CVs that clearly need restructuring.

            CV:
                {$cv}
            ";
    }

    /**
     * Obtiene prompt dinámico para mejora de CV según idioma
     */
    private function getPromptImproveCV(string $language, string $cvText): string
    {
        $currentDate = Carbon::now()->format('d/m/Y');

        if ($language === 'es') {
            return "
            Eres un motor profesional de reescritura de CV.

            Hoy es: {$currentDate}

            Reescribe y mejora el CV para que sea compatible con ATS y esté estructurado profesionalmente.
            NO lo adaptes a ninguna oferta de trabajo específica.
            NO inventes información.

            ====================
            REGLAS DE ESTRUCTURA GLOBAL
            ====================

            - Los títulos de sección SIEMPRE deben renderizarse como <h2> con texto en negrita.
            - Los títulos de sección NO deben ser viñetas.
            - Devuelve SOLO HTML limpio.
            - NO incluyas perfil o resumen.
            - NO incluyas nombre o datos de contacto.
            - Nunca inventes información.
            - Usa SOLO estas etiquetas HTML:
            h1, h2, p, ul, li, strong, em, u, span, br.

            ====================
            ALCANCE DE FORMATO ESTRICTO (MUY IMPORTANTE)
            ====================

            Las reglas de formato ESTRICTO que se describen a continuación aplican SOLO a:
            - Entradas de Experiencia Laboral
            - Entradas de Educación

            NO aplican a:
            - Habilidades
            - Habilidades Técnicas
            - Certificaciones
            - Proyectos Académicos
            - Idiomas
            - Herramientas
            - Habilidades Blandas
            - Cualquier otra sección no explícitamente listada arriba

            Todas las otras secciones DEBEN renderizarse en un formato de CV natural,
            usando párrafos y/o viñetas según corresponda.

            ====================
            FORMATO DE EXPERIENCIA LABORAL (ESTRICTO – SIN EXCEPCIONES)
            ====================

            Cada entrada de experiencia laboral DEBE seguir EXACTAMENTE esta estructura:

            1) PRIMER <p> — LÍNEA DE TÍTULO
            - Envuelto en <strong>
            - Contenido SOLO:
            Título del Trabajo – Empresa / Nombre de la Experiencia
            - NO DEBE incluir:
            Fechas, ubicación, descripciones, responsabilidades, habilidades o contexto extra

            2) SEGUNDO <p> — LÍNEA META
            - Texto plano (SIN <strong>)
            - Contenido SOLO:
            Ubicación — Rango de fechas
            - El formato de fecha DEBE ser:
            Mes YYYY – Mes YYYY
            O
            Mes YYYY – Presente

            3) DESPUÉS de estos dos <p>:
            - Responsabilidades / logros DEBEN ser viñetas (<ul><li>)
            - MÁXIMO 5 viñetas por rol

            ====================
            FORMATO DE EDUCACIÓN (ESTRICTO, DESCRIPCIÓN PERMITIDA)
            ====================

            Cada entrada de Educación DEBE seguir EXACTAMENTE esta estructura:

            1) PRIMER <p>
            - Envuelto en <strong>
            - Contenido:
            Título / Programa – Institución

            2) SEGUNDO <p>
            - Texto plano (SIN <strong>)
            - Contenido:
            Ubicación — Rango de fechas O graduación esperada

            3) TERCER <p> OPCIONAL (PERMITIDO Y ALENTADO):
            - Texto plano
            - Usado para describir:
            - Campo de estudio
            - Campus
            - Cursos relevantes
            - DEBE ser conciso
            - DEBE reflejar información presente en el CV original
            - NO DEBE incluir viñetas

            ====================
            TODAS LAS OTRAS SECCIONES (FORMATO NORMAL)
            ====================

            Para secciones como Habilidades, Proyectos Académicos, Certificaciones, Idiomas, etc.:

            - Usa un formato de CV natural
            - Las viñetas están permitidas y alentadas donde corresponda
            - No se requiere estructura de párrafo estricta
            - NO fuerces el formato Experiencia/Educación aquí

            --- CV ORIGINAL ---
            {$cvText}
            ";
        }

        // English version
        return "
            You are a professional CV rewriting engine.

            Rewrite and improve the CV to be ATS-compatible and professionally structured.
            Do NOT adapt it to any specific job offer.
            Do NOT invent information.

            ====================
            GLOBAL STRUCTURE RULES
            ====================

            - Section titles MUST ALWAYS be rendered as <h2> with bold text.
            - Section titles should NOT be bullet points.
            - Return ONLY clean HTML.
            - Do NOT include profile or summary.
            - Do NOT include name or contact information.
            - Never invent information.
            - Use ONLY these HTML tags:
            h1, h2, p, ul, li, strong, em, u, span, br.

            ====================
            STRICT FORMAT SCOPE (VERY IMPORTANT)
            ====================

            The strict formatting rules described below apply ONLY to:
            - Work Experience entries
            - Education entries

            They do NOT apply to:
            - Skills
            - Technical Skills
            - Certifications
            - Academic Projects
            - Languages
            - Tools
            - Soft Skills
            - Any other section not explicitly listed above

            All other sections MUST be rendered in a natural CV format,
            using paragraphs and/or bullet points as appropriate.

            ====================
            WORK EXPERIENCE FORMAT (STRICT – NO EXCEPTIONS)
            ====================

            Each work experience entry MUST follow EXACTLY this structure:

            1) FIRST <p> — TITLE LINE
            - Wrapped in <strong>
            - Content ONLY:
            Job Title – Company / Experience Name
            - Must NOT include:
            Dates, location, descriptions, responsibilities, skills or extra context

            2) SECOND <p> — META LINE
            - Plain text (NO <strong>)
            - Content ONLY:
            Location — Date Range
            - Date format MUST be:
            Month YYYY – Month YYYY
            OR
            Month YYYY – Present

            3) AFTER these two <p>:
            - Responsibilities / achievements MUST be bullet points (<ul><li>)
            - MAXIMUM 5 bullets per role

            ====================
            EDUCATION FORMAT (STRICT, DESCRIPTION ALLOWED)
            ====================

            Each Education entry MUST follow EXACTLY this structure:

            1) FIRST <p>
            - Wrapped in <strong>
            - Content:
            Degree / Program – Institution

            2) SECOND <p>
            - Plain text (NO <strong>)
            - Content:
            Location — Date Range OR expected graduation

            3) THIRD <p> OPTIONAL (ALLOWED AND ENCOURAGED):
            - Plain text
            - Used to describe:
            - Field of study
            - Campus
            - Relevant courses
            - MUST be concise
            - MUST reflect information present in original CV
            - Must NOT include bullet points

            ====================
            ALL OTHER SECTIONS (NORMAL FORMAT)
            ====================

            For sections like Skills, Academic Projects, Certifications, Languages, etc.:

            - Use a natural CV format
            - Bullet points are allowed and encouraged where appropriate
            - No strict paragraph structure required
            - Do NOT force Experience/Education format here

            --- ORIGINAL CV ---
            {$cvText}
            ";
    }

    /* =========================
     * IA — ANÁLISIS
     * ========================= */

    private function analyzeWithAI(string $cv): int
    {
        $language = $this->detectLanguage($cv);
        $prompt = $this->getPromptAnalyzeATS($language, $cv);

        $response = $this->anthropic->complete($prompt, maxTokens: 32);

        return (int) filter_var($response, FILTER_SANITIZE_NUMBER_INT);
    }

    /* =========================
     * IA — MEJORA (HTML)
     * ========================= */
    private function improveWithAI(string $cvText, string $language = 'en'): string
    {
        $prompt = $this->getPromptImproveCV($language, $cvText);

        return $this->anthropic->complete($prompt);
    }

    /* =========================
     * IA — EXTRACCIÓN
     * ========================= */

    private function extractNameFromCv(string $cvText, string $language = 'en'): string
    {
        $currentDate = Carbon::now()->format('d/m/Y');

        if ($language === 'es') {
            $prompt = "Hoy es: {$currentDate}\n\nExtrae el nombre completo del candidato. Devuelve SOLO el nombre o \"Desconocido\".\n\n{$cvText}";
        } else {
            $prompt = "Today is: {$currentDate}\n\nExtract the full name of the candidate. Return ONLY the name or \"Unknown\".\n\n{$cvText}";
        }

        return $this->anthropic->complete($prompt, maxTokens: 128);
    }

    private function extractContactLine(string $cvText, string $language = 'en'): string
    {
        $currentDate = Carbon::now()->format('d/m/Y');

        if ($language === 'es') {
            $prompt = "Hoy es: {$currentDate}\n\nExtrae ubicación, correo y teléfono en UNA línea separados por •.\n\n{$cvText}";
        } else {
            $prompt = "Today is: {$currentDate}\n\nExtract location, email and phone in ONE line separated by •.\n\n{$cvText}";
        }

        return $this->anthropic->complete($prompt, maxTokens: 128);
    }

    /* =========================
     * AYUDANTES DE PDF
     * ========================= */

    private function buildHeaderHtml(string $name, string $contactLine): string
    {
        return <<<HTML
            <h1>{$name}</h1>
            <span>{$contactLine}</span>
        HTML;
    }

    private function buildFileName(?string $name): string
    {
        return preg_replace('/[\/\\\\]/', '-', ($name ?: 'Desconocido') . '.pdf');
    }

    private function generatePdf(string $html): string
    {
        return Pdf::loadHTML("
            <html>
                <head>
                    <style>
                        body {
                            font-family: 'Calibri', sans-serif;
                            font-size: 12px; /* normal text */
                            font-weight: normal;
                            line-height: 1.4;
                            margin: 30px;
                        }

                        h1 {
                            font-family: 'Calibri', sans-serif;
                            font-size: 14px; /* Calibri bold 14 */
                            font-weight: bold;
                            margin-bottom: 4px;
                            text-align: center;
                            border-bottom: 1px solid #000;
                            padding-bottom: 4px;
                        }

                        h2, h3, h4, h5, h6 {
                            font-family: 'Calibri', sans-serif;
                            font-size: 12px; /* headings 11 */
                            font-weight: bold; /* bold for all subheadings */
                            margin-top: 15px;
                            margin-bottom: 4px;
                            border-bottom: 1px solid #000;
                            padding-bottom: 4px;
                        }

                        span {
                            font-family: 'Calibri', sans-serif;
                            font-size: 12px;
                            display: block;
                            text-align: center;
                        }

                        p {
                            margin-top: 0px;
                            padding-top: 0px;
                            margin-bottom: 0px;
                            padding-bottom: 0px;
                        }

                        ul {
                            margin-top: 0px;
                            padding-top: 0px;
                            margin-bottom: 0px;
                            padding-bottom: 0px;
                        }

                        li {
                            margin-top: 0px;
                            padding-top: 0px;
                            margin-bottom: 0px;
                            padding-bottom: 0px;
                        }
                    </style>
                </head>
                <body>
                    $html
                </body>
            </html>
        ")->output();
    }
}

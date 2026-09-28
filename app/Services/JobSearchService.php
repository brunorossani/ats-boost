<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Búsqueda real de vacantes vía la API de Composio (herramientas de búsqueda
 * web basadas en Exa). No hay una API de "buscar empleos" real disponible
 * para LinkedIn (solo da acceso al perfil propio del usuario autenticado) ni
 * para Uruguay/LatAm en los conectores de portales de empleo dedicados
 * (ZipRecruiter/Dice son solo EE.UU./Canadá) — así que esto usa búsqueda web
 * general apuntada a los portales relevantes, y filtra el ruido (perfiles de
 * personas, páginas de listado de empresa) para quedarse con postings reales.
 */
class JobSearchService
{
    private string $apiKey;

    private string $baseUri;

    /**
     * Dominios donde una URL encontrada casi seguro es un posting real (ATS
     * de terceros usados por empresas para publicar vacantes individuales).
     */
    private const ATS_DOMAINS = [
        'breezy.hr', 'greenhouse.io', 'lever.co', 'workable.com',
        'gupy.io', 'personio.com', 'ashbyhq.com', 'bamboohr.com',
        'recruitee.com', 'smartrecruiters.com',
    ];

    /**
     * Patrones de URL que indican un posting INDIVIDUAL (no un listado ni un
     * perfil de persona) en los portales grandes.
     */
    private const POSTING_PATH_PATTERNS = [
        '/\/jobs\/view\//i',                 // LinkedIn: job individual
        '/\/viewjob\b/i',                    // Indeed: job individual
        '/-oferta-de-trabajo-/i',            // Computrabajo: job individual
        '/\/empleos\/oferta\//i',
    ];

    /**
     * Patrones que indican ruido: perfiles de personas, listados/categorías,
     * páginas de empresa sin oferta específica.
     */
    private const NOISE_PATH_PATTERNS = [
        '/linkedin\.com\/in\//i',            // perfil de persona
        '/linkedin\.com\/posts\//i',         // post, no vacante estructurada
        '/\/empresas\//i',                   // computrabajo: página de empresa
        '/\/empleos$/i',                     // computrabajo: listado, no oferta puntual
        '/\/cmp\//i',                        // indeed: página de empresa
        '/q-.*-empleos\.html$/i',            // indeed: página de búsqueda/listado
    ];

    /**
     * Máximo de días de antigüedad para considerar una vacante "vigente".
     * LinkedIn/Indeed muestran vacantes de hasta años de antigüedad que
     * siguen indexadas aunque ya no acepten postulaciones — sin este corte
     * la búsqueda trae basura vieja mezclada con vacantes reales. Ajustable
     * vía JOB_SEARCH_MAX_AGE_DAYS sin tocar código.
     */
    private const DEFAULT_MAX_AGE_DAYS = 14;

    /**
     * Frases explícitas de "esta vacante ya cerró", en ES/EN. Si aparecen,
     * se descarta sin importar la antigüedad calculada.
     */
    private const CLOSED_PATTERNS = [
        '/ya no se aceptan solicitudes/iu',
        '/ya no se aceptan postulaciones/iu',
        '/no longer accepting applications/iu',
        '/this job is no longer available/iu',
        '/position has (already )?been filled/iu',
        '/vacante (cerrada|finalizada)/iu',
        '/oferta (cerrada|finalizada)/iu',
    ];

    /**
     * "Hace X día(s)/semana(s)/mes(es)/año(s)" (ES) y "X day(s)/week(s)/
     * month(s)/year(s) ago" (EN), como aparecen literalmente en las páginas
     * de LinkedIn/Indeed. El valor es días aproximados por unidad.
     */
    private const AGE_UNIT_DAYS = [
        'día' => 1, 'dia' => 1, 'day' => 1,
        'semana' => 7, 'week' => 7,
        'mes' => 30, 'month' => 30,
        'año' => 365, 'ano' => 365, 'year' => 365,
    ];

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: (string) config('composio.api_key', '');
        $this->baseUri = rtrim((string) config('composio.base_uri'), '/');

        if (! $this->apiKey) {
            throw new RuntimeException('API key de Composio no configurada. Configura COMPOSIO_API_KEY en .env');
        }
    }

    /**
     * Busca vacantes reales para un rol + ubicación, en LinkedIn, Indeed,
     * Computrabajo y portales/ATS en general. Devuelve postings con el texto
     * completo ya traído (no solo el link) para poder tailorear de verdad.
     *
     * @return array<int, array{title:string, company:string, location:string, url:string, channel:string, description:string}>
     */
    public function findJobPostings(string $role, string $location, int $limit = 6): array
    {
        $candidates = $this->collectCandidateUrls($role, $location);

        if (! $candidates) {
            return [];
        }

        // Se piden más candidatos de los que hacen falta porque el filtro de
        // antigüedad/cierre va a descartar varios — sin este margen, terminan
        // sobrando muy pocos resultados vigentes.
        $urls = array_slice(array_keys($candidates), 0, min(max($limit * 3, $limit), 20));
        $contents = $this->fetchContent($urls);

        $postings = [];
        $seen = [];

        foreach ($contents as $url => $text) {
            $posting = $this->buildPosting($url, $text, $candidates[$url]['title'] ?? null, $role, $location);

            if (! $posting) {
                continue;
            }

            // Algunos ATS (Lever, Greenhouse) publican la misma vacante varias
            // veces con URLs distintas (una por sede/variante) — sin esto se
            // ve la mitad de la lista repetida con la misma empresa.
            $dedupeKey = Str::slug("{$posting['company']}-{$posting['title']}");

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $postings[] = $posting;
        }

        // Vacantes con antigüedad confirmada primero (las más nuevas
        // arriba); las de antigüedad desconocida (portales que no muestran
        // fecha, ej. ATS de empresa) van al final, no se descartan.
        usort($postings, fn ($a, $b) => ($a['age_days'] ?? PHP_INT_MAX) <=> ($b['age_days'] ?? PHP_INT_MAX));

        return array_slice(
            array_map(fn ($p) => collect($p)->except('age_days')->all(), $postings),
            0,
            $limit
        );
    }

    /**
     * @return array<string, array{title: ?string, url: string}>
     */
    private function collectCandidateUrls(string $role, string $location): array
    {
        $candidates = [];

        foreach ($this->buildQueries($role, $location) as $query) {
            foreach ($this->searchWeb($query) as $citation) {
                $url = $citation['url'] ?? null;

                if (! $url || isset($candidates[$url]) || ! $this->looksLikeJobPosting($url)) {
                    continue;
                }

                $candidates[$url] = ['title' => $citation['title'] ?? null, 'url' => $url];
            }
        }

        return $candidates;
    }

    /**
     * Convierte el texto crudo de una página en un posting, o null si hay
     * que descartarla (contenido pobre, vacante vieja o cerrada).
     */
    private function buildPosting(string $url, string $text, ?string $citationTitle, string $role, string $location): ?array
    {
        // Contenido muy corto suele ser una página bloqueada/boilerplate, no
        // una oferta real.
        if (mb_strlen($text) < 200) {
            return null;
        }

        $age = $this->extractAgeSignal($text);

        $maxAgeDays = (int) config('composio.job_max_age_days', self::DEFAULT_MAX_AGE_DAYS);

        if ($age['closed'] || ($age['days'] !== null && $age['days'] > $maxAgeDays)) {
            return null;
        }

        [$title, $company] = $this->parseTitleAndCompany($citationTitle, $url, $text);

        return [
            'title' => $title ?: $role,
            'company' => $company,
            'location' => $location,
            'url' => $url,
            'channel' => str_contains($url, 'linkedin.com') ? 'linkedin' : 'portal',
            'description' => Str::limit($text, 4000, ''),
            'age_days' => $age['days'],
        ];
    }

    /**
     * Busca señales de antigüedad ("Hace 2 años", "3 days ago") y de cierre
     * ("Ya no se aceptan solicitudes") en el texto de la página.
     *
     * @return array{days: ?int, closed: bool}
     */
    private function extractAgeSignal(string $text): array
    {
        foreach (self::CLOSED_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return ['days' => null, 'closed' => true];
            }
        }

        if (preg_match('/\b(hoy|today)\b/iu', $text)) {
            return ['days' => 0, 'closed' => false];
        }

        if (preg_match('/\b(ayer|yesterday)\b/iu', $text)) {
            return ['days' => 1, 'closed' => false];
        }

        $unitsPattern = implode('|', array_keys(self::AGE_UNIT_DAYS));

        if (preg_match('/\bhace\s+(\d+|un|una)\s+('.$unitsPattern.')(?:es|s)?\b/iu', $text, $m)) {
            $count = is_numeric($m[1]) ? (int) $m[1] : 1;

            return ['days' => $count * self::AGE_UNIT_DAYS[mb_strtolower($m[2])], 'closed' => false];
        }

        if (preg_match('/\b(\d+)\s+('.$unitsPattern.')(?:es|s)?\s+ago\b/iu', $text, $m)) {
            return ['days' => ((int) $m[1]) * self::AGE_UNIT_DAYS[mb_strtolower($m[2])], 'closed' => false];
        }

        return ['days' => null, 'closed' => false];
    }

    /**
     * @return array<int, string>
     */
    private function buildQueries(string $role, string $location): array
    {
        return [
            "{$role} {$location} empleo reciente site:computrabajo.com.uy OR site:uy.indeed.com",
            "{$role} job {$location} recently posted site:linkedin.com/jobs",
            // Los ATS de terceros (empresas publicando directo) suelen tener
            // vacantes más frescas que LinkedIn, donde lo mejor rankeado por
            // relevancia suele ser justamente lo más viejo/indexado.
            "{$role} {$location} site:breezy.hr OR site:lever.co OR site:greenhouse.io OR site:gupy.io",
            "{$role} {$location} \"estamos buscando\" OR \"nos encontramos en búsqueda\" empleo 2026",
        ];
    }

    private function looksLikeJobPosting(string $url): bool
    {
        foreach (self::NOISE_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }

        if ($this->isAtsUrl($url)) {
            return true;
        }

        foreach (self::POSTING_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    private function isAtsUrl(string $url): bool
    {
        foreach (self::ATS_DOMAINS as $domain) {
            if (str_contains($url, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * LinkedIn titula sus postings de dos formas según el idioma:
     *   EN: "Job Title at Company"
     *   ES: "Empresa busca personal para el cargo de Job Title en Ubicación"
     * Se intentan ambos patrones antes de caer al dominio como último recurso.
     *
     * @return array{0: ?string, 1: string}
     */
    private function parseTitleAndCompany(?string $citationTitle, string $url, string $text): array
    {
        $raw = $citationTitle ?: trim(strtok($text, "\n"));

        if ($raw) {
            if (preg_match('/^(.+?)\s+busca personal para el cargo de\s+(.+?)(\s+en\s+.+)?$/iu', $raw, $m)) {
                return [trim($m[2]), trim($m[1])];
            }

            if (preg_match('/^(.+?)\s+at\s+(.+?)(\s*[-–—|]\s+.+)?$/iu', $raw, $m)) {
                return [trim($m[1]), trim($m[2])];
            }

            // "Empresa hiring Puesto in Ubicación"
            if (preg_match('/^(.+?)\s+hiring\s+(.+?)(\s+in\s+.+)?$/iu', $raw, $m)) {
                return [trim($m[2]), trim($m[1])];
            }

            // "Puesto ... en Empresa — Ubicación | Empleos de LinkedIn"
            if (preg_match('/^(.+?)\s+en\s+([A-ZÁÉÍÓÚÑ][\w.&\'-]{1,40}?)\s*[—–-]\s+.+$/u', $raw, $m)) {
                return [trim($m[1]), trim($m[2])];
            }

            // ATS de terceros (Lever/Greenhouse/Breezy): el <title> por defecto
            // de esas plataformas es "Empresa - Puesto".
            if ($this->isAtsUrl($url) && preg_match('/^([^-–—]{2,40})\s*[-–—]\s*(.+)$/u', $raw, $m)) {
                return [trim($m[2]), trim($m[1])];
            }
        }

        return [$raw ?: null, $this->guessCompanyFromDomain($url)];
    }

    private function guessCompanyFromDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $host = preg_replace('/^(www\.|uy\.|es\.|lt\.|sv\.)/', '', $host);
        $host = preg_replace('/\.(com|uy|io|hr|co).*/', '', $host);

        return Str::title(str_replace(['-', '.'], ' ', $host));
    }

    /**
     * @return array<int, array{title: ?string, url: ?string}>
     */
    private function searchWeb(string $query): array
    {
        try {
            $data = $this->execute('COMPOSIO_SEARCH_WEB', ['query' => $query]);
        } catch (\Throwable $e) {
            Log::warning('JobSearchService: búsqueda falló', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $data['citations'] ?? [];
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<string, string> url => texto extraído
     */
    private function fetchContent(array $urls, int $maxCharacters = 6000): array
    {
        if (! $urls) {
            return [];
        }

        try {
            $data = $this->execute('COMPOSIO_SEARCH_FETCH_URL_CONTENT', [
                'urls' => $urls,
                'max_characters' => $maxCharacters,
            ]);
        } catch (\Throwable $e) {
            Log::warning('JobSearchService: no se pudo traer el contenido de las URLs', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $results = [];

        foreach ($data['results'] ?? [] as $page) {
            $url = $page['url'] ?? $page['id'] ?? null;
            $text = $page['text'] ?? null;

            if ($url && is_string($text) && $text !== '') {
                $results[$url] = $text;
            }
        }

        return $results;
    }

    private function execute(string $toolSlug, array $arguments): array
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout((int) config('composio.request_timeout', 30))
            ->post("{$this->baseUri}/tools/execute/{$toolSlug}", [
                'arguments' => $arguments,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Composio ({$toolSlug}) falló: ".$response->body());
        }

        $body = $response->json();

        if (($body['successful'] ?? true) === false) {
            throw new RuntimeException("Composio ({$toolSlug}) devolvió un error: ".($body['error'] ?? 'desconocido'));
        }

        return $body['data'] ?? $body;
    }
}

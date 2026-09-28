<?php

use App\JobSearch\Providers\AdzunaProvider;
use App\JobSearch\Providers\ArbeitnowProvider;
use App\JobSearch\Providers\AtsBoardsProvider;
use App\JobSearch\Providers\GetOnBoardProvider;
use App\JobSearch\Providers\JoobleProvider;
use App\JobSearch\Providers\JSearchProvider;
use App\JobSearch\Providers\RemotiveProvider;

return [

    /*
    | Cada proveedor declara qué países/regiones cubre (supports()). En cada
    | búsqueda se consultan solo los que cubren la ubicación pedida:
    |
    |   LatAm:         JSearch, Get on Board, Jooble, Adzuna (BR/MX), Remotive, ATS
    |   Norteamérica:  JSearch, Adzuna, Jooble, Remotive, ATS
    |   Europa:        JSearch, Adzuna, Arbeitnow, Jooble, Remotive, ATS
    |   APAC / MEA:    JSearch, Adzuna (AU/NZ/SG/IN/ZA), Jooble, Remotive, ATS
    */
    'enabled_providers' => [
        JSearchProvider::class,
        AdzunaProvider::class,
        GetOnBoardProvider::class,
        ArbeitnowProvider::class,
        RemotiveProvider::class,
        JoobleProvider::class,
        AtsBoardsProvider::class,
    ],

    // Si la misma oferta aparece en varios portales, gana el primero de esta lista.
    'source_priority' => ['ats', 'getonbrd', 'jsearch', 'arbeitnow', 'remotive', 'adzuna', 'jooble'],

    'providers' => [
        'jsearch' => [
            'api_key' => env('JSEARCH_API_KEY'),
            'host' => env('JSEARCH_HOST', 'jsearch.p.rapidapi.com'),
        ],
        'adzuna' => [
            'app_id' => env('ADZUNA_APP_ID'),
            'app_key' => env('ADZUNA_APP_KEY'),
        ],
        'jooble' => [
            'api_key' => env('JOOBLE_API_KEY'),
        ],
        'arbeitnow' => [
            'pages' => 3,
        ],
        'ats' => [
            // Empresas a monitorear directo desde su página de empleos, por ejemplo:
            // ['ats' => 'greenhouse', 'slug' => 'gitlab', 'name' => 'GitLab'],
            // ['ats' => 'lever', 'slug' => 'nombre-en-lever', 'name' => 'Empresa'],
            // ['ats' => 'ashby', 'slug' => 'nombre-en-ashby', 'name' => 'Empresa'],
            'companies' => [],
        ],
    ],

    'http_timeout' => (int) env('JOB_SEARCH_HTTP_TIMEOUT', 20),

    // Cuándo corre `jobs:sync` (por defecto 07:17 y 19:17, hora del servidor).
    'sync_cron' => env('JOB_SEARCH_SYNC_CRON', '17 7,19 * * *'),

    // Ofertas más viejas que esto no se muestran (por perfil se puede bajar).
    'max_age_days' => (int) env('JOB_SEARCH_MAX_AGE_DAYS', 3),

    // Ofertas nuevas que se evalúan por perfil en cada sincronización (controla el costo de IA).
    'max_new_matches_per_sync' => (int) env('JOB_SEARCH_MAX_NEW_PER_SYNC', 25),

    // Solo se genera CV adaptado si la compatibilidad CV/oferta llega a este puntaje (0-100).
    'min_match_score' => (int) env('JOB_SEARCH_MIN_MATCH_SCORE', 45),

    'demo' => [
        // Usuario cuyas ofertas y CVs adaptados se muestran en la home. Se crea con `php artisan jobs:demo`.
        'user_email' => env('DEMO_USER_EMAIL', 'demo@atsboost.local'),
    ],
];

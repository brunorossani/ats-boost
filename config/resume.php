<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modelos de Claude
    |--------------------------------------------------------------------------
    |
    | Cada tarea usa el modelo más barato que la resuelve bien. La extracción
    | estructurada y el scoring son tareas mecánicas; la adaptación y la carta
    | de presentación requieren redacción, por eso usan el modelo grande.
    |
    */

    'models' => [
        'parsing' => env('RESUME_MODEL_PARSING', 'claude-haiku-4-5'),
        'tailoring' => env('RESUME_MODEL_TAILORING', 'claude-opus-5'),
        'analysis' => env('RESUME_MODEL_ANALYSIS', 'claude-haiku-4-5'),
        'cover_letter' => env('RESUME_MODEL_COVER_LETTER', 'claude-opus-5'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parámetros de la llamada a Claude
    |--------------------------------------------------------------------------
    |
    | `effort` regula cuánto razona el modelo grande (low, medium, high, xhigh,
    | max). Adaptar un CV se hace mientras el usuario espera, por eso el
    | default es medium y no el high de la API.
    |
    */

    'effort' => env('RESUME_AI_EFFORT', 'medium'),
    'max_tokens' => (int) env('RESUME_AI_MAX_TOKENS', 16000),
    'request_timeout' => (float) env('RESUME_AI_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Límites de entrada
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'upload_kilobytes' => 10240,
        'cv_characters' => 24000,
        'job_description_characters' => 12000,
        'bullets_per_entry' => 5,
        'demo_generations' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reintentos ante respuestas inválidas del modelo
    |--------------------------------------------------------------------------
    */

    'retries' => env('RESUME_AI_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Plantillas de PDF
    |--------------------------------------------------------------------------
    |
    | DejaVu Sans es la única familia con cobertura Unicode completa que
    | DomPDF trae de fábrica: sin ella los guiones largos, las viñetas y los
    | acentos salen como cuadrados. Para usar otra fuente hay que instalarla
    | con `php artisan dompdf:install-fonts` o dejar el .ttf en storage/fonts.
    |
    */

    'pdf' => [
        'font' => env('RESUME_PDF_FONT', 'DejaVu Sans'),
        'paper' => env('RESUME_PDF_PAPER', 'a4'),
    ],

];

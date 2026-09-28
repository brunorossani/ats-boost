<?php

return [
    'api_key' => env('COMPOSIO_API_KEY'),
    'base_uri' => env('COMPOSIO_BASE_URI', 'https://backend.composio.dev/api/v3.1'),
    'request_timeout' => env('COMPOSIO_REQUEST_TIMEOUT', 30),

    // Máximo de días de antigüedad para que una vacante encontrada se
    // considere vigente (ver App\Services\JobSearchService).
    'job_max_age_days' => env('JOB_SEARCH_MAX_AGE_DAYS', 14),
];

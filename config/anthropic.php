<?php

return [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'base_uri' => env('ANTHROPIC_BASE_URI', 'https://api.anthropic.com'),
    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5-20250929'),
    'request_timeout' => env('ANTHROPIC_REQUEST_TIMEOUT', 120),
];

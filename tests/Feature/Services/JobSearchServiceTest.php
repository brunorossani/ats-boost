<?php

use App\Services\JobSearchService;
use Illuminate\Support\Facades\Http;

test('fails with a useful exception when the api key is missing', function () {
    config()->set('composio.api_key', null);

    expect(fn () => new JobSearchService)
        ->toThrow(RuntimeException::class, 'API key de Composio no configurada.');
});

test('findJobPostings filters out noise URLs and keeps real postings', function () {
    Http::fake([
        '*/tools/execute/COMPOSIO_SEARCH_WEB' => Http::response([
            'successful' => true,
            'data' => [
                'citations' => [
                    // Ruido: debe descartarse
                    ['url' => 'https://www.linkedin.com/in/juan-perez', 'title' => 'Juan Pérez'],
                    ['url' => 'https://www.computrabajo.com.uy/empresas/acme', 'title' => 'Ofertas de ACME'],
                    ['url' => 'https://uy.indeed.com/q-software-empleos.html', 'title' => 'Buscar software'],
                    // Real: debe conservarse
                    ['url' => 'https://uy.linkedin.com/jobs/view/backend-developer-at-acme-123', 'title' => 'Backend Developer at Acme'],
                ],
            ],
        ], 200),
        '*/tools/execute/COMPOSIO_SEARCH_FETCH_URL_CONTENT' => Http::response([
            'successful' => true,
            'data' => [
                'results' => [
                    [
                        'url' => 'https://uy.linkedin.com/jobs/view/backend-developer-at-acme-123',
                        'text' => str_repeat('Buscamos un backend developer con experiencia en PHP y Laravel. ', 10),
                    ],
                ],
            ],
        ], 200),
    ]);

    $service = new JobSearchService('test-key');

    $postings = $service->findJobPostings('Backend Developer', 'Montevideo, Uruguay', limit: 6);

    expect($postings)->toHaveCount(1);
    expect($postings[0]['title'])->toBe('Backend Developer');
    expect($postings[0]['company'])->toBe('Acme');
    expect($postings[0]['channel'])->toBe('linkedin');
    expect($postings[0]['url'])->toBe('https://uy.linkedin.com/jobs/view/backend-developer-at-acme-123');
});

test('findJobPostings discards results with suspiciously short content', function () {
    Http::fake([
        '*/tools/execute/COMPOSIO_SEARCH_WEB' => Http::response([
            'successful' => true,
            'data' => [
                'citations' => [
                    ['url' => 'https://uy.linkedin.com/jobs/view/qa-at-acme-456', 'title' => 'QA at Acme'],
                ],
            ],
        ], 200),
        '*/tools/execute/COMPOSIO_SEARCH_FETCH_URL_CONTENT' => Http::response([
            'successful' => true,
            'data' => [
                'results' => [
                    // Contenido casi vacío -> boilerplate/página bloqueada, se descarta
                    ['url' => 'https://uy.linkedin.com/jobs/view/qa-at-acme-456', 'text' => 'Inicia sesión para ver más'],
                ],
            ],
        ], 200),
    ]);

    $service = new JobSearchService('test-key');

    expect($service->findJobPostings('QA', 'Montevideo', limit: 6))->toBeEmpty();
});

test('findJobPostings returns an empty array (not an exception) when the search API is unreachable', function () {
    Http::fake([
        '*/tools/execute/*' => Http::response(['successful' => false, 'error' => 'timeout'], 500),
    ]);

    $service = new JobSearchService('test-key');

    expect($service->findJobPostings('Backend Developer', 'Montevideo', limit: 6))->toBe([]);
});

test('findJobPostings discards postings that say they are no longer accepting applications', function () {
    Http::fake([
        '*/tools/execute/COMPOSIO_SEARCH_WEB' => Http::response([
            'successful' => true,
            'data' => ['citations' => [
                ['url' => 'https://uy.linkedin.com/jobs/view/backend-at-acme-1', 'title' => 'Backend at Acme'],
            ]],
        ], 200),
        '*/tools/execute/COMPOSIO_SEARCH_FETCH_URL_CONTENT' => Http::response([
            'successful' => true,
            'data' => ['results' => [
                [
                    'url' => 'https://uy.linkedin.com/jobs/view/backend-at-acme-1',
                    'text' => str_repeat('Backend developer role. ', 20).' Hace 3 días. Ya no se aceptan solicitudes.',
                ],
            ]],
        ], 200),
    ]);

    expect((new JobSearchService('test-key'))->findJobPostings('Backend', 'Montevideo', limit: 6))->toBeEmpty();
});

test('findJobPostings discards postings older than the recency threshold', function () {
    Http::fake([
        '*/tools/execute/COMPOSIO_SEARCH_WEB' => Http::response([
            'successful' => true,
            'data' => ['citations' => [
                ['url' => 'https://uy.linkedin.com/jobs/view/backend-at-acme-2', 'title' => 'Backend at Acme'],
            ]],
        ], 200),
        '*/tools/execute/COMPOSIO_SEARCH_FETCH_URL_CONTENT' => Http::response([
            'successful' => true,
            'data' => ['results' => [
                [
                    'url' => 'https://uy.linkedin.com/jobs/view/backend-at-acme-2',
                    'text' => str_repeat('Backend developer role. ', 20).' Hace 3 meses.',
                ],
            ]],
        ], 200),
    ]);

    expect((new JobSearchService('test-key'))->findJobPostings('Backend', 'Montevideo', limit: 6))->toBeEmpty();
});

test('findJobPostings keeps postings within the recency threshold', function () {
    Http::fake([
        '*/tools/execute/COMPOSIO_SEARCH_WEB' => Http::response([
            'successful' => true,
            'data' => ['citations' => [
                ['url' => 'https://uy.linkedin.com/jobs/view/backend-at-acme-3', 'title' => 'Backend at Acme'],
            ]],
        ], 200),
        '*/tools/execute/COMPOSIO_SEARCH_FETCH_URL_CONTENT' => Http::response([
            'successful' => true,
            'data' => ['results' => [
                [
                    'url' => 'https://uy.linkedin.com/jobs/view/backend-at-acme-3',
                    'text' => str_repeat('Backend developer role. ', 20).' Hace 5 días.',
                ],
            ]],
        ], 200),
    ]);

    expect((new JobSearchService('test-key'))->findJobPostings('Backend', 'Montevideo', limit: 6))->toHaveCount(1);
});

test('findJobPostings deduplicates the same posting published under different URLs', function () {
    Http::fake([
        '*/tools/execute/COMPOSIO_SEARCH_WEB' => Http::response([
            'successful' => true,
            'data' => ['citations' => [
                ['url' => 'https://jobs.lever.co/acme/aaa', 'title' => 'Backend Developer at Acme'],
                ['url' => 'https://jobs.lever.co/acme/bbb', 'title' => 'Backend Developer at Acme'],
            ]],
        ], 200),
        '*/tools/execute/COMPOSIO_SEARCH_FETCH_URL_CONTENT' => Http::response([
            'successful' => true,
            'data' => ['results' => [
                ['url' => 'https://jobs.lever.co/acme/aaa', 'text' => str_repeat('Backend developer role at Acme. ', 20)],
                ['url' => 'https://jobs.lever.co/acme/bbb', 'text' => str_repeat('Backend developer role at Acme. ', 20)],
            ]],
        ], 200),
    ]);

    expect((new JobSearchService('test-key'))->findJobPostings('Backend', 'Montevideo', limit: 6))->toHaveCount(1);
});

<?php

use App\JobSearch\JobQuery;
use App\JobSearch\JobSearchEngine;
use App\JobSearch\Regions;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'jobsearch.providers.jsearch.api_key' => 'test-key',
        'jobsearch.providers.adzuna.app_id' => 'id',
        'jobsearch.providers.adzuna.app_key' => 'key',
        'jobsearch.providers.jooble.api_key' => null,
        'jobsearch.providers.ats.companies' => [],
    ]);

    Http::preventStrayRequests();
});

function jsearchJob(array $overrides = []): array
{
    return array_merge([
        'job_id' => 'js-1',
        'employer_name' => 'Acme',
        'job_title' => 'Laravel Developer',
        'job_description' => 'We need PHP and Laravel.',
        'job_apply_link' => 'https://acme.example/jobs/1',
        'job_is_remote' => false,
        'job_posted_at_datetime_utc' => now()->subHours(5)->toIso8601String(),
        'job_city' => 'Montevideo',
        'job_country' => 'UY',
        'job_employment_type' => 'FULLTIME',
    ], $overrides);
}

it('resuelve países y zonas de trabajo remoto', function (): void {
    expect(Regions::regionOf('uy'))->toBe(Regions::LATAM)
        ->and(Regions::regionOf('US'))->toBe(Regions::NORTH_AMERICA)
        ->and(Regions::detectCountry('Remote - Montevideo, Uruguay'))->toBe('uy')
        ->and(Regions::remoteZoneAccepts('Worldwide', 'uy'))->toBeTrue()
        ->and(Regions::remoteZoneAccepts('LATAM only', 'uy'))->toBeTrue()
        ->and(Regions::remoteZoneAccepts('USA only', 'uy'))->toBeFalse()
        ->and(Regions::remoteZoneAccepts('Europe', 'es'))->toBeTrue();
});

it('cambia las convenciones del CV según la región de la oferta', function (): void {
    expect(Regions::cvConventions('us'))->toContain('résumé norteamericano')
        ->and(Regions::cvConventions('es'))->toContain('MCER')
        ->and(Regions::cvConventions('uy'))->toContain('latinoamericano')
        ->and(Regions::cvConventions('uy', remote: true))->toContain('remoto');
});

it('en LatAm consulta fuentes regionales y globales, descarta ofertas viejas o de otra zona y deduplica entre portales', function (): void {
    Http::fake([
        'jsearch.p.rapidapi.com/*' => Http::response(['data' => [
            jsearchJob(),
            jsearchJob(['job_id' => 'js-old', 'job_title' => 'Old PHP job', 'job_posted_at_datetime_utc' => now()->subDays(20)->toIso8601String()]),
            jsearchJob(['job_id' => 'js-xss', 'job_title' => 'Evil job', 'job_apply_link' => 'javascript:alert(1)']),
        ]]),
        'www.getonbrd.com/*' => Http::response(['data' => [[
            'id' => 'laravel-developer-acme',
            'attributes' => [
                'title' => 'Laravel Developer',
                'description' => '<p>Longer description from Get on Board with <strong>Laravel</strong> and PHP details.</p>',
                'countries' => ['Uruguay'],
                'remote' => false,
                'published_at' => now()->subHours(8)->timestamp,
                'company' => ['data' => ['id' => 'acme', 'attributes' => ['name' => 'Acme']]],
            ],
            'links' => ['public_url' => 'https://www.getonbrd.com/jobs/laravel-developer-acme'],
        ]]]),
        'remotive.com/*' => Http::response(['jobs' => [
            ['id' => 1, 'url' => 'https://remotive.com/1', 'title' => 'Senior Laravel Engineer', 'company_name' => 'Globex',
                'publication_date' => now()->subDay()->format('Y-m-d\TH:i:s'), 'candidate_required_location' => 'LATAM', 'description' => 'Remote LATAM'],
            ['id' => 2, 'url' => 'https://remotive.com/2', 'title' => 'Laravel Engineer (US only)', 'company_name' => 'Initech',
                'publication_date' => now()->subDay()->format('Y-m-d\TH:i:s'), 'candidate_required_location' => 'USA only', 'description' => 'US'],
        ]]),
    ]);

    $result = JobSearchEngine::fromConfig()->search(new JobQuery('Laravel developer', 'uy', maxAgeDays: 3));

    expect(array_keys($result->providers))->toEqualCanonicalizing(['jsearch', 'getonbrd', 'remotive', 'jooble', 'ats'])
        ->and($result->providers['jooble']['status'])->toBe('not_configured')
        ->and($result->providers['ats']['status'])->toBe('not_configured')
        ->and($result->providers)->not->toHaveKey('adzuna')
        ->and($result->providers)->not->toHaveKey('arbeitnow');

    $titles = array_map(fn ($p) => "{$p->company}|{$p->title}|{$p->source}", $result->postings);

    expect($titles)->toBe([
        'Acme|Laravel Developer|getonbrd',
        'Globex|Senior Laravel Engineer|remotive',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'jsearch')
        && $request['country'] === 'uy'
        && $request['date_posted'] === '3days'
        && $request->header('X-RapidAPI-Key')[0] === 'test-key');
});

it('informa el proveedor que falla sin cortar la búsqueda', function (): void {
    Http::fake([
        'jsearch.p.rapidapi.com/*' => Http::response('boom', 500),
        'api.adzuna.com/*' => Http::response(['results' => [[
            'id' => 99, 'title' => 'PHP Developer', 'description' => 'Laravel role', 'redirect_url' => 'https://adzuna.example/99',
            'created' => now()->subHours(3)->toIso8601String(), 'company' => ['display_name' => 'Umbrella'],
            'location' => ['display_name' => 'Madrid'],
        ]]]),
        'www.arbeitnow.com/*' => Http::response(['data' => []]),
        'remotive.com/*' => Http::response(['jobs' => []]),
    ]);

    $result = JobSearchEngine::fromConfig()->search(new JobQuery('php', 'es'));

    expect($result->providers['jsearch']['status'])->toBe('error')
        ->and($result->providers['adzuna']['status'])->toBe('ok')
        ->and($result->postings)->toHaveCount(1)
        ->and($result->postings[0]->countryCode)->toBe('es');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.adzuna.com/v1/api/jobs/es/search/1')
        && $request['sort_by'] === 'date');
});

it('en Europa usa Arbeitnow filtrando por palabras clave localmente', function (): void {
    config(['jobsearch.providers.jsearch.api_key' => null, 'jobsearch.providers.adzuna.app_id' => null, 'jobsearch.providers.arbeitnow.pages' => 1]);

    Http::fake([
        'www.arbeitnow.com/*' => Http::response(['data' => [
            ['slug' => 'a', 'company_name' => 'Hooli', 'title' => 'Backend Developer (Laravel)', 'description' => 'PHP', 'remote' => false,
                'url' => 'https://arbeitnow.example/a', 'location' => 'Berlin', 'created_at' => now()->subHours(2)->timestamp],
            ['slug' => 'b', 'company_name' => 'Hooli', 'title' => 'Sales Manager', 'description' => 'Sales', 'remote' => false,
                'url' => 'https://arbeitnow.example/b', 'location' => 'Berlin', 'created_at' => now()->subHours(2)->timestamp],
        ]]),
        'remotive.com/*' => Http::response(['jobs' => []]),
    ]);

    $result = JobSearchEngine::fromConfig()->search(new JobQuery('laravel', 'de'));

    expect($result->postings)->toHaveCount(1)
        ->and($result->postings[0]->title)->toBe('Backend Developer (Laravel)')
        ->and($result->postings[0]->countryCode)->toBe('de');
});

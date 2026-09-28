<?php

use App\Livewire\Resume\AutoApplyDemo;
use App\Services\CvTailorService;
use App\Services\JobSearchService;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function autoApplyFakeTailorResult(): array
{
    return [
        'cv' => [
            'lang' => 'es',
            'name' => 'Juan Pérez',
            'headline' => 'Backend Developer · PHP · Laravel',
            'contact' => [['label' => 'Montevideo, Uruguay']],
            'sections' => [],
        ],
        'html' => '<html><body><h1>CV adaptado</h1></body></html>',
        'cvText' => 'texto del cv',
    ];
}

function autoApplyFakeJobPostings(int $count = 6): array
{
    return collect(range(1, $count))->map(fn ($i) => [
        'title' => "Desarrollador Backend PHP #{$i}",
        'company' => "Empresa {$i}",
        'location' => 'Montevideo, Uruguay',
        'url' => "https://example.com/jobs/{$i}",
        'channel' => $i % 2 === 0 ? 'linkedin' : 'portal',
        'description' => str_repeat("Descripción real de la vacante {$i}. ", 20),
    ])->all();
}

test('the auto-apply preview page renders', function () {
    $this->get('/futuro')->assertOk();
});

test('the full flow goes from upload to a dashboard with real job postings', function () {
    $postings = autoApplyFakeJobPostings();
    $result = autoApplyFakeTailorResult();

    $this->mock(JobSearchService::class, function ($mock) use ($postings) {
        $mock->shouldReceive('findJobPostings')->once()->andReturn($postings);
    });

    $this->mock(CvTailorService::class, function ($mock) use ($result, $postings) {
        $mock->shouldReceive('tailorResume')
            ->once()
            ->with(Mockery::any(), $postings[0]['description'])
            ->andReturn($result);
    });

    $component = Livewire::test(AutoApplyDemo::class)
        ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
        ->call('startSearch')
        ->call('runSearch')
        ->assertSet('step', 'matches')
        ->assertSet('searchFailed', false);

    expect($component->get('matches'))->toHaveCount(6);

    $component->call('runSend')
        ->assertSet('step', 'dashboard');

    expect($component->get('sentLog'))->not->toBeEmpty();
    expect($component->get('sentToday'))->toBeGreaterThan(0);
    expect($component->get('tailoredPreviewHtml'))->toBe($result['html']);
});

test('shows a graceful empty state when the real search finds nothing', function () {
    $this->mock(JobSearchService::class, function ($mock) {
        $mock->shouldReceive('findJobPostings')->once()->andReturn([]);
    });

    Livewire::test(AutoApplyDemo::class)
        ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
        ->call('runSearch')
        ->assertSet('step', 'matches')
        ->assertSet('searchFailed', true)
        ->assertSet('matches', []);
});

test('a search failure does not crash the demo', function () {
    $this->mock(JobSearchService::class, function ($mock) {
        $mock->shouldReceive('findJobPostings')->once()->andThrow(new RuntimeException('Composio caído'));
    });

    Livewire::test(AutoApplyDemo::class)
        ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
        ->call('runSearch')
        ->assertSet('step', 'matches')
        ->assertSet('searchFailed', true);
});

test('the sent applications never exceed the daily limit', function () {
    $this->mock(JobSearchService::class, function ($mock) {
        $mock->shouldReceive('findJobPostings')->andReturn(autoApplyFakeJobPostings());
    });

    $this->mock(CvTailorService::class, function ($mock) {
        $mock->shouldReceive('tailorResume')->andReturn(autoApplyFakeTailorResult());
    });

    $component = Livewire::test(AutoApplyDemo::class)
        ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))
        ->call('runSearch')
        ->call('runSend');

    expect($component->get('sentToday'))->toBeLessThanOrEqual(AutoApplyDemo::DAILY_LIMIT);
});

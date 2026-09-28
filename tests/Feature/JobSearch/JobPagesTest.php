<?php

use App\Enums\DocumentType;
use App\Jobs\SyncJobSearchProfile;
use App\JobSearch\JobPosting;
use App\Livewire\Jobs\JobFeed;
use App\Livewire\Jobs\LiveDemo;
use App\Models\Document;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['jobsearch.demo.user_email' => 'demo@example.test']);
});

function readyMatch(User $user, string $country, string $title): JobMatch
{
    $profile = $user->jobSearchProfiles()->firstOrCreate(['country_code' => $country], [
        'keywords' => 'Laravel developer',
        'resume_filename' => 'cv.pdf',
        'resume_text' => 'CV',
    ]);

    $listing = JobListing::fromPosting(new JobPosting(
        source: 'jsearch', externalId: $title, title: $title, company: 'Acme',
        url: 'https://jobs.example/'.md5($title), description: 'Laravel', countryCode: $country,
        postedAt: CarbonImmutable::now()->subHours(3),
    ));

    $document = Document::factory()->for($user)->create(['type' => DocumentType::TailoredResume]);

    return $profile->matches()->create([
        'user_id' => $user->id,
        'job_listing_id' => $listing->id,
        'document_id' => $document->id,
        'status' => JobMatch::STATUS_READY,
        'match_score' => 80,
        'match_details' => ['reason' => 'Encaja bien', 'matching' => ['Laravel'], 'missing' => []],
    ]);
}

it('redirige /futuro a la home', function (): void {
    $this->get('/futuro')->assertStatus(301)->assertRedirect('/');
});

it('muestra en la home las ofertas de la demo por país, cada una con su CV', function (): void {
    $demo = User::factory()->create(['email' => 'demo@example.test']);
    readyMatch($demo, 'uy', 'Laravel Developer Montevideo');
    readyMatch($demo, 'us', 'Backend Engineer New York');

    $this->get('/')->assertOk()->assertSee('Laravel Developer Montevideo');

    Livewire::test(LiveDemo::class)
        ->assertSee('Laravel Developer Montevideo')
        ->assertDontSee('Backend Engineer New York')
        ->call('selectCountry', 'us')
        ->assertSee('Backend Engineer New York')
        ->assertDontSee('Laravel Developer Montevideo');
});

it('sirve sin login los PDF de la demo, pero nunca los de otros usuarios', function (): void {
    $demo = User::factory()->create(['email' => 'demo@example.test']);
    $demoMatch = readyMatch($demo, 'uy', 'Demo offer');
    $private = readyMatch(User::factory()->create(), 'uy', 'Private offer');

    $this->get(route('demo.document', $demoMatch->document_id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('demo.document', [$private->document_id, 'attachment']))->assertNotFound();

    Livewire::test(LiveDemo::class)
        ->call('showCv', $private->id)
        ->assertSet('previewId', null);
});

it('crea una búsqueda subiendo el CV y arranca la sincronización', function (): void {
    Queue::fake();
    $user = subscribedUser();

    Livewire::actingAs($user)->test(JobFeed::class)
        ->set('keywords', 'Laravel developer')
        ->set('countryCode', 'uy')
        ->set('maxAgeDays', 3)
        ->set('resumeFile', fakeResumeUpload())
        ->call('saveProfile')
        ->assertHasNoErrors();

    $profile = $user->jobSearchProfiles()->sole();

    expect($profile->country_code)->toBe('uy')
        ->and($profile->resume_filename)->toBe('cv.txt')
        ->and($profile->resume_text)->toContain('Bruno Rossani');

    Queue::assertPushed(SyncJobSearchProfile::class, fn ($job) => $job->profileId === $profile->id);
});

it('exige un país o aceptar ofertas remotas', function (): void {
    Livewire::actingAs(subscribedUser())->test(JobFeed::class)
        ->set('keywords', 'Laravel developer')
        ->set('countryCode', '')
        ->set('includeRemote', false)
        ->set('resumeFile', fakeResumeUpload())
        ->call('saveProfile')
        ->assertHasErrors('countryCode');
});

it('lista solo las ofertas del usuario y no deja tocar las ajenas', function (): void {
    $user = subscribedUser();
    $mine = readyMatch($user, 'uy', 'My Laravel offer');
    $theirs = readyMatch(User::factory()->create(), 'uy', 'Someone else offer');

    Livewire::actingAs($user)->test(JobFeed::class)
        ->assertSee('My Laravel offer')
        ->assertDontSee('Someone else offer')
        ->call('markApplied', $theirs->id)
        ->call('markApplied', $mine->id)
        ->set('tab', 'applied')
        ->assertSee('My Laravel offer');

    expect($mine->fresh()->status)->toBe(JobMatch::STATUS_APPLIED)
        ->and($theirs->fresh()->status)->toBe(JobMatch::STATUS_READY);
});

it('pide suscripción para el panel de ofertas', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('jobs.feed'))
        ->assertRedirect(route('subscriptions.edit'));
});

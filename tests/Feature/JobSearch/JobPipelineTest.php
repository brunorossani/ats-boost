<?php

use App\Actions\Jobs\SyncSearchProfile;
use App\Enums\DocumentType;
use App\Jobs\ProcessJobMatch;
use App\JobSearch\JobPosting;
use App\JobSearch\JobQuery;
use App\JobSearch\JobSearchEngine;
use App\JobSearch\Providers\JobProvider;
use App\Models\Document;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\JobSearchProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\DocumentFactory;
use Illuminate\Support\Facades\Queue;

function fakeProvider(array $postings): JobProvider
{
    return new class($postings) implements JobProvider
    {
        public function __construct(public array $postings) {}

        public function key(): string
        {
            return 'fake';
        }

        public function label(): string
        {
            return 'Fake';
        }

        public function isConfigured(): bool
        {
            return true;
        }

        public function supports(JobQuery $query): bool
        {
            return true;
        }

        public function search(JobQuery $query): array
        {
            return $this->postings;
        }
    };
}

function posting(string $source, string $id, string $title, string $country = 'us'): JobPosting
{
    return new JobPosting(
        source: $source,
        externalId: $id,
        title: $title,
        company: 'Acme',
        url: "https://jobs.example/{$source}/{$id}",
        description: "{$title}: PHP, Laravel, MySQL.",
        location: 'New York, NY',
        countryCode: $country,
        postedAt: CarbonImmutable::now()->subHours(2),
    );
}

function searchProfile(User $user, string $country = 'us', bool $parsed = true): JobSearchProfile
{
    return $user->jobSearchProfiles()->create([
        'keywords' => 'Laravel developer',
        'country_code' => $country,
        'resume_filename' => 'cv.pdf',
        'resume_text' => 'Bruno Rossani. Backend developer. PHP, Laravel, Livewire, MySQL.',
        'resume_payload' => $parsed ? DocumentFactory::resumePayload() : null,
    ]);
}

function matchFor(JobSearchProfile $profile, JobPosting $posting): JobMatch
{
    return $profile->matches()->create([
        'user_id' => $profile->user_id,
        'job_listing_id' => JobListing::fromPosting($posting)->id,
    ]);
}

function jobMatchPayload(int $score): array
{
    return ['score' => $score, 'matching' => ['Laravel', 'PHP'], 'missing' => ['AWS'], 'reason' => 'Encajás bien con el puesto.'];
}

it('parsea el CV base una sola vez, guarda las ofertas y no duplica la misma oferta de otro portal', function (): void {
    Queue::fake();
    fakeChatResponses(DocumentFactory::resumePayload());
    $profile = searchProfile(User::factory()->create(), parsed: false);

    app()->instance(JobSearchEngine::class, new JobSearchEngine([fakeProvider([
        posting('jsearch', '1', 'Laravel Developer'),
        posting('jsearch', '2', 'Senior PHP Engineer'),
    ])]));

    $report = app(SyncSearchProfile::class)->handle($profile);

    expect($report['new_matches'])->toBe(2)
        ->and($profile->fresh()->resume_payload['full_name'])->toBe('Bruno Rossani')
        ->and($profile->fresh()->last_synced_at)->not->toBeNull();

    Queue::assertPushed(ProcessJobMatch::class, 2);

    // Al día siguiente la misma oferta llega desde otro portal: no se duplica
    // y el CV no se vuelve a parsear (no hay más respuestas falsas de Claude).
    app()->instance(JobSearchEngine::class, new JobSearchEngine([fakeProvider([
        posting('adzuna', 'zz', 'Laravel Developer'),
        posting('adzuna', 'yy', 'Backend Laravel Engineer'),
    ])]));

    $report = app(SyncSearchProfile::class)->handle($profile->fresh());

    expect($report['new_matches'])->toBe(1)
        ->and($profile->matches()->count())->toBe(3);
});

it('genera el CV adaptado como documento, con las convenciones del país de la oferta', function (): void {
    $profile = searchProfile(User::factory()->create(), 'us');
    $match = matchFor($profile, posting('jsearch', '1', 'Laravel Developer'));

    fakeChatResponses(
        jobMatchPayload(82),                 // compatibilidad
        fakeJobPosting(),                    // lectura de la oferta
        DocumentFactory::resumePayload(),    // adaptación
    );

    ProcessJobMatch::dispatchSync($match->id);

    $match->refresh();
    $document = Document::sole();

    expect($match->status)->toBe(JobMatch::STATUS_READY)
        ->and($match->match_score)->toBe(82)
        ->and($match->match_details['missing'])->toBe(['AWS'])
        ->and($match->document_id)->toBe($document->id)
        ->and($document->type)->toBe(DocumentType::TailoredResume)
        ->and($document->user_id)->toBe($profile->user_id)
        ->and($document->source_filename)->toBe('cv.pdf');

    expect(sentChatRequests()[2]['system'])->toContain('résumé norteamericano');
});

it('no adapta una oferta con baja compatibilidad salvo que el usuario lo pida', function (): void {
    $profile = searchProfile(User::factory()->create(), 'es');
    $match = matchFor($profile, posting('adzuna', '7', 'Java Architect', 'es'));

    fakeChatResponses(jobMatchPayload(20));
    ProcessJobMatch::dispatchSync($match->id);

    expect($match->fresh()->status)->toBe(JobMatch::STATUS_LOW_MATCH)
        ->and(Document::count())->toBe(0);

    // Forzado: reutiliza el puntaje ya calculado y solo lee la oferta y adapta.
    fakeChatResponses(fakeJobPosting(), DocumentFactory::resumePayload());
    ProcessJobMatch::dispatchSync($match->id, true);

    expect($match->fresh()->status)->toBe(JobMatch::STATUS_READY);

    expect(sentChatRequests()[1]['system'])->toContain('MCER');
});

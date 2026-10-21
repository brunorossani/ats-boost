<?php

namespace App\Livewire\Jobs;

use App\Actions\Resume\ExtractResumeText;
use App\Jobs\ProcessJobMatch;
use App\Jobs\SyncJobSearchProfile;
use App\JobSearch\Regions;
use App\Livewire\Concerns\HandlesGenerationFailures;
use App\Models\JobMatch;
use App\Models\JobSearchProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class JobFeed extends Component
{
    use HandlesGenerationFailures, WithFileUploads;

    private const MAX_PROFILES = 3;

    private const TABS = [
        'all' => [
            JobMatch::STATUS_NEW,
            JobMatch::STATUS_PROCESSING,
            JobMatch::STATUS_READY,
            JobMatch::STATUS_FAILED,
            JobMatch::STATUS_LOW_MATCH,
        ],
        'saved' => [], // filtro especial por saved_at
        'applied' => [JobMatch::STATUS_APPLIED],
    ];

    #[Url]
    public string $tab = 'all';

    #[Url]
    public string $sort = 'recent'; // recent|salary|match|recommended

    #[Url]
    public ?int $postedWithin = null; // 1|3|7|30 días

    #[Url]
    public ?string $modality = null; // remote|hybrid|onsite

    #[Url]
    public ?string $employmentType = null;

    #[Url]
    public ?int $minSalary = null;

    #[Url]
    public bool $onlyWithSalary = false;

    #[Url]
    public ?int $minMatch = null; // 50|70|80

    #[Url]
    public bool $onlyWithCv = false;

    #[Url]
    public ?int $profileFilter = null;

    #[Url]
    public ?string $sourceFilter = null;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $oferta = null;

    public ?int $previewId = null;

    public ?int $editingId = null;

    public string $keywords = '';

    public string $countryCode = '';

    public string $city = '';

    public bool $includeRemote = true;

    public int $maxAgeDays = 3;

    /** Búsqueda de la que se reutiliza el CV base, en vez de subir uno nuevo. */
    public ?int $resumeFromId = null;

    public $resumeFile = null;

    #[Computed]
    public function profiles(): Collection
    {
        return Auth::user()->jobSearchProfiles()->orderBy('id')->get();
    }

    #[Computed]
    public function counts(): array
    {
        $user = Auth::user();

        return [
            'all' => $user->jobMatches()->where('status', '!=', JobMatch::STATUS_DISMISSED)->count(),
            'saved' => $user->jobMatches()->whereNotNull('saved_at')->count(),
            'applied' => $user->jobMatches()->where('status', JobMatch::STATUS_APPLIED)->count(),
        ];
    }

    #[Computed]
    public function matches(): Collection
    {
        $query = Auth::user()->jobMatches()
            ->with(['listing', 'profile'])
            ->join('job_listings', 'job_listings.id', '=', 'job_matches.job_listing_id');

        // Filtrar por tab
        if ($this->tab === 'saved') {
            $query->whereNotNull('job_matches.saved_at');
        } elseif ($this->tab === 'applied') {
            $query->where('job_matches.status', JobMatch::STATUS_APPLIED);
        } else {
            // 'all': excluir solo dismissed
            $query->where('job_matches.status', '!=', JobMatch::STATUS_DISMISSED);
        }

        // Filtros
        if ($this->postedWithin) {
            $query->where('job_listings.posted_at', '>=', now()->subDays($this->postedWithin));
        }

        if ($this->modality === 'remote') {
            $query->where('job_listings.is_remote', true);
        } elseif ($this->modality === 'onsite') {
            $query->where('job_listings.is_remote', false);
        }

        if ($this->employmentType) {
            $query->where('job_listings.employment_type', $this->employmentType);
        }

        if ($this->minSalary) {
            $query->where(function ($q) {
                $q->where('job_listings.salary_max_usd_month', '>=', $this->minSalary)
                    ->orWhere('job_listings.salary_min_usd_month', '>=', $this->minSalary);
            });
        }

        if ($this->onlyWithSalary) {
            $query->where(function ($q) {
                $q->whereNotNull('job_listings.salary_min_usd_month')
                    ->orWhereNotNull('job_listings.salary_max_usd_month');
            });
        }

        if ($this->minMatch) {
            $query->where('job_matches.match_score', '>=', $this->minMatch);
        }

        if ($this->onlyWithCv) {
            $query->whereNotNull('job_matches.document_id');
        }

        if ($this->profileFilter) {
            $query->where('job_matches.job_search_profile_id', $this->profileFilter);
        }

        if ($this->sourceFilter) {
            $query->where('job_listings.source', $this->sourceFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $term = '%' . $this->search . '%';
                $q->where('job_listings.title', 'like', $term)
                    ->orWhere('job_listings.company', 'like', $term)
                    ->orWhere('job_listings.location', 'like', $term);
            });
        }

        // Ordenar
        $query = $this->applySorting($query);

        return $query
            ->select('job_matches.*')
            ->limit(100)
            ->get();
    }

    private function applySorting($query)
    {
        return match ($this->sort) {
            'salary' => $query
                ->orderByRaw('COALESCE(job_listings.salary_max_usd_month, job_listings.salary_min_usd_month) DESC NULLS LAST')
                ->orderByDesc('job_matches.id'),
            'match' => $query
                ->orderByDesc('job_matches.match_score')
                ->orderByDesc('job_matches.id'),
            'recommended' => $query
                ->orderByRaw('(COALESCE(job_matches.match_score, 0) * 0.6 + DATEDIFF(NOW(), job_listings.posted_at) * -0.4) DESC')
                ->orderByDesc('job_matches.id'),
            default => $query // 'recent'
                ->orderByDesc('job_listings.posted_at')
                ->orderByDesc('job_matches.id'),
        };
    }

    #[Computed]
    public function preview(): ?JobMatch
    {
        return $this->previewId ? $this->findMatch($this->previewId) : null;
    }

    #[Computed]
    public function hasPending(): bool
    {
        return Auth::user()->jobMatches()
            ->whereIn('status', [JobMatch::STATUS_NEW, JobMatch::STATUS_PROCESSING])
            ->exists();
    }

    #[Computed]
    public function newCount(): int
    {
        return Auth::user()->jobMatches()
            ->where('status', '!=', JobMatch::STATUS_DISMISSED)
            ->whereNull('seen_at')
            ->count();
    }

    public function markAllAsSeen(): void
    {
        Auth::user()->jobMatches()
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        Auth::user()->update(['last_feed_visit_at' => now()]);

        unset($this->newCount);
    }

    public function countries(): array
    {
        return Regions::all();
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'all';
    }

    public function newProfile(): void
    {
        if ($this->profiles->count() >= self::MAX_PROFILES) {
            $this->failed('Podés tener hasta '.self::MAX_PROFILES.' búsquedas activas.');

            return;
        }

        $this->resetForm();
        $this->resumeFromId = $this->profiles->first(fn ($p) => $p->hasResume())?->id;
        $this->modal('profile-form')->show();
    }

    public function editProfile(int $id): void
    {
        $profile = $this->findProfile($id);

        $this->resetForm();
        $this->editingId = $profile->id;
        $this->keywords = $profile->keywords;
        $this->countryCode = (string) $profile->country_code;
        $this->city = (string) $profile->city;
        $this->includeRemote = $profile->include_remote;
        $this->maxAgeDays = $profile->max_age_days;
        $this->resumeFromId = $profile->id;

        $this->modal('profile-form')->show();
    }

    public function saveProfile(ExtractResumeText $extract): void
    {
        $this->validate([
            'keywords' => ['required', 'string', 'min:2', 'max:120'],
            'countryCode' => ['nullable', Rule::in(array_keys(Regions::all()))],
            'city' => ['nullable', 'string', 'max:80'],
            'includeRemote' => ['boolean'],
            'maxAgeDays' => ['required', Rule::in([1, 3, 7])],
            'resumeFile' => [$this->resumeFromId ? 'nullable' : 'required', 'file', 'mimes:pdf,txt', 'max:'.config('resume.limits.upload_kilobytes')],
            'resumeFromId' => ['nullable', Rule::exists('job_search_profiles', 'id')->where('user_id', Auth::id())],
        ], attributes: ['resumeFile' => 'CV', 'keywords' => 'puesto', 'countryCode' => 'país']);

        if ($this->countryCode === '' && ! $this->includeRemote) {
            $this->addError('countryCode', 'Elegí un país o activá las ofertas remotas.');

            return;
        }

        if (! $this->editingId && $this->profiles->count() >= self::MAX_PROFILES) {
            return;
        }

        $resume = $this->resumeFile
            ? $this->attempt(fn () => [
                'resume_filename' => $this->resumeFile->getClientOriginalName(),
                'resume_text' => $extract->handle($this->resumeFile),
                'resume_payload' => null,
            ])
            : $this->findProfile($this->resumeFromId)->only(['resume_filename', 'resume_text', 'resume_payload']);

        if ($resume === null) {
            return;
        }

        $attributes = [
            'keywords' => trim($this->keywords),
            'country_code' => $this->countryCode ?: null,
            'city' => trim($this->city) ?: null,
            'include_remote' => $this->includeRemote,
            'max_age_days' => $this->maxAgeDays,
            'is_active' => true,
            ...$resume,
        ];

        $profile = $this->editingId
            ? tap($this->findProfile($this->editingId))->update($attributes)
            : Auth::user()->jobSearchProfiles()->create($attributes);

        SyncJobSearchProfile::dispatch($profile->id);

        $this->modal('profile-form')->close();
        $this->resetForm();
        unset($this->profiles);

        $this->succeeded('Búsqueda guardada', 'Estamos buscando las ofertas de hoy. Los CVs adaptados aparecen a medida que se generan.');
    }

    public function deleteProfile(int $id): void
    {
        $this->findProfile($id)->delete();
        unset($this->profiles, $this->matches, $this->counts);
    }

    public function syncNow(int $id): void
    {
        $profile = $this->findProfile($id);
        $key = "job-sync:{$profile->id}";

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            $this->failed("Ya buscamos hace poco. Podés volver a buscar en {$minutes} min.");

            return;
        }

        RateLimiter::hit($key, 15 * 60);
        SyncJobSearchProfile::dispatch($profile->id);

        $this->succeeded('Buscando ofertas nuevas', 'Las vas a ver en unos minutos.');
    }

    public function showCv(int $matchId): void
    {
        $match = $this->findMatch($matchId);

        if ($match?->hasCv()) {
            // Marcar como visto al abrir
            if (! $match->seen_at) {
                $match->update(['seen_at' => now()]);
                unset($this->newCount);
            }

            $this->previewId = $matchId;
            $this->modal('cv-preview')->show();
        }
    }

    public function markApplied(int $matchId): void
    {
        $this->findMatch($matchId)?->update(['status' => JobMatch::STATUS_APPLIED, 'applied_at' => now()]);
        $this->modal('cv-preview')->close();
        unset($this->matches, $this->counts);
    }

    public function save(int $matchId): void
    {
        $this->findMatch($matchId)?->update(['saved_at' => now()]);
        unset($this->matches, $this->counts);
    }

    public function unsave(int $matchId): void
    {
        $this->findMatch($matchId)?->update(['saved_at' => null]);
        unset($this->matches, $this->counts);
    }

    public function dismiss(int $matchId): void
    {
        $this->findMatch($matchId)?->update(['status' => JobMatch::STATUS_DISMISSED]);
        unset($this->matches, $this->counts);
    }

    public function openDetail(int $matchId): void
    {
        $this->oferta = $matchId;
    }

    public function closeDetail(): void
    {
        $this->oferta = null;
    }

    public function onMatchDismissed(int $matchId): void
    {
        unset($this->matches, $this->counts);
    }

    public function retry(int $matchId, bool $force = false): void
    {
        $match = $this->findMatch($matchId);

        if (! $match || ! in_array($match->status, [JobMatch::STATUS_FAILED, JobMatch::STATUS_LOW_MATCH], true)) {
            return;
        }

        $force = $force || $match->status === JobMatch::STATUS_LOW_MATCH;
        $match->update(['status' => JobMatch::STATUS_NEW, 'error' => null]);
        ProcessJobMatch::dispatch($match->id, force: $force);

        unset($this->matches, $this->counts);
        $this->succeeded('Generando CV adaptado', 'Vas a verlo cuando esté listo.');
    }

    public function clearFilters(): void
    {
        $this->reset([
            'postedWithin',
            'modality',
            'employmentType',
            'minSalary',
            'onlyWithSalary',
            'minMatch',
            'onlyWithCv',
            'profileFilter',
            'sourceFilter',
            'search',
        ]);
    }

    private function findProfile(?int $id): JobSearchProfile
    {
        return Auth::user()->jobSearchProfiles()->findOrFail($id);
    }

    private function findMatch(int $id): ?JobMatch
    {
        return Auth::user()->jobMatches()->with('listing')->find($id);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'keywords', 'countryCode', 'city', 'includeRemote', 'maxAgeDays', 'resumeFromId', 'resumeFile']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.jobs.job-feed')->title('Mis ofertas • ATS Boost');
    }
}

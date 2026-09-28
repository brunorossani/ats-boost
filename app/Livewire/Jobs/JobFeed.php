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
        'ready' => [JobMatch::STATUS_READY],
        'processing' => [JobMatch::STATUS_NEW, JobMatch::STATUS_PROCESSING, JobMatch::STATUS_FAILED],
        'applied' => [JobMatch::STATUS_APPLIED],
        'low' => [JobMatch::STATUS_LOW_MATCH],
    ];

    #[Url]
    public string $tab = 'ready';

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
        $byStatus = Auth::user()->jobMatches()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(self::TABS)
            ->map(fn (array $statuses) => (int) collect($statuses)->sum(fn ($s) => $byStatus[$s] ?? 0))
            ->all();
    }

    #[Computed]
    public function matches(): Collection
    {
        return Auth::user()->jobMatches()
            ->whereIn('job_matches.status', self::TABS[$this->tab] ?? self::TABS['ready'])
            ->with(['listing', 'profile'])
            ->join('job_listings', 'job_listings.id', '=', 'job_matches.job_listing_id')
            ->orderByDesc('job_listings.posted_at')
            ->orderByDesc('job_matches.id')
            ->select('job_matches.*')
            ->limit(100)
            ->get();
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

    public function countries(): array
    {
        return Regions::all();
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'ready';
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
        if ($this->findMatch($matchId)?->hasCv()) {
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

    public function dismiss(int $matchId): void
    {
        $this->findMatch($matchId)?->update(['status' => JobMatch::STATUS_DISMISSED]);
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
        $this->succeeded('Generando CV adaptado', 'Lo vas a ver en la pestaña "Listas".');
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

<?php

namespace App\Livewire\Jobs;

use App\JobSearch\Regions;
use App\Models\JobMatch;
use App\Models\JobSearchProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Demo pública de la home: las ofertas del día del usuario demo, por país,
 * cada una con su CV adaptado.
 */
class LiveDemo extends Component
{
    #[Url(as: 'pais')]
    public ?string $country = null;

    public ?int $previewId = null;

    #[Computed]
    public function demoUser(): ?User
    {
        return User::where('email', config('jobsearch.demo.user_email'))->first();
    }

    /**
     * @return Collection<int, JobSearchProfile>
     */
    #[Computed]
    public function profiles(): Collection
    {
        if (! $this->demoUser) {
            return collect();
        }

        return $this->demoUser->jobSearchProfiles()
            ->where('is_active', true)
            ->withCount(['matches as ready_count' => fn ($q) => $q->withCv()])
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function profile(): ?JobSearchProfile
    {
        return $this->profiles->firstWhere('country_code', $this->country) ?? $this->profiles->first();
    }

    /**
     * @return Collection<int, JobMatch>
     */
    #[Computed]
    public function matches(): Collection
    {
        if (! $this->profile) {
            return collect();
        }

        return $this->profile->matches()
            ->withCv()
            ->with('listing')
            ->join('job_listings', 'job_listings.id', '=', 'job_matches.job_listing_id')
            ->orderByDesc('job_listings.posted_at')
            ->select('job_matches.*')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function preview(): ?JobMatch
    {
        if (! $this->previewId || ! $this->demoUser) {
            return null;
        }

        return JobMatch::withCv()->with('listing')->where('user_id', $this->demoUser->id)->find($this->previewId);
    }

    public function selectCountry(string $code): void
    {
        $this->country = Regions::exists($code) ? strtolower($code) : null;
        $this->previewId = null;
    }

    public function showCv(int $matchId): void
    {
        $this->previewId = $matchId;

        if ($this->preview) {
            $this->modal('demo-cv-preview')->show();
        } else {
            $this->previewId = null;
        }
    }

    public function render()
    {
        return view('livewire.jobs.live-demo');
    }
}

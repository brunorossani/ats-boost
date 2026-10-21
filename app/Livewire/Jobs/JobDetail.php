<?php

namespace App\Livewire\Jobs;

use App\Models\JobMatch;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Panel de detalle de una oferta: información completa, por qué encaja,
 * cómo postularse y acciones.
 *
 * En escritorio se muestra en un panel lateral (split view).
 * En móvil se muestra fullscreen o como modal flyout.
 */
class JobDetail extends Component
{
    public int $matchId;

    #[Computed]
    public function match(): ?JobMatch
    {
        $match = Auth::user()->jobMatches()
            ->with(['listing', 'profile', 'document'])
            ->find($this->matchId);

        // Marcar como visto al abrir el detalle
        if ($match && ! $match->seen_at) {
            $match->update(['seen_at' => now()]);
        }

        return $match;
    }

    public function close(): void
    {
        $this->dispatch('close-detail');
    }

    public function save(): void
    {
        $this->match?->update(['saved_at' => now()]);
        unset($this->match);
    }

    public function unsave(): void
    {
        $this->match?->update(['saved_at' => null]);
        unset($this->match);
    }

    public function dismiss(): void
    {
        $this->match?->update(['status' => JobMatch::STATUS_DISMISSED]);
        $this->dispatch('match-dismissed', $this->matchId);
        $this->close();
    }

    public function render()
    {
        if (! $this->match) {
            return view('livewire.jobs.job-detail-empty');
        }

        return view('livewire.jobs.job-detail');
    }
}

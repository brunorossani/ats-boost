<?php

namespace App\Livewire\Jobs;

use App\Actions\Jobs\DetectApplicationMethod;
use App\Actions\Jobs\GenerateApplicationEmail;
use App\Actions\Jobs\GenerateCoverLetterForMatch;
use App\Livewire\Concerns\HandlesGenerationFailures;
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
    use HandlesGenerationFailures;

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

    #[Computed]
    public function applicationMethod(): array
    {
        if (! $this->match) {
            return [
                'method' => 'unknown',
                'email' => null,
                'url' => null,
                'instructions' => '',
            ];
        }

        return app(DetectApplicationMethod::class)->handle($this->match->listing);
    }

    #[Computed]
    public function emailDraft(): ?array
    {
        if (! $this->match || $this->applicationMethod['method'] !== 'email') {
            return null;
        }

        $json = app(GenerateApplicationEmail::class)->handle($this->match);
        return json_decode($json, true);
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

    public function generateCoverLetter(GenerateCoverLetterForMatch $generate): void
    {
        if (! $this->match) {
            return;
        }

        $document = $this->attempt(fn () => $generate->handle($this->match));

        if ($document === null) {
            return;
        }

        $this->succeeded('Carta de presentación generada', 'Podés editarla antes de enviarla.');
        $this->redirect(route('documents.edit', $document), navigate: true);
    }

    public function render()
    {
        if (! $this->match) {
            return view('livewire.jobs.job-detail-empty');
        }

        return view('livewire.jobs.job-detail');
    }
}

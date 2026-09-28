<?php

namespace App\Livewire\Resume;

use App\Actions\Resume\GenerateTailoredResume;
use App\Data\ResumeData;
use App\Livewire\Concerns\HandlesGenerationFailures;
use App\Livewire\Forms\TailorResumeForm;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Prueba pública desde la landing.
 *
 * El límite se cuenta contra el RateLimiter y no contra la sesión. La versión
 * anterior guardaba `cv_usage_count` en la sesión del navegador, así que
 * borrar las cookies devolvía las tres pruebas gratis: cada reinicio costaba
 * siete llamadas al modelo pagadas por el producto.
 */
class Demo extends Component
{
    use HandlesGenerationFailures, WithFileUploads;

    public TailorResumeForm $form;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $result = null;

    #[Locked]
    public int $remaining = 0;

    public function mount(): void
    {
        $this->remaining = $this->remainingAttempts();
    }

    public function generate(GenerateTailoredResume $generate): void
    {
        if ($this->remainingAttempts() < 1) {
            $this->dispatch('demo-limit-reached');

            return;
        }

        $this->form->validate();

        $result = $this->attempt(fn () => $generate->handle(
            $this->form->resume,
            $this->form->description,
        ));

        if ($result === null) {
            return;
        }

        // Se consume el cupo recién cuando la generación salió bien: si falla
        // la API de Claude, el intento no se le cobra al visitante.
        RateLimiter::hit($this->limiterKey(), $this->decaySeconds());

        $this->result = $result['resume']->toArray();
        $this->remaining = $this->remainingAttempts();

        $this->dispatch('demo-finished');
    }

    public function tailored(): ?ResumeData
    {
        return $this->result === null ? null : ResumeData::from($this->result);
    }

    public function startOver(): void
    {
        $this->reset('result');
        $this->form->reset();
    }

    private function remainingAttempts(): int
    {
        return RateLimiter::remaining($this->limiterKey(), $this->maxAttempts());
    }

    private function maxAttempts(): int
    {
        return (int) config('resume.limits.demo_generations', 2);
    }

    private function decaySeconds(): int
    {
        return 24 * 60 * 60;
    }

    private function limiterKey(): string
    {
        return 'resume-demo:'.request()->ip();
    }

    public function render()
    {
        return view('livewire.resume.demo', ['tailored' => $this->tailored()]);
    }
}

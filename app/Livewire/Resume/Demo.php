<?php

namespace App\Livewire\Resume;

use App\Services\CvTailorService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Demo extends Component
{
    use WithFileUploads;

    #[Validate('required|mimes:pdf,txt|max:10240', as: 'curriculum')]
    public $resume = null;

    #[Validate('required|string|min:50', as: 'descripción')]
    public string $description = '';

    public array $cv = [];

    public string $previewHtml = '';

    public int $usageCount = 0;

    public string $cvText = '';

    public function mount()
    {
        // La sesión es solo para mostrar el contador al instante en la UI.
        // La fuente de verdad real (que no se puede resetear con una ventana
        // de incógnito o borrando cookies) es el RateLimiter por IP.
        $this->usageCount = max(
            (int) session('cv_usage_count', 0),
            RateLimiter::attempts($this->rateLimiterKey())
        );
    }

    /**
     * Clave del RateLimiter para este visitante, por IP.
     */
    private function rateLimiterKey(): string
    {
        return 'demo-tailor:'.request()->ip();
    }

    /**
     * Los usuarios autenticados y suscritos no tienen límite en el demo.
     */
    private function isDemoUnlimited(): bool
    {
        return Auth::check() && Auth::user()->isSubscribed();
    }

    /**
     * Único chequeo autoritativo del límite. Se re-evalúa siempre contra el
     * RateLimiter (backend de cache persistente), nunca solo contra la sesión.
     */
    private function isDemoLimitReached(): bool
    {
        return ! $this->isDemoUnlimited()
            && RateLimiter::tooManyAttempts($this->rateLimiterKey(), (int) config('demo.tailor_limit'));
    }

    public function startTailoring()
    {
        if ($this->isDemoLimitReached()) {
            $this->modal('limit-modal')->show();

            return;
        }

        $this->validate();

        $this->dispatch('tailoring-demo-started');
    }

    /**
     * Step 2: tailor resume
     *
     * Se llama directamente desde JS ($wire.call('tailorResumeDemo')), así que
     * NO puede confiar en que startTailoring() haya corrido antes. Por eso
     * repite el chequeo del límite y la validación acá, como única fuente de
     * verdad real.
     */
    public function tailorResumeDemo(CvTailorService $service)
    {
        if ($this->isDemoLimitReached()) {
            $this->modal('tailoring-demo-in-progress')->close();
            $this->modal('limit-modal')->show();

            return;
        }

        $this->validate();

        try {
            $result = $service->tailorResume(
                resumePath: $this->resume->getRealPath(),
                jobDescription: $this->description
            );
        } catch (\Throwable $e) {
            Log::error('Demo: fallo al adaptar el currículum', [
                'ip' => request()->ip(),
                'error' => $e->getMessage(),
            ]);

            $this->modal('tailoring-demo-in-progress')->close();

            Flux::toast(
                heading: 'No pudimos adaptar tu currículum',
                text: 'Ocurrió un problema al procesar tu solicitud. Intenta nuevamente en unos minutos.',
                variant: 'danger',
            );

            return;
        }

        $this->cv = $result['cv'];
        $this->previewHtml = $result['html'];

        if (! $this->isDemoUnlimited()) {
            RateLimiter::hit(
                $this->rateLimiterKey(),
                (int) config('demo.tailor_decay_days') * 86400
            );
        }

        $this->usageCount++;
        session(['cv_usage_count' => $this->usageCount]);

        $this->modal('tailoring-demo-in-progress')->close();

        $this->modal('tailoring-demo-result')->show();
    }

    /**
     * Download tailored resume as PDF
     */
    public function downloadPdf(CvTailorService $service)
    {
        if (empty($this->cv)) {
            return;
        }

        try {
            $pdf = $service->downloadPdf($this->cv);
        } catch (\Throwable $e) {
            Log::error('Demo: fallo al generar el PDF', [
                'ip' => request()->ip(),
                'error' => $e->getMessage(),
            ]);

            Flux::toast(
                heading: 'No se pudo generar el PDF',
                text: 'Intenta nuevamente en unos minutos.',
                variant: 'danger',
            );

            return;
        }

        Flux::toast(
            heading: '¡Pdf listo!',
            text: 'Tu currículum adaptado se descargó correctamente.',
            variant: 'success',
        );

        return $pdf;
    }

    public function render()
    {
        return view('livewire.resume.demo');
    }
}

<?php

namespace App\Livewire\Resume;

use App\Models\TailoredResume;
use App\Services\CvTailorService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class ResumeTailor extends Component
{
    use WithFileUploads;

    #[Validate('required|mimes:pdf,txt|max:10240', as: 'curriculum')]
    public $resume = null;

    #[Validate('required|string|min:50')]
    public string $description = '';

    public array $cv = [];

    public string $previewHtml = '';

    public string $cvText = '';

    public ?int $tailoredResumeId = null;

    /**
     * Step 1: validate + open progress modal
     */
    public function startTailoring()
    {
        $this->validate();
        $this->dispatch('tailoring-started');
    }

    /**
     * Step 2: tailor resume
     *
     * Se llama directamente desde JS, así que valida y maneja errores por su
     * cuenta en lugar de confiar en que startTailoring() haya corrido antes.
     */
    public function tailorResume(CvTailorService $service)
    {
        $this->validate();

        try {
            $result = $service->tailorResume(
                resumePath: $this->resume->getRealPath(),
                jobDescription: $this->description
            );
        } catch (\Throwable $e) {
            Log::error('ResumeTailor: fallo al adaptar el currículum', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            $this->modal('tailoring-in-progress')->close();

            Flux::toast(
                heading: 'No pudimos adaptar tu currículum',
                text: 'Ocurrió un problema al procesar tu solicitud. Intenta nuevamente en unos minutos.',
                variant: 'danger',
            );

            return;
        }

        $this->cv = $result['cv'];
        $this->previewHtml = $result['html'];
        $this->cvText = $result['cvText'];

        $candidateName = $this->cv['name'] ?? null;

        if (Auth::check()) {
            $user = Auth::user();
            $sourceHash = hash_file('sha256', $this->resume->getRealPath());
            $resume = $user->resumes()->where('source_hash', $sourceHash)->first();

            if (! $resume) {
                $resume = $user->resumes()->create([
                    'original_filename' => $this->resume->getClientOriginalName(),
                    'file_path' => $this->resume->store("resumes/{$user->id}", 'local'),
                    'source_hash' => $sourceHash,
                    'extracted_text' => $this->cvText,
                    'candidate_name' => $candidateName,
                ]);
            }

            $tailoredResume = TailoredResume::create([
                'user_id' => $user->id,
                'resume_id' => $resume->id,
                'title' => $candidateName
                    ? "CV adaptado de {$candidateName}"
                    : 'CV adaptado',
                'candidate_name' => $candidateName,
                'job_description' => $this->description,
                'html' => $this->previewHtml,
            ]);

            $this->tailoredResumeId = $tailoredResume->id;
        }

        $this->modal('tailoring-in-progress')->close();
        $this->modal('tailoring-result')->show();
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
            Log::error('ResumeTailor: fallo al generar el PDF', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            Flux::toast(
                heading: 'No se pudo generar el PDF',
                text: 'Intenta nuevamente en unos minutos.',
                variant: 'danger',
            );

            return;
        }

        if ($this->tailoredResumeId) {
            TailoredResume::query()
                ->whereKey($this->tailoredResumeId)
                ->where('user_id', Auth::id())
                ->update(['html' => $this->previewHtml]);
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
        return view('livewire.resume.resume-tailor')
            ->title('Adaptador de curriculum • ATS Boost');
    }
}

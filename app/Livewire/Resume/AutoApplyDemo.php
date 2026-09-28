<?php

namespace App\Livewire\Resume;

use App\Services\CvTailorService;
use App\Services\JobSearchService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Vista previa del próximo producto: en vez de que la persona adapte y
 * descargue su CV manualmente, la IA busca vacantes reales y arma la
 * postulación automáticamente.
 *
 * La búsqueda es REAL (JobSearchService, vía la API de Composio/Exa) —
 * trae vacantes de verdad de LinkedIn, Indeed, Computrabajo y ATS de
 * empresas. El CV de la vacante con mejor ranking se adapta de verdad
 * con el motor de IA existente (CvTailorService), usando el texto REAL
 * de esa oferta, no una descripción inventada.
 *
 * Lo que sigue siendo una simulación: el ENVÍO/postulación en sí — todavía
 * no hay integración que efectivamente postule en nombre del usuario en
 * cada portal. Eso es el siguiente paso, no este.
 */
class AutoApplyDemo extends Component
{
    use WithFileUploads;

    public const DAILY_LIMIT = 10;

    #[Validate('required|mimes:pdf,txt|max:10240', as: 'curriculum')]
    public $resume = null;

    public string $desiredRole = '';

    public string $location = '';

    /** upload | matches | dashboard */
    public string $step = 'upload';

    public array $matches = [];

    public bool $searchFailed = false;

    public array $sentLog = [];

    public ?string $tailoredPreviewHtml = null;

    public bool $tailoredPreviewFailed = false;

    public int $sentToday = 0;

    public int $dailyLimit = self::DAILY_LIMIT;

    private const CHANNELS = [
        'linkedin' => ['label' => 'LinkedIn', 'color' => 'blue', 'icon' => 'globe-alt'],
        'portal' => ['label' => 'Portal / ATS', 'color' => 'orange', 'icon' => 'magnifying-glass'],
    ];

    public function startSearch()
    {
        $this->validate();

        $this->dispatch('auto-apply-searching-started');
    }

    /**
     * Búsqueda REAL: LinkedIn, Indeed, Computrabajo y ATS de empresas, vía
     * JobSearchService (Composio/Exa). Sin datos inventados.
     */
    public function runSearch(JobSearchService $service)
    {
        $role = trim($this->desiredRole) ?: 'Desarrollador de software';
        $location = trim($this->location) ?: 'Uruguay';

        try {
            $postings = $service->findJobPostings($role, $location, limit: 6);
        } catch (\Throwable $e) {
            Log::error('AutoApplyDemo: la búsqueda de vacantes falló', [
                'error' => $e->getMessage(),
            ]);

            $postings = [];
        }

        $this->searchFailed = empty($postings);

        $this->matches = collect($postings)
            ->values()
            ->map(fn (array $job, int $i) => ['id' => $i + 1, ...$job])
            ->all();

        $this->step = 'matches';

        $this->dispatch('auto-apply-searching-finished');
    }

    public function startSending()
    {
        $this->dispatch('auto-apply-sending-started');
    }

    /**
     * "Postula" a cada vacante encontrada, respetando el tope diario. El
     * envío/postulación en sí sigue siendo simulado (todavía no hay
     * integración que postule de verdad en cada portal) — pero el CV que se
     * genera para la vacante top es real, adaptado con el texto real de esa
     * oferta.
     */
    public function runSend(CvTailorService $service)
    {
        $toSend = array_slice($this->matches, 0, self::DAILY_LIMIT);

        if ($toSend && $this->resume) {
            $top = $toSend[0];

            try {
                $result = $service->tailorResume(
                    resumePath: $this->resume->getRealPath(),
                    jobDescription: $top['description'] ?? "{$top['title']} en {$top['company']}",
                );

                $this->tailoredPreviewHtml = $result['html'];
            } catch (\Throwable $e) {
                Log::warning('AutoApplyDemo: no se pudo generar la vista previa real de IA', [
                    'error' => $e->getMessage(),
                ]);

                $this->tailoredPreviewFailed = true;
            }
        }

        $this->sentLog = collect($toSend)
            ->map(function (array $job) {
                $viewed = random_int(1, 100) <= 35;

                return [
                    ...$job,
                    'status' => 'Postulado automáticamente',
                    'follow_up' => $viewed ? 'Visto por el reclutador' : 'Esperando respuesta',
                ];
            })
            ->all();

        $this->sentToday = count($this->sentLog);
        $this->step = 'dashboard';

        $this->dispatch('auto-apply-sending-finished');
    }

    /**
     * No se llama "reset" a propósito: Livewire\Component ya expone un
     * método reset() heredado para resetear propiedades una por una, y
     * sobreescribirlo pisaría ese comportamiento.
     */
    public function restart()
    {
        $this->resume = null;
        $this->desiredRole = '';
        $this->location = '';
        $this->step = 'upload';
        $this->matches = [];
        $this->searchFailed = false;
        $this->sentLog = [];
        $this->tailoredPreviewHtml = null;
        $this->tailoredPreviewFailed = false;
        $this->sentToday = 0;
    }

    public function channelMeta(string $channel): array
    {
        return self::CHANNELS[$channel] ?? self::CHANNELS['portal'];
    }

    public function render()
    {
        return view('livewire.resume.auto-apply-demo');
    }
}

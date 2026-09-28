<?php

namespace App\JobSearch\Providers;

use App\JobSearch\JobPosting;
use App\JobSearch\JobQuery;

interface JobProvider
{
    public function key(): string;

    public function label(): string;

    /**
     * Credenciales / configuración presentes.
     */
    public function isConfigured(): bool;

    /**
     * ¿Este portal tiene cobertura para el país/región (o para remoto) de la búsqueda?
     */
    public function supports(JobQuery $query): bool;

    /**
     * @return list<JobPosting>
     */
    public function search(JobQuery $query): array;
}

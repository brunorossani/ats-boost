<?php

namespace App\Actions\Documents;

use App\Data\AtsReportData;
use App\Data\CoverLetterData;
use App\Data\ResumeData;
use App\Enums\DocumentType;
use App\Enums\ResumeTemplate;
use App\Models\Document;
use App\Models\User;

/**
 * Persiste (o actualiza) un documento generado.
 *
 * Todo lo que se guarda deriva del DTO, así que no hay forma de que el
 * historial y el PDF muestren cosas distintas.
 */
class StoreDocument
{
    public function handle(
        User $user,
        DocumentType $type,
        ResumeData|CoverLetterData $data,
        ?string $role = null,
        ?string $company = null,
        ?string $jobDescription = null,
        ?string $sourceFilename = null,
        ?AtsReportData $report = null,
        ResumeTemplate $template = ResumeTemplate::Claude,
        ?Document $existing = null,
    ): Document {
        $attributes = [
            'type' => $type,
            'title' => $this->title($type, $data, $role, $company),
            'language' => $data->language,
            'template' => $template,
            'payload' => $data->toArray(),
            'report' => $report?->toArray(),
            'ats_score' => $report?->score,
            'role' => $role,
            'company' => $company,
            'job_description' => $jobDescription,
            'source_filename' => $sourceFilename,
        ];

        // Regenerar sobre el mismo documento (por ejemplo, tras editarlo)
        // actualiza en lugar de llenar el historial de duplicados.
        if ($existing !== null) {
            $existing->update($attributes);

            return $existing->refresh();
        }

        return $user->documents()->create($attributes);
    }

    private function title(
        DocumentType $type,
        ResumeData|CoverLetterData $data,
        ?string $role,
        ?string $company,
    ): string {
        $subject = $role
            ?? ($data instanceof ResumeData ? $data->headline : $data->role)
            ?? $type->label();

        return trim($company ? "{$subject} · {$company}" : $subject);
    }
}

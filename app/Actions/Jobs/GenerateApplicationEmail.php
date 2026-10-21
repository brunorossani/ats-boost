<?php

namespace App\Actions\Jobs;

use App\Models\JobMatch;

/**
 * Genera un borrador de email de postulación basado en el match y el CV adaptado.
 */
class GenerateApplicationEmail
{
    public function handle(JobMatch $match, ?string $applicantName = null): string
    {
        $listing = $match->listing;
        $name = $applicantName ?? $match->user->name ?? 'el equipo';

        $subject = "Postulación para {$listing->title}";

        if ($listing->company) {
            $subject .= " en {$listing->company}";
        }

        $body = "Estimado/a {$name},\n\n";
        $body .= "Me comunico para postularme a la posición de {$listing->title}";

        if ($listing->company) {
            $body .= " en {$listing->company}";
        }

        $body .= ".\n\n";
        $body .= "Adjunto mi currículum para su consideración. ";

        if ($match->hasCv()) {
            $body .= "El CV está adaptado específicamente para este rol, destacando mi experiencia relevante";

            if (! empty($match->match_details['matching'])) {
                $skills = array_slice($match->match_details['matching'], 0, 3);
                $body .= ' en ' . implode(', ', $skills);
            }

            $body .= ".\n\n";
        }

        $body .= "Quedo a su disposición para ampliar la información que consideren necesaria.\n\n";
        $body .= "Saludos cordiales";

        return json_encode([
            'subject' => $subject,
            'body' => $body,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

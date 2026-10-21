<?php

namespace App\Actions\Jobs;

use App\Models\JobListing;

/**
 * Detecta el método de postulación de una oferta analizando su descripción y URL.
 *
 * Retorna un array con:
 * - method: 'email' | 'linkedin' | 'portal' | 'unknown'
 * - email: dirección de email si method === 'email'
 * - url: URL de postulación si method === 'portal' o 'linkedin'
 * - instructions: texto guía para el usuario
 */
class DetectApplicationMethod
{
    public function handle(JobListing $listing): array
    {
        // 1. Buscar email en la descripción
        $email = $this->extractEmail($listing->description);
        if ($email) {
            return [
                'method' => 'email',
                'email' => $email,
                'url' => null,
                'instructions' => "Enviá tu CV y carta de presentación a {$email}",
            ];
        }

        // 2. Detectar LinkedIn
        if ($this->isLinkedIn($listing->url)) {
            return [
                'method' => 'linkedin',
                'email' => null,
                'url' => $listing->safeUrl(),
                'instructions' => 'Postulate con LinkedIn Easy Apply',
            ];
        }

        // 3. Portal externo con URL conocida
        if ($listing->safeUrl()) {
            return [
                'method' => 'portal',
                'email' => null,
                'url' => $listing->safeUrl(),
                'instructions' => 'Completá el formulario en el portal de la empresa',
            ];
        }

        // 4. Método desconocido
        return [
            'method' => 'unknown',
            'email' => null,
            'url' => null,
            'instructions' => 'Consultá los detalles de la oferta para saber cómo postularte',
        ];
    }

    private function extractEmail(string $text): ?string
    {
        // Patrón para emails, evitando falsos positivos comunes
        if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/i', $text, $matches)) {
            $email = strtolower($matches[0]);

            // Filtrar emails genéricos que no son de postulación
            $blocklist = ['example.com', 'test.com', 'noreply', 'no-reply', 'donotreply'];
            foreach ($blocklist as $blocked) {
                if (str_contains($email, $blocked)) {
                    return null;
                }
            }

            return $email;
        }

        return null;
    }

    private function isLinkedIn(string $url): bool
    {
        return str_contains(strtolower($url), 'linkedin.com');
    }
}

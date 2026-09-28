<?php

namespace App\Services\Ai\Schemas;

use App\Services\Ai\JsonSchema;

final class CoverLetterSchema
{
    public static function structure(): JsonSchema
    {
        return JsonSchema::object('cover_letter', [
            'candidate_name' => JsonSchema::string('Nombre y apellido del candidato, tal como figura en el CV.'),
            'contact' => JsonSchema::shape([
                'location' => JsonSchema::nullableString('Ciudad y país.'),
                'email' => JsonSchema::nullableString('Correo electrónico.'),
                'phone' => JsonSchema::nullableString('Teléfono.'),
                'links' => JsonSchema::stringArray('Perfiles relevantes, sin https:// ni www.'),
            ], 'Datos de contacto del encabezado de la carta.'),
            'role' => JsonSchema::nullableString('Puesto al que se postula.'),
            'company' => JsonSchema::nullableString('Empresa destinataria.'),
            'greeting' => JsonSchema::string(
                'Saludo inicial. Usá el nombre de la empresa si se conoce; si no, un encabezado neutro y profesional. Sin "A quien corresponda".'
            ),
            'paragraphs' => JsonSchema::stringArray(
                'Entre 3 y 4 párrafos: motivo de la postulación, evidencia concreta del CV que responde a la oferta, y cierre con disponibilidad. Texto plano, sin HTML ni viñetas.'
            ),
            'closing' => JsonSchema::string('Despedida formal breve, sin incluir el nombre del candidato.'),
        ]);
    }
}

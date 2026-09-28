<?php

namespace App\Services\Ai\Schemas;

use App\Services\Ai\JsonSchema;

/**
 * Esquema del currículum estructurado.
 *
 * Es el contrato que reemplaza al HTML libre que devolvía el motor anterior.
 * Al fijar la forma acá, la plantilla del PDF puede alinear las fechas a la
 * derecha y armar la tabla de habilidades: con HTML suelto eso era imposible.
 */
final class ResumeSchema
{
    public static function structure(): JsonSchema
    {
        return JsonSchema::object('resume', [
            'full_name' => JsonSchema::string(
                'Nombre y apellido del candidato, exactamente como figura en el CV.'
            ),
            'headline' => JsonSchema::nullableString(
                'Titular profesional de una línea, en el formato "Cargo — Tecnología · Tecnología · Tecnología".'
            ),
            'contact' => self::contact(),
            'summary' => JsonSchema::nullableString(
                'Resumen profesional de 2 o 3 oraciones. Devolvé null salvo que el CV original ya traiga uno.'
            ),
            'experience' => JsonSchema::array(
                'Experiencia laboral, de la más reciente a la más antigua.',
                self::experienceEntry(),
            ),
            'education' => JsonSchema::array(
                'Formación académica, de la más reciente a la más antigua.',
                self::educationEntry(),
            ),
            'projects' => JsonSchema::array(
                'Proyectos personales, académicos o de código abierto. Array vacío si el CV no menciona ninguno.',
                self::projectEntry(),
            ),
            'skills' => JsonSchema::array(
                'Habilidades agrupadas por categoría. Entre 3 y 6 grupos.',
                self::skillGroup(),
            ),
            'certifications' => JsonSchema::array(
                'Certificaciones y cursos acreditados. Array vacío si no hay.',
                self::certification(),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function contact(): array
    {
        return JsonSchema::shape([
            'location' => JsonSchema::nullableString('Ciudad y país, por ejemplo "Montevideo, Uruguay".'),
            'email' => JsonSchema::nullableString('Correo electrónico.'),
            'phone' => JsonSchema::nullableString('Teléfono con prefijo internacional.'),
            'links' => JsonSchema::stringArray(
                'Perfiles y sitios, sin el prefijo https:// ni www. Por ejemplo "github.com/usuario" o "linkedin.com/in/usuario".'
            ),
        ], 'Datos de contacto del encabezado.');
    }

    /**
     * @return array<string, mixed>
     */
    private static function experienceEntry(): array
    {
        return JsonSchema::shape([
            'role' => JsonSchema::string('Cargo. Solo el título del puesto, sin empresa, fechas ni ubicación.'),
            'company' => JsonSchema::nullableString('Nombre de la empresa u organización, sin el cargo.'),
            'location' => JsonSchema::nullableString('Ciudad y país donde se desempeñó el puesto.'),
            'dates' => JsonSchema::nullableString(
                'Rango de fechas en el formato "Mes AAAA – Mes AAAA", o "Mes AAAA – Presente" si sigue vigente. Usá guion largo (–).'
            ),
            'bullets' => JsonSchema::stringArray(
                'Logros y responsabilidades. Cada viñeta empieza con un verbo de acción conjugado y es una sola oración sin punto final ni viñeta literal.'
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function educationEntry(): array
    {
        return JsonSchema::shape([
            'degree' => JsonSchema::string('Título o programa cursado.'),
            'institution' => JsonSchema::nullableString('Institución educativa.'),
            'location' => JsonSchema::nullableString('Campus, ciudad o país.'),
            'dates' => JsonSchema::nullableString('Rango de años, por ejemplo "2024 – 2027".'),
            'description' => JsonSchema::nullableString(
                'Una línea con materias relevantes o mención académica. Sin viñetas.'
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function projectEntry(): array
    {
        return JsonSchema::shape([
            'name' => JsonSchema::string('Nombre del proyecto.'),
            'description' => JsonSchema::nullableString(
                'Qué hace el proyecto y con qué tecnologías, en una o dos oraciones.'
            ),
            'meta' => JsonSchema::nullableString('Contexto y año, por ejemplo "Estructuras de Datos y Algoritmos, 2025".'),
            'link' => JsonSchema::nullableString('Dominio del proyecto, sin https:// ni www.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function skillGroup(): array
    {
        return JsonSchema::shape([
            'label' => JsonSchema::string(
                'Categoría de una sola palabra o dos, por ejemplo "Programación", "Frameworks", "Bases de datos", "Herramientas", "Idiomas".'
            ),
            'value' => JsonSchema::string(
                'Habilidades de esa categoría separadas por coma, en una sola línea, sin viñetas ni niveles de dominio inventados.'
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function certification(): array
    {
        return JsonSchema::shape([
            'name' => JsonSchema::string('Nombre de la certificación.'),
            'issuer' => JsonSchema::nullableString('Entidad que la emitió.'),
            'year' => JsonSchema::nullableString('Año de obtención.'),
        ]);
    }
}

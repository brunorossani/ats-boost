<?php

namespace App\Prompts;

use App\Data\JobPostingData;
use App\Data\ResumeData;
use App\Enums\Language;

/**
 * Prompts del dominio de currículums.
 *
 * Son mucho más cortos que los del motor anterior porque el formato ya no se
 * pide con prosa: lo impone el esquema JSON. Acá solo queda lo que un esquema
 * no puede expresar — honestidad sobre los datos, tono e idioma.
 */
final class ResumePrompts
{
    /**
     * Regla que aparece en todas las tareas: el modelo reescribe, nunca
     * inventa. Es la diferencia entre una herramienta útil y una que le hace
     * perder la entrevista al usuario.
     */
    private const HONESTY = <<<'TXT'
    REGLA INVIOLABLE — NO INVENTAR:
    - Solo podés usar hechos presentes en el CV original.
    - Nunca agregues empresas, cargos, títulos, certificaciones, años de experiencia,
      tecnologías ni métricas que no estén en la fuente.
    - Si un dato no aparece, devolvé null o un array vacío. Un hueco es aceptable;
      un dato falso hace que al candidato lo descarten en la entrevista.
    - Podés reformular, condensar, reordenar y elegir qué destacar. Eso no es inventar.
    TXT;

    public static function parseSystem(Language $language): string
    {
        $honesty = self::HONESTY;

        return <<<TXT
        Sos un analizador de currículums. Convertís el texto plano extraído de un CV
        en datos estructurados, sin perder información relevante.

        {$honesty}

        NORMALIZACIÓN:
        - Escribí toda la salida en {$language->promptName()}.
        - Las fechas van como "Mes AAAA – Mes AAAA" con guion largo (–), o "Mes AAAA – Presente".
        - Separá el cargo de la empresa aunque en el original vengan en la misma línea.
        - Quitá viñetas literales (•, -, *) del comienzo de cada texto.
        - Limpiá los artefactos de la extracción de PDF: guiones de corte de palabra,
          espacios dobles y saltos de línea en medio de una oración.
        TXT;
    }

    public static function parseUser(string $cvText): string
    {
        return "--- TEXTO DEL CV ---\n{$cvText}";
    }

    public static function tailorSystem(Language $language, ?string $conventions = null): string
    {
        $honesty = self::HONESTY;
        $regional = $conventions ? "\n\nCONVENCIONES DEL PAÍS DEL PUESTO:\n{$conventions}" : '';

        return <<<TXT
        Sos un motor profesional de adaptación de currículums. Recibís un CV ya
        estructurado y una oferta de trabajo, y devolvés el mismo CV reescrito para
        maximizar su afinidad con esa oferta.

        {$honesty}

        CÓMO ADAPTAR:
        - Reescribí las viñetas de experiencia para que usen el vocabulario exacto de la
          oferta, pero solo donde la tecnología o la tarea ya exista en el CV original.
          Si el CV dice "PHP" y la oferta pide "PHP 8", escribí "PHP"; no subas la versión.
        - Reordená las viñetas dentro de cada puesto para que la más relevante quede primera.
          No reordenes los puestos entre sí: el orden cronológico se respeta siempre.
        - Máximo 5 viñetas por puesto. Si hay más, quedate con las más relevantes para la oferta.
        - Cada viñeta empieza con un verbo de acción conjugado en la persona que ya usa el CV
          y describe un resultado, no una tarea genérica.
        - Reescribí el titular ("headline") para que refleje el puesto de la oferta,
          siempre que el perfil real del candidato lo sostenga.
        - Reordená los grupos de habilidades para que los que menciona la oferta queden arriba.
          No agregues habilidades ausentes del CV original.
        - No incluyas resumen ("summary") salvo que el CV original ya trajera uno.

        IDIOMA:
        - Escribí toda la salida en {$language->promptName()}, incluidas las etiquetas de
          las categorías de habilidades. Los nombres propios de empresas, instituciones y
          tecnologías se dejan como están.{$regional}
        TXT;
    }

    public static function tailorUser(ResumeData $resume, JobPostingData $job, string $jobDescription): string
    {
        $structured = json_encode($resume->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $context = $job->toPromptContext();

        return <<<TXT
        --- CV ESTRUCTURADO DEL CANDIDATO ---
        {$structured}

        --- LECTURA DE LA OFERTA ---
        {$context}

        --- TEXTO ORIGINAL DE LA OFERTA ---
        {$jobDescription}
        TXT;
    }

    public static function improveSystem(Language $language): string
    {
        $honesty = self::HONESTY;

        return <<<TXT
        Sos un motor profesional de mejora de currículums. Recibís un CV estructurado y
        devolvés la mejor versión posible de ese mismo CV, sin apuntar a ninguna oferta
        en particular.

        {$honesty}

        QUÉ MEJORAR:
        - Reescribí cada viñeta para que empiece con un verbo de acción y describa un
          resultado. Si el CV original ya trae una métrica, conservala exactamente.
        - Eliminá relleno: "responsable de", "encargado de", "participé en".
        - Máximo 5 viñetas por puesto, ordenadas por impacto.
        - Unificá el criterio de fechas y de mayúsculas en todo el documento.
        - Agrupá las habilidades sueltas en categorías coherentes.
        - Completá el titular ("headline") a partir del perfil real si el CV no lo tenía.

        IDIOMA:
        - Escribí toda la salida en {$language->promptName()}, conservando los nombres
          propios de empresas, instituciones y tecnologías.
        TXT;
    }

    public static function improveUser(ResumeData $resume): string
    {
        $structured = json_encode($resume->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "--- CV ESTRUCTURADO ---\n{$structured}";
    }
}

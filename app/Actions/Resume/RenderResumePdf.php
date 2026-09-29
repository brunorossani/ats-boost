<?php

namespace App\Actions\Resume;

use App\Data\ResumeData;
use App\Enums\ResumeTemplate;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Currículum estructurado → binario PDF.
 *
 * El HTML vive en una vista Blade, no en un heredoc dentro de una clase de
 * servicio: así el diseño se puede revisar en un diff y probar en el
 * navegador antes de compilarlo.
 */
class RenderResumePdf
{
    public function handle(ResumeData $resume, ResumeTemplate $template = ResumeTemplate::Claude): string
    {
        return Pdf::loadView($template->view(), [
            'resume' => $resume,
            'metrics' => $template->metrics(),
            'font' => config('resume.pdf.font'),
            'locale' => $resume->language->locale(),
        ])
            ->setPaper(config('resume.pdf.paper'), 'portrait')
            ->setOptions([
                'defaultFont' => config('resume.pdf.font'),
                // El CV nunca referencia recursos externos. Dejarlo apagado
                // cierra el SSRF que abriría un CV con un <img src> remoto.
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'dpi' => 96,
            ])
            ->output();
    }
}

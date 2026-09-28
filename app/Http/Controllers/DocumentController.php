<?php

namespace App\Http\Controllers;

use App\Actions\Resume\BuildDocumentFileName;
use App\Actions\Resume\RenderCoverLetterPdf;
use App\Actions\Resume\RenderResumePdf;
use App\Models\Document;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Entrega el PDF de un documento guardado.
 *
 * El binario se genera en cada pedido a partir del documento estructurado en
 * vez de guardarse en disco: no quedan archivos con datos personales en el
 * servidor y un ajuste de la plantilla alcanza a todo el historial.
 */
class DocumentController extends Controller
{
    public function __construct(
        private readonly RenderResumePdf $renderResume,
        private readonly RenderCoverLetterPdf $renderLetter,
        private readonly BuildDocumentFileName $buildFileName,
    ) {}

    public function download(Document $document): Response
    {
        $this->authorize('download', $document);

        return $this->pdf($document, disposition: 'attachment');
    }

    /**
     * Misma salida, servida en línea para el iframe de vista previa del editor.
     */
    public function preview(Document $document): Response
    {
        $this->authorize('view', $document);

        return $this->pdf($document, disposition: 'inline');
    }

    /**
     * CVs de la demo pública de la home: sin login, pero solo los del usuario demo.
     */
    public function demo(Document $document, string $disposition = 'inline'): Response
    {
        abort_unless(
            $document->user()->where('email', config('jobsearch.demo.user_email'))->exists(),
            404,
        );

        return $this->pdf($document, disposition: $disposition === 'attachment' ? 'attachment' : 'inline');
    }

    private function pdf(Document $document, string $disposition): Response
    {
        $binary = $document->type->isResume()
            ? $this->renderResume->handle($document->resume(), $document->template)
            : $this->renderLetter->handle($document->coverLetter(), $document->template);

        $filename = $this->buildFileName->handle(
            type: $document->type,
            candidateName: $document->type->isResume()
                ? $document->resume()->fullName
                : $document->coverLetter()->candidateName,
            role: $document->role,
            company: $document->company,
        );

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            // makeDisposition codifica el nombre según RFC 6266: sin esto los
            // acentos del nombre del candidato llegan rotos a Windows. Exige
            // además un alternativo ASCII para los clientes viejos, y lanza
            // excepción si no se lo damos.
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $disposition,
                $filename,
                $this->asciiFallback($filename),
            ),
            // El PDF se regenera en cada pedido; cachearlo mostraría la
            // versión anterior después de editar.
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function asciiFallback(string $filename): string
    {
        $ascii = Str::ascii($filename);

        // Sólo caracteres imprimibles ASCII, y ni % ni separadores de ruta.
        $ascii = preg_replace('/[^\x20-\x7e]|[%\/\\\\]/', '_', $ascii) ?? '';

        return trim($ascii) !== '' ? $ascii : 'documento.pdf';
    }
}

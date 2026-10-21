<?php

namespace App\Livewire\Resume;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Pantalla de Mi CV: CV base único, salud ATS, plantillas y documentos generados (placeholder Fase 0).
 * Se implementará en Fase 2 con el modelo Resume y en CU10.
 */
class BaseResume extends Component
{
    public function render()
    {
        $documents = Auth::user()->documents()->latestFirst()->limit(10)->get();

        return view('livewire.resume.base-resume', [
            'documents' => $documents,
        ])->title('Mi CV • ATS Boost');
    }
}

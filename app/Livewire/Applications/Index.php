<?php

namespace App\Livewire\Applications;

use Livewire\Component;

/**
 * Pantalla de seguimiento de postulaciones (placeholder Fase 0).
 * Se implementará en Fase 5 con estados, notas y recordatorios.
 */
class Index extends Component
{
    public function render()
    {
        return view('livewire.applications.index')->title('Postulaciones • ATS Boost');
    }
}

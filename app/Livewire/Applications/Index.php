<?php

namespace App\Livewire\Applications;

use App\Models\JobMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Pantalla de seguimiento de postulaciones.
 * Lista todas las ofertas a las que te postulaste con estado y notas.
 */
class Index extends Component
{
    public string $filter = 'all'; // all|active|archived

    public ?int $selectedId = null;

    #[Computed]
    public function applications(): Collection
    {
        $query = Auth::user()->jobMatches()
            ->with(['listing', 'profile'])
            ->where('status', JobMatch::STATUS_APPLIED)
            ->orderByDesc('applied_at');

        return $query->get();
    }

    public function selectApplication(int $id): void
    {
        $this->selectedId = $id;
    }

    public function render()
    {
        return view('livewire.applications.index')->title('Postulaciones • ATS Boost');
    }
}

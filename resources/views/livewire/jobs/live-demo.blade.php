<div class="space-y-6">
    @if ($this->profiles->isEmpty())
        <flux:callout icon="information-circle">
            <flux:callout.heading>La demo todavía no está configurada</flux:callout.heading>
            <flux:callout.text>
                Corré <code>php artisan jobs:demo ruta/al/cv.pdf --keywords="Laravel developer"</code> para cargar las ofertas del día.
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($this->profiles as $p)
                <flux:button
                    size="sm"
                    wire:key="country-{{ $p->id }}"
                    wire:click="selectCountry('{{ $p->country_code }}')"
                    :variant="$this->profile?->is($p) ? 'primary' : 'ghost'"
                >
                    {{ $p->label() }}
                    <flux:badge size="sm" class="ms-1">{{ $p->ready_count }}</flux:badge>
                </flux:button>
            @endforeach
        </div>

        @if ($this->profile)
            <flux:text>
                Buscando <strong>{{ $this->profile->keywords }}</strong> en {{ $this->profile->label() }}{{ $this->profile->include_remote ? ' y remoto' : '' }},
                publicadas en los últimos {{ $this->profile->max_age_days }} días.
                @if ($this->profile->last_synced_at)
                    Actualizado {{ $this->profile->last_synced_at->diffForHumans() }}.
                @endif
            </flux:text>
        @endif

        <div class="grid gap-4">
            @forelse ($this->matches as $match)
                <x-job-match-card :match="$match" wire:key="demo-match-{{ $match->id }}">
                    <flux:button size="sm" variant="primary" icon="document-text" wire:click="showCv({{ $match->id }})">
                        Ver CV adaptado
                    </flux:button>
                    <flux:button size="sm" icon="arrow-down-tray" :href="route('demo.document', [$match->document_id, 'attachment'])">
                        PDF
                    </flux:button>
                </x-job-match-card>
            @empty
                <flux:card>
                    <flux:subheading>
                        Todavía no hay CVs adaptados para este país. Se generan en cada búsqueda programada.
                    </flux:subheading>
                </flux:card>
            @endforelse
        </div>
    @endif

    <flux:modal name="demo-cv-preview" variant="floating" class="w-full! max-w-3xl space-y-4 p-4">
        @if ($this->preview)
            <div>
                <flux:heading size="lg">CV adaptado para {{ $this->preview->listing->title }}</flux:heading>
                <flux:subheading>{{ $this->preview->listing->company }} · {{ $this->preview->listing->locationLabel() }}</flux:subheading>
            </div>

            <iframe src="{{ route('demo.document', $this->preview->document_id) }}" title="CV adaptado"
                class="w-full h-[70vh] rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white"></iframe>

            <div class="flex justify-end gap-2">
                @if ($this->preview->listing->safeUrl())
                    <flux:button variant="ghost" icon-trailing="arrow-top-right-on-square" :href="$this->preview->listing->safeUrl()" target="_blank" rel="noopener noreferrer">
                        Ver oferta
                    </flux:button>
                @endif
                <flux:button variant="primary" icon="arrow-down-tray" :href="route('demo.document', [$this->preview->document_id, 'attachment'])">
                    Descargar PDF
                </flux:button>
            </div>
        @endif
    </flux:modal>
</div>

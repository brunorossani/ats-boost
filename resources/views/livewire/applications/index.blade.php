<x-layouts.app>
    <flux:main class="max-w-7xl space-y-6">
        <div>
            <flux:heading size="xl" level="1">Postulaciones</flux:heading>
            <flux:subheading size="lg">
                Seguimiento de todas las ofertas a las que te postulaste.
            </flux:subheading>
        </div>

        @if ($this->applications->isEmpty())
            <flux:card class="text-center space-y-4 py-12">
                <flux:icon.clipboard-document-check class="mx-auto size-16 text-zinc-400" />
                <div>
                    <flux:heading size="lg">Todavía no te postulaste a ninguna oferta</flux:heading>
                    <flux:text class="text-zinc-500">
                        Cuando marques una postulación, aparecerá acá para que puedas hacer seguimiento.
                    </flux:text>
                </div>
                <flux:button variant="primary" :href="route('dashboard')" wire:navigate>
                    Ver ofertas
                </flux:button>
            </flux:card>
        @else
            <div class="space-y-4">
                <flux:text>
                    <strong>{{ $this->applications->count() }}</strong> {{ $this->applications->count() === 1 ? 'postulación' : 'postulaciones' }}
                </flux:text>

                <div class="grid gap-4">
                    @foreach ($this->applications as $application)
                        <flux:card wire:key="application-{{ $application->id }}">
                            <div class="space-y-3">
                                <div>
                                    <flux:heading size="lg">{{ $application->listing->title }}</flux:heading>
                                    <flux:text>{{ $application->listing->company }}</flux:text>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge size="sm" icon="map-pin">
                                        {{ $application->listing->locationLabel() }}
                                    </flux:badge>
                                    @if ($application->listing->salaryLabel())
                                        <flux:badge size="sm" color="green" icon="banknotes">
                                            {{ $application->listing->salaryLabel() }}
                                        </flux:badge>
                                    @endif
                                    <flux:badge size="sm" color="blue">
                                        Postulado {{ $application->applied_at->diffForHumans() }}
                                    </flux:badge>
                                    @if ($application->match_score !== null)
                                        <flux:badge :color="match (true) {
                                            $application->match_score >= 75 => 'green',
                                            $application->match_score >= 55 => 'lime',
                                            default => 'zinc',
                                        }" size="sm">
                                            {{ $application->match_score }}% match
                                        </flux:badge>
                                    @endif
                                </div>

                                @if ($application->application_notes)
                                    <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                                        <flux:text size="xs" class="text-zinc-500 mb-1">Notas:</flux:text>
                                        <flux:text size="sm" class="whitespace-pre-line">{{ $application->application_notes }}</flux:text>
                                    </div>
                                @endif

                                <div class="flex flex-wrap gap-2">
                                    @if ($application->hasCv())
                                        <flux:button size="sm" variant="ghost" icon="document-text" :href="route('documents.edit', $application->document_id)" wire:navigate>
                                            Ver CV usado
                                        </flux:button>
                                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('documents.download', $application->document_id)">
                                            Descargar PDF
                                        </flux:button>
                                    @endif

                                    @if ($application->listing->safeUrl())
                                        <flux:button size="sm" variant="ghost" icon-trailing="arrow-top-right-on-square" :href="$application->listing->safeUrl()" target="_blank">
                                            Ver oferta original
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        </flux:card>
                    @endforeach
                </div>
            </div>
        @endif
    </flux:main>
</x-layouts.app>

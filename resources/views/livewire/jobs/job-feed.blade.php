<div class="space-y-8" @if ($this->hasPending) wire:poll.10s @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Ofertas</flux:heading>
            <flux:subheading size="lg">
                Ofertas nuevas de hoy, filtradas por compatibilidad. Elegí las mejores y generá tu CV adaptado.
            </flux:subheading>
        </div>
    </div>

    {{-- Búsquedas como chips compactos --}}
    @if ($this->profiles->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($this->profiles as $profile)
                <flux:badge size="lg" color="zinc" wire:key="profile-chip-{{ $profile->id }}" class="flex items-center gap-2">
                    <div class="flex items-center gap-1.5">
                        <span>{{ $profile->keywords }}</span>
                        <span class="text-zinc-400">·</span>
                        <span class="text-zinc-500">{{ $profile->label() }}</span>
                        @if ($profile->last_synced_at)
                            <flux:tooltip content="Última búsqueda {{ $profile->last_synced_at->diffForHumans() }}">
                                <flux:icon.clock class="size-3.5 text-zinc-400" />
                            </flux:tooltip>
                        @endif
                    </div>
                    <flux:button size="xs" variant="ghost" icon="pencil" wire:click="editProfile({{ $profile->id }})" aria-label="Editar búsqueda" />
                </flux:badge>
            @endforeach
            <flux:button size="sm" variant="ghost" icon="plus" wire:click="newProfile">Nueva búsqueda</flux:button>
        </div>
    @else
        <flux:card class="space-y-3">
            <flux:heading>Configurá tu primera búsqueda</flux:heading>
            <flux:subheading>
                Subí tu CV, decinos qué puesto buscás y dónde. Buscamos ofertas nuevas dos veces por día
                en los portales de esa región y te mostramos las más compatibles.
            </flux:subheading>
            <div><flux:button variant="primary" icon="plus" wire:click="newProfile">Crear búsqueda</flux:button></div>
        </flux:card>
    @endif

    {{-- Ofertas --}}
    @if ($this->profiles->isNotEmpty())
        <div class="space-y-4">
            <div class="flex flex-wrap gap-2">
                @foreach (['all' => 'Todas', 'saved' => 'Guardadas', 'applied' => 'Postuladas'] as $key => $label)
                    <flux:button size="sm" wire:click="setTab('{{ $key }}')" :variant="$tab === $key ? 'primary' : 'ghost'">
                        {{ $label }}
                        <flux:badge size="sm" class="ms-1">{{ $this->counts[$key] }}</flux:badge>
                    </flux:button>
                @endforeach
            </div>

            <div class="grid gap-4">
                @forelse ($this->matches as $match)
                    <x-job-match-card :match="$match" wire:key="match-{{ $match->id }}">
                        @if ($match->hasCv())
                            <flux:button size="sm" variant="primary" icon="document-text" wire:click="showCv({{ $match->id }})">Ver CV adaptado</flux:button>
                            <flux:button size="sm" icon="arrow-down-tray" :href="route('documents.download', $match->document_id)">PDF</flux:button>
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('documents.edit', $match->document_id)" wire:navigate>Editar</flux:button>
                        @elseif ($match->isPending())
                            <div class="flex items-center gap-2">
                                <flux:icon.arrow-path class="size-4 animate-spin text-blue-500" />
                                <flux:text size="sm">
                                    {{ $match->status === 'new' ? 'En cola para procesar' : 'Adaptando tu CV…' }}
                                </flux:text>
                            </div>
                        @elseif ($match->status === 'failed')
                            <flux:button size="sm" icon="arrow-path" wire:click="retry({{ $match->id }})">Reintentar</flux:button>
                        @elseif ($match->status === 'low_match')
                            <flux:button size="sm" icon="sparkles" wire:click="retry({{ $match->id }}, true)">Generar CV igual</flux:button>
                        @endif

                        @if ($match->status === 'ready')
                            <flux:button size="sm" variant="ghost" icon="check" wire:click="markApplied({{ $match->id }})">Me postulé</flux:button>
                        @endif

                        @if ($match->saved_at)
                            <flux:button size="sm" variant="ghost" icon="bookmark-slash" wire:click="unsave({{ $match->id }})" aria-label="Quitar de guardadas">
                                Guardada
                            </flux:button>
                        @else
                            <flux:button size="sm" variant="ghost" icon="bookmark" wire:click="save({{ $match->id }})" aria-label="Guardar">
                                Guardar
                            </flux:button>
                        @endif

                        @if (! in_array($match->status, ['applied', 'processing'], true))
                            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="dismiss({{ $match->id }})">Descartar</flux:button>
                        @endif
                    </x-job-match-card>
                @empty
                    <flux:card>
                        <flux:subheading>
                            @switch($tab)
                                @case('all')
                                    @if ($this->profiles->isEmpty())
                                        Creá tu primera búsqueda para empezar a ver ofertas.
                                    @else
                                        No hay ofertas nuevas. Las búsquedas se actualizan automáticamente dos veces por día.
                                    @endif
                                    @break
                                @case('saved') No guardaste ninguna oferta todavía. @break
                                @case('applied') Todavía no marcaste postulaciones. @break
                            @endswitch
                        </flux:subheading>
                    </flux:card>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Formulario de búsqueda --}}
    <flux:modal name="profile-form" class="w-full max-w-lg space-y-5">
        <div>
            <flux:heading size="lg">{{ $editingId ? 'Editar búsqueda' : 'Nueva búsqueda' }}</flux:heading>
            <flux:subheading>Según el país elegimos los portales de esa región y adaptamos el CV a sus convenciones.</flux:subheading>
        </div>

        <form wire:submit="saveProfile" class="space-y-4">
            <flux:input wire:model="keywords" label="Puesto que buscás" placeholder="Laravel developer, Data analyst, Product designer…" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="countryCode" label="País">
                    <flux:select.option value="">Solo remoto (sin país)</flux:select.option>
                    @foreach ($this->countries() as $code => $name)
                        <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="city" label="Ciudad (opcional)" placeholder="Buenos Aires" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="maxAgeDays" label="Antigüedad máxima">
                    <flux:select.option value="1">Últimas 24 h</flux:select.option>
                    <flux:select.option value="3">Últimos 3 días</flux:select.option>
                    <flux:select.option value="7">Última semana</flux:select.option>
                </flux:select>

                <div class="pt-7">
                    <flux:checkbox wire:model="includeRemote" label="Incluir ofertas remotas" />
                </div>
            </div>

            @php($withResume = $this->profiles->filter->hasResume())

            @if ($withResume->isNotEmpty())
                <flux:select wire:model="resumeFromId" label="CV base">
                    @foreach ($withResume as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->resume_filename }} (búsqueda «{{ $p->keywords }}»)</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input type="file" wire:model="resumeFile" accept=".pdf,.txt"
                :label="$withResume->isNotEmpty() ? 'O subí un CV nuevo' : 'Tu CV (PDF o TXT)'" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveProfile,resumeFile">
                    Guardar y buscar
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Vista previa del CV --}}
    <flux:modal name="cv-preview" variant="floating" class="w-full! max-w-4xl space-y-4 p-4">
        @if ($this->preview)
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <flux:heading size="lg">CV adaptado para {{ $this->preview->listing->title }}</flux:heading>
                    <flux:subheading>{{ $this->preview->listing->company }} · {{ $this->preview->listing->locationLabel() }}</flux:subheading>
                </div>
                <div class="flex gap-2">
                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('documents.edit', $this->preview->document_id)" wire:navigate>Editar</flux:button>
                    <flux:button size="sm" variant="primary" icon="arrow-down-tray" :href="route('documents.download', $this->preview->document_id)">PDF</flux:button>
                </div>
            </div>

            <div class="relative rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 overflow-hidden">
                <iframe
                    src="{{ route('documents.preview', $this->preview->document_id) }}"
                    title="CV adaptado"
                    class="w-full h-[75vh] bg-white dark:bg-zinc-800"
                    loading="lazy"
                ></iframe>
                <div class="absolute bottom-0 inset-x-0 h-12 bg-gradient-to-t from-zinc-100/50 to-transparent dark:from-zinc-900/50 pointer-events-none"></div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                <div class="flex gap-2">
                    @if ($this->preview->listing->safeUrl())
                        <flux:button icon-trailing="arrow-top-right-on-square" :href="$this->preview->listing->safeUrl()" target="_blank" rel="noopener noreferrer">
                            Ver oferta original
                        </flux:button>
                    @endif
                </div>
                <div class="flex gap-2">
                    @if ($this->preview->status === 'ready')
                        <flux:button variant="primary" icon="check" wire:click="markApplied({{ $this->preview->id }})">Marcar como postulado</flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>

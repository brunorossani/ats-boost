<div class="space-y-8" @if ($this->hasPending) wire:poll.10s @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Mis ofertas</flux:heading>
            <flux:subheading size="lg">
                Buscamos ofertas nuevas dos veces por día y generamos un CV adaptado para cada una que encaje con tu perfil.
            </flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="newProfile">Nueva búsqueda</flux:button>
    </div>

    {{-- Búsquedas --}}
    <div class="grid gap-4 md:grid-cols-3">
        @forelse ($this->profiles as $profile)
            <flux:card class="space-y-3" wire:key="profile-{{ $profile->id }}">
                <div>
                    <flux:heading>{{ $profile->keywords }}</flux:heading>
                    <flux:text>
                        {{ $profile->label() }}{{ $profile->country_code && $profile->include_remote ? ' + remoto' : '' }}
                        · últimos {{ $profile->max_age_days }} {{ $profile->max_age_days === 1 ? 'día' : 'días' }}
                    </flux:text>
                    <flux:text size="sm" class="mt-1">
                        CV base: {{ $profile->resume_filename ?? 'sin CV' }}
                    </flux:text>
                </div>

                @if ($profile->last_synced_at)
                    <div class="space-y-1">
                        <flux:text size="sm">
                            Última búsqueda {{ $profile->last_synced_at->diffForHumans() }}:
                            {{ $profile->last_sync_report['found'] ?? 0 }} ofertas, {{ $profile->last_sync_report['new_matches'] ?? 0 }} nuevas.
                        </flux:text>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($profile->last_sync_report['providers'] ?? [] as $provider)
                                <flux:tooltip :content="$provider['error'] ?? match ($provider['status']) { 'ok' => $provider['found'].' ofertas', 'not_configured' => 'Sin API key configurada', default => $provider['status'] }">
                                    <flux:badge size="sm" :color="match ($provider['status']) { 'ok' => 'green', 'error' => 'red', default => 'zinc' }">
                                        {{ $provider['label'] }}
                                    </flux:badge>
                                </flux:tooltip>
                            @endforeach
                        </div>
                    </div>
                @else
                    <flux:text size="sm">Buscando por primera vez…</flux:text>
                @endif

                <div class="flex gap-1">
                    <flux:button size="sm" icon="arrow-path" wire:click="syncNow({{ $profile->id }})">Buscar ahora</flux:button>
                    <flux:spacer />
                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editProfile({{ $profile->id }})" aria-label="Editar" />
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteProfile({{ $profile->id }})"
                        wire:confirm="¿Eliminar esta búsqueda y sus ofertas?" aria-label="Eliminar" />
                </div>
            </flux:card>
        @empty
            <flux:card class="md:col-span-3 space-y-3">
                <flux:heading>Configurá tu primera búsqueda</flux:heading>
                <flux:subheading>
                    Subí tu CV, decinos qué puesto buscás y dónde. Elegimos los portales de esa región
                    (LatAm, Norteamérica, Europa…) y te traemos las ofertas de los últimos días con un CV adaptado a cada una.
                </flux:subheading>
                <div><flux:button variant="primary" icon="plus" wire:click="newProfile">Crear búsqueda</flux:button></div>
            </flux:card>
        @endforelse
    </div>

    {{-- Ofertas --}}
    @if ($this->profiles->isNotEmpty())
        <div class="space-y-4">
            <div class="flex flex-wrap gap-2">
                @foreach (['ready' => 'Listas', 'processing' => 'En proceso', 'applied' => 'Postuladas', 'low' => 'Baja compatibilidad'] as $key => $label)
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
                            <flux:badge icon="arrow-path" size="sm">
                                {{ $match->status === 'new' ? 'En cola' : 'Adaptando tu CV…' }}
                            </flux:badge>
                        @elseif ($match->status === 'failed')
                            <flux:button size="sm" icon="arrow-path" wire:click="retry({{ $match->id }})">Reintentar</flux:button>
                        @elseif ($match->status === 'low_match')
                            <flux:button size="sm" icon="sparkles" wire:click="retry({{ $match->id }}, true)">Generar CV igual</flux:button>
                        @endif

                        @if ($match->status === 'ready')
                            <flux:button size="sm" variant="ghost" icon="check" wire:click="markApplied({{ $match->id }})">Me postulé</flux:button>
                        @endif
                        @if (! in_array($match->status, ['applied', 'processing'], true))
                            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="dismiss({{ $match->id }})">Descartar</flux:button>
                        @endif
                    </x-job-match-card>
                @empty
                    <flux:card>
                        <flux:subheading>
                            @switch($tab)
                                @case('ready') Todavía no hay CVs adaptados. Si acabás de crear la búsqueda, aparecen en unos minutos. @break
                                @case('processing') No hay ofertas en proceso. @break
                                @case('applied') Todavía no marcaste postulaciones. @break
                                @default Ninguna oferta quedó por debajo del mínimo de compatibilidad.
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
    <flux:modal name="cv-preview" variant="floating" class="w-full! max-w-3xl space-y-4 p-4">
        @if ($this->preview)
            <div>
                <flux:heading size="lg">CV adaptado para {{ $this->preview->listing->title }}</flux:heading>
                <flux:subheading>{{ $this->preview->listing->company }} · {{ $this->preview->listing->locationLabel() }}</flux:subheading>
            </div>

            <iframe src="{{ route('documents.preview', $this->preview->document_id) }}" title="CV adaptado"
                class="w-full h-[70vh] rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white"></iframe>

            <div class="flex flex-wrap justify-end gap-2">
                @if ($this->preview->listing->safeUrl())
                    <flux:button variant="ghost" icon-trailing="arrow-top-right-on-square" :href="$this->preview->listing->safeUrl()" target="_blank" rel="noopener noreferrer">
                        Ir a postularme
                    </flux:button>
                @endif
                @if ($this->preview->status === 'ready')
                    <flux:button icon="check" wire:click="markApplied({{ $this->preview->id }})">Me postulé</flux:button>
                @endif
                <flux:button icon="pencil-square" :href="route('documents.edit', $this->preview->document_id)" wire:navigate>Editar</flux:button>
                <flux:button variant="primary" icon="arrow-down-tray" :href="route('documents.download', $this->preview->document_id)">Descargar PDF</flux:button>
            </div>
        @endif
    </flux:modal>
</div>

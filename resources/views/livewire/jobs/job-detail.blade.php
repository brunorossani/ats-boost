<div class="flex h-full flex-col">
    {{-- Header --}}
    <div class="border-b border-zinc-200 dark:border-zinc-700 p-4 space-y-3">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <flux:heading size="lg" class="leading-tight">{{ $this->match->listing->title }}</flux:heading>
                <flux:text class="mt-1">{{ $this->match->listing->company }}</flux:text>
            </div>
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="close" aria-label="Cerrar" />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:badge size="sm" icon="map-pin">
                {{ $this->match->listing->locationLabel() }}
            </flux:badge>
            @if ($this->match->listing->salaryLabel())
                <flux:badge size="sm" color="green" icon="banknotes">
                    {{ $this->match->listing->salaryLabel() }}
                </flux:badge>
            @endif
            <flux:badge size="sm" icon="clock">
                {{ $this->match->listing->posted_at ? $this->match->listing->posted_at->diffForHumans() : 'Fecha no informada' }}
            </flux:badge>
            <flux:badge size="sm" color="blue">
                {{ $this->match->listing->sourceLabel() }}
            </flux:badge>
        </div>

        @if ($this->match->match_score !== null)
            <div class="flex items-center gap-2">
                <flux:badge :color="match (true) {
                    $this->match->match_score >= 75 => 'green',
                    $this->match->match_score >= 55 => 'lime',
                    $this->match->match_score >= 45 => 'amber',
                    default => 'zinc',
                }" size="lg" class="tabular-nums">
                    {{ $this->match->match_score }}% compatibilidad
                </flux:badge>
            </div>
        @endif
    </div>

    {{-- Content --}}
    <div class="flex-1 overflow-y-auto p-4 space-y-6">
        {{-- Por qué encaja --}}
        @if (! empty($this->match->match_details['reason']))
            <div>
                <flux:heading size="sm" class="mb-2">Por qué encaja</flux:heading>
                <flux:text>{{ $this->match->match_details['reason'] }}</flux:text>

                @if (! empty($this->match->match_details['matching']) || ! empty($this->match->match_details['missing']))
                    <div class="mt-3 space-y-2">
                        @if (! empty($this->match->match_details['matching']))
                            <div>
                                <flux:text size="sm" class="font-medium text-zinc-700 dark:text-zinc-300">Tenés:</flux:text>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($this->match->match_details['matching'] as $item)
                                        <li class="flex items-start gap-2">
                                            <flux:icon.check class="size-4 mt-0.5 shrink-0 text-green-600 dark:text-green-400" />
                                            <flux:text size="sm">{{ $item }}</flux:text>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (! empty($this->match->match_details['missing']))
                            <div>
                                <flux:text size="sm" class="font-medium text-zinc-700 dark:text-zinc-300">Te falta:</flux:text>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($this->match->match_details['missing'] as $item)
                                        <li class="flex items-start gap-2">
                                            <flux:icon.minus class="size-4 mt-0.5 shrink-0 text-zinc-400" />
                                            <flux:text size="sm">{{ $item }}</flux:text>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Cómo postularse --}}
        <div>
            <flux:heading size="sm" class="mb-2">Cómo te postulás</flux:heading>

            @php($method = $this->applicationMethod)

            @if ($method['method'] === 'email')
                <div class="space-y-3">
                    <flux:text>{{ $method['instructions'] }}</flux:text>

                    {{-- Email address --}}
                    <div class="flex items-center gap-2 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                        <flux:icon.envelope class="size-5 text-zinc-500" />
                        <flux:text class="font-mono">{{ $method['email'] }}</flux:text>
                        <flux:button size="xs" variant="ghost" x-data x-on:click="navigator.clipboard.writeText('{{ $method['email'] }}')">
                            Copiar
                        </flux:button>
                    </div>

                    {{-- Email draft --}}
                    @if ($this->emailDraft)
                        <div class="space-y-2">
                            <flux:text size="sm" class="font-medium">Borrador de email:</flux:text>
                            <div class="p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800 space-y-2 text-sm">
                                <div>
                                    <flux:text size="xs" class="text-zinc-500">Asunto:</flux:text>
                                    <flux:text size="sm" class="font-medium">{{ $this->emailDraft['subject'] }}</flux:text>
                                </div>
                                <div>
                                    <flux:text size="xs" class="text-zinc-500">Mensaje:</flux:text>
                                    <flux:text size="sm" class="whitespace-pre-line">{{ $this->emailDraft['body'] }}</flux:text>
                                </div>
                            </div>
                            <flux:button
                                size="xs"
                                variant="ghost"
                                x-data
                                x-on:click="navigator.clipboard.writeText('{{ addslashes($this->emailDraft['subject'] . '\n\n' . $this->emailDraft['body']) }}')"
                            >
                                Copiar borrador
                            </flux:button>
                        </div>
                    @endif
                </div>
            @elseif ($method['method'] === 'linkedin')
                <div class="space-y-2">
                    <flux:text>{{ $method['instructions'] }}</flux:text>
                    <flux:button size="sm" icon-trailing="arrow-top-right-on-square" :href="$method['url']" target="_blank">
                        Abrir en LinkedIn
                    </flux:button>
                </div>
            @elseif ($method['method'] === 'portal' && $method['url'])
                <div class="space-y-2">
                    <flux:text>{{ $method['instructions'] }}</flux:text>
                    <flux:button size="sm" icon-trailing="arrow-top-right-on-square" :href="$method['url']" target="_blank">
                        Ir al portal
                    </flux:button>
                </div>
            @else
                <flux:text class="text-zinc-500">
                    El método de postulación no está claro en la oferta. Revisá la descripción completa para encontrar instrucciones.
                </flux:text>
            @endif
        </div>

        {{-- Descripción --}}
        <div>
            <flux:heading size="sm" class="mb-2">Descripción</flux:heading>
            <flux:text class="whitespace-pre-line">{{ Str::limit($this->match->listing->description, 1000) }}</flux:text>
            @if (strlen($this->match->listing->description) > 1000)
                <flux:button size="sm" variant="ghost" :href="$this->match->listing->safeUrl()" target="_blank" class="mt-2">
                    Ver descripción completa
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Actions --}}
    <div class="border-t border-zinc-200 dark:border-zinc-700 p-4">
        <div class="flex flex-wrap items-center gap-2">
            @if ($this->match->hasCv())
                <flux:button variant="primary" icon="document-text" :href="route('documents.edit', $this->match->document_id)" wire:navigate>
                    Ver CV adaptado
                </flux:button>
                <flux:button size="sm" icon="arrow-down-tray" :href="route('documents.download', $this->match->document_id)">
                    Descargar PDF
                </flux:button>
            @elseif ($this->match->isPending())
                <flux:text size="sm" class="flex items-center gap-2">
                    <flux:icon.arrow-path class="size-4 animate-spin text-blue-500" />
                    {{ $this->match->status === 'processing' ? 'Adaptando CV...' : 'En cola...' }}
                </flux:text>
            @elseif ($this->match->status === 'failed')
                <flux:button variant="primary" icon="arrow-path" wire:click="$parent.retry({{ $this->match->id }})">
                    Reintentar
                </flux:button>
            @elseif ($this->match->status === 'low_match')
                <flux:button variant="primary" icon="sparkles" wire:click="$parent.retry({{ $this->match->id }}, true)">
                    Generar CV igual
                </flux:button>
            @else
                {{-- Fase 2: CV bajo demanda --}}
                <flux:button variant="primary" icon="sparkles" wire:click="$parent.generateCv({{ $this->match->id }})">
                    Generar CV adaptado
                </flux:button>
            @endif

            <flux:spacer />

            @if ($this->match->saved_at)
                <flux:button size="sm" variant="ghost" icon="bookmark-slash" wire:click="unsave">
                    Guardada
                </flux:button>
            @else
                <flux:button size="sm" variant="ghost" icon="bookmark" wire:click="save">
                    Guardar
                </flux:button>
            @endif

            @if ($this->match->listing->safeUrl())
                <flux:button size="sm" icon-trailing="arrow-top-right-on-square" :href="$this->match->listing->safeUrl()" target="_blank">
                    Ver en portal
                </flux:button>
            @endif

            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="dismiss">
                Descartar
            </flux:button>
        </div>
    </div>
</div>

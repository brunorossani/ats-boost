<div class="w-full py-6 space-y-6">

    {{-- Paso 1: cargar CV --}}
    @if ($step === 'upload')
        <flux:card class="p-6 space-y-6 md:p-8">
            <div class="w-full">
                <flux:file-upload required wire:model="resume" label="Cargar currículum">
                    <flux:file-upload.dropzone heading="Suelta tu CV o haz clic para examinar" text="PDF hasta 10MB"
                        with-progress inline />
                </flux:file-upload>

                @if ($resume)
                    <div class="mt-3">
                        <flux:file-item heading="{{ $resume->getClientOriginalName() }}">
                            <x-slot name="actions">
                                <flux:file-item.remove wire:click="$set('resume', null)" />
                            </x-slot>
                        </flux:file-item>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="desiredRole" label="¿Qué puesto buscás? (opcional)"
                    placeholder="Ej: Desarrollador Backend" />

                <flux:input wire:model="location" label="¿Dónde? (opcional)"
                    placeholder="Ej: Montevideo o Remoto" />
            </div>

            <flux:button x-on:click="$wire.startSearch()" icon="rocket-launch" variant="primary" class="w-full">
                Buscar vacantes y postularme automáticamente
            </flux:button>

            <flux:subheading class="text-center">
                Sin escribir descripciones de trabajo a mano — nuestra IA busca de verdad en LinkedIn, Indeed,
                Computrabajo y portales de empleo.
            </flux:subheading>
        </flux:card>
    @endif

    {{-- Paso 2: vacantes encontradas --}}
    @if ($step === 'matches')
        <div class="space-y-4">
            @if ($searchFailed)
                <flux:card class="p-6 space-y-3 text-center">
                    <flux:heading size="lg">No encontramos vacantes esta vez</flux:heading>
                    <flux:subheading class="max-w-md mx-auto">
                        La búsqueda es real, y a veces no hay resultados para ese puesto o ubicación en este
                        momento. Probá con un rol o ubicación más amplios.
                    </flux:subheading>
                    <flux:button x-on:click="$wire.restart()" variant="primary" class="mx-auto">
                        Probar de nuevo
                    </flux:button>
                </flux:card>
            @else
                <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
                    <flux:heading size="lg">Encontramos {{ count($matches) }} vacantes reales para vos</flux:heading>

                    <flux:badge color="green" icon="check-badge" size="sm" variant="pill">
                        Búsqueda real, no simulada
                    </flux:badge>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    @foreach ($matches as $job)
                        @php
                            $meta = $this->channelMeta($job['channel']);
                        @endphp

                        <flux:card size="sm" class="p-4 space-y-3">
                            <div>
                                <flux:heading size="sm">{{ $job['title'] }}</flux:heading>
                                <flux:subheading>{{ $job['company'] }} · {{ $job['location'] }}</flux:subheading>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" :color="$meta['color']" :icon="$meta['icon']">
                                    {{ $meta['label'] }}
                                </flux:badge>

                                @if (!empty($job['url']))
                                    <flux:button as="link" href="{{ $job['url'] }}" target="_blank" variant="ghost"
                                        size="sm" icon-trailing="arrow-up-right">
                                        Ver publicación original
                                    </flux:button>
                                @endif
                            </div>
                        </flux:card>
                    @endforeach
                </div>

                <flux:card class="p-4 !bg-blue-50 dark:!bg-blue-950/40">
                    <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <div>
                            <flux:heading size="sm">Postulación automática</flux:heading>
                            <flux:subheading>
                                Adaptamos tu CV para la vacante top con el texto real de la oferta — hasta
                                {{ $dailyLimit }} postulaciones por día.
                            </flux:subheading>
                        </div>

                        <flux:button x-on:click="$wire.startSending()" icon="paper-airplane" variant="primary">
                            Postularme automáticamente
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        </div>
    @endif

    {{-- Paso 3: panel de postulaciones, con progreso por vacante --}}
    @if ($step === 'dashboard')
        @php
            $stageDurationMs = 650;
            $staggerMs = 320;
            $totalRevealMs = (max(count($sentLog), 1) - 1) * $staggerMs + $stageDurationMs * 2 + 500;
        @endphp

        <div class="space-y-4">
            <div class="space-y-2">
                @foreach ($sentLog as $job)
                    @php
                        $meta = $this->channelMeta($job['channel']);
                        $startDelay = $loop->index * $staggerMs;
                        $actionLabel = 'Completando postulación en el portal';
                    @endphp

                    <div x-data="{
                            stage: 0,
                            init() {
                                setTimeout(() => { this.stage = 1 }, {{ $startDelay }} + {{ $stageDurationMs }});
                                setTimeout(() => { this.stage = 2 }, {{ $startDelay }} + {{ $stageDurationMs * 2 }});
                            }
                        }">
                        <flux:card size="sm" class="p-4 space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <flux:heading size="sm">{{ $job['title'] }}</flux:heading>
                                    <flux:subheading>{{ $job['company'] }} · {{ $job['location'] }}</flux:subheading>
                                </div>

                                <flux:badge size="sm" :color="$meta['color']" :icon="$meta['icon']">
                                    {{ $meta['label'] }}
                                </flux:badge>
                            </div>

                            {{-- Barra de progreso de 3 etapas --}}
                            <div class="flex gap-1">
                                <div class="h-1.5 flex-1 rounded-full transition-colors duration-500"
                                    :class="stage >= 0 ? 'bg-blue-500' : 'bg-zinc-200 dark:bg-zinc-700'"></div>
                                <div class="h-1.5 flex-1 rounded-full transition-colors duration-500"
                                    :class="stage >= 1 ? 'bg-blue-500' : 'bg-zinc-200 dark:bg-zinc-700'"></div>
                                <div class="h-1.5 flex-1 rounded-full transition-colors duration-500"
                                    :class="stage >= 2 ? 'bg-green-500' : 'bg-zinc-200 dark:bg-zinc-700'"></div>
                            </div>

                            {{-- Etiqueta de la etapa actual --}}
                            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400" x-show="stage !== 2">
                                <flux:icon.loading variant="mini" />
                                <span x-show="stage === 0">Adaptando tu CV a esta vacante...</span>
                                <span x-show="stage === 1">{{ $actionLabel }}...</span>
                            </div>

                            {{-- Resultado final --}}
                            <div class="flex flex-wrap items-center gap-2" x-show="stage >= 2" x-cloak
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0">
                                <flux:badge size="sm" color="green" icon="check-circle">{{ $job['status'] }}</flux:badge>
                                <flux:badge size="sm" icon="eye">{{ $job['follow_up'] }}</flux:badge>
                            </div>
                        </flux:card>
                    </div>
                @endforeach
            </div>

            <div x-data="{ show: false }" x-init="setTimeout(() => show = true, {{ $totalRevealMs }})" x-show="show"
                x-cloak x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                class="space-y-4">
                <flux:card class="p-6 space-y-1 text-center">
                    <flux:subheading>Postulaciones de hoy</flux:subheading>
                    <flux:heading size="xl">
                        {{ $sentToday }} / {{ $dailyLimit }}
                    </flux:heading>
                </flux:card>

                @if ($tailoredPreviewHtml)
                    <flux:card class="p-4 space-y-3">
                        <flux:heading size="sm">Así quedó tu CV adaptado para la vacante top</flux:heading>

                        <iframe srcdoc="{{ $tailoredPreviewHtml }}" title="Vista previa del CV adaptado"
                            class="w-full min-h-[400px] rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white"></iframe>
                    </flux:card>
                @elseif ($tailoredPreviewFailed)
                    <flux:subheading class="text-center">
                        (No se pudo generar la vista previa del CV adaptado en este momento, pero así se vería el
                        panel completo.)
                    </flux:subheading>
                @endif

                <flux:card class="p-6 space-y-4 text-center !bg-blue-50 dark:!bg-blue-950/40">
                    <flux:heading size="lg">Esto es lo que vendemos ahora</flux:heading>

                    <flux:subheading class="max-w-xl mx-auto">
                        ATS Boost ya no es solo "adaptá tu CV" — buscamos vacantes reales en LinkedIn, Indeed y
                        Computrabajo, y postulamos por vos con hasta {{ $dailyLimit }} CVs personalizados por día.
                        Vos solo subís tu CV.
                    </flux:subheading>

                    <div class="flex flex-col items-center justify-center gap-2 sm:flex-row">
                        <flux:button wire:navigate href="{{ route('pricing') }}" variant="primary"
                            icon-trailing="arrow-right">
                            Ver planes premium
                        </flux:button>

                        <flux:button x-on:click="$wire.restart()" variant="ghost">
                            Probar de nuevo
                        </flux:button>
                    </div>
                </flux:card>
            </div>
        </div>
    @endif

    {{-- Modal: buscando --}}
    <flux:modal class="!max-w-sm flex flex-col items-center space-y-6 p-4" name="auto-apply-searching"
        :dismissible="false" :closable="false" variant="floating">
        <div>
            <flux:heading size="lg" class="text-center">Buscando vacantes reales para vos</flux:heading>
            <flux:subheading class="text-center">
                Revisando LinkedIn, Indeed, Computrabajo y portales de empleo...
            </flux:subheading>
        </div>

        <flux:icon.loading />
    </flux:modal>

    {{-- Modal: enviando --}}
    <flux:modal class="!max-w-sm flex flex-col items-center space-y-6 p-4" name="auto-apply-sending"
        :dismissible="false" :closable="false" variant="floating">
        <div>
            <flux:heading size="lg" class="text-center">Adaptando y enviando tu CV</flux:heading>
            <flux:subheading class="text-center">
                Personalizando tu currículum para cada vacante y enviándolo por el canal correspondiente...
            </flux:subheading>
        </div>

        <flux:icon.loading />
    </flux:modal>
</div>

@script
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('auto-apply-searching-started', async () => {
                $flux.modal('auto-apply-searching').show();
                await new Promise(resolve => requestAnimationFrame(resolve));
                $wire.call('runSearch');
            });

            Livewire.on('auto-apply-searching-finished', () => {
                $flux.modal('auto-apply-searching').close();
            });

            Livewire.on('auto-apply-sending-started', async () => {
                $flux.modal('auto-apply-sending').show();
                await new Promise(resolve => requestAnimationFrame(resolve));
                $wire.call('runSend');
            });

            Livewire.on('auto-apply-sending-finished', () => {
                $flux.modal('auto-apply-sending').close();
            });
        });
    </script>
@endscript

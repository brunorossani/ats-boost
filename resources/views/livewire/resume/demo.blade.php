<div class="w-full py-6">
    <div class="flex flex-col lg:flex-row gap-6 w-full">
        <div class="w-full">
            <flux:file-upload required wire:model="resume" label="Cargar currículum">
                <flux:file-upload.dropzone heading="Suelta tu CV o haz clic para examinar" text="PDF hasta 10MB"
                    with-progress inline />
            </flux:file-upload>

            <div class="mt-3 flex flex-col gap-2">
                @if ($resume)
                    <flux:file-item heading="{{ $resume->getClientOriginalName() }}">
                        <x-slot name="actions">
                            <flux:file-item.remove wire:click="$set('resume', null)" />
                        </x-slot>
                    </flux:file-item>
                @endif
            </div>
        </div>

        <div class="w-full space-y-3">
            <flux:textarea required autofocus wire:model="description" rows="10" label="Descripción del trabajo"
                placeholder="Estamos buscando un desarrollador de software para unirse a nuestra empresa.

El candidato ideal debe ser..." />

            <flux:button x-on:click="$wire.startTailoring()" icon="sparkles" class="mt-4 w-full" variant="primary">
                Personalizar mi CV
            </flux:button>
        </div>
    </div>

    <!-- Progress modal -->
    <flux:modal class="!max-w-sm flex flex-col items-center space-y-6 p-4" name="tailoring-demo-in-progress"
        :dismissible="false" :closable="false" variant="floating">
        <div>
            <flux:heading size="lg" class="text-center">
                Adaptando tu currículum
            </flux:heading>
            <flux:subheading class="text-center">
                Adaptando tu experiencia a la descripción del trabajo mientras preservamos el contenido relevante.
            </flux:subheading>
        </div>

        <flux:icon.loading />
    </flux:modal>

    <!-- Result modal -->
    <flux:modal name="tailoring-demo-result" :dismissible="false" variant="floating"
        class="w-full! max-w-3xl space-y-6 p-4">
        <div>
            <flux:heading size="lg">
                Tu currículum adaptado está listo
            </flux:heading>

            <flux:subheading>
                Optimizamos tu currículum basándonos en la descripción del trabajo manteniendo tu contenido
                original intacto.
            </flux:subheading>
        </div>

        <iframe srcdoc="{{ $previewHtml }}" title="Vista previa del CV adaptado"
            class="w-full min-h-[500px] rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white"></iframe>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" icon="arrow-path" x-on:click="$wire.startTailoring()">
                Generar de nuevo
            </flux:button>

            <flux:button variant="primary" icon="arrow-down-tray" wire:click="downloadPdf">
                Descargar CV adaptado
            </flux:button>
        </div>
    </flux:modal>

    <flux:modal class="!max-w-sm flex flex-col items-center space-y-6 p-4" name="limit-modal" variant="floating">
        <div>
            <flux:heading size="lg" class="text-center">
                Alcanzaste el límite
            </flux:heading>

            <flux:subheading class="text-center">
                Estás comenzando bien. Actualiza para seguir construyendo currículums sin interrupciones.
            </flux:subheading>
        </div>

        <div class="flex justify-center gap-2">
            <flux:button icon-trailing="arrow-right" wire:navigate href="{{ route('register') }}">
                Crear cuenta
            </flux:button>

            <flux:button variant="primary" icon-trailing="arrow-right" wire:navigate href="{{ route('pricing') }}">
                Ver planes
            </flux:button>
        </div>
    </flux:modal>
</div>

@script
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('tailoring-demo-started', async () => {

                $flux.modal('tailoring-demo-in-progress').show();
                await new Promise(resolve => requestAnimationFrame(resolve));
                $wire.call('tailorResumeDemo');
            });
        });
    </script>
@endscript

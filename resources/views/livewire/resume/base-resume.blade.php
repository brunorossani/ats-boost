<x-layouts.app>
    <flux:main class="max-w-7xl space-y-6">
        <div>
            <flux:heading size="xl" level="1">Mi CV</flux:heading>
            <flux:subheading size="lg">
                Tu CV base, análisis de compatibilidad ATS y todos los documentos generados.
            </flux:subheading>
        </div>

        <flux:card class="space-y-4">
            <flux:heading size="lg">CV base</flux:heading>
            <flux:text class="text-zinc-500">
                Próximamente: tu CV base único para todas las búsquedas, con análisis de salud ATS
                y recomendaciones de mejora.
            </flux:text>
            <flux:button variant="primary" :href="route('resume.tailor')" wire:navigate>
                Ir a Adaptar CV
            </flux:button>
        </flux:card>

        @if ($documents->isNotEmpty())
            <div class="space-y-4">
                <flux:heading size="lg">Documentos generados</flux:heading>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($documents as $document)
                        <a href="{{ route('documents.edit', $document) }}" wire:navigate class="group">
                            <flux:card class="h-full transition group-hover:border-accent">
                                <flux:badge size="sm" color="zinc" :icon="$document->type->icon()" class="mb-2">
                                    {{ $document->type->label() }}
                                </flux:badge>
                                <flux:heading size="sm" class="line-clamp-2">{{ $document->title }}</flux:heading>
                                <flux:text size="sm" class="mt-1 text-zinc-500">
                                    {{ $document->created_at->diffForHumans() }}
                                </flux:text>
                            </flux:card>
                        </a>
                    @endforeach
                </div>

                <div class="flex justify-center">
                    <flux:button variant="ghost" :href="route('documents.index')" wire:navigate>
                        Ver todos los documentos
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:main>
</x-layouts.app>

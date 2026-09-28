<x-layouts.app :title="__('Panel de control • ATS Boost')">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Panel de control') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Bienvenido de nuevo') }} {{ auth()->user()->name }}! Vamos a
            potenciar tus solicitudes de empleo.
        </flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="flex h-auto w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid gap-4 md:grid-cols-3 items-stretch">
            {{-- Tailor Your Resume (core feature) --}}
            <a href="{{ route('resume.resume-tailor') }}">
                <flux:card
                    class="h-full group cursor-pointer transition hover:border-neutral-300 dark:hover:border-neutral-600">

                    <div class="mb-2 flex items-center">
                        <flux:heading>Adapta tu currículum</flux:heading>
                        <flux:spacer />
                        <flux:icon.arrow-right variant="micro"
                            class="text-zinc-500 dark:text-zinc-300 transition-transform duration-300 group-hover:translate-x-1" />
                    </div>

                    <flux:subheading>
                        Adapta tu currículum a cualquier descripción de trabajo. Optimízalo para el éxito en ATS.
                    </flux:subheading>
                </flux:card>
            </a>

            {{-- Resume Analyzer --}}
            <a href="{{ route('resume.resume-analyzer') }}">
                <flux:card
                    class="h-full group cursor-pointer transition hover:border-neutral-300 dark:hover:border-neutral-600">

                    <div class="mb-2 flex items-center">
                        <flux:heading>Analizador de currículum</flux:heading>
                        <flux:spacer />
                        <flux:icon.arrow-right variant="micro"
                            class="text-zinc-500 dark:text-zinc-300 transition-transform duration-300 group-hover:translate-x-1" />
                    </div>

                    <flux:subheading>
                        Obtén retroalimentación instantánea, puntuación de ATS y mejoras accionables para tu currículum.
                    </flux:subheading>
                </flux:card>
            </a>

            {{-- Cover Letter --}}
            <a href="{{ route('resume.cover-letter') }}">
                <flux:card
                    class="h-full group cursor-pointer transition hover:border-neutral-300 dark:hover:border-neutral-600">

                    <div class="mb-2 flex items-center">
                        <flux:heading>Carta de presentación</flux:heading>
                        <flux:spacer />
                        <flux:icon.arrow-right variant="micro"
                            class="text-zinc-500 dark:text-zinc-300 transition-transform duration-300 group-hover:translate-x-1" />
                    </div>

                    <flux:subheading>
                        Genera una carta de presentación basada en estándares profesionales comprobados.
                    </flux:subheading>
                </flux:card>
            </a>
        </div>

        <div class="mt-8 space-y-4">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <flux:heading size="lg">Tus CVs adaptados</flux:heading>
                    <flux:subheading>Revisá tus últimos resultados y mantené tus versiones organizadas.</flux:subheading>
                </div>
                <flux:badge>{{ auth()->user()->tailoredResumes()->count() }}</flux:badge>
            </div>

            <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @forelse (auth()->user()->tailoredResumes()->latest()->limit(10)->get() as $tailoredResume)
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <flux:heading size="sm">{{ $tailoredResume->title }}</flux:heading>
                            <flux:subheading class="truncate">
                                {{ \Illuminate\Support\Str::limit($tailoredResume->job_description, 120) }}
                            </flux:subheading>
                            <flux:text size="sm" class="mt-1">{{ $tailoredResume->created_at->translatedFormat('d M Y, H:i') }}</flux:text>
                        </div>

                        <form method="POST" action="{{ route('resume.tailored.destroy', $tailoredResume) }}">
                            @csrf
                            @method('DELETE')
                            <flux:button type="submit" variant="ghost" icon="trash" aria-label="Eliminar CV adaptado" />
                        </form>
                    </div>
                @empty
                    <div class="p-6">
                        <flux:subheading>Todavía no tenés CVs adaptados guardados.</flux:subheading>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- <div
            class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div> --}}
    </div>
</x-layouts.app>

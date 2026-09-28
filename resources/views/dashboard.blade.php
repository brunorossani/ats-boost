<x-layouts.app :title="__('Panel de control')">
    @php
        $user = auth()->user();
        $subscription = $user->subscription;

        $tools = [
            [
                'route' => 'jobs.feed',
                'icon' => 'briefcase',
                'title' => __('Mis ofertas del día'),
                'text' => __('Ofertas nuevas de los portales de tu país, filtradas por compatibilidad y cada una con tu CV adaptado.'),
            ],
            [
                'route' => 'resume.tailor',
                'icon' => 'sparkles',
                'title' => __('Adaptar currículum'),
                'text' => __('Reescribimos tu CV con el vocabulario de una oferta concreta, sin inventar experiencia.'),
            ],
            [
                'route' => 'resume.analyzer',
                'icon' => 'chart-bar',
                'title' => __('Analizar currículum'),
                'text' => __('Puntaje de compatibilidad con ATS y la lista de lo que conviene corregir.'),
            ],
            [
                'route' => 'resume.cover-letter',
                'icon' => 'envelope-open',
                'title' => __('Carta de presentación'),
                'text' => __('Una carta breve construida con hechos reales de tu CV.'),
            ],
        ];
    @endphp

    <div class="mb-6">
        <flux:heading size="xl" level="1">{{ __('Hola, :name', ['name' => $user->name]) }}</flux:heading>
        <flux:subheading size="lg">
            {{ __('Elegí por dónde arrancar.') }}
        </flux:subheading>
    </div>

    @unless ($user->isSubscribed())
        <flux:callout icon="lock-closed" variant="warning" class="mb-6">
            <flux:callout.heading>{{ __('Necesitás una suscripción activa') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Las herramientas de currículum están disponibles con cualquiera de los planes.') }}
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button :href="route('subscriptions.edit')" size="sm" variant="primary" wire:navigate>
                    {{ __('Ver planes') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @elseif ($subscription?->onTrial())
        <flux:callout icon="gift" variant="secondary" class="mb-6">
            <flux:callout.heading>{{ __('Estás en período de prueba') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Te quedan :days días de prueba.', ['days' => $subscription->daysRemaining()]) }}
            </flux:callout.text>
        </flux:callout>
    @endunless

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($tools as $tool)
            <a href="{{ route($tool['route']) }}" wire:navigate class="group">
                <flux:card class="h-full transition group-hover:border-accent">
                    <flux:icon :name="$tool['icon']" class="mb-3 size-6 text-accent" />
                    <flux:heading size="sm">{{ $tool['title'] }}</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-500">{{ $tool['text'] }}</flux:text>
                </flux:card>
            </a>
        @endforeach
    </div>

    @php
        $recent = $user->documents()->latestFirst()->limit(4)->get();
    @endphp

    @if ($recent->isNotEmpty())
        <div class="mt-10">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Lo último que generaste') }}</flux:heading>
                <flux:link :href="route('documents.index')" wire:navigate>{{ __('Ver todo') }}</flux:link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($recent as $document)
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
        </div>
    @endif
</x-layouts.app>

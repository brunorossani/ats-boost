@props(['match'])

@php
    $listing = $match->listing;
    $score = $match->match_score;
    $scoreColor = match (true) {
        $score === null => 'zinc',
        $score >= 75 => 'green',
        $score >= 55 => 'lime',
        $score >= 45 => 'amber',
        default => 'zinc',
    };
@endphp

<flux:card {{ $attributes->class('space-y-3') }}>
    <div class="flex items-start gap-4">
        <div class="min-w-0 flex-1">
            <flux:heading size="lg" class="leading-snug">{{ $listing->title }}</flux:heading>
            <flux:text class="mt-0.5">{{ $listing->company }} · {{ $listing->locationLabel() }}</flux:text>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <flux:badge size="sm" icon="clock">
                    {{ $listing->posted_at ? $listing->posted_at->diffForHumans() : 'Fecha no informada' }}
                </flux:badge>
                <flux:badge size="sm" color="blue">{{ $listing->sourceLabel() }}</flux:badge>
                @if ($listing->employment_type)
                    <flux:badge size="sm">{{ $listing->employment_type }}</flux:badge>
                @endif
                @if ($listing->salary_min || $listing->salary_max)
                    <flux:badge size="sm" color="green">
                        {{ $listing->salary_currency }}
                        {{ collect([$listing->salary_min, $listing->salary_max])->filter()->map(fn ($v) => number_format((float) $v, 0, ',', '.'))->implode(' – ') }}
                    </flux:badge>
                @endif
            </div>
        </div>

        @if ($score !== null)
            <div class="shrink-0 text-center">
                <flux:badge :color="$scoreColor" size="lg" class="tabular-nums">{{ $score }}%</flux:badge>
                <flux:text size="sm" class="mt-1">compatibilidad</flux:text>
            </div>
        @endif
    </div>

    @if (! empty($match->match_details['reason']))
        <flux:text>{{ $match->match_details['reason'] }}</flux:text>
    @endif

    @if (! empty($match->match_details['matching']) || ! empty($match->match_details['missing']))
        <div class="flex flex-wrap gap-1.5">
            @foreach ($match->match_details['matching'] ?? [] as $item)
                <flux:badge size="sm" color="green" icon="check">{{ $item }}</flux:badge>
            @endforeach
            @foreach ($match->match_details['missing'] ?? [] as $item)
                <flux:badge size="sm" color="zinc" icon="minus">{{ $item }}</flux:badge>
            @endforeach
        </div>
    @endif

    @if ($match->error)
        <flux:text class="text-red-600 dark:text-red-400">{{ $match->error }}</flux:text>
    @endif

    <div class="flex flex-wrap items-center gap-2 pt-1">
        {{ $slot }}

        <flux:spacer />

        @if ($listing->safeUrl())
            <flux:button size="sm" variant="ghost" icon-trailing="arrow-top-right-on-square" :href="$listing->safeUrl()" target="_blank" rel="noopener noreferrer">
                Ver oferta
            </flux:button>
        @endif
    </div>
</flux:card>

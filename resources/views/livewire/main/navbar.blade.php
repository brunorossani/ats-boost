{{-- <flux:header sticky container class="relative bg-white dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-600"> --}}
<flux:header  container class="relative bg-transparent">
    <div class="flex items-center gap-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2 font-medium" wire:navigate>
            <span class="flex h-7 w-7 items-center justify-center rounded-md">
                <x-app-logo-icon class="size-7 text-blue-600/90 dark:text-white" />
            </span>
            <flux:text size="lg" class="text-blue-600/90 dark:text-white">
                ATS <span class="font-bold">Boost</span>
            </flux:text>
        </a>
    </div>

    <div class="absolute left-1/2 -translate-x-1/2 max-md:hidden">
        <flux:navbar class="-mb-px flex gap-2">
            <flux:navbar.item :href="route('demo.tailor')" :current="request()->routeIs('demo.tailor')" wire:navigate>Adaptar un CV</flux:navbar.item>
            <flux:navbar.item :href="route('features')" :current="request()->routeIs('features')" wire:navigate>Características</flux:navbar.item>
            <flux:navbar.item :href="route('pricing')" :current="request()->routeIs('pricing')" wire:navigate>Precios</flux:navbar.item>
        </flux:navbar>
    </div>

    <div class="ml-auto flex items-center gap-3">
        @if (Auth::user())
            <flux:navbar.item href="{{ route('dashboard') }}" wire:navigate>
                Panel de control
            </flux:navbar.item>
        @else
            <flux:navbar.item href="{{ route('login') }}" wire:navigate>
                Iniciar sesión
            </flux:navbar.item>

            <flux:button class="max-sm:hidden" variant="primary" size="sm" as="link" href="{{ route('register') }}" wire:navigate>
                Registrarse
            </flux:button>
        @endif

        <flux:sidebar.toggle class="md:hidden" icon="bars-2" />

        <flux:sidebar sticky collapsible="mobile"
            class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700 max-sm:w-full md:hidden">
            <flux:sidebar.header>
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-7 w-7 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-7 text-blue-600/90 dark:text-white" />
                    </span>
                    <flux:text size="lg" class="text-blue-600/90 dark:text-white">
                        ATS <span class="font-bold">Boost</span>
                    </flux:text>
                </a>

                <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="scissors" wire:navigate href="{{ route('demo.tailor') }}">Adaptar un CV</flux:sidebar.item>
                <flux:sidebar.item icon="sparkles" wire:navigate href="{{ route('features') }}">Características</flux:sidebar.item>
                <flux:sidebar.item icon="credit-card" wire:navigate href="{{ route('pricing') }}">Precios</flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            @if (Auth::user())
                <flux:button variant="primary" icon="squares-plus" as="link" href="{{ route('dashboard') }}" wire:navigate>
                    Panel de control
                </flux:button>
            @else
                <div class="flex flex-col w-full space-y-2">
                    <flux:sidebar.item href="{{ route('login') }}" wire:navigate class="!text-center items-center">
                        Iniciar sesión
                    </flux:sidebar.item>

                    <flux:button variant="primary" as="link" href="{{ route('register') }}" wire:navigate>
                        Registrarse
                    </flux:button>
                </div>
            @endif
        </flux:sidebar>
    </div>
</flux:header>

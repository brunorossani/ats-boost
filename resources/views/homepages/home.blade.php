<x-layouts.main title="Ofertas del día con tu CV adaptado • ATS Boost">
    <section class="space-y-10">
        <div class="space-y-6">
            <div class="!text-center lg:!text-start">
                <flux:badge color="blue" icon="sparkles" size="sm" variant="pill">
                    Demo en vivo · ofertas reales de los últimos días
                </flux:badge>
            </div>

            <flux:heading level="1"
                class="!text-4xl md:!text-6xl font-black max-w-4xl mx-auto lg:mx-0 text-center lg:text-start">
                Todas las ofertas del día,<br class="max-sm:hidden">
                <span class="text-transparent bg-gradient-to-r from-blue-600 via-blue-400 to-blue-700 dark:from-blue-500 dark:via-blue-300 dark:to-blue-600 bg-clip-text">
                    cada una con tu CV adaptado
                </span>
            </flux:heading>

            <flux:subheading level="2" class="max-w-2xl mx-auto text-sm text-center md:text-base lg:text-start lg:mx-0">
                Buscamos cada día en los portales de cada región —Google Jobs (LinkedIn, Indeed, Computrabajo, Bumeran…),
                Get on Board, Adzuna, Arbeitnow, Remotive y las páginas de empleo de las empresas—, filtramos lo que de
                verdad encaja con tu perfil y generamos un CV adaptado a cada oferta, con las convenciones del país.
            </flux:subheading>

            <div class="flex flex-wrap justify-center gap-2 lg:justify-start">
                <flux:button variant="primary" icon="bolt" :href="route('register')">Recibir mis ofertas</flux:button>
                <flux:button variant="ghost" :href="route('demo.tailor')" wire:navigate>Adaptar mi CV a una oferta puntual</flux:button>
            </div>
        </div>

        <div class="space-y-3">
            <flux:heading size="xl">Así se ve con un CV real</flux:heading>
            <livewire:jobs.live-demo />
        </div>
    </section>
</x-layouts.main>

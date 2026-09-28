<!-- Pricing -->
<div class="flex flex-col w-full max-w-md gap-6 py-6 mx-auto lg:flex-row lg:max-w-none lg:gap-0">

    <!-- Weekly Plan -->
    <div
        class="flex flex-col flex-1 w-full gap-2 p-2 border rounded-2xl border-stone-200 dark:border-stone-700/75 bg-transparent lg:mt-10 lg:pr-0 lg:border-r-0 lg:rounded-r-none">
        <flux:card class="flex flex-col h-full p-6 rounded-lg shadow-sm md:p-8 lg:rounded-r-none">
            <div class="space-y-6">
                <div class="space-y-2">
                    <flux:subheading class="!text-sm">Plan semanal</flux:subheading>
                    <flux:heading class="!text-3xl">{{ $prices['weekly']['formatted'] ?? 'Precio no disponible' }} /semana</flux:heading>
                </div>

                <!-- Features -->
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <flux:icon.fire class="text-amber-600 dark:text-amber-400" variant="solid" />
                        <flux:heading>Currículums personalizados ilimitados</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Optimización de palabras clave para ATS</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Mejoras de gramática y claridad</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Generador de cartas de presentación</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Puntuación y mejora de ATS</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Formato profesional</flux:heading>
                    </div>
                </div>

                <flux:button class="w-full" as="link"
                    href="{{ route('checkout.start', ['variant' => config('services.mercadopago.plans.weekly')]) }}"
                    variant="filled" icon-trailing="chevron-right">
                    Prueba gratis
                </flux:button>
            </div>
        </flux:card>
    </div>

    <!-- Monthly Plan (Most Popular) -->
    <div
        class="flex flex-col flex-1 gap-2 p-2 border-2 border-blue-800 rounded-2xl dark:border-blue-200 bg-transparent lg:-mb-4">
        <flux:card class="flex flex-col h-full p-6 rounded-lg shadow-sm md:p-8 lg:pb-12">
            <div class="space-y-6">
                <div class="space-y-2">
                    <flux:badge icon="fire" size="sm" color="green" class="mb-2">Más popular
                    </flux:badge>
                    <flux:subheading class="!text-sm">Plan mensual</flux:subheading>
                    <flux:heading class="!text-3xl">{{ $prices['monthly']['formatted'] ?? 'Precio no disponible' }} /mes</flux:heading>
                </div>

                <!-- Features -->
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <flux:icon.fire class="text-amber-600 dark:text-amber-400" variant="solid" />
                        <flux:heading>Currículums personalizados ilimitados</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Optimización de palabras clave para ATS</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Mejoras de gramática y claridad</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Generador de cartas de presentación</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Puntuación y mejora de ATS</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Formato profesional</flux:heading>
                    </div>
                </div>

                <flux:button class="w-full" as="link"
                    href="{{ route('checkout.start', ['variant' => config('services.mercadopago.plans.monthly')]) }}"
                    variant="primary" icon-trailing="chevron-right">
                    Prueba gratis
                </flux:button>
            </div>
        </flux:card>
    </div>

    <!-- Yearly Plan -->
    <div
        class="flex flex-col flex-1 gap-2 p-2 border rounded-2xl border-stone-200 dark:border-stone-700/75 bg-transparent lg:mt-10 lg:rounded-l-none lg:border-l-0 lg:pl-0">
        <flux:card class="flex flex-col h-full p-6 rounded-lg shadow-sm md:p-8 lg:rounded-l-none">
            <div class="space-y-6">
                <div class="space-y-2">
                    <flux:subheading class="!text-sm">Plan anual</flux:subheading>
                    <flux:heading class="!text-3xl">{{ $prices['yearly']['formatted'] ?? 'Precio no disponible' }} /año</flux:heading>
                </div>

                <!-- Features -->
                <div class="flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <flux:icon.fire class="text-amber-600 dark:text-amber-400" variant="solid" />
                        <flux:heading>Currículums personalizados ilimitados</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Optimización de palabras clave para ATS</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Mejoras de gramática y claridad</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Generador de cartas de presentación</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Puntuación y mejora de ATS</flux:heading>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="text-green-600 dark:text-green-400" variant="solid" />
                        <flux:heading>Formato profesional</flux:heading>
                    </div>
                </div>

                <flux:button class="w-full" as="link"
                    href="{{ route('checkout.start', ['variant' => config('services.mercadopago.plans.yearly')]) }}"
                    variant="filled" icon-trailing="chevron-right">
                    Prueba gratis
                </flux:button>
            </div>
        </flux:card>
    </div>
</div>

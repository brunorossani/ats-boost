<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Suscripciones')" :subheading="__('Gestiona tu suscripción de cuenta')">
        @if ($subscription)
            @php
                $hasAccess = $subscription->ends_at?->isFuture();
            @endphp

            {{-- Status-based callouts with subscription info --}}
            <div class="space-y-6 mb-6">
                @if ($subscription->status === 'cancelled')
                    @if(!$hasAccess)
                        <flux:callout icon="shield-exclamation" color="red">
                            <flux:callout.heading>
                                Suscripción cancelada
                            </flux:callout.heading>
                            <flux:callout.text>
                                Tu suscripción ha sido cancelada permanentemente.
                            </flux:callout.text>
                        </flux:callout>
                    @else
                        <flux:callout icon="clock" color="amber">
                            <flux:callout.heading>
                                Suscripción cancelada
                            </flux:callout.heading>
                            <flux:callout.text>
                                Seguirás teniendo acceso hasta {{ $subscription->ends_at->format('d/m/Y') }}.
                            </flux:callout.text>
                        </flux:callout>
                    @endif
                @else ($subscription->status === 'authorized' || $subscription->status === 'active')
                    <flux:callout icon="check-circle" color="green">
                        <flux:callout.heading>
                            Suscripción activa
                        </flux:callout.heading>
                        <flux:callout.text>
                            Tu suscripción está activa y los beneficios están habilitados.
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            <div class="space-y-6">
                @if ($subscription->status !== 'cancelled')
                    {{-- Change plan (hidden for cancelled subscriptions) --}}
                    <flux:radio.group
                        variant="cards"
                        wire:model.live="newPlan"
                        label="Actualizar plan"
                        class="flex-col gap-4"
                        required
                    >

                        {{-- Monthly --}}
                        <flux:radio
                            value="{{ config('services.mercadopago.plans.monthly') }}"
                            :disabled="$subscription->mp_plan_id === config('services.mercadopago.plans.monthly')"
                        >
                            <flux:radio.indicator/>

                            <div class="flex items-center justify-between w-full">
                                <div class="flex-1">
                                    <flux:heading size="sm">Plan mensual</flux:heading>
                                    <flux:text size="sm" class="mt-1">
                                        {{ $prices['monthly']['formatted'] ?? 'Precio no disponible' }} /mes
                                    </flux:text>
                                </div>

                                <div class="flex gap-2">
                                    @if ($subscription->mp_plan_id === config('services.mercadopago.plans.monthly'))
                                        <flux:badge size="sm" color="green">Actual</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </flux:radio>


                        {{-- Weekly --}}
                        <flux:radio
                            value="{{ config('services.mercadopago.plans.weekly') }}"
                            :disabled="$subscription->mp_plan_id === config('services.mercadopago.plans.weekly')"
                        >
                            <flux:radio.indicator/>

                            <div class="flex items-center justify-between w-full">
                                <div class="flex-1">
                                    <flux:heading size="sm">Plan semanal</flux:heading>
                                    <flux:text size="sm" class="mt-1">
                                        {{ $prices['weekly']['formatted'] ?? 'Precio no disponible' }} /semana
                                    </flux:text>
                                </div>

                                <div class="flex gap-2">
                                    @if ($subscription->mp_plan_id === config('services.mercadopago.plans.weekly'))
                                        <flux:badge size="sm" color="green">Actual</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </flux:radio>


                        {{-- Yearly --}}
                        <flux:radio
                            value="{{ config('services.mercadopago.plans.yearly') }}"
                            :disabled="$subscription->mp_plan_id === config('services.mercadopago.plans.yearly')"
                        >
                            <flux:radio.indicator/>

                            <div class="flex items-center justify-between w-full">
                                <div class="flex-1">
                                    <flux:heading size="sm">Plan anual</flux:heading>
                                    <flux:text size="sm" class="mt-1">
                                        {{ $prices['yearly']['formatted'] ?? 'Precio no disponible' }} /año
                                    </flux:text>
                                </div>

                                <div class="flex gap-2">
                                    @if ($subscription->mp_plan_id === config('services.mercadopago.plans.yearly'))
                                        <flux:badge size="sm" color="green">Actual</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </flux:radio>
                    </flux:radio.group>

                    <flux:modal.trigger name="update-subscription">
                        <flux:button variant="primary" :disabled="$newPlan === $subscription->mp_plan_id">
                            Actualizar suscripción
                        </flux:button>
                    </flux:modal.trigger>

                    <div class="mt-6"></div>

                    <flux:separator/>
                @endif

                {{-- Status-based actions --}}
                @if ($subscription->status === 'cancelled')
                    {{-- Cancelled (permanent) --}}
                    <div class="space-y-4">
                        <flux:heading>Suscripción cancelada</flux:heading>
                        <flux:subheading>
                            Tu suscripción ha sido cancelada. Para recuperar el acceso a funciones
                            premium, necesitarás crear una nueva suscripción.
                        </flux:subheading>

                        <flux:button href="{{ route('pricing') }}" wire:navigate>
                            Crear nueva suscripción
                        </flux:button>
                    </div>
                @elseif ($subscription->status === 'authorized' || $subscription->status === 'active')
                    {{-- Active (can be cancelled) --}}
                    <div class="space-y-4">
                        <flux:heading>Cancelar plan</flux:heading>
                        <flux:subheading>
                            Cancelar tu suscripción mantendrá tu acceso hasta el final del período de facturación
                            actual.
                        </flux:subheading>
                        <flux:modal.trigger name="cancel-subscription">
                            <flux:button variant="danger">
                                Cancelar suscripción
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                @endif
            </div>

            {{-- Modals --}}
            <flux:modal name="update-subscription" class="md:w-96">
                <div class="space-y-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="lg">¿Actualizar suscripción?</flux:heading>
                            <flux:text class="mt-2">
                                La suscripción actual será cancelada.<br> Se generará una nueva suscripción y
                                no se aplicarán cargos <adicionales class=""></adicionales>
                            </flux:text>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4">
                        <flux:button x-on:click="$flux.modal('update-subscription').close()" variant="ghost"> Deshacer
                        </flux:button>
                        <flux:button variant="primary" wire:click="changePlan"> Actualizar suscripción</flux:button>
                    </div>
                </div>
            </flux:modal>

            <flux:modal name="cancel-subscription" class="md:w-96">
                <div class="space-y-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="lg">¿Cancelar suscripción?</flux:heading>
                            <flux:text class="mt-2"> Tu suscripción será cancelada.<br> Perderás acceso a todas las
                                funciones premium al final de tu período de facturación.
                            </flux:text>
                        </div>
                    </div>
                    <div class="flex justify-end gap-4">
                        <flux:button x-on:click="$flux.modal('cancel-subscription').close()" variant="ghost"> Deshacer
                        </flux:button>
                        <flux:button variant="danger" wire:click="cancelSubscription"> Cancelar suscripción
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @else
            {{-- No subscription --}}
            <flux:callout icon="shield-check" color="blue" inline>
                <flux:callout.heading>
                    Aún no tienes una suscripción activa
                </flux:callout.heading>
                <flux:callout.text>
                    Obtén acceso a todas nuestras funciones y beneficios premium.
                </flux:callout.text>

                <x-slot name="actions">
                    <flux:button icon-trailing="arrow-right" href="{{ route('pricing') }}" wire:navigate>
                        Actualizar a pro
                    </flux:button>
                </x-slot>
            </flux:callout>
        @endif
    </x-settings.layout>
</section>

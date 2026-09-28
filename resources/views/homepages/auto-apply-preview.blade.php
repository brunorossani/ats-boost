<x-layouts.main title="El futuro de ATS Boost • Postulación automática">
    <section class="space-y-6">
        <div class="!text-center lg:!text-start">
            <flux:badge color="orange" icon="rocket-launch" size="sm" variant="pill">
                Vista previa del producto — próximamente
            </flux:badge>
        </div>

        <div>
            <flux:heading level="1"
                class="max-sm:hidden !text-5xl !mb-6 md:!text-6xl font-black max-w-4xl mx-auto lg:mx-0 lg:max-w-full text-center lg:text-start">
                Dejá de mandar CVs.<br>Dejá que
                <span
                    class="text-5xl font-black text-transparent md:text-6xl dark:from-blue-500 dark:via-blue-300 dark:to-blue-600 bg-gradient-to-r from-blue-600 via-blue-400 to-blue-700 bg-clip-text">
                    nosotros los mandemos
                </span>
            </flux:heading>

            <!-- Mobile -->
            <flux:heading level="1" class="sm:hidden !text-5xl text-center">
                Dejá que nosotros mandemos tus CVs
            </flux:heading>

            <flux:subheading level="2"
                class="max-w-xl mx-auto text-sm text-center md:text-base lg:text-start lg:mx-0">
                Subís tu currículum una vez. Nuestra IA busca vacantes reales que coinciden con tu perfil en
                LinkedIn, Indeed y Computrabajo — y postula por vos, con un CV adaptado al texto real de cada
                oferta.<br><br>
                Así se va a ver el panel cuando lo lancemos.
            </flux:subheading>
        </div>

        <livewire:resume.auto-apply-demo />

        <flux:subheading level="2" class="max-w-xl mx-auto text-sm text-center md:text-base lg:text-start lg:mx-0">
            Esta página es una demostración: la búsqueda de vacantes y la adaptación del CV son reales. Lo único
            que todavía no está conectado es el envío/postulación efectiva en cada portal.
        </flux:subheading>
    </section>
</x-layouts.main>

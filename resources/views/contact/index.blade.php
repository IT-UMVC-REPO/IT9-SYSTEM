<x-layouts::app.header :title="__('Contact Us')">
    <main class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <section class="grid gap-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <div class="brand-panel-muted h-fit p-6 sm:p-8">
                <p class="brand-kicker">{{ __('Support') }}</p>
                <h1 class="brand-serif mt-4 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Contact Us') }}</h1>
                <p class="mt-4 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                    {{ __('Questions about orders, vendor onboarding, rider applications, reports, or billing? Send the Sukimarket team the details and we will route it to the right desk.') }}
                </p>
            </div>

            <livewire:contact-form />
        </section>
    </main>
</x-layouts::app.header>

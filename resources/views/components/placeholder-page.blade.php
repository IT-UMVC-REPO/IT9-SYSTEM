@props([
    'eyebrow',
    'title',
    'description',
    'sections' => [],
    'notes' => [],
    'links' => [],
    'status' => 'Placeholder page',
])

<section class="mx-auto max-w-[1500px] px-4 py-8 sm:px-6 lg:px-8">
    <div class="brand-panel px-6 py-16 text-center sm:px-10">
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100 sm:text-5xl">{{ $title }}</h1>
        <p class="mt-4 text-lg font-semibold uppercase tracking-[0.28em] text-stone-400 dark:text-zinc-400">TBD</p>
    </div>
</section>

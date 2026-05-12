@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="brand-serif suki-reveal mt-5 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ $title }}</h1>
    <p class="suki-reveal mt-3 text-sm leading-7 text-neutral-500 dark:text-zinc-400" style="transition-delay: 80ms">{{ $description }}</p>
</div>

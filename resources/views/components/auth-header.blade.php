@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <span class="brand-kicker mx-auto">SukiMarket access</span>
    <h1 class="brand-serif mt-5 text-4xl font-bold text-neutral-900">{{ $title }}</h1>
    <p class="mt-3 text-sm leading-7 text-neutral-500">{{ $description }}</p>
</div>

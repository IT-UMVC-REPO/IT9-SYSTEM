@php
    $title ??= 'Dashboard';
    $heading ??= 'Marketplace dashboard';
    $description ??= 'Your next marketplace actions will appear here.';
    $highlights ??= [];
@endphp

<x-layouts::app :title="__($title)">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-neutral-500 dark:text-neutral-400">{{ $title }}</p>
            <h1 class="mt-3 text-3xl font-semibold text-neutral-950 dark:text-white">{{ $heading }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-neutral-600 dark:text-neutral-300">{{ $description }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($highlights as $highlight)
                <section class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-emerald-500 via-amber-400 to-rose-500"></div>
                    <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ $highlight['label'] }}</p>
                    <p class="mt-4 text-2xl font-semibold text-neutral-950 dark:text-white">{{ $highlight['value'] }}</p>
                    <p class="mt-3 text-sm leading-6 text-neutral-600 dark:text-neutral-300">{{ $highlight['description'] }}</p>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts::app>

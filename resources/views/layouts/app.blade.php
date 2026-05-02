<x-layouts::app.header :title="$title ?? null">
    <main class="min-h-[calc(100vh-4.75rem)] pb-20 lg:pb-0">
        {{ $slot }}
    </main>
</x-layouts::app.header>

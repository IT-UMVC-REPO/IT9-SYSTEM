<x-layouts::app.header :title="$title ?? null">
    <main class="min-h-[calc(100vh-4.75rem)]">
        {{ $slot }}
    </main>
</x-layouts::app.header>

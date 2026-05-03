@php
    $isConversationSurface = request()->routeIs('messages.conversation', 'messages.group');
@endphp

<x-layouts::app.header :title="$title ?? null">
    <main @class([
        'min-h-[calc(100vh-4.75rem)] pb-20 lg:pb-0' => ! $isConversationSurface,
        'h-[calc(100dvh-52px)] overflow-hidden pb-0' => $isConversationSurface,
    ])>
        {{ $slot }}
    </main>
</x-layouts::app.header>

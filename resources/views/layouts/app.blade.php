@php
    $isConversationSurface = request()->routeIs('messages.conversation', 'messages.group');
@endphp

<x-layouts::app.header :title="$title ?? null">
    <main @class([
        'min-w-0 overflow-x-clip',
        'min-h-[calc(100vh-4.75rem)] pb-20 lg:pb-0' => ! $isConversationSurface,
        'h-[calc(100dvh-52px)] overflow-hidden pb-0' => $isConversationSurface,
    ])>
        <div class="suki-page-enter min-w-0 overflow-x-clip">
            {{ $slot }}
        </div>
    </main>
</x-layouts::app.header>

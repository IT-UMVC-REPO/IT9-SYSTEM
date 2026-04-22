@php
    $isVendor = auth()->user()->effectiveMarketplaceRole() === \App\Enums\UserRole::Vendor;
    $workspaceLabel = $isVendor ? 'Vendor orders shell' : 'Customer orders shell';
    $workspaceHref = $isVendor ? route('vendor.orders') : route('shop.orders');

    $sections = [
        ['label' => 'Threads', 'title' => 'Conversation list', 'description' => 'This page will later list buyer-seller conversations with order context and recency cues.'],
        ['label' => 'Sorting', 'title' => 'Unread and recent filters', 'description' => 'Unread states and quick filtering belong here once the inbox has real data.'],
        ['label' => 'Context', 'title' => 'Order-linked messaging', 'description' => 'Conversations will eventually connect directly to order detail pages and storefront context.'],
        ['label' => 'Response flow', 'title' => 'Fast handoff into threads', 'description' => 'The real inbox should make it easy to move from the list into one conversation without losing context.'],
    ];

    $notes = [
        'This inbox needs to work for both customers and approved vendors, so the layout should stay neutral.',
        'Unread state, order references, and timestamps will likely be the most important signals here.',
        'The shared route already exists so header shortcuts have a stable destination while messaging is still pending.',
    ];

    $links = [
        ['label' => 'Conversation shell', 'href' => route('messages.conversation', ['conversationReference' => 'sample-thread']), 'description' => 'Preview the future thread page.'],
        ['label' => $workspaceLabel, 'href' => $workspaceHref, 'description' => 'Jump back to the role-specific order workspace.'],
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'description' => 'Return to the current portal home.'],
    ];
@endphp

<x-layouts::app :title="__('Messages')">
    {{-- TODO: Replace this shell with the real conversation list, unread indicators, and order-linked thread previews. --}}
    {{-- TODO: Add filtering for recent threads, unread messages, and active order conversations. --}}
    {{-- TODO: Make it easy to jump from each thread into the matching order detail or storefront context. --}}
    <x-placeholder-page
        eyebrow="Shared inbox"
        title="Messages inbox"
        description="This placeholder keeps the future customer-vendor messaging flow visible in navigation now, while the actual conversation system stays turned off."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

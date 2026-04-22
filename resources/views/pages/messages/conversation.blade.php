@php
    $isVendor = auth()->user()->effectiveMarketplaceRole() === \App\Enums\UserRole::Vendor;
    $workspaceLabel = $isVendor ? 'Vendor orders shell' : 'Customer orders shell';
    $workspaceHref = $isVendor ? route('vendor.orders') : route('shop.orders');

    $sections = [
        ['label' => 'Thread', 'title' => 'Message timeline', 'description' => 'The final version will show the back-and-forth conversation between customer and vendor here.'],
        ['label' => 'Composer', 'title' => 'Reply tools', 'description' => 'A live reply field, attachments if needed, and send-state feedback belong in this future layout.'],
        ['label' => 'Order context', 'title' => 'Related purchase details', 'description' => 'The eventual thread can stay grounded by showing the linked order summary nearby.'],
        ['label' => 'Signals', 'title' => 'Read state and timing', 'description' => 'Message timestamps and read indicators will likely matter once the inbox is active.'],
    ];

    $notes = [
        'A conversation thread should feel lightweight and focused, especially on mobile.',
        'Order context will help users stay oriented without opening another tab or page.',
        'Reply composition and polling behavior can be added later once the message model is wired up.',
    ];

    $links = [
        ['label' => 'Messages inbox shell', 'href' => route('messages.inbox'), 'description' => 'Return to the conversation list.'],
        ['label' => $workspaceLabel, 'href' => $workspaceHref, 'description' => 'Jump back to the role-specific order page.'],
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'description' => 'Return to the current portal home.'],
    ];
@endphp

<x-layouts::app :title="__('Conversation')">
    {{-- TODO: Replace this shell with the live message thread, reply composer, and order-linked context. --}}
    {{-- TODO: Add polling or refresh behavior, timestamps, and unread state once messaging exists. --}}
    {{-- TODO: Keep a clear path back to both the inbox and the related order detail. --}}
    <x-placeholder-page
        eyebrow="Shared thread"
        title="Conversation detail"
        description="This placeholder keeps the messaging flow two steps deep so the inbox already feels like part of a full feature set."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

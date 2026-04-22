@php
    $sections = [
        ['label' => 'Market overview', 'title' => 'Shopping pulse', 'description' => 'This area is reserved for recent orders, saved stalls, and fresh picks matched to the customer account.'],
        ['label' => 'Next steps', 'title' => 'Quick shopper actions', 'description' => 'Shortcuts will eventually take customers straight to their cart, checkout flow, and favorite vendors.'],
        ['label' => 'Messages', 'title' => 'Seller conversations', 'description' => 'Unread conversations and order-linked replies will surface here once messaging is connected.'],
        ['label' => 'Trust signals', 'title' => 'Marketplace updates', 'description' => 'Announcements, vendor approvals, and personalized notices can live here later on.'],
    ];

    $notes = [
        'Recent order statuses and delivery checkpoints will be summarized here.',
        'A compact cart preview will help shoppers jump back into checkout quickly.',
        'Favorite vendors and unread messages will become dashboard widgets once those modules are wired up.',
    ];

    $links = [
        ['label' => 'Browse storefront', 'href' => route('shop.home'), 'description' => 'Go back to the customer-facing catalog.'],
        ['label' => 'Open cart placeholder', 'href' => route('shop.cart'), 'description' => 'Preview the future cart shell.'],
        ['label' => 'Seller setup placeholder', 'href' => route('vendor.registration'), 'description' => 'See the future onboarding page for new vendors.'],
    ];
@endphp

<x-layouts::app :title="__('Customer Dashboard')">
    {{-- TODO: Replace this hero with live shopper metrics, recent activity, and recommended vendors. --}}
    {{-- TODO: Add dashboard cards for cart progress, active orders, and unread seller messages. --}}
    {{-- TODO: Surface personalized marketplace notices once notifications are implemented. --}}
    <x-placeholder-page
        eyebrow="Customer portal"
        title="Customer dashboard"
        description="This tailored dashboard gives customers a proper portal home instead of dropping them straight into browsing. For now it stays intentionally lightweight while the real shopping widgets are still being built."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

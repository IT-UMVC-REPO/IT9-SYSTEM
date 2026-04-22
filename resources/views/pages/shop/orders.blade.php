@php
    $sections = [
        ['label' => 'History', 'title' => 'Past and active orders', 'description' => 'Customers will later review their order history and current order states from this page.'],
        ['label' => 'Tracking', 'title' => 'Status awareness', 'description' => 'Delivered, ready, preparing, and pending states should be easy to scan in the final version.'],
        ['label' => 'Support', 'title' => 'Vendor follow-up', 'description' => 'The order list should eventually connect naturally into messaging and order detail views.'],
        ['label' => 'Reassurance', 'title' => 'Totals and timing', 'description' => 'Order totals, placement dates, and key timing context belong in this future layout.'],
    ];

    $notes = [
        'This page should help customers understand what is happening after checkout without extra friction.',
        'Status chips and clear recency cues will matter more than dense order tables for most shoppers.',
        'Order detail and message shortcuts will likely be the most-used actions once the flow is live.',
    ];

    $links = [
        ['label' => 'Order detail shell', 'href' => route('shop.orders.show', ['orderReference' => 'sample-order']), 'description' => 'Preview the future order detail page.'],
        ['label' => 'Favorites shell', 'href' => route('shop.favorites'), 'description' => 'Open the customer favorites page.'],
        ['label' => 'Storefront', 'href' => route('shop.home'), 'description' => 'Return to product browsing.'],
    ];
@endphp

<x-layouts::app :title="__('My Orders')">
    {{-- TODO: Replace this shell with customer order history, status chips, and recency details. --}}
    {{-- TODO: Add quick links into order detail pages and vendor conversations. --}}
    {{-- TODO: Surface totals, placement dates, and fulfillment progress clearly for shoppers. --}}
    <x-placeholder-page
        eyebrow="Customer orders"
        title="Order history"
        description="This page gives customers a destination for tracking their activity after checkout, even before the actual order lifecycle is implemented."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

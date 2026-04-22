@php
    $sections = [
        ['label' => 'Queue', 'title' => 'Incoming orders', 'description' => 'This seller-facing view will later list new and active orders that belong to the current vendor.'],
        ['label' => 'Workflow', 'title' => 'Status progression', 'description' => 'Pending, confirmed, preparing, ready, and delivered actions will eventually live here.'],
        ['label' => 'Customer context', 'title' => 'Buyer details', 'description' => 'Basic customer information, notes, and totals should be available in the eventual order list.'],
        ['label' => 'Drill-down', 'title' => 'Order detail handoff', 'description' => 'Each row in the real queue should link into the order detail page for deeper handling.'],
    ];

    $notes = [
        'This page should stay tightly scoped to the authenticated vendor profile.',
        'Status actions need to be clear and fast because they will likely be the most frequent seller interaction.',
        'Order notes and linked customer messages will fit naturally once those modules are connected.',
    ];

    $links = [
        ['label' => 'Order detail shell', 'href' => route('vendor.orders.show', ['orderReference' => 'sample-order']), 'description' => 'Preview the seller order detail page.'],
        ['label' => 'Vendor dashboard', 'href' => route('vendor.dashboard'), 'description' => 'Return to the seller portal home.'],
        ['label' => 'Sales shell', 'href' => route('vendor.sales'), 'description' => 'Open the revenue summary placeholder.'],
    ];
@endphp

<x-layouts::app :title="__('Vendor Orders')">
    {{-- TODO: Replace this shell with the vendor order queue, status filters, and fulfillment actions. --}}
    {{-- TODO: Add customer details, notes, totals, and clear status progression controls. --}}
    {{-- TODO: Link each order into a richer detail view with messaging and payment context. --}}
    <x-placeholder-page
        eyebrow="Vendor orders"
        title="Order management"
        description="This dummy page reserves the seller order-processing workspace so the vendor portal already feels complete and navigable."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

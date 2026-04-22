@php
    $sections = [
        ['label' => 'Contents', 'title' => 'Order item breakdown', 'description' => 'The final version will show line items, quantities, pricing, and customer notes here.'],
        ['label' => 'Progress', 'title' => 'Status timeline', 'description' => 'Sellers will later update the order from pending through delivery on this screen.'],
        ['label' => 'Support', 'title' => 'Customer communication', 'description' => 'A linked conversation or message shortcut belongs here once messaging is active.'],
        ['label' => 'Payment', 'title' => 'Collection context', 'description' => 'Payment method, status, and any manual follow-up notes can appear here later.'],
    ];

    $notes = [
        'This page should become the vendor\'s main workspace for one order at a time.',
        'Status changes, customer notes, and payment context are the three most important future additions here.',
        'A message shortcut makes sense once the customer-vendor inbox is functional.',
    ];

    $links = [
        ['label' => 'Order queue shell', 'href' => route('vendor.orders'), 'description' => 'Return to the vendor order list.'],
        ['label' => 'Messages inbox shell', 'href' => route('messages.inbox'), 'description' => 'Open the shared buyer-seller inbox placeholder.'],
        ['label' => 'Sales shell', 'href' => route('vendor.sales'), 'description' => 'Open the seller revenue summary page.'],
    ];
@endphp

<x-layouts::app :title="__('Order Detail')">
    {{-- TODO: Replace this shell with item details, status controls, payment context, and customer notes. --}}
    {{-- TODO: Add action buttons for confirmation, preparation, readiness, and delivery states. --}}
    {{-- TODO: Link order-level messaging and support context once those modules exist. --}}
    <x-placeholder-page
        eyebrow="Vendor detail"
        title="Order detail"
        description="This detail page keeps the vendor order flow from feeling incomplete while the real fulfillment logic is still waiting to be built."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

@php
    $sections = [
        ['label' => 'Items', 'title' => 'Order contents', 'description' => 'The real version will show purchased items, quantities, pricing, and vendor details.'],
        ['label' => 'Progress', 'title' => 'Status timeline', 'description' => 'A clear order progression view belongs here once the customer order lifecycle is active.'],
        ['label' => 'Support', 'title' => 'Vendor communication', 'description' => 'The shopper should eventually be able to move naturally from this page into the relevant conversation thread.'],
        ['label' => 'Payment', 'title' => 'Method and notes', 'description' => 'Payment method, order notes, and delivery instructions can be shown here later on.'],
    ];

    $notes = [
        'This page should give customers confidence that the order is moving in the right direction.',
        'Status history and vendor contact options will likely matter as much as the line-item summary.',
        'A future version can surface reorder or support actions once the core workflow is stable.',
    ];

    $links = [
        ['label' => 'Order history shell', 'href' => route('shop.orders'), 'description' => 'Return to the customer order list.'],
        ['label' => 'Messages conversation shell', 'href' => route('messages.conversation', ['conversationReference' => 'sample-thread']), 'description' => 'Preview the future linked conversation page.'],
        ['label' => 'Storefront', 'href' => route('shop.home'), 'description' => 'Return to shopping.'],
    ];
@endphp

<x-layouts::app :title="__('Order Detail')">
    {{-- TODO: Replace this shell with line items, status history, payment info, and vendor details. --}}
    {{-- TODO: Add message shortcuts and clearer tracking cues for the customer. --}}
    {{-- TODO: Show delivery notes and order summary data once checkout is connected. --}}
    <x-placeholder-page
        eyebrow="Customer detail"
        title="Order detail"
        description="This placeholder keeps the future customer tracking flow complete, with a dedicated page for one order at a time."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

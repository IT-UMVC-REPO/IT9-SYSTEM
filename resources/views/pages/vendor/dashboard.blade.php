@php
    $sections = [
        ['label' => 'Catalog', 'title' => 'Listing management', 'description' => 'This future dashboard area will summarize product counts, stock levels, and edit shortcuts.'],
        ['label' => 'Orders', 'title' => 'Fulfillment pulse', 'description' => 'Incoming order activity and next status changes should eventually be visible at a glance.'],
        ['label' => 'Sales', 'title' => 'Revenue snapshot', 'description' => 'Weekly and monthly sales summaries belong in this dashboard once the reporting logic exists.'],
        ['label' => 'Status', 'title' => 'Store readiness', 'description' => 'Approval state, missing setup steps, and seller reminders can all live here later on.'],
    ];

    $notes = [
        'Vendor metrics should highlight what needs attention first, especially new orders and low stock.',
        'Quick links into products, orders, and sales should eventually carry real counts and badges.',
        'Seller onboarding or compliance reminders can surface here when that flow is implemented.',
    ];

    $links = [
        ['label' => 'Products shell', 'href' => route('vendor.products'), 'description' => 'Open the future product management page.'],
        ['label' => 'Orders shell', 'href' => route('vendor.orders'), 'description' => 'Open the vendor order queue.'],
        ['label' => 'Sales shell', 'href' => route('vendor.sales'), 'description' => 'Open the revenue summary placeholder.'],
    ];
@endphp

<x-layouts::app :title="__('Vendor Dashboard')">
    {{-- TODO: Replace these planning cards with live product counts, order totals, and sales summaries. --}}
    {{-- TODO: Add approval-state messaging and shortcuts into listing, order, and sales workflows. --}}
    {{-- TODO: Surface urgent seller tasks like low stock, pending orders, and unread customer messages. --}}
    <x-placeholder-page
        eyebrow="Vendor portal"
        title="Vendor dashboard"
        description="Approved vendors now have a dashboard that feels distinct from both the customer and admin experiences, even while the seller tools are still placeholders."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

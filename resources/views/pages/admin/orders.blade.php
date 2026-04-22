@php
    $sections = [
        ['label' => 'Feed', 'title' => 'Marketplace order stream', 'description' => 'Admins will later review every order across the marketplace from this page.'],
        ['label' => 'Filters', 'title' => 'Status and vendor drill-downs', 'description' => 'Order status, vendor, customer, and date filters belong in this future oversight tool.'],
        ['label' => 'Intervention', 'title' => 'Escalations and disputes', 'description' => 'This view should eventually highlight orders that need admin attention or manual follow-up.'],
        ['label' => 'Context', 'title' => 'Cross-role visibility', 'description' => 'A final version can tie together vendor and customer context for each order in one place.'],
    ];

    $notes = [
        'This page will become the read-only operations lens for all marketplace orders.',
        'Escalated and disputed orders should be easy to spot without scanning the whole list.',
        'Future filters will need to pair well with vendor review and user management pages.',
    ];

    $links = [
        ['label' => 'Admin dashboard', 'href' => route('admin.dashboard'), 'description' => 'Return to the admin portal home.'],
        ['label' => 'Vendor approval shell', 'href' => route('admin.vendors'), 'description' => 'Review pending vendor applications.'],
        ['label' => 'User management shell', 'href' => route('admin.users'), 'description' => 'Open the account oversight page.'],
    ];
@endphp

<x-layouts::app :title="__('Marketplace Orders')">
    {{-- TODO: Replace this shell with a global order table and status filters for admins. --}}
    {{-- TODO: Add dispute indicators, vendor and customer references, and read-only order details. --}}
    {{-- TODO: Surface exceptions that require admin action without exposing vendor-only controls. --}}
    <x-placeholder-page
        eyebrow="Admin orders"
        title="Marketplace order oversight"
        description="This placeholder makes the admin order-monitoring route real now, so the portal structure is ready before the order tools themselves are built."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

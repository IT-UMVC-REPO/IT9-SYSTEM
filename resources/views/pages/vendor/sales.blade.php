@php
    $sections = [
        ['label' => 'Reporting', 'title' => 'Revenue summary', 'description' => 'This page will later show weekly and monthly sales totals for the current vendor.'],
        ['label' => 'Trends', 'title' => 'Period comparisons', 'description' => 'Future charts and breakdowns can help sellers understand how their store is performing over time.'],
        ['label' => 'Orders', 'title' => 'Completed order context', 'description' => 'Completed and delivered order counts will likely support this reporting page later on.'],
        ['label' => 'Planning', 'title' => 'Seller decisions', 'description' => 'Stock planning and product adjustments can eventually be informed by the numbers shown here.'],
    ];

    $notes = [
        'The first useful version of this page probably needs totals, counts, and a simple date filter.',
        'Comparisons by week and month would help vendors understand momentum without overcomplicating the UI.',
        'Links back into product and order pages should stay close once sales data is live.',
    ];

    $links = [
        ['label' => 'Vendor dashboard', 'href' => route('vendor.dashboard'), 'description' => 'Return to the seller portal home.'],
        ['label' => 'Products shell', 'href' => route('vendor.products'), 'description' => 'Open the vendor catalog page.'],
        ['label' => 'Orders shell', 'href' => route('vendor.orders'), 'description' => 'Open the order queue placeholder.'],
    ];
@endphp

<x-layouts::app :title="__('Sales Summary')">
    {{-- TODO: Replace this shell with sales totals, charts, and date-range reporting for the vendor. --}}
    {{-- TODO: Add completed-order aggregation and simple period filters for revenue review. --}}
    {{-- TODO: Connect the resulting insights back to product and order management once data exists. --}}
    <x-placeholder-page
        eyebrow="Vendor reporting"
        title="Sales summary"
        description="This placeholder secures a home for seller reporting so the vendor portal already has a full navigation map, even before analytics are implemented."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

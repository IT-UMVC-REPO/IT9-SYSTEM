@php
    $sections = [
        ['label' => 'Editing', 'title' => 'Prefilled product form', 'description' => 'This future screen will mirror product creation but load the vendor\'s existing listing details.'],
        ['label' => 'Inventory', 'title' => 'Stock and availability', 'description' => 'Stock updates, sell-out state, and visibility toggles should eventually live here.'],
        ['label' => 'Media', 'title' => 'Image replacement', 'description' => 'Vendors will later be able to update product images and previews from this page.'],
        ['label' => 'Lifecycle', 'title' => 'Archive and restore', 'description' => 'If the project adds soft archive states, this is the natural place for those controls.'],
    ];

    $notes = [
        'The edit flow should stay vendor-scoped and never expose listings owned by other sellers.',
        'Stock, pricing, and image changes will likely be the most common edits here.',
        'A product activity summary or linked order references could be useful later on.',
    ];

    $links = [
        ['label' => 'Product list shell', 'href' => route('vendor.products'), 'description' => 'Return to the vendor product list.'],
        ['label' => 'Create product shell', 'href' => route('vendor.products.create'), 'description' => 'Open the matching create form placeholder.'],
        ['label' => 'Order queue shell', 'href' => route('vendor.orders'), 'description' => 'Jump to the seller order page.'],
    ];
@endphp

<x-layouts::app :title="__('Edit Product')">
    {{-- TODO: Replace this placeholder with a prefilled product edit form scoped to the signed-in vendor. --}}
    {{-- TODO: Add stock, price, category, and image update controls for the current listing. --}}
    {{-- TODO: Support archive or publish-state changes once the catalog workflow exists. --}}
    <x-placeholder-page
        eyebrow="Vendor edit"
        title="Edit product"
        description="This page keeps the future vendor edit route in place now, so the seller experience already has a complete skeleton."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

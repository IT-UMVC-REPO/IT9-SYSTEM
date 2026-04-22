@php
    $sections = [
        ['label' => 'Inventory', 'title' => 'Product table', 'description' => 'This page will later list the signed-in vendor\'s products with category, price, stock, and status details.'],
        ['label' => 'Filters', 'title' => 'Catalog controls', 'description' => 'Search, category filters, and stock-state filters belong here once the data table is real.'],
        ['label' => 'Actions', 'title' => 'Create and edit flows', 'description' => 'Vendors will eventually launch product creation and editing from this page.'],
        ['label' => 'Media', 'title' => 'Image readiness', 'description' => 'Preview thumbnails and upload state should be visible here once storage is wired up.'],
    ];

    $notes = [
        'The final version should always scope results to the authenticated vendor profile.',
        'Stock, category, and status filters will matter more than broad marketplace search here.',
        'Bulk archive or low-stock workflows can be added later once the base CRUD exists.',
    ];

    $links = [
        ['label' => 'Create product shell', 'href' => route('vendor.products.create'), 'description' => 'Open the placeholder create form.'],
        ['label' => 'Edit product shell', 'href' => route('vendor.products.edit', ['productReference' => 'sample-product']), 'description' => 'Open the placeholder edit screen.'],
        ['label' => 'Vendor dashboard', 'href' => route('vendor.dashboard'), 'description' => 'Return to the seller portal home.'],
    ];
@endphp

<x-layouts::app :title="__('My Products')">
    {{-- TODO: Replace this shell with a paginated vendor-only product table and catalog filters. --}}
    {{-- TODO: Add create, edit, archive, and stock update actions tied to the vendor profile. --}}
    {{-- TODO: Show image previews, pricing, category tags, and publish status for each product. --}}
    <x-placeholder-page
        eyebrow="Vendor catalog"
        title="Product management"
        description="This placeholder page carves out the vendor catalog workspace so the navigation and seller flow are already mapped before CRUD behavior is added."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

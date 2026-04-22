@php
    $sections = [
        ['label' => 'Form', 'title' => 'Listing details', 'description' => 'The real form will later collect product name, category, price, stock, and description fields.'],
        ['label' => 'Media', 'title' => 'Image upload', 'description' => 'Image previews and file validation will eventually sit in this layout once uploads are ready.'],
        ['label' => 'State', 'title' => 'Draft and publish status', 'description' => 'Product visibility and save-state controls can live here once the listing workflow exists.'],
        ['label' => 'Feedback', 'title' => 'Validation and success messaging', 'description' => 'Inline validation and seller-friendly save confirmations belong in this future form.'],
    ];

    $notes = [
        'The create form should eventually rely on category selection, stock input, and image validation.',
        'Draft-save behavior could be useful later if vendors need to stage listings before publishing them.',
        'A live preview block would fit naturally in this page once uploads are supported.',
    ];

    $links = [
        ['label' => 'Product list shell', 'href' => route('vendor.products'), 'description' => 'Return to the product management page.'],
        ['label' => 'Edit product shell', 'href' => route('vendor.products.edit', ['productReference' => 'sample-product']), 'description' => 'Preview the matching edit page.'],
        ['label' => 'Sales shell', 'href' => route('vendor.sales'), 'description' => 'Open the seller reporting placeholder.'],
    ];
@endphp

<x-layouts::app :title="__('Create Product')">
    {{-- TODO: Replace this shell with the full product creation form, validation, and upload handling. --}}
    {{-- TODO: Add vendor-scoped category selection, stock input, and publish controls. --}}
    {{-- TODO: Include image previews and save feedback once storage support is available. --}}
    <x-placeholder-page
        eyebrow="Vendor form"
        title="Create product"
        description="This route gives the seller flow a real destination for product creation without introducing the actual persistence logic yet."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

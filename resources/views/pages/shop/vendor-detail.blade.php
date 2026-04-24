@php
    $sections = [
        ['label' => 'Identity', 'title' => 'Store hero and profile', 'description' => 'This storefront should eventually introduce the stall, its owner identity, and what shoppers can expect from the catalog.'],
        ['label' => 'Catalog', 'title' => 'Active product listing', 'description' => 'Approved and available products should appear here with category context, stock visibility, and quick product-detail links.'],
        ['label' => 'Suki', 'title' => 'Follow this vendor', 'description' => 'A future suki follow action should let customers keep this stall close for repeat browsing and favorites.'],
        ['label' => 'Support', 'title' => 'Vendor messaging shortcut', 'description' => 'Customers should be able to message the vendor directly from this page once storefront conversations are live.'],
    ];

    $notes = [
        'The finished storefront should feel like a real vendor destination, not just another product grid.',
        'Vendor identity, active catalog count, and follow actions should stay visible without overpowering the listings.',
        'Messaging should connect naturally from here so shoppers can ask questions before placing an order.',
    ];

    $links = [
        ['label' => 'Market stalls shell', 'href' => route('shop.vendors'), 'description' => 'Return to the vendor directory placeholder.'],
        ['label' => 'Storefront', 'href' => route('shop.home'), 'description' => 'Go back to the main customer storefront.'],
        ['label' => 'Favorites shell', 'href' => route('shop.favorites'), 'description' => 'Open the customer favorites placeholder.'],
    ];
@endphp

<x-layouts::app :title="__('Vendor Profile')">
    {{-- TODO: Replace this shell with a real vendor storefront, approved catalog listing, and storefront identity blocks. --}}
    {{-- TODO: Add suki follow actions, direct vendor messaging, and featured products once those flows are implemented. --}}
    {{-- TODO: Surface only approved vendor data here and keep the browsing path customer-friendly. --}}
    <x-placeholder-page
        eyebrow="Vendor storefront"
        title="Vendor profile"
        description="This page will show the vendor's full store identity, active product catalog, and a way to follow or message them directly."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

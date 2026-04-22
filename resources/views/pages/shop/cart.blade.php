@php
    $sections = [
        ['label' => 'Cart contents', 'title' => 'Selected products', 'description' => 'The real version of this page will later list all cart items, quantities, and vendor details for the shopper.'],
        ['label' => 'Rules', 'title' => 'Single-vendor guardrails', 'description' => 'This placeholder reserves space for the future one-vendor-per-cart guidance described in the README.'],
        ['label' => 'Pricing', 'title' => 'Running totals', 'description' => 'Order totals, subtotals, and checkout prep belong in this future cart view.'],
        ['label' => 'Actions', 'title' => 'Quantity updates and removal', 'description' => 'Line-item actions and validation feedback will eventually live here.'],
    ];

    $notes = [
        'The cart should become the bridge between storefront browsing and checkout.',
        'Quantity controls and vendor consistency messaging are the two most important future interactions here.',
        'Once checkout exists, this page should make it obvious how close the shopper is to placing the order.',
    ];

    $links = [
        ['label' => 'Checkout shell', 'href' => route('shop.checkout'), 'description' => 'Open the future checkout page.'],
        ['label' => 'Storefront', 'href' => route('shop.home'), 'description' => 'Return to browsing approved listings.'],
        ['label' => 'Orders shell', 'href' => route('shop.orders'), 'description' => 'Open the order history placeholder.'],
    ];
@endphp

<x-layouts::app :title="__('Cart')">
    {{-- TODO: Replace this shell with cart items, quantity controls, and single-vendor cart messaging. --}}
    {{-- TODO: Add order totals, remove-item actions, and a clear handoff into checkout. --}}
    {{-- TODO: Show vendor context so shoppers understand which stall they are buying from. --}}
    <x-placeholder-page
        eyebrow="Customer cart"
        title="Cart"
        description="This placeholder sets up the shopper cart route now so the header and dashboard can link into a full purchase flow later on."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

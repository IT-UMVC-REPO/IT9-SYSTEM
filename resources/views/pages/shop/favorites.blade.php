@php
    $sections = [
        ['label' => 'Following', 'title' => 'Saved vendors', 'description' => 'This page will later show the vendor stalls a customer follows through the suki system.'],
        ['label' => 'Discovery', 'title' => 'Recommended return visits', 'description' => 'A future version can highlight active listings from followed vendors here.'],
        ['label' => 'Updates', 'title' => 'Vendor activity signals', 'description' => 'New products or returning stock could eventually be surfaced in this favorites space.'],
        ['label' => 'Shortcuts', 'title' => 'Fast paths back to shopping', 'description' => 'Customers should later be able to jump back into storefronts they trust from this page.'],
    ];

    $notes = [
        'The final favorites page should feel like a relationship hub, not just a static bookmark list.',
        'Vendor activity and storefront shortcuts will likely matter more than generic counts here.',
        'This route now exists so the header heart icon has a real destination today.',
    ];

    $links = [
        ['label' => 'Storefront', 'href' => route('shop.home'), 'description' => 'Return to browsing approved listings.'],
        ['label' => 'Orders shell', 'href' => route('shop.orders'), 'description' => 'Open the customer order history page.'],
        ['label' => 'Messages inbox shell', 'href' => route('messages.inbox'), 'description' => 'Open the shared inbox placeholder.'],
    ];
@endphp

<x-layouts::app :title="__('Favourites')">
    {{-- TODO: Replace this shell with followed vendor cards, vendor activity, and quick storefront links. --}}
    {{-- TODO: Add the real suki follow system once favorites can be persisted. --}}
    {{-- TODO: Surface useful updates from followed stalls instead of leaving this page static. --}}
    <x-placeholder-page
        eyebrow="Customer favorites"
        title="Favourites"
        description="This placeholder gives the heart icon and the suki system a real home before the follow mechanics are implemented."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

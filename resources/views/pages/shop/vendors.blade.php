@php
    $sections = [
        ['label' => 'Directory', 'title' => 'Search by category', 'description' => 'Customers should eventually filter approved stalls by category, specialty, and what they usually shop for first.'],
        ['label' => 'Trust', 'title' => 'Vendor ratings', 'description' => 'Store quality signals, shopper feedback, and reliability cues should help people choose a stall confidently.'],
        ['label' => 'Discovery', 'title' => 'Stall highlights', 'description' => 'Featured vendors, market badges, and listing counts belong here to make the directory feel lively and easy to scan.'],
        ['label' => 'Loyalty', 'title' => 'Follow favorite vendors', 'description' => 'Customers should be able to keep their suki stalls close and jump back into their favorite storefronts quickly.'],
    ];

    $notes = [
        'Search and category filters will likely be the first thing shoppers use when the directory goes live.',
        'Ratings and marketplace trust signals should stay simple and readable instead of feeling like a crowded marketplace dashboard.',
        'Following a vendor should feel like a natural extension of the suki relationship, not a generic social action.',
    ];

    $links = [
        ['label' => 'Storefront', 'href' => route('shop.home'), 'description' => 'Return to the main customer storefront.'],
        ['label' => 'Favorites shell', 'href' => route('shop.favorites'), 'description' => 'Open the customer favorites placeholder.'],
        ['label' => 'Messages inbox', 'href' => route('messages.inbox'), 'description' => 'Preview the shared messages inbox.'],
    ];
@endphp

<x-layouts::app :title="__('Market Stalls')">
    {{-- TODO: Replace this shell with an approved-vendor directory, discovery filters, and trust signals. --}}
    {{-- TODO: Add category browsing, vendor ratings, and follow actions once storefront discovery is implemented. --}}
    {{-- TODO: Surface real vendor cards with identity, product counts, and direct paths into storefront detail pages. --}}
    <x-placeholder-page
        eyebrow="Vendor directory"
        title="Market stalls"
        description="This page will be where customers discover and browse all approved vendor storefronts across the marketplace."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

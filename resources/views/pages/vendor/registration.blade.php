@php
    $sections = [
        ['label' => 'Application', 'title' => 'Seller onboarding form', 'description' => 'Customers who want to become vendors will eventually fill out their store details and onboarding fields here.'],
        ['label' => 'Review', 'title' => 'Approval expectations', 'description' => 'This page can later explain what gets reviewed, how long it takes, and what happens after submission.'],
        ['label' => 'Requirements', 'title' => 'Store information and assets', 'description' => 'Business details, market identity, and any upload requirements can live here in the final flow.'],
        ['label' => 'Status', 'title' => 'Next steps after submit', 'description' => 'Customers should eventually see a pending-state explanation and approval follow-up guidance here.'],
    ];

    $notes = [
        'The real form should collect enough store identity information for admins to review confidently.',
        'Pending and rejected applicants will likely need clear guidance about what happens next.',
        'This route already exists so customer-facing navigation can point to seller onboarding now.',
    ];

    $links = [
        ['label' => 'Customer dashboard', 'href' => route('customer.dashboard'), 'description' => 'Return to the shopper portal home.'],
        ['label' => 'Browse storefront', 'href' => route('shop.home'), 'description' => 'Go back to the marketplace catalog.'],
        ['label' => 'Favorites shell', 'href' => route('shop.favorites'), 'description' => 'Open the customer favorites placeholder.'],
    ];
@endphp

<x-layouts::app :title="__('Vendor Registration')">
    {{-- TODO: Replace this shell with the customer-to-vendor onboarding form and submission flow. --}}
    {{-- TODO: Add store profile fields, review guidance, and any required uploads or acknowledgements. --}}
    {{-- TODO: Show pending-state messaging once vendor applications can actually be submitted. --}}
    <x-placeholder-page
        eyebrow="Seller onboarding"
        title="Vendor registration"
        description="This placeholder gives customers a real destination for the future seller onboarding flow without switching on the business logic ahead of time."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

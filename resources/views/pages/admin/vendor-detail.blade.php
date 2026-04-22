@php
    $sections = [
        ['label' => 'Profile', 'title' => 'Store identity review', 'description' => 'The vendor profile, store description, and owner details will eventually be reviewed on this screen.'],
        ['label' => 'Evidence', 'title' => 'Supporting materials', 'description' => 'Submitted documents, photos, and onboarding notes can slot into this future review layout.'],
        ['label' => 'Decision', 'title' => 'Approval and rejection controls', 'description' => 'Admins will later confirm or reject a vendor here, including a rejection reason when needed.'],
        ['label' => 'Snapshot', 'title' => 'Catalog and account context', 'description' => 'A short overview of linked products and account history can appear here later on.'],
    ];

    $notes = [
        'This page should become the single review surface for a vendor application.',
        'Approval decisions will likely need notification hooks and audit context.',
        'A read-only product snapshot would help admins understand the store before approving it.',
    ];

    $links = [
        ['label' => 'Back to vendor queue', 'href' => route('admin.vendors'), 'description' => 'Return to the vendor review list.'],
        ['label' => 'Admin dashboard', 'href' => route('admin.dashboard'), 'description' => 'Return to the admin portal home.'],
        ['label' => 'Order oversight shell', 'href' => route('admin.orders'), 'description' => 'Open the marketplace order monitoring page.'],
    ];
@endphp

<x-layouts::app :title="__('Vendor Review')">
    {{-- TODO: Replace this shell with the full vendor application profile, review notes, and decision controls. --}}
    {{-- TODO: Add approve and reject actions tied to status updates and vendor notifications. --}}
    {{-- TODO: Show any submitted store assets, documents, and account context in one place. --}}
    <x-placeholder-page
        eyebrow="Admin detail"
        title="Vendor review detail"
        description="This placeholder stands in for the single-vendor approval screen so the admin flow already has a navigable destination."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

@php
    $sections = [
        ['label' => 'Review queue', 'title' => 'Pending applications', 'description' => 'This placeholder will become the main list for vendor profiles waiting on approval or rejection.'],
        ['label' => 'Statuses', 'title' => 'Approved and rejected tabs', 'description' => 'Admins will later filter vendors by status, date submitted, and review outcome.'],
        ['label' => 'Actions', 'title' => 'Approval workflow', 'description' => 'Buttons for approval, rejection, and vendor notifications will eventually live in this workspace.'],
        ['label' => 'Drill-down', 'title' => 'Individual vendor review', 'description' => 'Each row should later connect to a detailed seller profile review screen.'],
    ];

    $notes = [
        'A real queue table will need vendor profile metadata, timestamps, and review actions.',
        'Status grouping and filters should make it easy for admins to triage applications quickly.',
        'Notifications and rejection reasons can be stitched into the review flow once those modules go live.',
    ];

    $links = [
        ['label' => 'Admin dashboard', 'href' => route('admin.dashboard'), 'description' => 'Return to the admin portal home.'],
        ['label' => 'Vendor detail shell', 'href' => route('admin.vendors.show', ['vendorReference' => 'sample-vendor']), 'description' => 'Preview the future vendor review page.'],
        ['label' => 'User management shell', 'href' => route('admin.users'), 'description' => 'Jump to the future account oversight page.'],
    ];
@endphp

<x-layouts::app :title="__('Vendor Approvals')">
    {{-- TODO: Replace this layout with a paginated vendor review table grouped by approval status. --}}
    {{-- TODO: Add approve and reject actions with required rejection notes and notification hooks. --}}
    {{-- TODO: Show store details, submitted documents, and timestamps for each vendor application. --}}
    <x-placeholder-page
        eyebrow="Admin review"
        title="Vendor approvals"
        description="This dummy page reserves the space for the admin vendor approval queue described in the README, without adding business logic yet."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

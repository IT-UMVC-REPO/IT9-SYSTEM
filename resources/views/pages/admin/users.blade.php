@php
    $sections = [
        ['label' => 'Directory', 'title' => 'Marketplace accounts', 'description' => 'This page will later list customers, vendors, and admins with search and filtering controls.'],
        ['label' => 'State', 'title' => 'Activation controls', 'description' => 'Account enablement, deactivation, and trust-state indicators belong in this future view.'],
        ['label' => 'Roles', 'title' => 'Role awareness', 'description' => 'The eventual table should make it easy to distinguish customers, approved vendors, and admins.'],
        ['label' => 'Safety', 'title' => 'Account health', 'description' => 'Flags for suspicious activity or support intervention can be surfaced here later.'],
    ];

    $notes = [
        'Search, role filters, and account-state toggles are the key missing interactions for this screen.',
        'A future version should make it clear when a vendor is pending or rejected but still effectively a customer.',
        'Operational notes and audit trails can slot into a detail drawer or follow-up page later on.',
    ];

    $links = [
        ['label' => 'Admin dashboard', 'href' => route('admin.dashboard'), 'description' => 'Return to the admin portal home.'],
        ['label' => 'Vendor review shell', 'href' => route('admin.vendors'), 'description' => 'Open the vendor approvals workspace.'],
        ['label' => 'Order oversight shell', 'href' => route('admin.orders'), 'description' => 'Open the marketplace order monitor.'],
    ];
@endphp

<x-layouts::app :title="__('User Management')">
    {{-- TODO: Replace this placeholder with a searchable user table that supports role and status filtering. --}}
    {{-- TODO: Add account activation controls, moderation notes, and marketplace role context. --}}
    {{-- TODO: Surface customer, vendor, and admin account summaries with pagination. --}}
    <x-placeholder-page
        eyebrow="Admin accounts"
        title="User management"
        description="This page gives the admin portal a real destination for account oversight while the underlying management tools are still pending."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

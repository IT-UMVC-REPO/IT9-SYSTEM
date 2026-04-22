@php
    $sections = [
        ['label' => 'Operations', 'title' => 'Marketplace health', 'description' => 'Admin-facing charts, approval counts, and order signals will live here once backend data is connected.'],
        ['label' => 'Queue', 'title' => 'Vendor approvals', 'description' => 'Pending seller reviews, rejection reasons, and approval actions will eventually anchor this dashboard.'],
        ['label' => 'Accounts', 'title' => 'User oversight', 'description' => 'Future widgets can summarize active customers, vendors, and accounts needing intervention.'],
        ['label' => 'Orders', 'title' => 'Platform-wide visibility', 'description' => 'This area is reserved for escalations, unusual order spikes, and marketplace exceptions.'],
    ];

    $notes = [
        'Approval queue snapshots will show pending, approved, and rejected vendor counts here.',
        'User moderation and marketplace alerts can be surfaced without leaving the dashboard.',
        'Global order monitoring will later help admins spot issues before they spread across the platform.',
    ];

    $links = [
        ['label' => 'Vendor review list', 'href' => route('admin.vendors'), 'description' => 'Placeholder for seller approval management.'],
        ['label' => 'User management shell', 'href' => route('admin.users'), 'description' => 'Placeholder for account oversight.'],
        ['label' => 'Order oversight shell', 'href' => route('admin.orders'), 'description' => 'Placeholder for platform-wide order monitoring.'],
    ];
@endphp

<x-layouts::app :title="__('Admin Dashboard')">
    {{-- TODO: Replace these planning cards with approval queue totals, account summaries, and operational alerts. --}}
    {{-- TODO: Add escalation widgets for disputes, suspicious activity, and marketplace-wide service issues. --}}
    {{-- TODO: Surface deep links into vendor reviews, user audits, and order exceptions. --}}
    <x-placeholder-page
        eyebrow="Admin portal"
        title="Admin dashboard"
        description="Admins now have a dedicated portal home that matches the oversight role described in the README. The structure is ready for real moderation and reporting data once those features are implemented."
        :sections="$sections"
        :notes="$notes"
        :links="$links"
    />
</x-layouts::app>

<x-layouts::app :title="__('Vendor Review')">
    {{-- TODO: Replace this shell with the full vendor application profile, review notes, and decision controls. --}}
    {{-- TODO: Add approve and reject actions tied to status updates and vendor notifications. --}}
    {{-- TODO: Show any submitted store assets, documents, and account context in one place. --}}
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @livewire('admin-vendor-review', ['vendor' => $vendorReference])
            </div>
        </div>
    <x-placeholder-page title="Vendor review detail" />
</x-layouts::app>

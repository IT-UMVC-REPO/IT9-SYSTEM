<x-layouts::app :title="__('Vendor Approvals')">
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <h1 class="text-2xl font-bold mb-6 text-gray-800">{{ __('Manage Vendor Applications') }}</h1>
            
            {{-- Task B1: Replace placeholder with the livewire table --}}
            @livewire('admin-vendor-list')
        </div>
    </div>
</x-layouts::app>
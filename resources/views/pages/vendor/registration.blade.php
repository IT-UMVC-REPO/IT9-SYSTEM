<x-layouts::app :title="__('Vendor Registration')">
    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4">
            <div class="bg-white p-8 shadow rounded-lg border">
                
                {{-- Task A2: Check if user already has a record (Pending Message) --}}
                @if(auth()->user()->vendorProfile)
                    <div class="text-center py-6">
                        <div class="text-indigo-600 mb-4">
                            <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900">Application Pending</h1>
                        <p class="mt-2 text-gray-600">Your application is currently being reviewed. You will be notified once you are approved.</p>
                    </div>
                @else
                    <h1 class="text-2xl font-bold mb-6">Become a Vendor</h1>
                    {{-- Task A1: The Onboarding Form --}}
                    @livewire('vendor-registration-form')
                @endif

            </div>
        </div>
    </div>
</x-layouts::app>
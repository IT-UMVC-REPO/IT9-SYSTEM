<div class="p-6 bg-white shadow rounded-lg">
    <div class="mb-6 border-b pb-4">
        <h1 class="text-2xl font-bold">Review Vendor: {{ $vendor->store_name }}</h1>
        <p class="text-gray-600">Owner: {{ $vendor->user->name }}</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <h3 class="font-bold text-gray-700">Store Description</h3>
            <p class="mt-2 text-gray-600">{{ $vendor->store_description }}</p>
            
            <h3 class="font-bold text-gray-700 mt-6">Submitted On</h3>
            <p class="text-gray-600">{{ $vendor->created_at->format('M d, Y') }}</p>
        </div>

        <div>
            <h3 class="font-bold text-gray-700 mb-2">Store Image</h3>
            <img src="{{ asset('storage/' . $vendor->store_image) }}" class="w-full rounded border shadow-sm">
        </div>
    </div>

    <div class="mt-10 pt-6 border-t">
        <div class="flex flex-col gap-4">
            {{-- Approve Button (Task B3) --}}
            <button wire:click="approve" class="bg-green-600 text-white font-bold py-3 rounded-lg hover:bg-green-700 transition">
                Approve Application
            </button>

            <div class="border p-4 rounded-lg bg-gray-50">
                <h4 class="font-bold text-red-600 mb-2">Reject Application</h4>
                <textarea wire:model="rejection_reason" placeholder="Reason for rejection (Required)..." class="w-full border p-2 rounded"></textarea>
                @error('rejection_reason') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                
                {{-- Reject Button (Task B4) --}}
                <button wire:click="reject" class="mt-2 w-full bg-red-600 text-white font-bold py-2 rounded hover:bg-red-700">
                    Submit Rejection
                </button>
            </div>
        </div>
    </div>
</div>
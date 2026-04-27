<form wire:submit.prevent="submit" class="space-y-6">
    <div>
        <label class="block text-sm font-medium text-gray-700">Store Name</label>
        <input type="text" wire:model="store_name" class="mt-1 block w-full border border-gray-300 rounded-md p-2">
        @error('store_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Store Description</label>
        <textarea wire:model="store_description" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md p-2"></textarea>
        @error('store_description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Store Image</label>
        <input type="file" wire:model="store_image" class="mt-1 block w-full text-sm text-gray-500">
        <div wire:loading wire:target="store_image" class="text-xs text-blue-500 mt-1">Uploading...</div>
        @error('store_image') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
    </div>

    <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-md hover:bg-indigo-700">
        Submit Application
    </button>
</form>
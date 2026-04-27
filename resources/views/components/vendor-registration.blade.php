<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<form wire:submit.prevent="submit" class="space-y-6">
    {{-- Store Name --}}
    <div>
        <label for="store_name" class="block text-sm font-medium text-gray-700">Store Name</label>
        <input type="text" id="store_name" wire:model="store_name" 
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border p-2">
        @error('store_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
    </div>

    {{-- Store Description --}}
    <div>
        <label for="store_description" class="block text-sm font-medium text-gray-700">Store Description</label>
        <textarea id="store_description" wire:model="store_description" rows="4"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 border p-2"></textarea>
        @error('store_description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
    </div>

    {{-- Store Image --}}
    <div>
        <label for="store_image" class="block text-sm font-medium text-gray-700">Store Logo / Image</label>
        <input type="file" id="store_image" wire:model="store_image" 
            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
        
        {{-- Loading indicator for large images --}}
        <div wire:loading wire:target="store_image" class="text-xs text-gray-500 mt-1">Uploading image...</div>
        
        @error('store_image') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        
        {{-- Image Preview --}}
        @if ($store_image)
            <div class="mt-4">
                <p class="text-xs text-gray-400 mb-1">Preview:</p>
                <img src="{{ $store_image->temporaryUrl() }}" class="h-32 w-32 object-cover rounded-md border">
            </div>
        @endif
    </div>

    {{-- Submit Button --}}
    <div class="pt-4">
        <button type="submit" wire:loading.attr="disabled"
            class="w-full inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50">
            <span wire:loading.remove wire:target="submit">Submit Application</span>
            <span wire:loading wire:target="submit">Submitting...</span>
        </button>
    </div>
</form>
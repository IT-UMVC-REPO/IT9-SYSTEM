<?php

use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public int $productId;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
    }

    public function delete(): void
    {
        $this->dispatch('delete-product-listing-confirmed', productId: $this->productId);
    }
}; ?>

<flux:modal name="delete-product-listing" class="max-w-lg">
    <div class="space-y-6 p-6">
        <div>
            <flux:heading size="lg">{{ __('Delete this listing?') }}</flux:heading>
            <flux:subheading>
                {{ __('This action removes the product from your catalog immediately and cannot be undone.') }}
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" x-on:click="$dispatch('delete-product-listing-confirmed', { productId: {{ $productId }} })">
                {{ __('Delete listing') }}
            </flux:button>
        </div>
    </div>
</flux:modal>

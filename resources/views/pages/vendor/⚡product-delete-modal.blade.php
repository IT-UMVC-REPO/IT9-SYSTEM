<?php

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

<x-confirmation-modal
    name="delete-product-listing"
    :heading="__('Delete this listing?')"
    :body="__('This action removes the product from your catalog immediately and cannot be undone.')"
    :confirm-label="__('Delete listing')"
    confirm-action="delete"
    max-width="max-w-md"
/>

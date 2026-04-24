<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="space-y-6">
    <div class="flex items-start gap-4">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
            <i class="fa-solid fa-triangle-exclamation text-lg"></i>
        </span>

        <div>
            <flux:heading>{{ __('Delete account') }}</flux:heading>
            <flux:subheading>{{ __('Delete your account and all of its resources') }}</flux:subheading>
        </div>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" data-test="delete-user-button">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <livewire:pages::settings.delete-user-modal />
</section>

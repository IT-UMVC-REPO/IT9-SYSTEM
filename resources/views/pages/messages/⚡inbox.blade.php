<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Messages')] class extends Component {};
?>

<div class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Messaging') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Messages inbox') }}</h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Check the latest buyer and vendor conversations, spot unread replies quickly, and jump straight into the thread that needs your attention.') }}
        </p>
    </section>

    <livewire:messages.conversation-sidebar :key="'messages-inbox-sidebar'" />
</div>

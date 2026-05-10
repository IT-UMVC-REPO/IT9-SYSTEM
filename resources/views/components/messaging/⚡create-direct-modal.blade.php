<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showDirectModal = false;

    public string $search = '';

    #[Computed]
    public function searchResults(): Collection
    {
        if (trim($this->search) === '') {
            return collect();
        }

        return User::query()
            ->whereKeyNot(auth()->id())
            ->where('name', 'like', '%'.trim($this->search).'%')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'profile_image']);
    }

    public function openConversation(int $userId): void
    {
        $user = User::query()
            ->whereKeyNot(auth()->id())
            ->findOrFail($userId);

        $this->redirect(route('messages.conversation', ['conversationReference' => $user->id]), navigate: true);
    }
};
?>

<div>
    <button type="button" class="brand-button-secondary active:scale-[0.96]" x-on:click="$wire.showDirectModal = true">
        <i class="fa-solid fa-user-plus text-xs"></i>
        {{ __('Add contact') }}
    </button>

    <flux:modal wire:model="showDirectModal" class="w-full max-w-lg">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('New direct message') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Search for a buyer, vendor, or admin to start a one-on-one thread.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Search people') }}</flux:label>
                <flux:input wire:model.live.debounce.250ms="search" :placeholder="__('Search by name')" autofocus />
            </flux:field>

            @if ($this->searchResults->isNotEmpty())
                <div class="grid gap-2 rounded-2xl border border-stone-200 bg-stone-50 p-2 dark:border-white/10 dark:bg-white/5">
                    @foreach ($this->searchResults as $user)
                        <button type="button" wire:click="openConversation({{ $user->id }})" wire:key="direct-search-{{ $user->id }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-left transition hover:bg-white dark:hover:bg-white/10">
                            <x-user-avatar :user="$user" size="sm" />
                            <span class="text-sm font-semibold text-neutral-800 dark:text-zinc-100">{{ $user->name }}</span>
                        </button>
                    @endforeach
                </div>
            @elseif (trim($search) !== '')
                <div class="rounded-2xl border border-dashed border-stone-200 px-4 py-6 text-center text-sm text-neutral-500 dark:border-white/10 dark:text-zinc-400">
                    {{ __('No people matched that search.') }}
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button type="button" variant="ghost" x-on:click="$wire.showDirectModal = false">{{ __('Cancel') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

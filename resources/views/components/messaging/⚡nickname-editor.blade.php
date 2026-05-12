<?php

use App\Models\User;
use App\Models\UserNickname;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $targetUserId;

    public ?string $profileRoute = null;

    public string $nickname = '';

    public string $draftNickname = '';

    public bool $isEditing = false;

    public function mount(int $targetUserId): void
    {
        $this->targetUserId = $targetUserId;
        $this->nickname = (string) (UserNickname::query()
            ->where('owner_id', auth()->id())
            ->where('target_id', $targetUserId)
            ->value('nickname') ?? '');
        $this->draftNickname = $this->nickname;
    }

    #[Computed]
    public function targetUser(): User
    {
        return User::query()->findOrFail($this->targetUserId);
    }

    public function edit(): void
    {
        $this->draftNickname = $this->nickname;
        $this->isEditing = true;
    }

    public function cancel(): void
    {
        $this->draftNickname = $this->nickname;
        $this->isEditing = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'draftNickname' => ['nullable', 'string', 'max:80'],
        ]);

        $nickname = trim($validated['draftNickname'] ?? '');

        if ($nickname === '') {
            $this->remove();

            return;
        }

        UserNickname::query()->updateOrCreate(
            [
                'owner_id' => auth()->id(),
                'target_id' => $this->targetUserId,
            ],
            [
                'nickname' => $nickname,
            ],
        );

        $this->nickname = $nickname;
        $this->draftNickname = $nickname;
        $this->isEditing = false;

        $this->dispatch('nickname-updated');
    }

    public function remove(): void
    {
        UserNickname::query()
            ->where('owner_id', auth()->id())
            ->where('target_id', $this->targetUserId)
            ->delete();

        $this->nickname = '';
        $this->draftNickname = '';
        $this->isEditing = false;

        $this->dispatch('nickname-updated');
    }
};
?>

<div class="min-w-0">
    @if (! $isEditing)
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            @if ($profileRoute)
                <a href="{{ $profileRoute }}" wire:navigate class="brand-hover-text truncate transition hover:underline">
                    {{ filled($nickname) ? $nickname : $this->targetUser->name }}
                </a>
            @else
                <span class="truncate">{{ filled($nickname) ? $nickname : $this->targetUser->name }}</span>
            @endif

            @if (filled($nickname))
                <span class="text-sm font-medium text-neutral-400 dark:text-zinc-500">({{ $this->targetUser->name }})</span>
            @endif

            <button
                type="button"
                wire:click="edit"
                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-sm text-neutral-400 transition hover:bg-stone-100 hover:text-[var(--brand-700)] dark:text-zinc-500 dark:hover:bg-white/10 dark:hover:text-[var(--brand-300)]"
                title="{{ __('Edit nickname') }}"
            >
                <i class="fa-solid fa-pencil text-xs"></i>
            </button>
        </div>
    @else
        <div class="flex max-w-xl flex-wrap items-center gap-2">
            <flux:input wire:model="draftNickname" :placeholder="$this->targetUser->name" class="min-w-48 flex-1" />
            <flux:button type="button" variant="primary" wire:click="save" size="sm">{{ __('Save') }}</flux:button>
            <flux:button type="button" variant="ghost" wire:click="cancel" size="sm">{{ __('Cancel') }}</flux:button>

            @if (filled($nickname))
                <button type="button" wire:click="remove" class="text-xs font-semibold text-rose-600 transition hover:text-rose-700 dark:text-rose-400">
                    {{ __('Remove nickname') }}
                </button>
            @endif

            @error('draftNickname')
                <p class="basis-full text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    @endif
</div>

<?php

use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showModal = false;

    public string $groupName = '';

    public string $search = '';

    /** @var array<int, int> */
    public array $selectedUserIds = [];

    #[Computed]
    public function searchResults(): Collection
    {
        if (trim($this->search) === '') {
            return collect();
        }

        return User::query()
            ->whereKeyNot(auth()->id())
            ->whereNotIn('id', $this->selectedUserIds)
            ->where('name', 'like', '%'.trim($this->search).'%')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'profile_image']);
    }

    #[Computed]
    public function selectedUsers(): Collection
    {
        return User::query()
            ->whereIn('id', $this->selectedUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'profile_image']);
    }

    public function addMember(int $userId): void
    {
        if ($userId === auth()->id() || in_array($userId, $this->selectedUserIds, true)) {
            return;
        }

        $this->selectedUserIds[] = $userId;
        $this->search = '';
        unset($this->searchResults);
        unset($this->selectedUsers);
    }

    public function removeMember(int $userId): void
    {
        $this->selectedUserIds = array_values(array_filter(
            $this->selectedUserIds,
            fn (int $selectedUserId): bool => $selectedUserId !== $userId,
        ));

        unset($this->selectedUsers);
    }

    public function createGroup(): void
    {
        $validated = $this->validate([
            'groupName' => ['nullable', 'string', 'max:120'],
            'selectedUserIds' => ['array', 'min:1'],
            'selectedUserIds.*' => ['integer', 'exists:users,id'],
        ]);

        $group = DB::transaction(function () use ($validated): ConversationGroup {
            $group = ConversationGroup::query()->create([
                'name' => filled($validated['groupName'] ?? null) ? trim($validated['groupName']) : null,
                'created_by' => auth()->id(),
            ]);

            ConversationGroupMember::query()->create([
                'group_id' => $group->getKey(),
                'user_id' => auth()->id(),
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            foreach (array_unique($validated['selectedUserIds']) as $userId) {
                if ((int) $userId === auth()->id()) {
                    continue;
                }

                ConversationGroupMember::query()->create([
                    'group_id' => $group->getKey(),
                    'user_id' => (int) $userId,
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }

            return $group;
        });

        $this->redirect(route('messages.group', ['groupId' => $group->getKey()]), navigate: true);
    }
};
?>

<div>
    <button type="button" class="brand-button-primary" x-on:click="$wire.showModal = true">
        <i class="fa-solid fa-user-group text-xs"></i>
        {{ __('New group') }}
    </button>

    <flux:modal wire:model="showModal" class="w-full max-w-xl">
        <form wire:submit="createGroup" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Create group') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Start a shared conversation with buyers, vendors, or admins.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Group name') }}</flux:label>
                <flux:input wire:model="groupName" :placeholder="__('Optional')" />
                <flux:error name="groupName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Add members') }}</flux:label>
                <flux:input wire:model.live.debounce.250ms="search" :placeholder="__('Search by name')" />
                <flux:error name="selectedUserIds" />
            </flux:field>

            @if ($this->searchResults->isNotEmpty())
                <div class="grid gap-2 rounded-2xl border border-stone-200 bg-stone-50 p-2 dark:border-white/10 dark:bg-white/5">
                    @foreach ($this->searchResults as $user)
                        <button type="button" wire:click="addMember({{ $user->id }})" wire:key="group-search-{{ $user->id }}" class="flex items-center gap-3 rounded-xl px-3 py-2 text-left transition hover:bg-white dark:hover:bg-white/10">
                            <x-user-avatar :user="$user" size="sm" />
                            <span class="text-sm font-semibold text-neutral-800 dark:text-zinc-100">{{ $user->name }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($this->selectedUsers->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->selectedUsers as $user)
                        <span wire:key="selected-group-user-{{ $user->id }}" class="inline-flex items-center gap-2 rounded-full bg-stone-100 px-3 py-1.5 text-sm text-neutral-700 dark:bg-zinc-800 dark:text-zinc-100">
                            {{ $user->name }}
                            <button type="button" wire:click="removeMember({{ $user->id }})" class="text-neutral-400 hover:text-rose-600">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" x-on:click="$wire.showModal = false">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="createGroup">
                    <span wire:loading.remove wire:target="createGroup">{{ __('Create group') }}</span>
                    <span wire:loading wire:target="createGroup">{{ __('Creating...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>

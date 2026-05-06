<?php

use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use Livewire\Component;

new class extends Component
{
    public ?int $callId = null;

    public ?int $callerId = null;

    public ?string $callerName = null;

    public ?int $groupId = null;

    public ?string $groupName = null;

    public bool $isGroupCall = false;

    public bool $isVisible = false;

    public ?int $currentConversationUserId = null;

    public ?int $currentGroupId = null;

    public ?string $declineEndpoint = null;

    public function mount(?int $currentConversationUserId = null): void
    {
        $routeReference = request()->routeIs('messages.conversation')
            ? request()->route('conversationReference')
            : null;

        $this->currentConversationUserId = $currentConversationUserId ?? (
            is_numeric($routeReference) ? (int) $routeReference : null
        );
        $groupReference = request()->routeIs('messages.group') ? request()->route('groupId') : null;
        $this->currentGroupId = is_numeric($groupReference) ? (int) $groupReference : null;
    }

    public function getListeners(): array
    {
        if (! auth()->check()) {
            return [];
        }

        $listeners = [
            'echo-private:calls.'.auth()->id().',.VideoCallInitiated' => 'showDirectCall',
        ];

        ConversationGroupMember::query()
            ->where('user_id', auth()->id())
            ->pluck('group_id')
            ->each(function (int $groupId) use (&$listeners): void {
                $listeners['echo-private:group.'.$groupId.',.GroupCallInitiated'] = 'showGroupCall';
            });

        return $listeners;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function showDirectCall(array $event): void
    {
        if ($this->isVisible || (int) ($event['caller_id'] ?? 0) === auth()->id()) {
            return;
        }

        if ($this->currentConversationUserId === (int) ($event['caller_id'] ?? 0)) {
            return;
        }

        $this->callId = (int) $event['call_id'];
        $this->callerId = (int) $event['caller_id'];
        $this->callerName = (string) ($event['caller_name'] ?? __('Someone'));
        $this->groupId = null;
        $this->groupName = null;
        $this->isGroupCall = false;
        $this->isVisible = true;
        $this->declineEndpoint = route('calls.decline', ['call' => $this->callId]);

        $this->dispatch('livewire-call-incoming');
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function showGroupCall(array $event): void
    {
        if ($this->isVisible || (int) ($event['caller_id'] ?? 0) === auth()->id()) {
            return;
        }

        $groupId = (int) ($event['group_id'] ?? 0);

        if ($this->currentGroupId === $groupId) {
            return;
        }

        $group = ConversationGroup::query()
            ->with('memberUsers:id,name')
            ->find($groupId);

        if ($group === null) {
            return;
        }

        $this->callId = (int) $event['call_id'];
        $this->callerId = (int) $event['caller_id'];
        $this->callerName = (string) ($event['caller_name'] ?? __('Someone'));
        $this->groupId = $group->getKey();
        $this->groupName = $group->displayName((int) auth()->id());
        $this->isGroupCall = true;
        $this->isVisible = true;
        $this->declineEndpoint = null;

        $this->dispatch('livewire-call-incoming');
    }

    public function accept(): void
    {
        if (! $this->isVisible || $this->callId === null) {
            return;
        }

        if ($this->isGroupCall && $this->groupId !== null) {
            $targetUrl = route('messages.group', [
                'groupId' => $this->groupId,
                'incoming_call' => 1,
                'call_id' => $this->callId,
            ]);
        } else {
            $targetUrl = route('messages.conversation', [
                'conversationReference' => $this->callerId,
                'incoming_call' => 1,
                'call_id' => $this->callId,
            ]);
        }

        $this->dismiss();
        $this->redirect($targetUrl, navigate: true);
    }

    public function dismiss(): void
    {
        $this->reset(['callId', 'callerId', 'callerName', 'groupId', 'groupName', 'declineEndpoint']);
        $this->isGroupCall = false;
        $this->isVisible = false;

        $this->dispatch('livewire-call-dismissed');
    }

};
?>

<div
    x-data="{
        dismissTimer: null,
        scheduleDismiss() {
            window.clearTimeout(this.dismissTimer);
            window.sukiRingtone?.start();
            this.dismissTimer = window.setTimeout(() => {
                window.sukiRingtone?.stop();
                $wire.dismiss();
            }, 45000);
        },
        async decline() {
            window.clearTimeout(this.dismissTimer);
            window.sukiRingtone?.stop();

            const declineUrl = $wire.declineEndpoint;

            if (declineUrl) {
                await fetch(declineUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                }).catch(() => {});
            }

            $wire.dismiss();
        },
    }"
    x-on:livewire-call-incoming.window="scheduleDismiss()"
    x-on:livewire-call-dismissed.window="window.clearTimeout(dismissTimer); window.sukiRingtone?.stop()"
    wire:ignore.self
>
    <div
        x-cloak
        x-show="$wire.isVisible"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="-translate-y-4 opacity-0"
        class="fixed left-1/2 top-4 z-[9999] w-[min(28rem,calc(100vw-2rem))] -translate-x-1/2"
    >
        <section class="brand-panel rounded-3xl p-4 shadow-2xl shadow-stone-950/20 dark:shadow-black/40">
            <div class="flex items-start gap-4">
                <span class="mt-1 flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[var(--brand-100)] text-[var(--brand-700)] dark:bg-[color:color-mix(in_oklab,var(--brand-500),transparent_82%)] dark:text-[var(--brand-300)]">
                    <span class="h-3 w-3 animate-pulse rounded-full bg-[var(--brand-600)]"></span>
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">
                        {{ __('Incoming video call') }}
                    </p>
                    <h2 class="mt-1 truncate text-base font-semibold text-neutral-900 dark:text-zinc-100">
                        {{ $isGroupCall ? $groupName : $callerName }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-zinc-400">
                        {{ $isGroupCall ? __(':name started a group call.', ['name' => $callerName]) : __(':name is calling you.', ['name' => $callerName]) }}
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="accept" x-on:click="window.sukiRingtone?.stop()" class="brand-button-primary px-4 py-2">
                            <i class="fa-solid fa-phone text-xs"></i>
                            {{ __('Accept') }}
                        </button>
                        <button type="button" x-on:click="decline()" class="brand-button-secondary px-4 py-2">
                            <i class="fa-solid fa-phone-slash text-xs"></i>
                            {{ __('Decline') }}
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $activeConversationUserId = null;

    #[Computed]
    public function conversations(): Collection
    {
        return Message::query()
            ->where(function ($query): void {
                $query
                    ->where('sender_id', auth()->id())
                    ->orWhere('receiver_id', auth()->id());
            })
            ->with([
                'sender:id,name,profile_image',
                'receiver:id,name,profile_image',
                'order:id,order_status',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn (Message $message): string => Message::conversationKey(
                $message->sender_id === auth()->id() ? $message->receiver_id : $message->sender_id,
            ))
            ->map(fn (Collection $messages): Message => $messages->first())
            ->values();
    }

    public function otherParticipant(Message $message): User
    {
        return $message->sender_id === auth()->id()
            ? $message->receiver
            : $message->sender;
    }
};
?>

<div wire:poll.15s class="contents">
    @if ($this->conversations->isNotEmpty())
        <section class="brand-panel overflow-hidden p-3 sm:p-4">
            <div class="space-y-2">
                @foreach ($this->conversations as $message)
                    @php($otherUser = $this->otherParticipant($message))
                    @php($isActiveConversation = $activeConversationUserId === $otherUser->getKey())

                    <a
                        href="{{ route('messages.conversation', ['conversationReference' => $otherUser->getKey()]) }}"
                        wire:key="message-thread-{{ $otherUser->getKey() }}"
                        wire:navigate
                        @if ($isActiveConversation) aria-current="page" @endif
                        @class([
                            'flex items-start gap-4 rounded-[1.5rem] border px-4 py-4 transition',
                            'border-[var(--brand-200)] bg-[color:color-mix(in_oklab,var(--brand-50),white_30%)] dark:border-[var(--brand-500)] dark:bg-zinc-800/90' => $isActiveConversation,
                            'border-transparent hover:border-stone-200 hover:bg-stone-50/70 dark:hover:border-white/10 dark:hover:bg-white/5' => ! $isActiveConversation,
                        ])
                    >
                        <x-user-avatar :user="$otherUser" size="md" />

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-base font-semibold text-neutral-900 dark:text-zinc-100">{{ $otherUser->name }}</p>
                                    <p @class([
                                        'mt-1 truncate text-sm',
                                        'font-semibold text-neutral-900 dark:text-zinc-100' => ! $message->is_read && $message->receiver_id === auth()->id(),
                                        'text-neutral-500 dark:text-zinc-400' => $message->is_read || $message->receiver_id !== auth()->id(),
                                    ])>
                                        {{ Str::limit($message->content, 60) }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    @if (! $message->is_read && $message->receiver_id === auth()->id())
                                        <span class="inline-flex h-2.5 w-2.5 rounded-full" style="background-color: var(--brand-600);"></span>
                                    @endif

                                    <span class="text-xs font-medium text-neutral-400 dark:text-zinc-500">{{ $message->timeAgo() }}</span>
                                </div>
                            </div>

                            @if ($message->order !== null)
                                <p class="mt-2 text-xs font-medium text-neutral-400 dark:text-zinc-500">
                                    {{ __('Order context: :status', ['status' => Str::headline($message->order->order_status->value)]) }}
                                </p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @else
        <section class="brand-panel px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-neutral-600 dark:text-zinc-300" style="background-color: color-mix(in oklab, var(--brand-50) 72%, white 28%);">
                <i class="fa-regular fa-comments text-xl"></i>
            </span>
            <h2 class="brand-serif mt-5 text-3xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('No conversations yet') }}</h2>
            <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                {{ __('Start from a product or vendor page when you are ready to message a stall.') }}
            </p>
        </section>
    @endif
</div>

<?php

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Messages')] class extends Component
{
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

<div wire:poll.30s class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4">
        <span class="brand-kicker">{{ __('Messaging') }}</span>
        <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Messages inbox') }}</h1>
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ __('Check the latest buyer and vendor conversations, spot unread replies quickly, and jump straight into the thread that needs your attention.') }}
        </p>
    </section>

    @if ($this->conversations->isNotEmpty())
        <section class="brand-panel overflow-hidden p-3 sm:p-4">
            <div class="space-y-2">
                @foreach ($this->conversations as $message)
                    @php($otherUser = $this->otherParticipant($message))
                    <a
                        href="{{ route('messages.conversation', ['conversationReference' => $otherUser->id]) }}"
                        wire:key="message-thread-{{ $message->id }}"
                        wire:navigate
                        class="flex items-start gap-4 rounded-[1.5rem] border border-transparent px-4 py-4 transition hover:border-stone-200 hover:bg-stone-50/70 dark:hover:border-white/10 dark:hover:bg-white/5"
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

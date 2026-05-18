<div wire:poll.visible.15s class="mx-auto flex max-w-[1500px] flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="brand-kicker">{{ __('Support inbox') }}</p>
            <h1 class="brand-serif mt-4 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Contact messages') }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                {{ __('Review customer, vendor, rider, report, and billing inquiries sent through the public contact form.') }}
            </p>
        </div>
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="brand-button-secondary active:scale-[0.96]">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            {{ __('Dashboard') }}
        </a>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(24rem,0.45fr)]">
        <div class="brand-panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm dark:divide-white/10">
                    <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:bg-zinc-800/70 dark:text-zinc-500">
                        <tr>
                            <th class="px-5 py-4">{{ __('Name') }}</th>
                            <th class="px-5 py-4">{{ __('Email') }}</th>
                            <th class="px-5 py-4">{{ __('Subject') }}</th>
                            <th class="px-5 py-4">{{ __('Date Received') }}</th>
                            <th class="px-5 py-4">{{ __('Status') }}</th>
                            <th class="px-5 py-4">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 dark:divide-white/10">
                        @forelse ($messages as $contactMessage)
                            <tr
                                wire:key="contact-message-{{ $contactMessage->getKey() }}"
                                class="{{ $contactMessage->is_read ? 'bg-white dark:bg-zinc-900' : 'border-l-4 border-l-emerald-500 bg-emerald-50/50 font-semibold dark:bg-emerald-500/10' }}"
                            >
                                <td class="px-5 py-4 text-neutral-900 dark:text-zinc-100">{{ $contactMessage->name }}</td>
                                <td class="px-5 py-4 text-neutral-600 dark:text-zinc-300">{{ $contactMessage->email }}</td>
                                <td class="px-5 py-4 text-neutral-600 dark:text-zinc-300">{{ $contactMessage->subjectLabel() }}</td>
                                <td class="px-5 py-4 text-neutral-500 dark:text-zinc-400">{{ $contactMessage->created_at?->format('M j, Y g:i A') }}</td>
                                <td class="px-5 py-4">
                                    <span class="{{ $contactMessage->is_read ? 'bg-stone-100 text-neutral-600 dark:bg-zinc-800 dark:text-zinc-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' }} inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.08em]">
                                        {{ $contactMessage->is_read ? __('Read') : __('Unread') }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" wire:click="selectMessage({{ $contactMessage->getKey() }})" class="brand-button-secondary px-3 py-2 text-xs active:scale-[0.96]">
                                            {{ __('View') }}
                                        </button>
                                        @if (! $contactMessage->is_read)
                                            <button type="button" wire:click="markAsRead({{ $contactMessage->getKey() }})" class="brand-button-primary px-3 py-2 text-xs active:scale-[0.96]">
                                                {{ __('Mark as Read') }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-sm text-neutral-500 dark:text-zinc-400">
                                    {{ __('No contact messages yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-stone-200 p-4 dark:border-white/10">
                {{ $messages->links('layouts.app.livewire-paginate') }}
            </div>
        </div>

        <aside class="brand-panel h-fit p-6 xl:sticky xl:top-24">
            @if ($this->selectedMessage)
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400 dark:text-zinc-500">{{ __('Selected message') }}</p>
                <h2 class="mt-3 text-xl font-bold text-neutral-900 dark:text-zinc-100">{{ $this->selectedMessage->subjectLabel() }}</h2>
                <dl class="mt-5 space-y-3 text-sm">
                    <div>
                        <dt class="font-semibold text-neutral-900 dark:text-zinc-100">{{ __('From') }}</dt>
                        <dd class="mt-1 text-neutral-500 dark:text-zinc-400">{{ $this->selectedMessage->name }} &lt;{{ $this->selectedMessage->email }}&gt;</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Phone') }}</dt>
                        <dd class="mt-1 text-neutral-500 dark:text-zinc-400">{{ $this->selectedMessage->phone ?: __('Not provided') }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Message') }}</dt>
                        <dd class="mt-1 whitespace-pre-line text-neutral-600 dark:text-zinc-300">{{ $this->selectedMessage->message }}</dd>
                    </div>
                    @if ($this->selectedMessage->attachment_path)
                        <div>
                            <dt class="font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Attachment') }}</dt>
                            <dd class="mt-2">
                                <a href="{{ route('admin.contact-messages.attachment', $this->selectedMessage) }}" class="brand-button-secondary active:scale-[0.96]">
                                    <i class="fa-solid fa-download text-xs"></i>
                                    {{ __('Download attachment') }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if (! $this->selectedMessage->is_read)
                    <button type="button" wire:click="markAsRead({{ $this->selectedMessage->getKey() }})" class="brand-button-primary mt-6 w-full active:scale-[0.96]">
                        {{ __('Mark as Read') }}
                    </button>
                @endif
            @else
                <div class="py-8 text-center">
                    <span class="brand-soft-surface mx-auto flex h-12 w-12 items-center justify-center rounded-2xl">
                        <i class="fa-solid fa-envelope-open-text"></i>
                    </span>
                    <p class="mt-4 text-sm text-neutral-500 dark:text-zinc-400">{{ __('Select a message to view the full details.') }}</p>
                </div>
            @endif
        </aside>
    </section>
</div>

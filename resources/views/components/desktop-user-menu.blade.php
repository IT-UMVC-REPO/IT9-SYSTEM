<flux:dropdown {{ $attributes->class('') }} position="bottom" align="end">
    <button
        type="button"
        class="flex items-center gap-3 rounded-[1.25rem] border border-stone-200 bg-white px-3 py-2 text-left shadow-sm transition hover:border-emerald-200"
        data-test="sidebar-menu-button"
    >
        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-neutral-900 text-sm font-semibold text-white">
            {{ auth()->user()->initials() }}
        </span>

        <span class="hidden min-w-0 xl:block">
            <span class="block truncate text-sm font-semibold text-neutral-900">{{ auth()->user()->name }}</span>
            <span class="block truncate text-xs text-neutral-400">{{ auth()->user()->email }}</span>
        </span>

        <i class="fa-solid fa-chevron-down text-xs text-neutral-400"></i>
    </button>

    <flux:menu class="min-w-64">
        <div class="flex items-center gap-3 px-2 py-2 text-start text-sm">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-neutral-900 text-sm font-semibold text-white">
                {{ auth()->user()->initials() }}
            </span>
            <div class="grid min-w-0 flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.radio.group>
            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
        </flux:menu.radio.group>
        <flux:menu.separator />
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item
                as="button"
                type="submit"
                icon="arrow-right-start-on-rectangle"
                class="w-full cursor-pointer"
                data-test="logout-button"
            >
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>

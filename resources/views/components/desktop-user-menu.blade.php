<flux:dropdown {{ $attributes->class('') }} position="bottom" align="end">
    <button
        type="button"
        class="flex items-center gap-2 rounded-full border border-stone-200 bg-white py-1 pl-1 pr-2 text-left shadow-sm transition hover:border-emerald-200 dark:border-white/10 dark:bg-zinc-900/80 dark:hover:border-emerald-500"
        data-test="sidebar-menu-button"
    >
        <span class="flex h-[34px] w-[34px] items-center justify-center rounded-full bg-stone-900 text-sm font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">
            {{ auth()->user()->initials() }}
        </span>

        <i class="fa-solid fa-chevron-down text-xs text-stone-400 dark:text-zinc-400"></i>
    </button>

    <flux:menu class="min-w-64">
        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
            {{ __('Settings') }}
        </flux:menu.item>
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

@props(['kicker' => null, 'title', 'description' => null, 'icon' => null])

<section {{ $attributes->merge(['class' => 'flex flex-col gap-4']) }}>
    @if (filled($kicker))
        <span class="brand-kicker">
            @if ($icon)
                <i class="{{ $icon }}"></i>
            @endif
            {{ $kicker }}
        </span>
    @endif

    <h1 class="brand-serif text-3xl font-bold text-neutral-900 dark:text-zinc-100 sm:text-4xl">
        {{ $title }}
    </h1>

    @if (filled($description))
        <p class="max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400">
            {{ $description }}
        </p>
    @endif
</section>

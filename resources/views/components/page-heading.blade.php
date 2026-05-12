@props(['kicker' => null, 'title', 'description' => null, 'icon' => null])

<section {{ $attributes->merge(['class' => 'suki-reveal flex flex-col gap-4']) }}>
    @if (filled($kicker))
        <span class="brand-kicker suki-kicker-animate">
            @if ($icon)
                <i class="{{ $icon }}"></i>
            @endif
            {{ $kicker }}
        </span>
    @endif

    <h1 class="brand-serif suki-reveal text-3xl font-bold text-neutral-900 dark:text-zinc-100 sm:text-4xl" style="transition-delay: 60ms">
        {{ $title }}
    </h1>

    @if (filled($description))
        <p class="suki-reveal max-w-3xl text-base leading-8 text-neutral-500 dark:text-zinc-400" style="transition-delay: 120ms">
            {{ $description }}
        </p>
    @endif
</section>

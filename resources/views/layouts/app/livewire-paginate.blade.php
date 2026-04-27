@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Storefront pagination"
        class="brand-panel-muted flex flex-col gap-4 px-5 py-4 sm:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="brand-accent-text text-xs font-semibold uppercase tracking-[0.18em]">
                    More results
                </p>
                <p class="mt-1 text-sm text-stone-500">
                    Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }}
                    {{ \Illuminate\Support\Str::plural('result', $paginator->total()) }}
                </p>
            </div>

            <div class="flex items-center gap-2 sm:hidden">
                @if ($paginator->onFirstPage())
                    <span
                        class="inline-flex min-h-11 flex-1 items-center justify-center rounded-2xl border border-stone-200 bg-white/70 px-4 text-sm font-semibold text-stone-300">
                        Previous
                    </span>
                @else
                    <button
                        type="button"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                        class="brand-button-secondary min-h-11 flex-1"
                    >
                        Previous
                    </button>
                @endif

                @if ($paginator->hasMorePages())
                    <button
                        type="button"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                        class="brand-button-primary min-h-11 flex-1"
                    >
                        Next
                    </button>
                @else
                    <span
                        class="inline-flex min-h-11 flex-1 items-center justify-center rounded-2xl border border-stone-200 bg-white/70 px-4 text-sm font-semibold text-stone-300">
                        Next
                    </span>
                @endif
            </div>
        </div>

        <div class="hidden items-center justify-center gap-2 sm:flex sm:flex-wrap">
            @if ($paginator->onFirstPage())
                <span
                    class="inline-flex h-11 min-w-11 items-center justify-center rounded-2xl border border-stone-200 bg-white/70 px-3 text-stone-300">
                    <span class="sr-only">Previous</span>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd"
                            d="M11.78 4.22a.75.75 0 0 1 0 1.06L7.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z"
                            clip-rule="evenodd" />
                    </svg>
                </span>
            @else
                <button
                    type="button"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    wire:loading.attr="disabled"
                    class="brand-outline-hover inline-flex h-11 min-w-11 items-center justify-center rounded-2xl border border-stone-300 bg-white px-3 text-stone-500 shadow-sm transition"
                >
                    <span class="sr-only">Previous</span>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd"
                            d="M11.78 4.22a.75.75 0 0 1 0 1.06L7.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span
                        class="inline-flex h-11 min-w-11 items-center justify-center rounded-2xl border border-transparent px-3 text-sm font-semibold text-stone-400">
                        {{ $element }}
                    </span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <span wire:key="storefront-paginator-page-{{ $page }}">
                            @if ($page === $paginator->currentPage())
                                <span aria-current="page"
                                    class="brand-accent-pill inline-flex h-11 min-w-11 items-center justify-center rounded-2xl border px-4 text-sm font-semibold shadow-sm">
                                    {{ $page }}
                                </span>
                            @else
                                <button
                                    type="button"
                                    wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                    wire:loading.attr="disabled"
                                    class="brand-outline-hover inline-flex h-11 min-w-11 items-center justify-center rounded-2xl border border-stone-300 bg-white px-4 text-sm font-semibold text-stone-600 shadow-sm transition"
                                    aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                >
                                    {{ $page }}
                                </button>
                            @endif
                        </span>
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button
                    type="button"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    wire:loading.attr="disabled"
                    class="brand-outline-hover inline-flex h-11 min-w-11 items-center justify-center rounded-2xl border border-stone-300 bg-white px-3 text-stone-500 shadow-sm transition"
                >
                    <span class="sr-only">Next</span>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd"
                            d="M8.22 4.22a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 1 1-1.06-1.06L12.94 10 8.22 5.28a.75.75 0 0 1 0-1.06Z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            @else
                <span
                    class="inline-flex h-11 min-w-11 items-center justify-center rounded-2xl border border-stone-200 bg-white/70 px-3 text-stone-300">
                    <span class="sr-only">Next</span>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd"
                            d="M8.22 4.22a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 1 1-1.06-1.06L12.94 10 8.22 5.28a.75.75 0 0 1 0-1.06Z"
                            clip-rule="evenodd" />
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif

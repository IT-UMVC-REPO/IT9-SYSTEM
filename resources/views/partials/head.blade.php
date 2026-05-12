<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="description" content="{{ $metaDescription ?? __('SukiMarket - Your Local Market, Delivered. Order fresh produce, seafood, meat, and daily goods from verified local vendors.') }}" />
<meta property="og:image" content="{{ asset('imgs/sukiheader.webp') }}" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
<meta property="og:title" content="{{ filled($title ?? null) ? $title.' - SukiMarket' : 'SukiMarket - Your Local Market, Delivered' }}" />
<meta property="og:description" content="{{ $metaDescription ?? __('Your Local Market, Delivered. Browse fresh goods from verified local vendors on SukiMarket.') }}" />
<meta property="og:type" content="website" />

<title>
    {{ filled($title ?? null) ? $title.' - '.'SukiMarket' : 'SukiMarket' }}
</title>

<link rel="icon" type="image/png" href="{{ asset('imgs/sukilogo.png') }}">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=dm-sans:400,500,700|playfair-display:600,700,800" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>

@auth
    <style>:root{ {!! auth()->user()->brandColorCssVars() !!} }</style>
@endauth

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

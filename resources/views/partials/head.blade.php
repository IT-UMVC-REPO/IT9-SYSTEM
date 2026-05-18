<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
@php
    $sukiTitle = filled($title ?? null) ? "{$title} - SukiMarket" : __('SukiMarket - Videre Est Scire');
    $sukiText = $metaDescription ?? __('Order fresh produce, seafood, meat, and daily goods from verified local vendors on SukiMarket.');
    $sukiHeader = secure_asset('imgs/sukiheader.webp');
    $sukiUrl = secure_url(request()->path());
    $sukiRealtimeConfig = config('broadcasting.connections.pusher.client', []);
@endphp
<meta name="description" content="{{ $sukiText }}" />
<meta property="og:site_name" content="SukiMarket" />
<meta property="og:url" content="{{ $sukiUrl }}" />
<meta property="og:title" content="{{ $sukiTitle }}" />
<meta property="og:description" content="{{ $sukiText }}" />
<meta property="og:type" content="website" />
<meta property="og:image" content="{{ $sukiHeader }}" />
<meta property="og:image:secure_url" content="{{ $sukiHeader }}" />
<meta property="og:image:type" content="image/webp" />
<meta property="og:image:width" content="536" />
<meta property="og:image:height" content="357" />
<meta property="og:image:alt" content="{{ __('SukiMarket local market stalls with fresh produce') }}" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $sukiTitle }}" />
<meta name="twitter:description" content="{{ $sukiText }}" />
<meta name="twitter:image" content="{{ $sukiHeader }}" />
<meta name="twitter:image:alt" content="{{ __('SukiMarket local market stalls with fresh produce') }}" />

<title>
    {{ $sukiTitle }}
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

<script>
    window.sukiRealtimeConfig = @js([
        'key' => $sukiRealtimeConfig['key'] ?? null,
        'cluster' => $sukiRealtimeConfig['cluster'] ?? 'mt1',
        'host' => $sukiRealtimeConfig['host'] ?? null,
        'port' => (int) ($sukiRealtimeConfig['port'] ?? 443),
        'scheme' => $sukiRealtimeConfig['scheme'] ?? 'https',
    ]);
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

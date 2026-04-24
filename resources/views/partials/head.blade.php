<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.'SukiMarket' : '' }}
</title>

<link rel="icon" type="image/png" href="{{ asset('imgs/sukilogo.png') }}">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=dm-sans:400,500,700|playfair-display:600,700,800" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />

@auth
    <style>:root{ {!! auth()->user()->brandColorCssVars() !!} }</style>
@endauth

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

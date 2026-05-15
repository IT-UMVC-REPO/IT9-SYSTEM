<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', [
        'title' => __('Fresh from the Palengke'),
        'metaDescription' => __('Order fresh produce, seafood, meat, and daily goods from verified local vendors on SukiMarket.'),
    ])
</head>
<body>
    <h1>{{ __('Fresh from the Palengke - SukiMarket') }}</h1>
    <p>{{ __('Order fresh produce, seafood, meat, and daily goods from verified local vendors on SukiMarket.') }}</p>
</body>
</html>

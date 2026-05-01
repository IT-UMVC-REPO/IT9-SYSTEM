<?php

$normalizeUrl = static function (string $url, string $scheme): string {
    if ($url === '' || preg_match('/^(stun|turns?):/i', $url) === 1) {
        return $url;
    }

    return $scheme.':'.$url;
};

$parseUrls = static function (?string $value, array $default = [], string $scheme = 'stun') use ($normalizeUrl): array {
    if (! filled($value)) {
        return $default;
    }

    return array_values(array_filter(array_map(
        static fn (string $url): string => $normalizeUrl(trim($url), $scheme),
        explode(',', $value),
    )));
};

return [
    'stun_urls' => $parseUrls(env('WEBRTC_STUN_URLS'), [
        'stun:stun.l.google.com:19302',
        'stun:stun1.l.google.com:19302',
        'stun:stun2.l.google.com:19302',
        'stun:stun3.l.google.com:19302',
    ], 'stun'),

    'turn_urls' => $parseUrls(env('WEBRTC_TURN_URLS'), [], 'turn'),
    'turn_username' => env('WEBRTC_TURN_USERNAME'),
    'turn_credential' => env('WEBRTC_TURN_CREDENTIAL'),
    'turn_shared_secret' => env('WEBRTC_TURN_SHARED_SECRET'),
    'turn_ttl' => (int) env('WEBRTC_TURN_TTL', 3600),
    'ice_transport_policy' => env('WEBRTC_ICE_TRANSPORT_POLICY', 'all'),
];

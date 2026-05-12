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
    /*
    |--------------------------------------------------------------------------
    | ICE Servers
    |--------------------------------------------------------------------------
    |
    | STUN helps browsers discover a public media path, but it is not enough for
    | many mobile carrier networks, strict NATs, or Cloudflare Tunnel paths.
    | Configure TURN credentials for reliable cross-network mobile calls.
    |
    */
    'stun_urls' => $parseUrls(env('WEBRTC_STUN_URLS'), [
        'stun:openrelay.metered.ca:80',
        'stun:stun.l.google.com:19302',
        'stun:stun1.l.google.com:19302',
    ], 'stun'),

    /*
    |--------------------------------------------------------------------------
    | TURN Credentials
    |--------------------------------------------------------------------------
    |
    | TURN is mandatory for dependable phone-to-phone calls across different
    | networks. Use static credentials or a shared secret from your TURN provider.
    |
    */
    'turn_urls' => $parseUrls(env('WEBRTC_TURN_URLS'), [
        'turn:openrelay.metered.ca:80',
        'turn:openrelay.metered.ca:80?transport=tcp',
        'turn:openrelay.metered.ca:443',
        'turns:openrelay.metered.ca:443?transport=tcp',
    ], 'turn'),
    'turn_username' => env('WEBRTC_TURN_USERNAME', 'openrelayproject'),
    'turn_credential' => env('WEBRTC_TURN_CREDENTIAL', 'openrelayproject'),
    'turn_shared_secret' => env('WEBRTC_TURN_SHARED_SECRET'),
    'turn_ttl' => (int) env('WEBRTC_TURN_TTL', 3600),
    'ice_transport_policy' => env('WEBRTC_ICE_TRANSPORT_POLICY', 'all'),
];

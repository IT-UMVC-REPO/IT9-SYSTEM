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
    'cloudflare_turn_key_id' => env('WEBRTC_CLOUDFLARE_TURN_KEY_ID'),
    'cloudflare_turn_api_token' => env('WEBRTC_CLOUDFLARE_TURN_API_TOKEN'),
    'cloudflare_turn_ttl' => (int) env('WEBRTC_CLOUDFLARE_TURN_TTL', env('WEBRTC_TURN_TTL', 3600)),
    'cloudflare_turn_endpoint' => env('WEBRTC_CLOUDFLARE_TURN_ENDPOINT', 'https://rtc.live.cloudflare.com/v1/turn/keys'),
    'ice_transport_policy' => env('WEBRTC_ICE_TRANSPORT_POLICY', 'all'),
];

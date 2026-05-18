<?php

test('nativephp mobile configuration uses the sukimarket app contract', function () {
    expect(config('nativephp.app_id'))->toBe('com.sukimarket.app')
        ->and(config('nativephp.start_url'))->toBe('/dashboard')
        ->and(config('nativephp.deeplink_scheme'))->toBe('sukimarket')
        ->and(config('nativephp.server.service_name'))->toBe('SukiMarket Mobile');
});

test('nativephp mobile bundle cleanup keeps railway and backend secrets out of packaged env', function () {
    expect(config('nativephp.cleanup_env_keys'))->toContain(
        'DATABASE_URL',
        'DB_PASSWORD',
        'DB_USERNAME',
        'MAIL_PASSWORD',
        'MYSQL*',
        'PAYMONGO_*',
        'PUSHER_APP_SECRET',
        'RAILWAY_*',
        'REDIS_*',
        'RESEND_KEY',
        'REVERB_APP_SECRET',
    );
});

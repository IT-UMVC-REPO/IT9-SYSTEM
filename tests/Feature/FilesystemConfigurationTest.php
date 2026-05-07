<?php

test('public filesystem disk is configured for local serving or R2', function () {
    $publicDisk = config('filesystems.disks.public');
    $publicDriver = env('FILESYSTEM_PUBLIC_DRIVER') ?: 'local';
    $publicRoot = $publicDriver === 's3' ? '' : storage_path('app/public');

    expect(config('filesystems.default'))->toBe(env('FILESYSTEM_DISK', 'public'))
        ->and($publicDisk['driver'])->toBe($publicDriver)
        ->and($publicDisk['root'])->toBe($publicRoot)
        ->and($publicDisk['url'])->toBe(env('FILESYSTEM_PUBLIC_URL') ?: env('AWS_URL') ?: '/storage')
        ->and($publicDisk['visibility'])->toBe('public')
        ->and($publicDisk['serve'])->toBeTrue()
        ->and($publicDisk['throw'])->toBeTrue()
        ->and($publicDisk['report'])->toBeFalse()
        ->and($publicDisk['region'])->toBe(env('AWS_DEFAULT_REGION') ?: 'auto')
        ->and($publicDisk)->toHaveKeys([
            'key',
            'secret',
            'bucket',
            'endpoint',
            'use_path_style_endpoint',
        ]);
});

test('public filesystem disk uses bucket root when backed by s3', function () {
    $publicDisk = publicFilesystemDiskConfigForDriver('s3');

    expect($publicDisk['driver'])->toBe('s3')
        ->and($publicDisk['root'])->toBe('');
});

test('public filesystem disk keeps the storage root when backed by local storage', function (?string $driver) {
    $publicDisk = publicFilesystemDiskConfigForDriver($driver);

    expect($publicDisk['driver'])->toBe($driver ?: 'local')
        ->and($publicDisk['root'])->toBe(storage_path('app/public'));
})->with([
    'unset driver' => null,
    'local driver' => 'local',
]);

test('example environment documents public R2 filesystem variables', function () {
    $contents = file_get_contents(base_path('.env.example'));
    $exampleEnvironment = collect(preg_split('/\R/', $contents) ?: [])
        ->filter(fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#') && str_contains($line, '='))
        ->mapWithKeys(function (string $line): array {
            [$key, $value] = explode('=', $line, 2);

            return [$key => $value];
        })
        ->all();

    preg_match_all('/^FILESYSTEM_DISK=/m', $contents, $filesystemDiskMatches);

    expect($exampleEnvironment)->toBeArray()
        ->and($filesystemDiskMatches[0])->toHaveCount(1)
        ->and($exampleEnvironment['FILESYSTEM_DISK'])->toBe('public')
        ->and($exampleEnvironment['FILESYSTEM_PUBLIC_DRIVER'])->toBe('local')
        ->and($exampleEnvironment['FILESYSTEM_PUBLIC_URL'])->toBe('')
        ->and($exampleEnvironment['AWS_DEFAULT_REGION'])->toBe('auto')
        ->and($exampleEnvironment)->toHaveKeys([
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_BUCKET',
            'AWS_URL',
            'AWS_ENDPOINT',
            'AWS_USE_PATH_STYLE_ENDPOINT',
        ]);
});

test('railway deploy command refreshes the storage link fallback', function () {
    expect(file_get_contents(base_path('railway.toml')))
        ->toContain('php artisan storage:link --force');
});

/**
 * @return array<string, mixed>
 */
function publicFilesystemDiskConfigForDriver(?string $driver): array
{
    $hadEnvValue = array_key_exists('FILESYSTEM_PUBLIC_DRIVER', $_ENV);
    $previousEnvValue = $_ENV['FILESYSTEM_PUBLIC_DRIVER'] ?? null;
    $hadServerValue = array_key_exists('FILESYSTEM_PUBLIC_DRIVER', $_SERVER);
    $previousServerValue = $_SERVER['FILESYSTEM_PUBLIC_DRIVER'] ?? null;
    $previousPutenvValue = getenv('FILESYSTEM_PUBLIC_DRIVER');

    if ($driver === null) {
        unset($_ENV['FILESYSTEM_PUBLIC_DRIVER'], $_SERVER['FILESYSTEM_PUBLIC_DRIVER']);
        putenv('FILESYSTEM_PUBLIC_DRIVER');
    } else {
        $_ENV['FILESYSTEM_PUBLIC_DRIVER'] = $driver;
        $_SERVER['FILESYSTEM_PUBLIC_DRIVER'] = $driver;
        putenv("FILESYSTEM_PUBLIC_DRIVER={$driver}");
    }

    try {
        return (require config_path('filesystems.php'))['disks']['public'];
    } finally {
        if ($hadEnvValue) {
            $_ENV['FILESYSTEM_PUBLIC_DRIVER'] = $previousEnvValue;
        } else {
            unset($_ENV['FILESYSTEM_PUBLIC_DRIVER']);
        }

        if ($hadServerValue) {
            $_SERVER['FILESYSTEM_PUBLIC_DRIVER'] = $previousServerValue;
        } else {
            unset($_SERVER['FILESYSTEM_PUBLIC_DRIVER']);
        }

        if ($previousPutenvValue === false) {
            putenv('FILESYSTEM_PUBLIC_DRIVER');
        } else {
            putenv("FILESYSTEM_PUBLIC_DRIVER={$previousPutenvValue}");
        }
    }
}

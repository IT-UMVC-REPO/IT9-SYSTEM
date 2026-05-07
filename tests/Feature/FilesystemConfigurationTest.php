<?php

test('public filesystem disk is configured for local serving or R2', function () {
    $publicDisk = config('filesystems.disks.public');

    expect(config('filesystems.default'))->toBe(env('FILESYSTEM_DISK', 'public'))
        ->and($publicDisk['driver'])->toBe(env('FILESYSTEM_PUBLIC_DRIVER') ?: 'local')
        ->and($publicDisk['root'])->toBe(storage_path('app/public'))
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

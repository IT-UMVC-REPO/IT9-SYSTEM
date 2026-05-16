<?php

test('public filesystem disk is configured for local serving or R2', function () {
    $publicDisk = config('filesystems.disks.public');
    $publicDriver = env('FILESYSTEM_PUBLIC_DRIVER') ?: 'local';
    $publicRoot = $publicDriver === 's3' ? '' : storage_path('app/public');

    expect(config('filesystems.default'))->toBe(env('FILESYSTEM_DISK', 'public'))
        ->and($publicDisk['driver'])->toBe($publicDriver)
        ->and($publicDisk['root'])->toBe($publicRoot)
        ->and($publicDisk['url'])->toBe(env('FILESYSTEM_PUBLIC_URL') ?: env('AWS_URL') ?: env('APP_URL').'/storage')
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

test('public filesystem disk falls back to an absolute application storage url', function () {
    $publicDisk = publicFilesystemDiskConfigForPublicUrl(null, null, 'https://market.example.test');

    expect($publicDisk['url'])->toBe('https://market.example.test/storage')
        ->and($publicDisk['url'])->not->toBe('/storage');
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
        ->and($exampleEnvironment['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'])->toBe('local')
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

test('livewire temporary uploads stay local unless explicitly configured otherwise', function () {
    expect(livewireTemporaryUploadDiskConfigFor(null))->toBe('local')
        ->and(livewireTemporaryUploadDiskConfigFor('s3'))->toBe('s3');
});

test('railway deploy command refreshes the storage link fallback', function () {
    expect(file_get_contents(base_path('railway.toml')))
        ->toContain('php artisan storage:link --force');
});

test('railway web process only serves http traffic', function () {
    expect(file_get_contents(base_path('railway.toml')))
        ->toContain('php artisan serve --host=0.0.0.0 --port=${PORT}')
        ->not->toContain('queue:work');
});

test('railway worker process handles queued verification mail', function () {
    expect(file_get_contents(base_path('railway-worker.toml')))
        ->toContain('php artisan queue:work ${QUEUE_CONNECTION:-redis} --tries=3 --sleep=1 --timeout=90');
});

/**
 * @return array<string, mixed>
 */
function publicFilesystemDiskConfigForDriver(?string $driver): array
{
    return filesystemConfigWithEnvironment([
        'FILESYSTEM_PUBLIC_DRIVER' => $driver,
    ])['disks']['public'];
}

/**
 * @return array<string, mixed>
 */
function publicFilesystemDiskConfigForPublicUrl(?string $publicUrl, ?string $awsUrl, ?string $appUrl): array
{
    return filesystemConfigWithEnvironment([
        'FILESYSTEM_PUBLIC_URL' => $publicUrl,
        'AWS_URL' => $awsUrl,
        'APP_URL' => $appUrl,
    ])['disks']['public'];
}

/**
 * @param  array<string, string|null>  $environment
 * @return array<string, mixed>
 */
function filesystemConfigWithEnvironment(array $environment): array
{
    $previousValues = [];

    foreach ($environment as $key => $value) {
        $previousValues[$key] = [
            'had_env_value' => array_key_exists($key, $_ENV),
            'env_value' => $_ENV[$key] ?? null,
            'had_server_value' => array_key_exists($key, $_SERVER),
            'server_value' => $_SERVER[$key] ?? null,
            'putenv_value' => getenv($key),
        ];

        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            continue;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }

    try {
        return require config_path('filesystems.php');
    } finally {
        foreach ($previousValues as $key => $previousValue) {
            if ($previousValue['had_env_value']) {
                $_ENV[$key] = $previousValue['env_value'];
            } else {
                unset($_ENV[$key]);
            }

            if ($previousValue['had_server_value']) {
                $_SERVER[$key] = $previousValue['server_value'];
            } else {
                unset($_SERVER[$key]);
            }

            if ($previousValue['putenv_value'] === false) {
                putenv($key);
            } else {
                putenv("{$key}={$previousValue['putenv_value']}");
            }
        }
    }
}

function livewireTemporaryUploadDiskConfigFor(?string $disk): ?string
{
    $hadEnvValue = array_key_exists('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', $_ENV);
    $previousEnvValue = $_ENV['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'] ?? null;
    $hadServerValue = array_key_exists('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', $_SERVER);
    $previousServerValue = $_SERVER['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'] ?? null;
    $previousPutenvValue = getenv('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK');

    if ($disk === null) {
        unset($_ENV['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'], $_SERVER['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK']);
        putenv('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK');
    } else {
        $_ENV['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'] = $disk;
        $_SERVER['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'] = $disk;
        putenv("LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK={$disk}");
    }

    try {
        return (require config_path('livewire.php'))['temporary_file_upload']['disk'];
    } finally {
        if ($hadEnvValue) {
            $_ENV['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'] = $previousEnvValue;
        } else {
            unset($_ENV['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK']);
        }

        if ($hadServerValue) {
            $_SERVER['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'] = $previousServerValue;
        } else {
            unset($_SERVER['LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK']);
        }

        if ($previousPutenvValue === false) {
            putenv('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK');
        } else {
            putenv("LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK={$previousPutenvValue}");
        }
    }
}

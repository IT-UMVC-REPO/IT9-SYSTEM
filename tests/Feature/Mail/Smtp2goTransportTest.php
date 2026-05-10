<?php

use App\Mail\Smtp2goTransport;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;

test('resolves the smtp2go transport from the mail manager', function (): void {
    config(['mail.default' => 'smtp2go']);
    config(['mail.mailers.smtp2go' => ['transport' => 'smtp2go']]);
    config(['services.smtp2go.key' => 'test-key']);
    config(['mail.from.address' => 'test@example.com']);
    config(['mail.from.name' => 'Test']);

    expect(Mail::mailer('smtp2go'))->toBeInstanceOf(Mailer::class);
});

test('builds a transport instance with the configured api key', function (): void {
    $transport = new Smtp2goTransport(
        apiKey: 'test-api-key',
        senderName: 'LocalPalengke',
        senderEmail: 'no-reply@localpalengke.app',
    );

    expect((string) $transport)->toBe('smtp2go');
});

test('formats smtp2go recipients as api address strings', function (): void {
    $transport = new Smtp2goTransport(
        apiKey: 'test-api-key',
        senderName: 'LocalPalengke',
        senderEmail: 'no-reply@localpalengke.app',
    );

    $method = new ReflectionMethod($transport, 'addressesFor');

    expect($method->invoke($transport, [
        new Address('buyer@example.com', 'Buyer Mina'),
        new Address('vendor@example.com'),
    ]))->toBe([
        '"Buyer Mina" <buyer@example.com>',
        'vendor@example.com',
    ]);
});

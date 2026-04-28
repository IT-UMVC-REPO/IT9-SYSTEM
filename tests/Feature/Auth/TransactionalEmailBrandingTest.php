<?php

use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Markdown;

test('email verification mailable renders with sukimarket branding', function () {
    $user = User::factory()->make([
        'name' => 'Aling Rosa',
        'email' => 'rosa@example.com',
    ]);

    $mail = (new EmailVerification(
        user: $user,
        verificationUrl: 'https://sukimarket.test/email/verify/demo',
        verificationCode: '123456',
    ))->to($user->email, $user->name);

    $mail->assertHasSubject("Welcome to SukiMarket \u{2014} Verify your email")
        ->assertSeeInHtml('SukiMarket')
        ->assertSeeInHtml('Digital palengke')
        ->assertSeeInHtml('Welcome to SukiMarket, Aling Rosa!')
        ->assertSeeInHtml('Verify my email')
        ->assertSeeInHtml('123456')
        ->assertSeeInHtml('2025 SukiMarket')
        ->assertSeeInHtml('Digital Palengke')
        ->assertSeeInText('Welcome to SukiMarket, Aling Rosa!');
});

test('reset password notification renders the branded markdown template', function () {
    $user = User::factory()->make([
        'name' => 'Aling Rosa',
        'email' => 'rosa@example.com',
    ]);

    $notification = new ResetPassword('test-token');
    $mailMessage = $notification->toMail($user);
    $resetUrl = url(route('password.reset', [
        'token' => 'test-token',
        'email' => $user->email,
    ], false));

    $html = (string) app(Markdown::class)->render($mailMessage->markdown, array_merge(
        $mailMessage->data(),
        [
            'user' => $user,
            'resetUrl' => $resetUrl,
            'expirationMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
        ],
    ));

    expect($mailMessage->subject)->toBe('Reset your SukiMarket password')
        ->and($mailMessage->markdown)->toBe('emails.auth.reset-password')
        ->and($html)->toContain('SukiMarket')
        ->and($html)->toContain('Reset my password')
        ->and($html)->toContain('2025 SukiMarket')
        ->and($html)->toContain('Digital Palengke');
});

<?php

use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Markdown;

test('email verification mailable renders with localpalengke branding', function () {
    $user = User::factory()->make([
        'name' => 'Aling Rosa',
        'email' => 'rosa@example.com',
    ]);

    $mail = (new EmailVerification(
        user: $user,
        verificationUrl: 'https://localpalengke.test/email/verify/demo',
        verificationCode: '123456',
    ))->to($user->email, $user->name);

    $mail->assertHasSubject("Welcome to LocalPalengke \u{2014} Verify your email")
        ->assertSeeInHtml('LocalPalengke')
        ->assertSeeInHtml('Your Local Market, Delivered')
        ->assertSeeInHtml('Welcome to LocalPalengke, Aling Rosa!')
        ->assertSeeInHtml('Verify my email')
        ->assertSeeInHtml('123456')
        ->assertSeeInHtml('2025 LocalPalengke')
        ->assertSeeInHtml('Your Local Market, Delivered')
        ->assertSeeInText('Welcome to LocalPalengke, Aling Rosa!');
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

    expect($mailMessage->subject)->toBe('Reset your LocalPalengke password')
        ->and($mailMessage->markdown)->toBe('emails.auth.reset-password')
        ->and($html)->toContain('LocalPalengke')
        ->and($html)->toContain('Reset my password')
        ->and($html)->toContain('2025 LocalPalengke')
        ->and($html)->toContain('Your Local Market, Delivered');
});

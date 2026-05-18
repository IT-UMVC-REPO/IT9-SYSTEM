<?php

use App\Mail\EmailVerification;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

test('email verification mailable is queued by default', function () {
    expect(EmailVerification::class)->toImplement(ShouldQueue::class);
});

test('email verification screen can be rendered and queues a branded verification email when needed', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create([
        'email_verification_code' => null,
        'email_verification_code_expires_at' => null,
    ]);

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $user->refresh();

    $response->assertOk()
        ->assertSee('Verify your email')
        ->assertSee($user->email)
        ->assertSee('Back to home')
        ->assertDontSee('<html lang="'.str_replace('_', '-', app()->getLocale()).'" x-cloak>', false);

    expect($user->email_verification_code)->not->toBeNull()
        ->and(strlen($user->email_verification_code))->toBe(6)
        ->and($user->email_verification_code_expires_at)->not->toBeNull();

    Mail::assertQueued(EmailVerification::class, function (EmailVerification $mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->hasSubject("Welcome to SukiMarket \u{2014} Verify your email")
            && $mail->verificationCode === $user->email_verification_code;
    });
});

test('already verified user visiting verification notice is redirected home', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertRedirect(route($user->homeRoute(), absolute: false));
});

test('email can be verified with a valid code', function () {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create([
        'email_verification_code' => '123456',
        'email_verification_code_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($user)->post(route('verification.code.verify'), [
        'code' => '123456',
    ]);

    $response->assertRedirect(route($user->homeRoute(), absolute: false))
        ->assertSessionHas('status', 'email-verified');

    $user->refresh();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->email_verification_code)->toBeNull()
        ->and($user->email_verification_code_expires_at)->toBeNull();

    Event::assertDispatched(Verified::class);
});

test('verification code endpoint redirects get requests back to the notice', function () {
    $user = User::factory()->unverified()->create([
        'email_verification_code' => '123456',
        'email_verification_code_expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($user)
        ->get(route('verification.code.verify'))
        ->assertRedirect(route('verification.notice', absolute: false));

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email can be verified when a code is pasted with formatting', function () {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create([
        'email_verification_code' => '123456',
        'email_verification_code_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($user)->post(route('verification.code.verify'), [
        'code' => ' 123-456 ',
    ]);

    $response->assertRedirect(route($user->homeRoute(), absolute: false))
        ->assertSessionHas('status', 'email-verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatched(Verified::class);
});

test('email can be verified from the signed link', function () {
    Event::fake([Verified::class]);

    $user = User::factory()->unverified()->create([
        'email_verification_code' => '123456',
        'email_verification_code_expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($user)->get($user->emailVerificationUrl());

    $response->assertRedirect(route($user->homeRoute(), absolute: false))
        ->assertSessionHas('status', 'email-verified');

    $user->refresh();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->email_verification_code)->toBeNull()
        ->and($user->email_verification_code_expires_at)->toBeNull();

    Event::assertDispatched(Verified::class);
});

test('email is not verified with an invalid code', function () {
    $user = User::factory()->unverified()->create([
        'email_verification_code' => '123456',
        'email_verification_code_expires_at' => now()->addMinutes(10),
    ]);

    $this->from(route('verification.notice'))
        ->actingAs($user)
        ->post(route('verification.code.verify'), [
            'code' => '654321',
        ])
        ->assertRedirect(route('verification.notice', absolute: false))
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email is not verified with an expired code', function () {
    $user = User::factory()->unverified()->create([
        'email_verification_code' => '123456',
        'email_verification_code_expires_at' => now()->subMinute(),
    ]);

    $this->from(route('verification.notice'))
        ->actingAs($user)
        ->post(route('verification.code.verify'), [
            'code' => '123456',
        ])
        ->assertRedirect(route('verification.notice', absolute: false))
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('users can resend verification emails', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create([
        'email_verification_code' => '111111',
        'email_verification_code_expires_at' => now()->addMinute(),
    ]);

    $this->from(route('verification.notice'))
        ->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice', absolute: false))
        ->assertSessionHas('status', 'verification-email-sent');

    $user->refresh();

    expect($user->email_verification_code)->not->toBe('111111')
        ->and($user->email_verification_code_expires_at)->not->toBeNull();

    Mail::assertQueued(EmailVerification::class, function (EmailVerification $mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->verificationCode === $user->email_verification_code;
    });
});

test('unverified customers are redirected to the verification notice from protected marketplace routes', function () {
    $user = User::factory()->unverified()->create();

    foreach ([
        route('dashboard'),
        route('notifications.index'),
        route('customer.dashboard'),
        route('shop.home'),
        route('messages.inbox'),
        route('vendor.registration'),
    ] as $protectedRoute) {
        $this->actingAs($user)
            ->get($protectedRoute)
            ->assertRedirect(route('verification.notice', absolute: false));
    }
});

test('unverified approved vendors are redirected to the verification notice from vendor routes', function () {
    $user = User::factory()->vendor()->unverified()->create();
    VendorProfile::factory()->for($user, 'user')->approved()->create();

    $this->actingAs($user)
        ->get(route('vendor.dashboard'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('unverified admins are redirected to the verification notice from admin routes', function () {
    $user = User::factory()->admin()->unverified()->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

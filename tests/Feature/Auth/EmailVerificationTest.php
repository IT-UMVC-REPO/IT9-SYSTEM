<?php

use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk()
        ->assertDontSee('<html lang="'.str_replace('_', '-', app()->getLocale()).'" x-cloak>', false);
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertNotDispatched(Verified::class);
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

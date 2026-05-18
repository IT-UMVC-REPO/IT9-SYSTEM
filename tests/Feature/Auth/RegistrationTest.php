<?php

use App\Enums\UserRole;
use App\Mail\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'agree_terms' => 'on',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('customer.dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->is_active)->toBeTrue()
        ->and($user->profile_image)->toBeNull()
        ->and($user->vendorProfile)->toBeNull();
});

test('new users can register with optional phone and address', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Maria Santos',
        'email' => 'maria@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '+63 912 345 6789',
        'address' => '12 Tindalo St, Quezon City',
        'agree_terms' => 'on',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('customer.dashboard', absolute: false));

    $user = User::query()->where('email', 'maria@example.com')->firstOrFail();

    expect($user->phone)->toBe('+63 912 345 6789')
        ->and($user->address)->toBe('12 Tindalo St, Quezon City');
});

test('phone and address are optional during registration', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'agree_terms' => 'on',
    ]);

    $response->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'juan@example.com')->firstOrFail();

    expect($user->phone)->toBeNull()
        ->and($user->address)->toBeNull();
});

test('new users are redirected to their intended page after registration', function () {
    $this->get(route('shop.cart'))
        ->assertRedirect(route('login'));

    $response = $this->post(route('register.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'agree_terms' => 'on',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('shop.cart', absolute: false));

    $this->assertAuthenticated();
});

test('new users receive a branded verification email and land on the verification screen', function () {
    Mail::fake();

    $response = $this->followingRedirects()->post(route('register.store'), [
        'name' => 'New OTP User',
        'email' => 'otp-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'agree_terms' => 'on',
    ]);

    $user = User::query()->where('email', 'otp-user@example.com')->firstOrFail();
    $user->refresh();

    $response->assertOk()
        ->assertSee('Verify your email')
        ->assertSee($user->email);

    expect($user->email_verification_code)->not->toBeNull();

    Mail::assertQueued(EmailVerification::class, function (EmailVerification $mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->verificationCode === $user->email_verification_code;
    });
});

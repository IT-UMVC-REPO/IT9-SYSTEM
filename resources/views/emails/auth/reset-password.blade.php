@component('mail::message')
# Reset your LocalPalengke password

Hello {{ $user->name }},

We received a request to reset the password for your LocalPalengke account. Click the button below to choose a new one.

@component('mail::button', ['url' => $resetUrl])
Reset my password
@endcomponent

This password reset link expires in {{ $expirationMinutes }} minutes. If you did not request a password reset, you can safely ignore this email.

The LocalPalengke Team
@endcomponent

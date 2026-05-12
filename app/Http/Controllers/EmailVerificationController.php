<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRoute());
        }

        if (
            blank($user->email_verification_code)
            || $user->email_verification_code_expires_at === null
            || ! $user->email_verification_code_expires_at->isFuture()
        ) {
            $user->sendEmailVerificationNotification();
        }

        return view('pages.auth.verify-email');
    }

    public function verifyLink(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRoute());
        }

        $request->fulfill();

        $user->forceFill([
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ])->save();

        return redirect()
            ->route($user->homeRoute())
            ->with('status', 'email-verified');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRoute());
        }

        $submittedCode = $this->normalizeVerificationCode((string) $validated['code']);
        $codeHasExpired = $user->email_verification_code_expires_at === null
            || ! $user->email_verification_code_expires_at->isFuture();

        if (! hash_equals((string) $user->email_verification_code, $submittedCode) || $codeHasExpired) {
            return back()->withErrors([
                'code' => __('Invalid or expired code. Please request a new one.'),
            ]);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ])->save();

        event(new Verified($user));

        return redirect()
            ->route($user->homeRoute())
            ->with('status', 'email-verified');
    }

    private function normalizeVerificationCode(string $code): string
    {
        return preg_replace('/\D+/', '', $code) ?? '';
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route($user->homeRoute());
        }

        $user->sendEmailVerificationCode();

        return back()->with('status', 'verification-email-sent');
    }
}

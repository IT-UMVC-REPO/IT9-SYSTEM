@php($user = auth()->user())

<x-layouts::auth :title="__('Verify your email')">
    <div class="flex flex-col gap-5">
        <div
            class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-2xl border border-emerald-300/60 bg-emerald-50 text-emerald-700 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300">
            <i class="fa-solid fa-envelope-open-text text-lg"></i>
        </div>
        <x-auth-header :title="__('Verify your email')" :description="__('We sent a 6-digit code to :email. Enter it below or click the link in the email.', [
            'email' => $user->email,
        ])" />

        @if (session('status') === 'verification-email-sent')
            <p class="text-center text-sm font-medium text-emerald-600 dark:text-emerald-400">
                {{ __('A fresh verification email has been sent.') }}
            </p>
        @endif

        <form method="POST" action="{{ route('verification.code.verify') }}" class="flex flex-col gap-4">
            @csrf

            <div class="flex flex-col items-center gap-2">
                <flux:otp name="code" length="6" autofocus class="mx-auto" />
                @error('code')
                    <flux:text color="red" class="text-center text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Verify email') }}
            </flux:button>
        </form>
        <hr class="border-t border-stone-200 dark:border-white/10" />
        <div class="flex items-center justify-center gap-3 text-sm text-neutral-500 dark:text-zinc-400">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="font-medium underline hover:text-neutral-800 dark:hover:text-zinc-100">
                    {{ __('Resend email') }}
                </button>
            </form>
            <span aria-hidden="true">&middot;</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="font-medium underline hover:text-neutral-800 dark:hover:text-zinc-100">
                    {{ __('Log out') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts::auth>

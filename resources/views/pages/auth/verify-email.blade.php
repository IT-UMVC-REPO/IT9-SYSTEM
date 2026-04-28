@php($user = auth()->user())

<x-layouts::auth.card>
    <div class="space-y-6">
        <div class="space-y-3 text-center">
            <h1 class="brand-serif text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Verify your email') }}</h1>
            <p class="text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                {!! __('We sent a verification email to <strong class="font-semibold text-neutral-900 dark:text-zinc-100">:email</strong>. Use the button in that message to activate your account, or enter the 6-digit backup code from the same email below.', ['email' => e($user->email)]) !!}
            </p>
        </div>

        @if (session('status') === 'verification-email-sent')
            <flux:text class="text-center font-medium !text-green-600 !dark:text-green-400">
                {{ __('A fresh verification email has been sent.') }}
            </flux:text>
        @endif

        <form method="POST" action="{{ route('verification.code.verify') }}" class="space-y-6">
            @csrf

            <div class="space-y-4">
                <div class="flex justify-center">
                    <flux:otp name="code" length="6" autofocus class="mx-auto" />
                </div>

                @error('code')
                    <flux:text color="red" class="text-center">{{ $message }}</flux:text>
                @enderror
            </div>

            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Verify email') }}
            </flux:button>
        </form>

        <div class="space-y-4">
            <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-stone-200 dark:border-white/10"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-3 text-xs font-semibold uppercase tracking-[0.2em] text-neutral-400 dark:bg-zinc-950 dark:text-zinc-500">
                        {{ __('Need another email?') }}
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <flux:button type="submit" variant="ghost" class="w-full">
                    {{ __('Send another email') }}
                </flux:button>
            </form>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button variant="ghost" type="submit" class="w-full text-sm cursor-pointer" data-test="logout-button">
                {{ __('Log out') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth.card>

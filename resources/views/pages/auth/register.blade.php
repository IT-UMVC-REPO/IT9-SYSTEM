<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create your SukiMarket account')" :description="__('Register to access the customer storefront and follow the next marketplace features as they launch.')" />
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="suki-reveal flex flex-col gap-6" style="transition-delay: 120ms">
            @csrf
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                :placeholder="__('email@example.com')"
            />
            <flux:input
                name="phone"
                :label="__('Phone number')"
                :description="__('Optional - used for delivery coordination')"
                :value="old('phone')"
                type="tel"
                autocomplete="tel"
                :placeholder="__('+63 9XX XXX XXXX')"
            />
            <flux:input
                name="address"
                :label="__('Delivery address')"
                :description="__('Optional - you can update this later in profile settings')"
                :value="old('address')"
                type="text"
                autocomplete="street-address"
                :placeholder="__('Street, barangay, city')"
            />
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                viewable
            />
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                viewable
            />

            <div>
                <label for="agree_terms" class="flex items-start gap-2 text-sm leading-6 text-neutral-600 dark:text-zinc-300">
                    <input
                        id="agree_terms"
                        name="agree_terms"
                        type="checkbox"
                        value="1"
                        required
                        @checked(old('agree_terms'))
                        class="mt-1 h-4 w-4 rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 dark:border-white/10 dark:bg-zinc-800"
                    >
                    <span>
                        {{ __('I have read and agree to the') }}
                        <a href="{{ route('legal.terms-and-conditions') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[var(--brand-700)] underline dark:text-[var(--brand-300)]">{{ __('Terms and Conditions') }}</a>
                        {{ __('and') }}
                        <a href="{{ route('legal.privacy-policy') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[var(--brand-700)] underline dark:text-[var(--brand-300)]">{{ __('Privacy Policy') }}</a>.
                    </span>
                </label>

                @error('agree_terms')
                    <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full transition-all duration-150 active:scale-[0.97]" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-neutral-500">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate class="font-semibold">{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>

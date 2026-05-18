<x-layouts::app.header :title="__('Terms and Conditions')">
    <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <article class="brand-panel p-6 sm:p-10">
            <p class="brand-kicker">{{ __('Legal') }}</p>
            <h1 class="brand-serif mt-4 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Terms and Conditions') }}</h1>
            <p class="mt-4 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                {{ __('Last updated: May 18, 2026. These Terms and Conditions govern your use of Sukimarket, a Philippine online marketplace connecting customers, vendors, riders, and administrators.') }}
            </p>

            <div class="mt-8 space-y-8 text-sm leading-7 text-neutral-600 dark:text-zinc-300">
                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Acceptance of Terms') }}</h2>
                    <p class="mt-3">{{ __('By creating an account, browsing listings, placing orders, selling goods, delivering orders, or using any Sukimarket feature, you agree to these Terms and all marketplace policies that apply to your role.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('User Accounts') }}</h2>
                    <p class="mt-3">{{ __('You are responsible for keeping your account information accurate and your password secure. Sukimarket may review, suspend, or restrict accounts that provide false information, misuse the platform, or create safety, fraud, or compliance risks.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Prohibited Conduct') }}</h2>
                    <p class="mt-3">{{ __('You must not use Sukimarket for illegal goods, fraud, harassment, impersonation, unauthorized access, spam, abuse of refunds or disputes, manipulation of listings or reviews, or conduct that violates Philippine consumer protection principles, trade laws, privacy laws, or public safety rules.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Intellectual Property') }}</h2>
                    <p class="mt-3">{{ __('Sukimarket owns or licenses the platform design, trademarks, logos, text, and software. Vendors remain responsible for the product images, names, descriptions, and other content they upload and must have the right to use them.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Limitation of Liability') }}</h2>
                    <p class="mt-3">{{ __('Sukimarket provides marketplace tools and does not guarantee that every transaction will be uninterrupted, error-free, or free from third-party conduct. To the extent allowed by Philippine law, Sukimarket is not liable for indirect, incidental, or consequential losses arising from platform use.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Governing Law') }}</h2>
                    <p class="mt-3">{{ __('These Terms are governed by the laws of the Republic of the Philippines, including applicable consumer protection, electronic commerce, privacy, and civil law principles. Disputes may be brought before the proper courts or agencies in the Philippines, subject to applicable rules.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Amendments') }}</h2>
                    <p class="mt-3">{{ __('Sukimarket may update these Terms when platform features, laws, or business needs change. Material updates will be posted on this page or communicated through reasonable platform notices.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Contact Us') }}</h2>
                    <p class="mt-3">{{ __('For legal questions, contact legal@sukimarket.ph or Sukimarket Legal Office, 12/F Marketplace Center, Ortigas Avenue, Pasig City, Metro Manila, Philippines.') }}</p>
                </section>
            </div>
        </article>
    </main>
</x-layouts::app.header>

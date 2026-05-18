<x-layouts::app.header :title="__('Privacy Policy')">
    <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <article class="brand-panel p-6 sm:p-10">
            <p class="brand-kicker">{{ __('Legal') }}</p>
            <h1 class="brand-serif mt-4 text-4xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Privacy Policy') }}</h1>
            <p class="mt-4 text-sm leading-7 text-neutral-500 dark:text-zinc-400">
                {{ __('Last updated: May 18, 2026. This Privacy Policy explains how Sukimarket collects, uses, stores, and protects personal information in line with the Data Privacy Act of 2012 (Republic Act No. 10173) and its implementing rules and regulations.') }}
            </p>

            <div class="mt-8 space-y-8 text-sm leading-7 text-neutral-600 dark:text-zinc-300">
                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Information We Collect') }}</h2>
                    <p class="mt-3">{{ __('We collect account details such as name, email address, password, phone number, delivery address, marketplace role, profile photo, and location coordinates when you choose to provide them. For vendors and riders, Sukimarket may also collect store, vehicle, application, order, payout, and compliance details needed to operate the marketplace.') }}</p>
                    <h3 class="mt-5 text-lg font-semibold text-neutral-900 dark:text-zinc-100">{{ __('Transactional and Technical Data') }}</h3>
                    <p class="mt-2">{{ __('We may collect order history, messages, uploaded attachments, device information, IP address, browser type, logs, and security events to keep the platform reliable and safe.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('How We Use Your Information') }}</h2>
                    <p class="mt-3">{{ __('Sukimarket uses your information to create accounts, process orders, coordinate delivery, verify vendors and riders, support customers, prevent fraud, improve platform features, send service messages, and comply with Philippine legal obligations.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Data Sharing') }}</h2>
                    <p class="mt-3">{{ __('We share only the information needed to complete marketplace activity. Customers may share delivery details with vendors and riders; vendors may receive order information; riders may receive pickup and delivery details; and administrators may review account and report records. We may also share data with service providers for hosting, email, payments, storage, analytics, legal compliance, and safety investigations.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Your Rights Under RA 10173') }}</h2>
                    <p class="mt-3">{{ __('Under the Data Privacy Act of 2012, you may request access to your personal information, correction of inaccurate data, deletion or blocking where allowed by law, withdrawal of consent for optional processing, data portability, and information about how your data is processed. Some requests may be limited when records must be retained for orders, disputes, safety, accounting, or legal compliance.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Cookies') }}</h2>
                    <p class="mt-3">{{ __('Sukimarket uses cookies and similar technologies for login sessions, security, preferences, analytics, and performance. You may adjust browser settings to limit cookies, but some features may stop working properly.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Data Retention') }}</h2>
                    <p class="mt-3">{{ __('We retain personal information only for as long as needed for marketplace operations, customer support, fraud prevention, legal compliance, tax and accounting requirements, dispute handling, and legitimate business purposes. When information is no longer needed, we securely delete, anonymize, or restrict it.') }}</p>
                </section>

                <section>
                    <h2 class="text-2xl font-bold text-neutral-900 dark:text-zinc-100">{{ __('Contact Us') }}</h2>
                    <p class="mt-3">{{ __('For privacy questions or rights requests, contact Sukimarket at legal@sukimarket.ph or write to Sukimarket Legal Office, 12/F Marketplace Center, Ortigas Avenue, Pasig City, Metro Manila, Philippines.') }}</p>
                </section>
            </div>
        </article>
    </main>
</x-layouts::app.header>

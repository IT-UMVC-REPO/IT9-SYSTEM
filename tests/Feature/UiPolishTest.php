<?php

function uiPolishBlade(string $pattern, ?string $exclude = null): string
{
    foreach (glob(resource_path($pattern)) as $path) {
        if ($exclude === null || ! str_contains($path, $exclude)) {
            return file_get_contents($path);
        }
    }

    throw new RuntimeException("No Blade file matched {$pattern}.");
}

test('shared app logo uses the current marketplace tagline', function () {
    $appLogo = uiPolishBlade('views/components/app-logo.blade.php');

    expect($appLogo)
        ->toContain("{{ __('Videre Est Scire') }}")
        ->toContain('tracking-[0.16em]')
        ->not->toContain('Your Local Market, Delivered')
        ->not->toContain('tracking-[0.28em]');
});

test('shared footer owns public contact and legal navigation', function () {
    $siteFooter = uiPolishBlade('views/components/site-footer.blade.php');
    $header = uiPolishBlade('views/layouts/app/header.blade.php');
    $welcome = uiPolishBlade('views/welcome.blade.php');

    expect($siteFooter)
        ->toContain('Videre Est Scire')
        ->toContain("route('contact.index')")
        ->toContain("route('legal.privacy-policy')")
        ->toContain("route('legal.terms-and-conditions')")
        ->toContain('data-test="site-footer"')
        ->toContain('lg:grid-cols-[minmax(0,1fr)_auto]')
        ->toContain('lg:py-7')
        ->toContain('flex flex-wrap gap-2')
        ->not->toContain('lg:py-14')
        ->not->toContain('sm:grid-cols-3')
        ->and($header)
        ->toContain('<x-site-footer />')
        ->not->toContain("{{ __('Contact Us') }}")
        ->not->toContain("route('contact.index')")
        ->and($welcome)
        ->not->toContain('<footer class=')
        ->not->toContain('$footerLinks');
});

test('shared app logo mark inherits the active brand color', function () {
    $appLogo = uiPolishBlade('views/components/app-logo.blade.php');
    $appCss = file_get_contents(resource_path('css/app.css'));

    expect($appLogo)
        ->toContain('brand-logo-mark')
        ->toContain("--suki-logo-mask: url('{{ asset('imgs/sukilogo.png') }}')")
        ->not->toContain('<img src="{{ asset(\'imgs/sukilogo.png\') }}"')
        ->and($appCss)
        ->toContain('.brand-logo-mark')
        ->toContain('background-color: currentColor;')
        ->toContain('mask: var(--suki-logo-mask) center / contain no-repeat;')
        ->toContain('color: var(--brand-700);')
        ->toContain('color: var(--brand-300);');
});

test('vendor registration sample products use single column image first layout', function () {
    $registration = uiPolishBlade('views/pages/vendor/*registration.blade.php');

    expect($registration)
        ->toContain('class="mt-6 space-y-4"')
        ->toContain('class="aspect-video w-full rounded-2xl object-cover"')
        ->toContain('class="flex aspect-video w-full cursor-pointer')
        ->not->toContain('lg:grid-cols-[280px_minmax(0,1fr)]');
});

test('messaging pending attachments render filename chips without temporary image previews', function () {
    $conversation = uiPolishBlade('views/pages/messages/*conversation.blade.php', 'group-conversation');
    $groupConversation = uiPolishBlade('views/pages/messages/*group-conversation.blade.php');
    $groupComponent = file_get_contents(app_path('Livewire/Pages/Messages/GroupConversation.php'));

    expect($conversation)
        ->toContain('class="mt-2 flex flex-wrap gap-2"')
        ->toContain('$upload->getClientOriginalName()')
        ->not->toContain('$upload->temporaryUrl()')
        ->and($groupConversation)
        ->toContain('class="mt-2 flex flex-wrap gap-2"')
        ->toContain('$upload->getClientOriginalName()')
        ->not->toContain('$upload->temporaryUrl()');
});

test('contact form phone copy and admin inbox live refresh stay compact', function () {
    $contactForm = uiPolishBlade('views/livewire/contact-form.blade.php');
    $contactComponent = file_get_contents(app_path('Livewire/ContactForm.php'));
    $adminMessages = uiPolishBlade('views/pages/admin/*contact-messages.blade.php');

    expect($contactForm)
        ->toContain('placeholder="+63 917 123 4567"')
        ->not->toContain('Optional. Use')
        ->and($contactComponent)
        ->toContain('normalizePhoneForValidation')
        ->toContain("preg_replace('/[\\s().-]+/'")
        ->not->toContain('Please enter a valid Philippine mobile number (e.g., 09171234567).')
        ->and($adminMessages)
        ->toContain('wire:poll.visible.15s')
        ->toContain('whitespace-nowrap')
        ->not->toContain('tracking-[0.16em]');
});

test('modal components avoid nested card wrappers', function () {
    $confirmationModal = uiPolishBlade('views/components/confirmation-modal.blade.php');
    $createAdminModal = uiPolishBlade('views/pages/admin/*create-admin-modal.blade.php');
    $reportModal = uiPolishBlade('views/components/report/*report-modal.blade.php');

    expect($confirmationModal)
        ->toContain('<flux:modal name="{{ $name }}" class="{{ $maxWidth }} p-6 sm:p-7"')
        ->not->toContain('border border-stone-200 bg-white p-6 shadow-2xl')
        ->and($createAdminModal)
        ->toContain('<flux:modal name="create-admin" class="max-h-[90vh] max-w-md overflow-y-auto p-6 sm:p-7">')
        ->not->toContain('rounded-[1.75rem] border border-stone-200 bg-white p-6 shadow-2xl')
        ->and($reportModal)
        ->toContain('class="max-w-2xl overflow-hidden"')
        ->not->toContain('p-0! shadow-none! ring-0!');
});

test('vendor cards and order tracking use compact polished classes', function () {
    $vendorCard = uiPolishBlade('views/components/vendor-card.blade.php');
    $orders = uiPolishBlade('views/pages/shop/*orders.blade.php');

    expect($vendorCard)
        ->toContain('aspect-[3/2] w-full object-cover')
        ->toContain('line-clamp-2 text-sm leading-relaxed')
        ->toContain('group/btn relative flex w-full items-center justify-center')
        ->and($orders)
        ->toContain('<span class="brand-kicker">{{ __(\'Order tracking\') }}</span>')
        ->not->toContain('border-emerald-800/50 bg-emerald-950/40 px-4 py-2');
});

test('video call overlays use compact header controls', function () {
    $conversation = uiPolishBlade('views/pages/messages/*conversation.blade.php', 'group-conversation');
    $groupConversation = uiPolishBlade('views/pages/messages/*group-conversation.blade.php');
    $videoCallControl = file_get_contents(resource_path('js/video-call-control.js'));

    expect($conversation)
        ->toContain('data-conversation-video-call')
        ->toContain('x-on:video-call-start.window="startCall()"')
        ->toContain('<flux:icon.microphone variant="mini" />')
        ->toContain('<flux:icon.video-camera-slash variant="mini" />')
        ->not->toContain('CALL STATUS')
        ->and($groupConversation)
        ->toContain('data-group-video-call')
        ->toContain('x-on:group-call-start.window="startCall()"')
        ->toContain('<flux:icon.microphone variant="mini" />')
        ->toContain('<flux:icon.video-camera-slash variant="mini" />')
        ->and($videoCallControl)
        ->toContain('$el.closest(\'[data-conversation-video-call]\')?.__conversationVideoCall');
});

test('map components keep complex leaflet setup out of inline alpine', function () {
    $vendorMap = uiPolishBlade('views/components/vendor-location-map.blade.php');
    $orderMap = uiPolishBlade('views/components/order-location-map.blade.php');
    $appJs = file_get_contents(resource_path('js/app.js'));
    $vendorMapJs = file_get_contents(resource_path('js/maps/vendor-map.js'));

    expect($vendorMap)
        ->toContain('x-data="vendorLocationMap(@js($mapId), @js($zoom), @js($vendorPoint))"')
        ->toContain('style="height: {{ $height }}"')
        ->not->toContain('map'.'Full'.'screen')
        ->not->toContain('href=&quot;https://www.openstreetmap.org/copyright&quot;')
        ->and($orderMap)
        ->toContain('x-data="sukiOrderLocationMap({')
        ->not->toContain('sukiVendorMap()')
        ->and($appJs)
        ->toContain('window.vendorLocationMap = (mapId, zoom, vendor) => ({')
        ->toContain('initialized: false')
        ->toContain('Number.isFinite(vendorLat)')
        ->toContain('router.project-osrm.org/route/v1/driving')
        ->toContain('const routeProviderCooldowns = new Map();')
        ->toContain('const routeCache = new Map();')
        ->toContain('routeRequestPendingKey: null')
        ->toContain('hasAutoAdjustedViewport: false')
        ->toContain("this.map?.on('dragstart zoomstart'")
        ->toContain('return !this.hasUserAdjustedView && !this.hasAutoAdjustedViewport;')
        ->toContain('if (this.routeRequestPendingKey === routeKey || (this.routeLine && this.routeRequestKey === routeKey))')
        ->toContain('attributionControl: false')
        ->toContain('L.control.attribution({ prefix: false }).addTo(this.map);')
        ->not->toContain('L.polyline(bounds')
        ->not->toContain('distanceInKilometers(')
        ->and($vendorMapJs)
        ->toContain('const routeProviderCooldowns = new Map();')
        ->toContain('const routeCache = new Map();')
        ->toContain("if (response.status === 429) {\n                coolDownRouteProvider('valhalla');");
});

test('profile map and audit date picker use shared javascript helpers', function () {
    $profile = uiPolishBlade('views/pages/settings/*profile.blade.php');
    $auditLog = uiPolishBlade('views/pages/admin/*audit-log.blade.php');
    $head = uiPolishBlade('views/partials/head.blade.php');
    $appJs = file_get_contents(resource_path('js/app.js'));
    $appCss = file_get_contents(resource_path('css/app.css'));

    expect($profile)
        ->toContain('x-data="sukiProfileMap({')
        ->toContain("mapId: 'profile-location-map'")
        ->toContain("x-on:input.debounce.600ms=\"window.dispatchEvent(new CustomEvent('profile-address-updated'")
        ->and($auditLog)
        ->toContain('x-data="sukiDatePicker({')
        ->toContain('type="text"')
        ->not->toContain('type="date"')
        ->and($head)
        ->toContain('flatpickr/4.6.13/flatpickr.min.css')
        ->toContain('flatpickr/4.6.13/flatpickr.min.js')
        ->and($appJs)
        ->toContain('window.sukiProfileMap = (config) => ({')
        ->toContain('nominatim.openstreetmap.org/search')
        ->toContain('window.sukiDatePicker = (config) => ({')
        ->and($appCss)
        ->toContain('.flatpickr-calendar')
        ->toContain('.flatpickr-day.selected');
});

test('shared head exposes branded social preview metadata', function () {
    $head = uiPolishBlade('views/partials/head.blade.php');

    expect($head)
        ->toContain('$sukiTitle')
        ->toContain('$sukiText')
        ->toContain('$sukiHeader = secure_asset(\'imgs/sukiheader.webp\')')
        ->toContain('$sukiUrl = secure_url(request()->path())')
        ->toContain('<meta property="og:site_name" content="SukiMarket" />')
        ->toContain('<meta property="og:url" content="{{ $sukiUrl }}" />')
        ->toContain('<meta property="og:title" content="{{ $sukiTitle }}" />')
        ->toContain('<meta property="og:description" content="{{ $sukiText }}" />')
        ->toContain('<meta property="og:image" content="{{ $sukiHeader }}" />')
        ->toContain('<meta property="og:image:secure_url" content="{{ $sukiHeader }}" />')
        ->toContain('<meta property="og:image:alt" content="{{ __(\'SukiMarket local market stalls with fresh produce\') }}" />')
        ->toContain('<meta name="twitter:card" content="summary_large_image" />')
        ->toContain('<meta name="twitter:title" content="{{ $sukiTitle }}" />')
        ->toContain('<meta name="twitter:description" content="{{ $sukiText }}" />')
        ->toContain('<meta name="twitter:image" content="{{ $sukiHeader }}" />');
});

test('group message reactions escape clipping and overlap bubble corners', function () {
    $groupConversation = uiPolishBlade('views/pages/messages/*group-conversation.blade.php');

    expect($groupConversation)
        ->toContain('x-teleport="body"')
        ->toContain('x-bind:style="pickerStyle"')
        ->toContain('relative mb-4 inline-block')
        ->toContain('absolute -bottom-3 left-2')
        ->toContain('inline-flex self-start border border-stone-200 bg-stone-50 px-3.5 py-1');
});

test('vendor product forms use compact conversion controls and temporary upload previews', function () {
    $productCreate = uiPolishBlade('views/pages/vendor/*product-create.blade.php');
    $productEdit = uiPolishBlade('views/pages/vendor/*product-edit.blade.php');
    $registration = uiPolishBlade('views/pages/vendor/*registration.blade.php');
    $products = uiPolishBlade('views/pages/vendor/*products.blade.php');
    $stocks = uiPolishBlade('views/pages/vendor/*stocks.blade.php');
    $vendorManagementTabs = uiPolishBlade('views/components/vendor-management-tabs.blade.php');

    expect($productCreate)
        ->toContain('<flux:input.group.prefix>&#8369;</flux:input.group.prefix>')
        ->toContain('wire:model.live.debounce.250ms="price"')
        ->toContain('class:input="h-11"')
        ->toContain('model="stock_quantity"')
        ->toContain(':suffix="$selectedUnit?->abbreviation()"')
        ->toContain("__('How many :base per 1 :unit?'")
        ->toContain('border-2 border-[var(--brand-500)]')
        ->toContain('$productImageUpload->temporaryUrl()')
        ->not->toContain('php artisan storage:link')
        ->not->toContain('border-l-4 border-l-[var(--brand-600)]')
        ->and($productEdit)
        ->toContain('<flux:input.group.prefix>&#8369;</flux:input.group.prefix>')
        ->toContain('model="stock_quantity"')
        ->toContain(':suffix="$selectedUnit?->abbreviation()"')
        ->toContain('$productImageUpload->temporaryUrl()')
        ->not->toContain('php artisan storage:link')
        ->not->toContain('border-left-color: var(--brand-600)')
        ->and($registration)
        ->toContain('$storeImageUpload->temporaryUrl()')
        ->toContain('$sampleProductUpload->temporaryUrl()')
        ->not->toContain('php artisan storage:link')
        ->and($products)
        ->toContain('<x-vendor-management-tabs />')
        ->toContain('flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-stone-200 pb-5')
        ->not->toContain('<flux:navbar>')
        ->and($stocks)
        ->toContain('<x-vendor-management-tabs />')
        ->not->toContain('<flux:navbar>')
        ->not->toContain('border-b-4 px-4 py-4')
        ->and($vendorManagementTabs)
        ->toContain('Stock Manager')
        ->toContain('Orders')
        ->toContain('Sales');
});

test('message groups anchor sender avatars to the final bubble row', function () {
    $conversation = uiPolishBlade('views/pages/messages/*conversation.blade.php', 'group-conversation');
    $groupConversation = uiPolishBlade('views/pages/messages/*group-conversation.blade.php');

    expect($conversation)
        ->toContain('$isGroupedWithNext')
        ->toContain('@if ($isGroupedWithNext)')
        ->and($groupConversation)
        ->toContain('$showSenderAvatar = ! $isOwnMessage && ! $isGroupedWithNext;')
        ->toContain("'items-end' => true")
        ->not->toContain("'items-start' => \$showSenderLabel")
        ->not->toContain('class="mt-5 shrink-0"');
});

test('shared layouts and compact commerce chrome use the requested polish classes', function () {
    $header = uiPolishBlade('views/layouts/app/header.blade.php');
    $authSimple = uiPolishBlade('views/layouts/auth/simple.blade.php');
    $authCard = uiPolishBlade('views/layouts/auth/card.blade.php');
    $addToCart = uiPolishBlade('views/components/cart/*add-to-cart.blade.php');

    expect($header)
        ->toContain('<flux:toast.group position="bottom left">')
        ->not->toContain("\$navItem('Audit Log', 'admin.audit'")
        ->not->toContain("\$navItem('Audit', 'admin.audit'")
        ->and($authSimple)
        ->toContain('<flux:toast.group position="bottom left">')
        ->and($authCard)
        ->toContain('<flux:toast.group position="bottom left">')
        ->and($addToCart)
        ->not->toContain('$product->unit->abbreviation()');
});

test('scroll reveal and navigation progress have safety fallbacks', function () {
    $appJs = file_get_contents(resource_path('js/app.js'));
    $appCss = file_get_contents(resource_path('css/app.css'));
    $header = uiPolishBlade('views/layouts/app/header.blade.php');
    $authSimple = uiPolishBlade('views/layouts/auth/simple.blade.php');
    $authCard = uiPolishBlade('views/layouts/auth/card.blade.php');

    expect($appJs)
        ->toContain("Array.from(document.querySelectorAll('.suki-reveal:not(.is-visible)'))")
        ->toContain('getBoundingClientRect()')
        ->toContain("document.addEventListener('livewire:updated', () => scheduleSukiReveal(50));")
        ->toContain("livewire.hook('morphed', () => scheduleSukiReveal());")
        ->toContain("livewire.hook('morph.added'")
        ->toContain("threshold: 0, rootMargin: '0px 0px 0px 0px'")
        ->toContain('window._sukiRevealFallback')
        ->toContain("document.body.style.overflow = '';")
        ->toContain("document.documentElement.style.overflow = '';")
        ->and($appCss)
        ->toContain('pointer-events: none;')
        ->toContain('user-select: none;')
        ->not->toContain('.brand-floating-card:hover')
        ->and($header)
        ->toContain('barSafetyTimer')
        ->toContain('}, 6000);')
        ->and($authSimple)
        ->toContain('barSafetyTimer')
        ->toContain('}, 6000);')
        ->and($authCard)
        ->toContain('barSafetyTimer')
        ->toContain('}, 6000);');
});

test('bug fix pass removes reveal dependencies from always visible panels', function () {
    $catalog = uiPolishBlade('views/pages/shop/*catalog-browser.blade.php');
    $vendors = uiPolishBlade('views/pages/shop/*vendors.blade.php');
    $orders = uiPolishBlade('views/pages/shop/*orders.blade.php');
    $settings = uiPolishBlade('views/pages/settings/layout.blade.php');
    $registration = uiPolishBlade('views/pages/vendor/*registration.blade.php');
    $vendorProducts = uiPolishBlade('views/pages/vendor/*products.blade.php');
    $customerDashboard = uiPolishBlade('views/pages/customer/*dashboard.blade.php');
    $vendorDashboard = uiPolishBlade('views/pages/vendor/*dashboard.blade.php');
    $adminDashboard = uiPolishBlade('views/pages/admin/*dashboard.blade.php');
    $reportDetail = uiPolishBlade('views/pages/admin/*report-detail.blade.php');

    expect($catalog)
        ->not->toContain('class="suki-reveal group')
        ->not->toContain('transition-delay: {{ min($loop->index * 50, 400) }}ms')
        ->and($vendors)
        ->toContain('bg-neutral-950/40 px-4 py-6 sm:py-6')
        ->not->toContain('brand-panel suki-reveal')
        ->not->toContain('class="suki-reveal"')
        ->not->toContain('transition-delay: {{ min($loop->index * 60, 400) }}ms')
        ->not->toContain('bg-neutral-950/55 px-4 py-6 backdrop-blur-sm')
        ->and($orders)
        ->toContain('brand-panel-muted flex flex-wrap gap-2 rounded-[2rem] p-3')
        ->toContain('class="inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold transition-all duration-150 active:scale-[0.97]"')
        ->toContain('class="brand-panel overflow-hidden p-6 sm:p-7"')
        ->not->toContain('brand-panel-muted grid gap-3 rounded-[2rem] p-3')
        ->not->toContain('class="brand-panel suki-reveal overflow-hidden p-6 sm:p-7"')
        ->and($settings)
        ->toContain('settings-content-panel p-4 sm:p-8')
        ->not->toContain('settings-content-panel suki-reveal')
        ->and($registration)
        ->not->toContain('x-show="true"')
        ->not->toContain('class="brand-panel suki-reveal p-6 sm:p-8"')
        ->not->toContain('class="suki-reveal space-y-6')
        ->and($vendorProducts)
        ->toContain('class="brand-panel flex h-full flex-col p-5 sm:p-6"')
        ->not->toContain('class="brand-panel suki-reveal flex h-full flex-col p-5 sm:p-6"')
        ->and($customerDashboard)
        ->toContain('wire:key="customer-dashboard-stat-{{ Str::slug($stat[\'label\']) }}"')
        ->not->toContain('customer-dashboard-stat-{{ Str::slug($stat[\'label\']) }}" style="transition-delay')
        ->not->toContain('wire:key="customer-dashboard-order-{{ $order->id }}" style="transition-delay')
        ->and($vendorDashboard)
        ->not->toContain('wire:key="vendor-dashboard-order-{{ $order->id }}" style="transition-delay')
        ->not->toContain('wire:key="vendor-low-stock-{{ $product->id }}" style="transition-delay')
        ->and($adminDashboard)
        ->toContain('max-h-64')
        ->toContain('<div class="flex items-baseline gap-2">')
        ->not->toContain('<div class="suki-reveal flex items-baseline gap-2"')
        ->and($reportDetail)
        ->toContain("\$this->dispatch('page-updated');")
        ->toContain('x-on:page-updated.window="$nextTick(() => window.sukiRevealAll && window.sukiRevealAll())"')
        ->toContain('x-init="$nextTick(() => window.sukiRevealAll && window.sukiRevealAll())"');
});

test('application javascript uses the auto injected livewire runtime once', function () {
    $appJs = file_get_contents(resource_path('js/app.js'));
    $header = uiPolishBlade('views/layouts/app/header.blade.php');

    expect($appJs)
        ->toContain('const livewire = window.Livewire;')
        ->not->toContain('vendor/livewire/livewire/dist/livewire.esm')
        ->not->toContain('Livewire.start(')
        ->and($header)
        ->toContain('@fluxScripts');
});

test('view transition names stay unique on conversation surfaces', function () {
    $appCss = file_get_contents(resource_path('css/app.css'));

    expect($appCss)
        ->toContain('body > header')
        ->toContain('view-transition-name: suki-header')
        ->not->toContain("\n    header {\n        view-transition-name: suki-header;");
});

test('shared mobile shell constrains horizontal overflow', function () {
    $appLayout = uiPolishBlade('views/layouts/app.blade.php');
    $header = uiPolishBlade('views/layouts/app/header.blade.php');
    $appCss = file_get_contents(resource_path('css/app.css'));

    expect($appLayout)
        ->toContain('min-w-0 overflow-x-clip')
        ->toContain('class="suki-page-enter min-w-0 overflow-x-clip"')
        ->and($header)
        ->toContain('overflow-x-clip')
        ->and($appCss)
        ->toContain('max-width: 100%;')
        ->toContain('overflow-wrap: anywhere;')
        ->toContain('.brand-panel {')
        ->toContain('.brand-button-primary {')
        ->toContain('.brand-mobile-nav-link {')
        ->toContain('min-width: 0;');
});

test('admin and notification list items constrain long text and unread borders uniformly', function () {
    $vendors = uiPolishBlade('views/pages/admin/*vendors.blade.php');
    $users = uiPolishBlade('views/pages/admin/*users.blade.php');
    $notificationBell = uiPolishBlade('views/components/notifications/*notification-bell.blade.php');
    $notificationIndex = uiPolishBlade('views/pages/notifications/*index.blade.php');

    expect($vendors)
        ->toContain('class="h-10 w-10 shrink-0 overflow-hidden rounded-full border border-stone-200 dark:border-white/10"')
        ->toContain('class="truncate font-semibold text-neutral-900 dark:text-zinc-100"')
        ->toContain('class="truncate text-sm text-neutral-500 dark:text-zinc-400"')
        ->and($users)
        ->toContain('class="min-w-0 max-w-[16rem] overflow-hidden"')
        ->toContain('class="mt-2 truncate text-sm text-neutral-600 dark:text-zinc-300"')
        ->and($notificationBell)
        ->toContain('border-2 border-[var(--brand-500)]')
        ->toContain('dark:bg-zinc-800/90')
        ->not->toContain('border-l-4 border-l-[var(--brand-500)]')
        ->and($notificationIndex)
        ->toContain('border-2 border-[var(--brand-500)]')
        ->not->toContain('border-l-4 border-l-[var(--brand-500)]');
});

test('deleted chat messages are inert tombstones and invite links copy without showing tokens', function () {
    $conversation = uiPolishBlade('views/pages/messages/*conversation.blade.php', 'group-conversation');
    $groupConversation = uiPolishBlade('views/pages/messages/*group-conversation.blade.php');
    $groupComponent = file_get_contents(app_path('Livewire/Pages/Messages/GroupConversation.php'));

    expect($conversation)
        ->toContain('pointer-events-none rounded-2xl bg-stone-100')
        ->toContain('@if (! $isDeleted && $attachments->isNotEmpty())')
        ->not->toContain('aria-label="{{ __(\'Copy message\') }}"')
        ->and($groupConversation)
        ->toContain('x-on:copy-invite-link.window="copyInviteLink($event.detail.url)"')
        ->toContain('pointer-events-none rounded-2xl bg-stone-100')
        ->toContain('@if (! $isDeleted && $attachments->isNotEmpty())')
        ->toContain('{{ __(\'Copy invite\') }}')
        ->not->toContain('?invite=\'.$this->group->invite_token')
        ->not->toContain('aria-label="{{ __(\'Copy message\') }}"')
        ->and($groupComponent)
        ->toContain("Flux::toast(variant: 'success', text: __('Invite Link Copied'))")
        ->toContain("\$this->dispatch('copy-invite-link'");
});

test('round three chat controls expose edit pin inbox archive and group profile actions', function () {
    $conversation = uiPolishBlade('views/pages/messages/*conversation.blade.php', 'group-conversation');
    $groupConversation = uiPolishBlade('views/pages/messages/*group-conversation.blade.php');
    $sidebar = uiPolishBlade('views/components/messages/*conversation-sidebar.blade.php');
    $groupComponent = file_get_contents(app_path('Livewire/Pages/Messages/GroupConversation.php'));

    expect($conversation)
        ->toContain('wire:click.stop="pinMessage')
        ->toContain('$wire.editMessage')
        ->toContain('flex flex-row items-center gap-1 opacity-0 transition group-hover:opacity-100')
        ->toContain('data-no-loading-spinner')
        ->toContain("wire:target=\"deleteMessage({{ \$message['id'] }}, true)\"")
        ->not->toContain('flex flex-col gap-1 opacity-0 transition group-hover:opacity-100')
        ->not->toContain('wire:loading.remove wire:target="deleteMessage')
        ->not->toContain('wire:loading.remove wire:target="send"')
        ->not->toContain('h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent')
        ->and($groupConversation)
        ->toContain('wire:click="pinMessage')
        ->toContain('$wire.editMessage')
        ->toContain('wire:model="groupAvatarUpload"')
        ->toContain('wire:change="setMemberNickname')
        ->toContain('data-no-loading-spinner')
        ->toContain("wire:target=\"deleteMessage({{ \$message['id'] }}, true)\"")
        ->not->toContain('wire:loading.remove wire:target="deleteMessage')
        ->not->toContain('wire:loading.remove wire:target="send"')
        ->not->toContain('h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent')
        ->and($groupComponent)
        ->toContain('public mixed $groupAvatarUpload = null;')
        ->toContain("'avatar_path' => \$avatarPath")
        ->and($sidebar)
        ->toContain('archiveDirectThread')
        ->toContain('unarchiveDirectThread')
        ->toContain('deleteDirectThread')
        ->toContain('archiveGroupThread')
        ->toContain('unarchiveGroupThread')
        ->toContain('deleteGroupThread')
        ->toContain('showArchivedThreads')
        ->toContain('data-no-loading-spinner')
        ->toContain('[&_.avatar-frame]:ring-emerald-400')
        ->not->toContain('title="{{ __(\'Online\') }}"')
        ->not->toContain('<flux:icon.archive-box')
        ->not->toContain('<flux:icon.trash');
});

test('mobile shell integration has been removed from the application', function () {
    $needle = 'native'.'php';
    $composer = file_get_contents(base_path('composer.json'));
    $lock = file_get_contents(base_path('composer.lock'));

    expect(file_exists(config_path($needle.'.php')))->toBeFalse()
        ->and(file_exists(base_path('native')))->toBeFalse()
        ->and(file_exists(base_path($needle.'.lock')))->toBeFalse()
        ->and(is_dir(base_path($needle)))->toBeFalse()
        ->and(file_exists(base_path('tests/Feature/NativePhpConfigurationTest.php')))->toBeFalse()
        ->and(mb_strtolower($composer))->not->toContain($needle)
        ->and(mb_strtolower($lock))->not->toContain($needle);
});

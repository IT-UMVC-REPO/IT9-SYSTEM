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

    expect($conversation)
        ->toContain('class="mt-2 flex flex-wrap gap-2"')
        ->toContain('$upload->getClientOriginalName()')
        ->not->toContain('$upload->temporaryUrl()')
        ->and($groupConversation)
        ->toContain('class="mt-2 flex flex-wrap gap-2"')
        ->toContain('$upload->getClientOriginalName()')
        ->not->toContain('$upload->temporaryUrl()');
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

    expect($vendorMap)
        ->toContain('x-data="vendorLocationMap(@js($mapId), @js($zoom), @js($vendorPoint))"')
        ->not->toContain('href=&quot;https://www.openstreetmap.org/copyright&quot;')
        ->and($orderMap)
        ->toContain('x-data="sukiOrderLocationMap({')
        ->not->toContain('sukiVendorMap()')
        ->and($appJs)
        ->toContain('window.vendorLocationMap = (mapId, zoom, vendor) => ({')
        ->toContain('router.project-osrm.org/route/v1/driving')
        ->toContain('attributionControl: false')
        ->toContain('L.control.attribution({ prefix: false }).addTo(this.map);')
        ->not->toContain('L.polyline(bounds')
        ->not->toContain('distanceInKilometers(');
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

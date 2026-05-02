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
        ->toContain('aspect-[4/3] w-full rounded-t-[2rem]')
        ->toContain('line-clamp-1 text-sm leading-6')
        ->toContain('brand-button-secondary mt-auto w-full !py-2')
        ->and($orders)
        ->toContain('<span class="brand-kicker">{{ __(\'Order tracking\') }}</span>')
        ->not->toContain('border-emerald-800/50 bg-emerald-950/40 px-4 py-2');
});

test('video call overlays use compact header controls', function () {
    $conversation = uiPolishBlade('views/pages/messages/*conversation.blade.php', 'group-conversation');
    $groupConversation = uiPolishBlade('views/pages/messages/*group-conversation.blade.php');

    expect($conversation)
        ->toContain('$el.closest(\'[data-conversation-video-call]\').__conversationVideoCall')
        ->toContain('fa-microphone-slash')
        ->toContain('fa-video-slash')
        ->not->toContain('CALL STATUS')
        ->and($groupConversation)
        ->toContain('$el.closest(\'[data-group-video-call]\').__groupConversationVideoCall')
        ->toContain('fa-microphone-slash')
        ->toContain('fa-video-slash');
});

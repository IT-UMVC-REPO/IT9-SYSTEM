<?php

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function createMarketplaceMessage(User $sender, User $receiver, string $content, array $overrides = []): Message
{
    return Message::query()->create(array_merge([
        'sender_id' => $sender->getKey(),
        'receiver_id' => $receiver->getKey(),
        'order_id' => null,
        'content' => $content,
        'is_read' => false,
        'created_at' => now(),
    ], $overrides));
}

test('inbox shows threads the user is part of', function () {
    $user = User::factory()->create();
    $vendor = User::factory()->create([
        'name' => 'Vendor Ramon',
    ]);
    $customer = User::factory()->create([
        'name' => 'Buyer Lea',
    ]);

    createMarketplaceMessage($vendor, $user, 'Older vendor note', [
        'created_at' => now()->subMinutes(20),
    ]);
    createMarketplaceMessage($user, $vendor, 'Latest vendor reply', [
        'created_at' => now()->subMinutes(5),
    ]);
    createMarketplaceMessage($customer, $user, 'Can you confirm the order time?', [
        'created_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($user)
        ->get(route('messages.inbox'))
        ->assertOk()
        ->assertSee('Vendor Ramon')
        ->assertSee('Buyer Lea')
        ->assertSee('Add contact')
        ->assertSee('Latest vendor reply')
        ->assertDontSee('Older vendor note');
});

test('direct message modal searches contacts and redirects to the selected conversation', function () {
    $user = User::factory()->create([
        'name' => 'Current User',
    ]);
    $target = User::factory()->create([
        'name' => 'Aling Marta',
    ]);
    $other = User::factory()->create([
        'name' => 'Kuya Ben',
    ]);

    Livewire::actingAs($user)
        ->test('messaging.create-direct-modal')
        ->set('search', 'Marta')
        ->assertSee('Aling Marta')
        ->assertDontSee('Current User')
        ->assertDontSee('Kuya Ben')
        ->call('openConversation', $target->getKey())
        ->assertRedirect(route('messages.conversation', ['conversationReference' => $target->getKey()]));
});

test('inbox does not show other users threads', function () {
    $user = User::factory()->create();
    $otherA = User::factory()->create();
    $otherB = User::factory()->create();

    createMarketplaceMessage($otherA, $otherB, 'Private between others');

    $this->actingAs($user)
        ->get(route('messages.inbox'))
        ->assertOk()
        ->assertDontSee('Private between others');
});

test('sending a message creates a message record', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::messages.conversation', ['conversationReference' => (string) $otherUser->getKey()])
        ->set('newMessage', 'Can you confirm today\'s stock?')
        ->call('send')
        ->assertDispatched('message-sent');

    $messages = $component->get('messages');

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['content'])->toBe('Can you confirm today\'s stock?')
        ->and($messages[0]['sender_id'])->toBe($user->getKey());

    expect(Message::query()
        ->where('sender_id', $user->getKey())
        ->where('receiver_id', $otherUser->getKey())
        ->where('content', 'Can you confirm today\'s stock?')
        ->exists())->toBeTrue();

    Event::assertDispatched(MessageSent::class);
});

test('reverb allows client whispers on private channels', function () {
    expect(config('reverb.apps.apps.0.accept_client_events_from'))->toBe('all');
});

test('sending a message with an attachment stores metadata', function () {
    Storage::fake('public');
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $attachment = UploadedFile::fake()->create('market-note.pdf', 64, 'application/pdf');

    Livewire::actingAs($user)
        ->test('pages::messages.conversation', ['conversationReference' => (string) $otherUser->getKey()])
        ->set('newMessage', '')
        ->set('attachmentUploads', [$attachment])
        ->call('send')
        ->assertHasNoErrors()
        ->assertDispatched('message-sent');

    $message = Message::query()
        ->where('sender_id', $user->getKey())
        ->where('receiver_id', $otherUser->getKey())
        ->latest('id')
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->content)->toBe('')
        ->and($message->attachment_name)->toBe('market-note.pdf')
        ->and($message->attachment_path)->not->toBeNull()
        ->and($message->attachments)->toHaveCount(1)
        ->and(Storage::disk('public')->exists($message->attachment_path))->toBeTrue();
});

test('sending a message stores multiple attachments', function () {
    Storage::fake('public');
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::messages.conversation', ['conversationReference' => (string) $otherUser->getKey()])
        ->set('newMessage', 'See these files')
        ->set('attachmentUploads', [
            UploadedFile::fake()->create('one.jpg', 64, 'image/jpeg'),
            UploadedFile::fake()->create('two.pdf', 64, 'application/pdf'),
        ])
        ->call('send')
        ->assertHasNoErrors()
        ->assertDispatched('message-sent');

    $message = Message::query()
        ->where('sender_id', $user->getKey())
        ->where('receiver_id', $otherUser->getKey())
        ->latest('id')
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->attachments()->count())->toBe(2)
        ->and($message->attachment_path)->toBe($message->attachments()->oldest('id')->first()->path);
});

test('legacy attachment metadata still renders through display attachments', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $message = createMarketplaceMessage($sender, $receiver, 'Legacy file', [
        'attachment_path' => 'message-attachments/legacy.pdf',
        'attachment_name' => 'legacy.pdf',
        'attachment_mime' => 'application/pdf',
        'attachment_size' => 1024,
    ]);

    expect($message->attachmentsForDisplay())->toHaveCount(1)
        ->and($message->attachmentsForDisplay()->first()->path)->toBe('message-attachments/legacy.pdf');
});

test('conversation attachment links use the configured public disk url', function () {
    config(['filesystems.disks.public.url' => 'https://pub.example.test']);

    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $message = createMarketplaceMessage($sender, $receiver, 'Here is the file');

    $message->attachments()->create([
        'path' => 'message-attachments/r2-proof.jpg',
        'name' => 'r2-proof.jpg',
        'mime' => 'image/jpeg',
        'size' => 1024,
        'created_at' => now(),
    ]);
    $message->attachments()->create([
        'path' => 'https://cdn.example.test/message-attachments/external-proof.jpg',
        'name' => 'external-proof.jpg',
        'mime' => 'image/jpeg',
        'size' => 1024,
        'created_at' => now(),
    ]);

    $this->actingAs($receiver)
        ->get(route('messages.conversation', ['conversationReference' => $sender->getKey()]))
        ->assertOk()
        ->assertSee('https://pub.example.test/message-attachments/r2-proof.jpg', false)
        ->assertSee('https://cdn.example.test/message-attachments/external-proof.jpg', false)
        ->assertSee('referrerpolicy="no-referrer"', false)
        ->assertSee("onerror=\"this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden'); this.nextElementSibling.classList.add('flex');\"", false)
        ->assertSee('Image unavailable')
        ->assertDontSee('crossorigin="anonymous"', false)
        ->assertDontSee('https://placehold.co', false)
        ->assertSee('x-data="{ showTime: false }"', false)
        ->assertSee('x-on:click.stop="showTime = ! showTime"', false)
        ->assertSee('x-show="showTime"', false)
        ->assertDontSee('/storage/message-attachments/r2-proof.jpg', false)
        ->assertDontSee('https://pub.example.test/https://cdn.example.test/message-attachments/external-proof.jpg', false);
});

test('sending a message rejects unsupported attachment types', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $attachment = UploadedFile::fake()->create('payload.exe', 64, 'application/octet-stream');

    Livewire::actingAs($user)
        ->test('pages::messages.conversation', ['conversationReference' => (string) $otherUser->getKey()])
        ->set('newMessage', '')
        ->set('attachmentUploads', [$attachment])
        ->call('send')
        ->assertHasErrors(['attachmentUploads.0']);

    expect(Message::query()
        ->where('sender_id', $user->getKey())
        ->where('receiver_id', $otherUser->getKey())
        ->count())->toBe(0);
});

test('messages are marked read when the conversation is opened', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $message = createMarketplaceMessage($otherUser, $user, 'Unread note from the vendor');

    Livewire::actingAs($user)
        ->test('pages::messages.conversation', ['conversationReference' => (string) $otherUser->getKey()]);

    expect($message->fresh()->is_read)->toBeTrue();
});

test('users cannot message themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('messages.conversation', ['conversationReference' => $user->getKey()]))
        ->assertForbidden();
});

test('thread shows messages in chronological order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $yesterdayAt = now()->subDay()->setTime(9, 15);
    $todayAt = now()->setTime(10, 30);
    $readAt = now()->setTime(10, 35);

    createMarketplaceMessage($user, $otherUser, 'First message', [
        'created_at' => $yesterdayAt,
    ]);
    createMarketplaceMessage($otherUser, $user, 'Second message', [
        'created_at' => $todayAt,
    ]);
    createMarketplaceMessage($user, $otherUser, 'Latest read message', [
        'created_at' => $readAt,
        'is_read' => true,
    ]);

    $this->actingAs($user)
        ->get(route('messages.conversation', ['conversationReference' => $otherUser->getKey()]))
        ->assertOk()
        ->assertSeeInOrder(['Yesterday', 'First message', 'Today', 'Second message', 'Latest read message'])
        ->assertSee($yesterdayAt->format('g:i A'))
        ->assertSee($readAt->format('g:i A'))
        ->assertSee('Active now')
        ->assertSee('Read')
        ->assertSee('Write a message...')
        ->assertSee('Video call')
        ->assertSee('data-conversation-presence', false)
        ->assertSee('onlinePresence', false)
        ->assertSee('typingIndicator', false);
});

test('conversation page shows the shared sidebar and highlights the active thread', function () {
    $user = User::factory()->create();
    $activeUser = User::factory()->create([
        'name' => 'Active Conversation',
    ]);
    $secondUser = User::factory()->create([
        'name' => 'Another Thread',
    ]);

    createMarketplaceMessage($activeUser, $user, 'Latest from the active thread', [
        'created_at' => now()->subMinutes(2),
    ]);
    createMarketplaceMessage($secondUser, $user, 'A second thread preview', [
        'created_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->get(route('messages.conversation', ['conversationReference' => $activeUser->getKey()]))
        ->assertOk()
        ->assertSee('All conversations')
        ->assertSee('Active Conversation')
        ->assertSee('Another Thread')
        ->assertSee(route('messages.conversation', ['conversationReference' => $secondUser->getKey()]), false)
        ->assertSee('aria-current="page"', false);
});

test('conversation header links to customer profile when other user is a customer', function () {
    $vendor = User::factory()->vendor()->create();
    $customer = User::factory()->create([
        'name' => 'Customer Link Target',
    ]);

    $this->actingAs($vendor)
        ->get(route('messages.conversation', ['conversationReference' => $customer->getKey()]))
        ->assertOk()
        ->assertSee('Customer Link Target')
        ->assertSee(route('shop.customers.show', $customer), false);
});

test('conversation header links to vendor storefront when other user is a vendor', function () {
    $customer = User::factory()->create();
    $vendorUser = User::factory()->vendor()->create([
        'name' => 'Vendor Storefront Link',
    ]);
    $vendorProfile = VendorProfile::factory()->for($vendorUser, 'user')->approved()->create();

    $this->actingAs($customer)
        ->get(route('messages.conversation', ['conversationReference' => $vendorUser->getKey()]))
        ->assertOk()
        ->assertSee('Vendor Storefront Link')
        ->assertSee(route('shop.vendors.show', $vendorProfile), false);
});

test('admins can access messaging pages and link back to admin profiles', function () {
    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->create([
        'name' => 'Reported Buyer',
    ]);

    createMarketplaceMessage($otherUser, $admin, 'Please review this conversation context.');

    $this->actingAs($admin)
        ->get(route('messages.inbox'))
        ->assertOk()
        ->assertSee('Reported Buyer');

    $this->actingAs($admin)
        ->get(route('messages.conversation', ['conversationReference' => $otherUser->getKey()]))
        ->assertOk()
        ->assertSee('Reported Buyer')
        ->assertSee(route('admin.users.show', $otherUser), false);
});

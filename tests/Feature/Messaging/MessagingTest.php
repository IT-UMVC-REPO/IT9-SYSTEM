<?php

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
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
        ->assertSee('Latest vendor reply')
        ->assertDontSee('Older vendor note');
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

    Livewire::actingAs($user)
        ->test('pages::messages.conversation', ['conversationReference' => (string) $otherUser->getKey()])
        ->set('newMessage', 'Can you confirm today\'s stock?')
        ->call('send')
        ->assertDispatched('message-sent');

    expect(Message::query()
        ->where('sender_id', $user->getKey())
        ->where('receiver_id', $otherUser->getKey())
        ->where('content', 'Can you confirm today\'s stock?')
        ->exists())->toBeTrue();

    Event::assertDispatched(MessageSent::class);
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
        ->set('attachmentUpload', $attachment)
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
        ->and(Storage::disk('public')->exists($message->attachment_path))->toBeTrue();
});

test('sending a message rejects unsupported attachment types', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $attachment = UploadedFile::fake()->create('payload.exe', 64, 'application/octet-stream');

    Livewire::actingAs($user)
        ->test('pages::messages.conversation', ['conversationReference' => (string) $otherUser->getKey()])
        ->set('newMessage', '')
        ->set('attachmentUpload', $attachment)
        ->call('send')
        ->assertHasErrors(['attachmentUpload']);

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

    createMarketplaceMessage($user, $otherUser, 'First message', [
        'created_at' => now()->subMinutes(15),
    ]);
    createMarketplaceMessage($otherUser, $user, 'Second message', [
        'created_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->get(route('messages.conversation', ['conversationReference' => $otherUser->getKey()]))
        ->assertOk()
        ->assertSeeInOrder(['First message', 'Second message']);
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

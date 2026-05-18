<?php

use App\Events\MessageThreadUpdated;
use App\Events\MessageUpdated;
use App\Models\ChatParticipantState;
use App\Models\Message;
use App\Models\MessagePin;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function createDirectChatMessage(User $sender, User $receiver, string $content, array $overrides = []): Message
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

test('chat states pin mute archive label unread and soft delete direct threads per user', function () {
    $viewer = User::factory()->create();
    $pinnedContact = User::factory()->create(['name' => 'Pinned Vendor']);
    $archivedContact = User::factory()->create(['name' => 'Archived Vendor']);
    $deletedContact = User::factory()->create(['name' => 'Deleted Vendor']);
    $normalContact = User::factory()->create(['name' => 'Normal Vendor']);

    createDirectChatMessage($normalContact, $viewer, 'Normal latest note', ['created_at' => now()->subMinutes(2)]);
    createDirectChatMessage($pinnedContact, $viewer, 'Pinned older note', ['created_at' => now()->subHour()]);
    createDirectChatMessage($archivedContact, $viewer, 'Archived note', ['created_at' => now()->subMinute()]);
    createDirectChatMessage($deletedContact, $viewer, 'Deleted note', ['created_at' => now()]);

    ChatParticipantState::forDirect($viewer, $pinnedContact)->update([
        'pinned_at' => now(),
        'muted_until' => now()->addWeek(),
        'label' => 'Work',
        'marked_unread_at' => now(),
    ]);
    ChatParticipantState::forDirect($viewer, $archivedContact)->update(['archived_at' => now()]);
    ChatParticipantState::forDirect($viewer, $deletedContact)->delete();

    $this->actingAs($viewer)
        ->get(route('messages.inbox'))
        ->assertOk()
        ->assertSeeInOrder(['Pinned Vendor', 'Normal Vendor'])
        ->assertSee('Muted')
        ->assertSee('Work')
        ->assertDontSee('Archived Vendor')
        ->assertDontSee('Deleted Vendor');
});

test('direct messages can be edited pinned deleted and unsent with realtime events', function () {
    Event::fake([MessageUpdated::class, MessageThreadUpdated::class]);

    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $message = createDirectChatMessage($sender, $receiver, 'Original note', [
        'is_read' => true,
        'created_at' => now()->subMinutes(10),
    ]);

    Livewire::actingAs($sender)
        ->test('pages::messages.conversation', ['conversationReference' => (string) $receiver->getKey()])
        ->call('editMessage', $message->getKey(), 'Updated note')
        ->call('pinMessage', $message->getKey())
        ->call('deleteMessage', $message->getKey(), true)
        ->assertDispatched('message-sent');

    $message->refresh();

    expect($message->content)->toBe('This message was deleted')
        ->and($message->edited_at)->not->toBeNull()
        ->and($message->deleted_at)->not->toBeNull()
        ->and($message->deleted_for_everyone_at)->not->toBeNull()
        ->and(MessagePin::query()->where('pinnable_type', Message::class)->where('pinnable_id', $message->getKey())->exists())->toBeTrue();

    Event::assertDispatched(MessageUpdated::class);
    Event::assertDispatched(MessageThreadUpdated::class);
});

test('inbox actions can archive and delete direct chats', function () {
    $viewer = User::factory()->create();
    $archivedContact = User::factory()->create(['name' => 'Archive Contact']);
    $deletedContact = User::factory()->create(['name' => 'Delete Contact']);

    createDirectChatMessage($archivedContact, $viewer, 'Archive this thread');
    createDirectChatMessage($deletedContact, $viewer, 'Delete this thread');

    Livewire::actingAs($viewer)
        ->test('messages.conversation-sidebar')
        ->call('archiveDirectThread', $archivedContact->getKey())
        ->call('deleteDirectThread', $deletedContact->getKey());

    expect(ChatParticipantState::withTrashed()
        ->where('user_id', $viewer->getKey())
        ->where('direct_user_id', $archivedContact->getKey())
        ->value('archived_at'))->not->toBeNull()
        ->and(ChatParticipantState::withTrashed()
            ->where('user_id', $viewer->getKey())
            ->where('direct_user_id', $deletedContact->getKey())
            ->first()?->trashed())->toBeTrue();
});

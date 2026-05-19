<?php

use App\Events\MessageThreadUpdated;
use App\Events\MessageUpdated;
use App\Models\ChatParticipantState;
use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\GroupMessage;
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

test('archived direct chats move to archived view and can be restored before delete', function () {
    $viewer = User::factory()->create();
    $archivedContact = User::factory()->create(['name' => 'Recoverable Vendor']);
    $deletedContact = User::factory()->create(['name' => 'Disposable Vendor']);

    createDirectChatMessage($archivedContact, $viewer, 'Keep this thread');
    createDirectChatMessage($deletedContact, $viewer, 'Remove this thread');

    Livewire::actingAs($viewer)
        ->test('messages.conversation-sidebar')
        ->assertSee('Recoverable Vendor')
        ->assertSee('Disposable Vendor')
        ->call('archiveDirectThread', $archivedContact->getKey())
        ->call('deleteDirectThread', $deletedContact->getKey())
        ->assertDontSee('Recoverable Vendor')
        ->assertDontSee('Disposable Vendor')
        ->call('showArchivedThreads')
        ->assertSee('Recoverable Vendor')
        ->assertDontSee('Disposable Vendor')
        ->call('unarchiveDirectThread', $archivedContact->getKey())
        ->assertDontSee('Recoverable Vendor')
        ->call('showInboxThreads')
        ->assertSee('Recoverable Vendor')
        ->assertDontSee('Disposable Vendor');

    expect(ChatParticipantState::query()
        ->where('user_id', $viewer->getKey())
        ->where('direct_user_id', $archivedContact->getKey())
        ->value('archived_at'))->toBeNull()
        ->and(ChatParticipantState::withTrashed()
            ->where('user_id', $viewer->getKey())
            ->where('direct_user_id', $deletedContact->getKey())
            ->first()?->trashed())->toBeTrue();
});

test('archived group chats are restorable while deleted group chats leave the sidebar', function () {
    $viewer = User::factory()->create();
    $sender = User::factory()->create();
    $archivedGroup = ConversationGroup::factory()->create(['name' => 'Recoverable Group']);
    $deletedGroup = ConversationGroup::factory()->create(['name' => 'Disposable Group']);

    ConversationGroupMember::factory()->create([
        'group_id' => $archivedGroup->getKey(),
        'user_id' => $viewer->getKey(),
        'joined_at' => now()->subMinutes(10),
    ]);
    ConversationGroupMember::factory()->create([
        'group_id' => $deletedGroup->getKey(),
        'user_id' => $viewer->getKey(),
        'joined_at' => now()->subMinutes(10),
    ]);

    GroupMessage::factory()->create([
        'group_id' => $archivedGroup->getKey(),
        'sender_id' => $sender->getKey(),
        'content' => 'Keep this group',
    ]);
    GroupMessage::factory()->create([
        'group_id' => $deletedGroup->getKey(),
        'sender_id' => $sender->getKey(),
        'content' => 'Remove this group',
    ]);

    Livewire::actingAs($viewer)
        ->test('messages.conversation-sidebar')
        ->call('archiveGroupThread', $archivedGroup->getKey())
        ->call('deleteGroupThread', $deletedGroup->getKey())
        ->call('showArchivedThreads')
        ->assertSee('Recoverable Group')
        ->assertDontSee('Disposable Group')
        ->call('unarchiveGroupThread', $archivedGroup->getKey())
        ->call('showInboxThreads')
        ->assertSee('Recoverable Group')
        ->assertDontSee('Disposable Group');

    expect(ConversationGroupMember::query()
        ->where('group_id', $archivedGroup->getKey())
        ->where('user_id', $viewer->getKey())
        ->value('archived_at'))->toBeNull()
        ->and(ConversationGroupMember::withTrashed()
            ->where('group_id', $deletedGroup->getKey())
            ->where('user_id', $viewer->getKey())
            ->first()?->trashed())->toBeTrue();
});

<?php

use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\GroupMessage;
use App\Models\Message;
use App\Models\User;
use App\Models\UserNickname;
use Livewire\Livewire;

test('users can create and remove nicknames for conversation participants', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create([
        'name' => 'Real Customer Name',
    ]);

    Livewire::actingAs($viewer)
        ->test('messaging.nickname-editor', ['targetUserId' => $target->getKey()])
        ->call('edit')
        ->set('draftNickname', 'Suki Regular')
        ->call('save')
        ->assertDispatched('nickname-updated')
        ->assertSee('Suki Regular')
        ->assertSee('Real Customer Name');

    expect($target->nicknameFor($viewer->getKey()))->toBe('Suki Regular');

    Livewire::actingAs($viewer)
        ->test('messaging.nickname-editor', ['targetUserId' => $target->getKey()])
        ->call('remove')
        ->assertDispatched('nickname-updated');

    expect($target->fresh()->nicknameFor($viewer->getKey()))->toBeNull();
});

test('conversation sidebar displays nicknames instead of real names', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create([
        'name' => 'Original Vendor Name',
    ]);

    UserNickname::factory()->create([
        'owner_id' => $viewer->getKey(),
        'target_id' => $target->getKey(),
        'nickname' => 'Favorite Fish Stall',
    ]);

    Message::factory()->create([
        'sender_id' => $target->getKey(),
        'receiver_id' => $viewer->getKey(),
        'content' => 'Fresh catch is ready.',
        'created_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get(route('messages.inbox'))
        ->assertOk()
        ->assertSee('Favorite Fish Stall')
        ->assertDontSee('Original Vendor Name');
});

test('unread badge counts direct and group unread messages', function () {
    $viewer = User::factory()->create();
    $directSender = User::factory()->create();
    $groupSender = User::factory()->create([
        'name' => 'Group Sender',
    ]);
    $group = ConversationGroup::factory()->create([
        'created_by' => $viewer->getKey(),
    ]);

    ConversationGroupMember::factory()->create([
        'group_id' => $group->getKey(),
        'user_id' => $viewer->getKey(),
        'role' => 'admin',
        'joined_at' => now()->subHour(),
        'last_read_at' => now()->subMinutes(10),
    ]);
    ConversationGroupMember::factory()->create([
        'group_id' => $group->getKey(),
        'user_id' => $groupSender->getKey(),
        'joined_at' => now()->subHour(),
    ]);

    Message::factory()->create([
        'sender_id' => $directSender->getKey(),
        'receiver_id' => $viewer->getKey(),
        'is_read' => false,
    ]);
    Message::factory()->create([
        'sender_id' => $viewer->getKey(),
        'receiver_id' => $directSender->getKey(),
        'is_read' => false,
    ]);
    GroupMessage::factory()->create([
        'group_id' => $group->getKey(),
        'sender_id' => $groupSender->getKey(),
        'created_at' => now()->subMinute(),
    ]);

    Livewire::actingAs($viewer)
        ->test('messaging.unread-badge', ['isActive' => false])
        ->assertSee('2')
        ->call('refreshBadge')
        ->assertSee('2');
});

test('opening conversations marks unread messages and refreshes badge count', function () {
    $viewer = User::factory()->create();
    $sender = User::factory()->create();

    Message::factory()->create([
        'sender_id' => $sender->getKey(),
        'receiver_id' => $viewer->getKey(),
        'is_read' => false,
    ]);

    Livewire::actingAs($viewer)
        ->test('messaging.unread-badge')
        ->assertSee('1');

    Livewire::actingAs($viewer)
        ->test('pages::messages.conversation', ['conversationReference' => (string) $sender->getKey()])
        ->assertDispatched('message-marked-read');

    Livewire::actingAs($viewer)
        ->test('messaging.unread-badge')
        ->assertDontSee('absolute -right-1', false);

    expect(Message::query()
        ->where('sender_id', $sender->getKey())
        ->where('receiver_id', $viewer->getKey())
        ->where('is_read', false)
        ->exists())->toBeFalse();
});

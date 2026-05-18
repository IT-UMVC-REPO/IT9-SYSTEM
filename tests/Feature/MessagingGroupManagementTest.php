<?php

use App\Events\GroupMessageUpdated;
use App\Events\MessageThreadUpdated;
use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\GroupMessage;
use App\Models\MessagePin;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function createManagedMessagingGroup(User $owner, array $members = [], array $overrides = []): ConversationGroup
{
    $group = ConversationGroup::factory()->create(array_merge([
        'created_by' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
        'name' => 'Morning Vendors',
    ], $overrides));

    ConversationGroupMember::factory()->create([
        'group_id' => $group->getKey(),
        'user_id' => $owner->getKey(),
        'role' => 'admin',
        'joined_at' => now()->subMinutes(30),
    ]);

    foreach ($members as $index => $member) {
        ConversationGroupMember::factory()->create([
            'group_id' => $group->getKey(),
            'user_id' => $member->getKey(),
            'role' => 'member',
            'joined_at' => now()->subMinutes(20 - $index),
        ]);
    }

    return $group;
}

test('group admins can manage group details members nicknames limits and invites', function () {
    Event::fake([MessageThreadUpdated::class]);

    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Ramon Member']);
    $removeable = User::factory()->create(['name' => 'Remove Me']);
    $group = createManagedMessagingGroup($owner, [$member, $removeable]);

    Livewire::actingAs($owner)
        ->test('pages::messages.group-conversation', ['groupId' => $group->getKey()])
        ->set('groupName', 'Renamed Market Crew')
        ->set('groupDescription', 'Early-morning vendor coordination')
        ->set('maxMembers', 12)
        ->call('saveGroupDetails')
        ->call('setMemberNickname', $member->getKey(), 'Kuya Ramon')
        ->call('promoteMember', $member->getKey())
        ->call('removeMember', $removeable->getKey())
        ->call('regenerateInviteLink', 60, 5)
        ->assertDispatched('copy-invite-link')
        ->assertDispatched('message-sent');

    $group->refresh();

    expect($group->name)->toBe('Renamed Market Crew')
        ->and($group->description)->toBe('Early-morning vendor coordination')
        ->and($group->max_members)->toBe(12)
        ->and($group->invite_token)->not->toBeNull()
        ->and($group->invite_usage_limit)->toBe(5)
        ->and($group->members()->where('user_id', $removeable->getKey())->exists())->toBeFalse()
        ->and($group->members()->where('user_id', $member->getKey())->value('role'))->toBe('admin')
        ->and($group->members()->where('user_id', $member->getKey())->value('nickname'))->toBe('Kuya Ramon');

    Event::assertDispatched(MessageThreadUpdated::class);
});

test('group owners can transfer ownership and leaving auto promotes the oldest member', function () {
    $owner = User::factory()->create();
    $oldestMember = User::factory()->create();
    $newerMember = User::factory()->create();
    $group = createManagedMessagingGroup($owner, [$oldestMember, $newerMember]);

    Livewire::actingAs($owner)
        ->test('pages::messages.group-conversation', ['groupId' => $group->getKey()])
        ->call('transferOwnership', $oldestMember->getKey());

    expect($group->fresh()->owner_id)->toBe($oldestMember->getKey())
        ->and($group->members()->where('user_id', $oldestMember->getKey())->value('role'))->toBe('admin');

    $soloAdmin = User::factory()->create();
    $autoPromotedMember = User::factory()->create();
    $laterMember = User::factory()->create();
    $autoPromoteGroup = createManagedMessagingGroup($soloAdmin, [$autoPromotedMember, $laterMember]);

    Livewire::actingAs($soloAdmin)
        ->test('pages::messages.group-conversation', ['groupId' => $autoPromoteGroup->getKey()])
        ->call('leaveGroup');

    expect($autoPromoteGroup->fresh()->owner_id)->toBe($autoPromotedMember->getKey())
        ->and($autoPromoteGroup->members()->where('user_id', $autoPromotedMember->getKey())->value('role'))->toBe('admin');
});

test('group admins can pin and tombstone any group message', function () {
    Event::fake([GroupMessageUpdated::class, MessageThreadUpdated::class]);

    $admin = User::factory()->create();
    $member = User::factory()->create();
    $group = createManagedMessagingGroup($admin, [$member]);
    $message = GroupMessage::factory()->create([
        'group_id' => $group->getKey(),
        'sender_id' => $member->getKey(),
        'content' => 'Message needing moderation',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::messages.group-conversation', ['groupId' => $group->getKey()])
        ->call('pinMessage', $message->getKey())
        ->call('deleteMessage', $message->getKey(), true)
        ->assertDispatched('group-message-sent');

    $message->refresh();

    expect($message->content)->toBe('This message was deleted')
        ->and($message->deleted_at)->not->toBeNull()
        ->and(MessagePin::query()->where('pinnable_type', GroupMessage::class)->where('pinnable_id', $message->getKey())->exists())->toBeTrue();

    Event::assertDispatched(GroupMessageUpdated::class);
    Event::assertDispatched(MessageThreadUpdated::class);
});

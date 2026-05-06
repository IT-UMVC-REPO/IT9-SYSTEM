<?php

use App\Enums\VideoCallStatus;
use App\Events\GroupCallInitiated;
use App\Events\GroupCallSignal;
use App\Events\GroupCallStatusChanged;
use App\Events\GroupMessageSent;
use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\GroupMessage;
use App\Models\GroupMessageAttachment;
use App\Models\GroupMessageReaction;
use App\Models\User;
use App\Models\VideoCall;
use App\Models\VideoCallParticipant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function createMessagingGroup(User $creator, array $members = [], array $overrides = []): ConversationGroup
{
    $group = ConversationGroup::factory()->create(array_merge([
        'created_by' => $creator->getKey(),
    ], $overrides));

    ConversationGroupMember::factory()->create([
        'group_id' => $group->getKey(),
        'user_id' => $creator->getKey(),
        'role' => 'admin',
        'joined_at' => now()->subMinutes(10),
    ]);

    foreach ($members as $member) {
        ConversationGroupMember::factory()->create([
            'group_id' => $group->getKey(),
            'user_id' => $member->getKey(),
            'role' => 'member',
            'joined_at' => now()->subMinutes(10),
        ]);
    }

    return $group;
}

test('members can view group conversations and non members cannot', function () {
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $group = createMessagingGroup($creator, [$member], [
        'name' => 'Market Prep',
    ]);

    $this->actingAs($member)
        ->get(route('messages.group', ['groupId' => $group->getKey()]))
        ->assertOk()
        ->assertSee('Market Prep');

    $this->actingAs($outsider)
        ->get(route('messages.group', ['groupId' => $group->getKey()]))
        ->assertForbidden();
});

test('group conversation renders the polished mobile thread and call controls', function () {
    $creator = User::factory()->create([
        'name' => 'Group Admin',
    ]);
    $member = User::factory()->create([
        'name' => 'Ramon Vendor',
    ]);
    $group = createMessagingGroup($creator, [$member], [
        'name' => 'Morning Market Crew',
    ]);
    $yesterdayAt = now()->subDay()->setTime(8, 5);
    $todayAt = now()->setTime(9, 10);

    GroupMessage::factory()->create([
        'group_id' => $group->getKey(),
        'sender_id' => $member->getKey(),
        'content' => 'Yesterday prep note',
        'created_at' => $yesterdayAt,
    ]);
    GroupMessage::factory()->create([
        'group_id' => $group->getKey(),
        'sender_id' => $member->getKey(),
        'content' => 'Fresh stock is ready.',
        'created_at' => $todayAt,
    ]);
    $activeCall = VideoCall::query()->create([
        'caller_id' => $member->getKey(),
        'receiver_id' => null,
        'group_id' => $group->getKey(),
        'is_group_call' => true,
        'conversation_key' => 'group-'.$group->getKey(),
        'status' => VideoCallStatus::Active,
        'started_at' => now(),
        'created_at' => now(),
    ]);
    VideoCallParticipant::factory()->create([
        'video_call_id' => $activeCall->getKey(),
        'user_id' => $member->getKey(),
        'left_at' => null,
    ]);

    $this->actingAs($creator)
        ->get(route('messages.group', ['groupId' => $group->getKey()]))
        ->assertOk()
        ->assertSee('Morning Market Crew')
        ->assertSee('Ramon Vendor')
        ->assertSee('Yesterday')
        ->assertSee('Today')
        ->assertSee($todayAt->format('g:i A'))
        ->assertSee('Write a message...')
        ->assertSee('Start call')
        ->assertSee('A group call is in progress')
        ->assertSee('Join call')
        ->assertSee('group-call-join', false)
        ->assertSee('group-call-local-grid-video', false)
        ->assertSee('group-call-speaker-video', false)
        ->assertSee('group-call-local-thumbnail-video', false)
        ->assertSee('participant.thumbnailElementId', false)
        ->assertSee('participant.tileElementId', false)
        ->assertSee('Waiting for others to join...')
        ->assertDontSee('LOCAL PREVIEW');
});

test('group conversation ignores stale group call banners', function () {
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $group = createMessagingGroup($creator, [$member], [
        'name' => 'Morning Market Crew',
    ]);
    $staleCall = VideoCall::query()->create([
        'caller_id' => $member->getKey(),
        'receiver_id' => null,
        'group_id' => $group->getKey(),
        'is_group_call' => true,
        'conversation_key' => 'group-'.$group->getKey(),
        'status' => VideoCallStatus::Active,
        'started_at' => now()->subMinutes(91),
        'created_at' => now()->subMinutes(91),
    ]);

    VideoCallParticipant::factory()->create([
        'video_call_id' => $staleCall->getKey(),
        'user_id' => $member->getKey(),
        'left_at' => null,
    ]);

    $this->actingAs($creator)
        ->get(route('messages.group', ['groupId' => $group->getKey()]))
        ->assertOk()
        ->assertSee('Morning Market Crew')
        ->assertSee('Start call')
        ->assertDontSee('A group call is in progress')
        ->assertDontSee('Join call');
});

test('create group modal persists creator and selected members', function () {
    $creator = User::factory()->create();
    $member = User::factory()->create([
        'name' => 'Buyer Mina',
    ]);

    Livewire::actingAs($creator)
        ->test('messaging.create-group-modal')
        ->set('groupName', 'Saturday Pickup')
        ->set('selectedUserIds', [$member->getKey()])
        ->call('createGroup')
        ->assertRedirect();

    $group = ConversationGroup::query()->where('name', 'Saturday Pickup')->first();

    expect($group)->not->toBeNull()
        ->and(ConversationGroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $creator->getKey())
            ->where('role', 'admin')
            ->exists())->toBeTrue()
        ->and(ConversationGroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $member->getKey())
            ->where('role', 'member')
            ->exists())->toBeTrue();
});

test('group conversation sends messages with multiple attachments', function () {
    Storage::fake('public');
    Event::fake([GroupMessageSent::class]);

    $creator = User::factory()->create();
    $member = User::factory()->create();
    $group = createMessagingGroup($creator, [$member]);

    Livewire::actingAs($member)
        ->test('pages::messages.group-conversation', ['groupId' => $group->getKey()])
        ->set('newMessage', 'Sharing the pickup proof.')
        ->set('attachmentUploads', [
            UploadedFile::fake()->create('basket.jpg', 64, 'image/jpeg'),
            UploadedFile::fake()->create('receipt.pdf', 64, 'application/pdf'),
        ])
        ->call('send')
        ->assertHasNoErrors()
        ->assertDispatched('group-message-sent');

    $message = GroupMessage::query()
        ->where('group_id', $group->getKey())
        ->where('sender_id', $member->getKey())
        ->latest('id')
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->content)->toBe('Sharing the pickup proof.')
        ->and($message->attachments()->count())->toBe(2)
        ->and(Storage::disk('public')->exists($message->attachments()->oldest('id')->first()->path))->toBeTrue();

    Event::assertDispatched(GroupMessageSent::class, fn (GroupMessageSent $event) => $event->message->is($message));
});

test('group conversation sends replies and toggles emoji reactions', function () {
    Event::fake([GroupMessageSent::class]);

    $creator = User::factory()->create([
        'name' => 'Mina Buyer',
    ]);
    $member = User::factory()->create([
        'name' => 'Ramon Vendor',
    ]);
    $group = createMessagingGroup($creator, [$member]);
    $originalMessage = GroupMessage::factory()->create([
        'group_id' => $group->getKey(),
        'sender_id' => $creator->getKey(),
        'content' => 'Can you prep the bundles?',
    ]);

    Livewire::actingAs($member)
        ->test('pages::messages.group-conversation', ['groupId' => $group->getKey()])
        ->call('setReplyTo', $originalMessage->getKey())
        ->assertSet('replyingToId', $originalMessage->getKey())
        ->assertSet('replyingToSender', 'Mina Buyer')
        ->set('newMessage', 'Yes, I will prep them.')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('replyingToId', null);

    $reply = GroupMessage::query()
        ->where('group_id', $group->getKey())
        ->where('sender_id', $member->getKey())
        ->latest('id')
        ->first();

    expect($reply)->not->toBeNull()
        ->and($reply->reply_to_id)->toBe($originalMessage->getKey());

    Livewire::actingAs($creator)
        ->test('pages::messages.group-conversation', ['groupId' => $group->getKey()])
        ->call('toggleReaction', $reply->getKey(), '👍')
        ->call('toggleReaction', $reply->getKey(), 'not-allowed');

    expect(GroupMessageReaction::query()
        ->where('group_message_id', $reply->getKey())
        ->where('user_id', $creator->getKey())
        ->where('emoji', '👍')
        ->exists())->toBeTrue()
        ->and(GroupMessageReaction::query()
            ->where('group_message_id', $reply->getKey())
            ->where('emoji', 'not-allowed')
            ->exists())->toBeFalse();

    Livewire::actingAs($creator)
        ->test('pages::messages.group-conversation', ['groupId' => $group->getKey()])
        ->call('toggleReaction', $reply->getKey(), '👍');

    expect(GroupMessageReaction::query()
        ->where('group_message_id', $reply->getKey())
        ->where('user_id', $creator->getKey())
        ->where('emoji', '👍')
        ->exists())->toBeFalse();
});

test('group sidebar merges group threads with unread counts and sender previews', function () {
    $viewer = User::factory()->create();
    $sender = User::factory()->create([
        'name' => 'Ramon Vendor',
    ]);
    $group = createMessagingGroup($viewer, [$sender], [
        'name' => 'Morning Orders',
    ]);

    ConversationGroupMember::query()
        ->where('group_id', $group->getKey())
        ->where('user_id', $viewer->getKey())
        ->update(['last_read_at' => now()->subMinutes(15)]);

    GroupMessage::factory()->create([
        'group_id' => $group->getKey(),
        'sender_id' => $sender->getKey(),
        'content' => 'The vegetables are ready.',
        'created_at' => now()->subMinutes(2),
    ]);

    $this->actingAs($viewer)
        ->get(route('messages.inbox'))
        ->assertOk()
        ->assertSee('Morning Orders')
        ->assertSee('Ramon: The vegetables are ready.')
        ->assertSee('1');
});

test('group call routes authorize members and persist active participants', function () {
    Event::fake([
        GroupCallInitiated::class,
        GroupCallSignal::class,
        GroupCallStatusChanged::class,
        GroupMessageSent::class,
    ]);

    $creator = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $group = createMessagingGroup($creator, [$member]);

    $this->actingAs($creator)
        ->postJson(route('calls.group.initiate'), [
            'group_id' => $group->getKey(),
        ])
        ->assertCreated()
        ->assertJsonPath('group_id', $group->getKey())
        ->assertJsonPath('caller_id', $creator->getKey())
        ->assertJsonPath('participants.0', $creator->getKey());

    $call = VideoCall::query()->where('group_id', $group->getKey())->first();

    expect($call)->not->toBeNull()
        ->and($call->is_group_call)->toBeTrue()
        ->and($call->receiver_id)->toBeNull()
        ->and(GroupMessage::query()
            ->where('group_id', $group->getKey())
            ->where('is_system_message', true)
            ->where('system_event', 'call_started')
            ->exists())->toBeTrue()
        ->and(VideoCallParticipant::query()
            ->where('video_call_id', $call->getKey())
            ->where('user_id', $creator->getKey())
            ->whereNull('left_at')
            ->exists())->toBeTrue();

    $this->actingAs($member)
        ->postJson(route('calls.group.answer', ['call' => $call]))
        ->assertSuccessful()
        ->assertJsonPath('status', VideoCallStatus::Active->value);

    expect(GroupMessage::query()
        ->where('group_id', $group->getKey())
        ->where('is_system_message', true)
        ->where('system_event', 'user_joined')
        ->where('system_actor_id', $member->getKey())
        ->exists())->toBeTrue();

    Event::assertDispatched(GroupCallStatusChanged::class, function (GroupCallStatusChanged $event) use ($call, $creator, $member): bool {
        $payload = $event->broadcastWith();

        return $event->videoCall->is($call)
            && $payload['status'] === VideoCallStatus::Active->value
            && collect($payload['participants'])->contains($creator->getKey())
            && collect($payload['participants'])->contains($member->getKey());
    });

    $this->actingAs($member)
        ->postJson(route('calls.group.signal', ['call' => $call]), [
            'recipient_id' => $creator->getKey(),
            'signal_data' => [
                'type' => 'offer',
                'sdp' => 'v=0',
            ],
        ])
        ->assertSuccessful();

    $this->actingAs($outsider)
        ->postJson(route('calls.group.answer', ['call' => $call]))
        ->assertForbidden();

    $this->actingAs($member)
        ->postJson(route('calls.group.end', ['call' => $call]))
        ->assertSuccessful()
        ->assertJsonPath('status', VideoCallStatus::Active->value);

    $this->actingAs($creator)
        ->postJson(route('calls.group.end', ['call' => $call]))
        ->assertSuccessful()
        ->assertJsonPath('status', VideoCallStatus::Ended->value);

    expect(GroupMessage::query()
        ->where('group_id', $group->getKey())
        ->where('is_system_message', true)
        ->where('system_event', 'user_left')
        ->count())->toBe(2)
        ->and(GroupMessage::query()
            ->where('group_id', $group->getKey())
            ->where('is_system_message', true)
            ->where('system_event', 'call_ended')
            ->exists())->toBeTrue();

    Event::assertDispatched(GroupCallInitiated::class, fn (GroupCallInitiated $event) => $event->videoCall->is($call));
    Event::assertDispatched(GroupCallSignal::class, fn (GroupCallSignal $event) => $event->videoCall->is($call)
        && $event->senderId === $member->getKey()
        && $event->recipientId === $creator->getKey());
    Event::assertDispatched(GroupCallStatusChanged::class, fn (GroupCallStatusChanged $event) => $event->videoCall->is($call));
});

test('group call routes expire stale calls before starting or joining', function () {
    Event::fake([
        GroupCallInitiated::class,
        GroupCallStatusChanged::class,
        GroupMessageSent::class,
    ]);

    $creator = User::factory()->create();
    $member = User::factory()->create();
    $group = createMessagingGroup($creator, [$member]);
    $staleStartedAt = now()->subMinutes(91);
    $staleCall = VideoCall::query()->create([
        'caller_id' => $member->getKey(),
        'receiver_id' => null,
        'group_id' => $group->getKey(),
        'is_group_call' => true,
        'conversation_key' => 'group-'.$group->getKey(),
        'status' => VideoCallStatus::Active,
        'started_at' => $staleStartedAt,
        'created_at' => $staleStartedAt,
    ]);

    VideoCallParticipant::factory()->create([
        'video_call_id' => $staleCall->getKey(),
        'user_id' => $member->getKey(),
        'left_at' => null,
    ]);

    $this->actingAs($creator)
        ->postJson(route('calls.group.initiate'), [
            'group_id' => $group->getKey(),
        ])
        ->assertCreated();

    $staleCall->refresh();

    expect($staleCall->status)->toBe(VideoCallStatus::Ended)
        ->and($staleCall->ended_at)->not->toBeNull();

    $staleJoinCall = VideoCall::query()->create([
        'caller_id' => $creator->getKey(),
        'receiver_id' => null,
        'group_id' => $group->getKey(),
        'is_group_call' => true,
        'conversation_key' => 'group-'.$group->getKey(),
        'status' => VideoCallStatus::Pending,
        'created_at' => now()->subMinutes(91),
    ]);

    VideoCallParticipant::factory()->create([
        'video_call_id' => $staleJoinCall->getKey(),
        'user_id' => $creator->getKey(),
        'left_at' => null,
    ]);

    $this->actingAs($member)
        ->postJson(route('calls.group.answer', ['call' => $staleJoinCall]))
        ->assertStatus(409);

    $staleJoinCall->refresh();

    expect($staleJoinCall->status)->toBe(VideoCallStatus::Ended)
        ->and($staleJoinCall->ended_at)->not->toBeNull();
});

test('group message attachments have a model relation', function () {
    $sender = User::factory()->create();
    $group = createMessagingGroup($sender);
    $message = GroupMessage::factory()->create([
        'group_id' => $group->getKey(),
        'sender_id' => $sender->getKey(),
    ]);

    $attachment = GroupMessageAttachment::factory()->create([
        'group_message_id' => $message->getKey(),
        'name' => 'proof.pdf',
    ]);

    expect($attachment->groupMessage->is($message))->toBeTrue()
        ->and($message->attachments()->first()->is($attachment))->toBeTrue();
});

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
        ->and(VideoCallParticipant::query()
            ->where('video_call_id', $call->getKey())
            ->where('user_id', $creator->getKey())
            ->whereNull('left_at')
            ->exists())->toBeTrue();

    $this->actingAs($member)
        ->postJson(route('calls.group.answer', ['call' => $call]))
        ->assertSuccessful()
        ->assertJsonPath('status', VideoCallStatus::Active->value);

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

    Event::assertDispatched(GroupCallInitiated::class, fn (GroupCallInitiated $event) => $event->videoCall->is($call));
    Event::assertDispatched(GroupCallSignal::class, fn (GroupCallSignal $event) => $event->videoCall->is($call)
        && $event->senderId === $member->getKey()
        && $event->recipientId === $creator->getKey());
    Event::assertDispatched(GroupCallStatusChanged::class, fn (GroupCallStatusChanged $event) => $event->videoCall->is($call));
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

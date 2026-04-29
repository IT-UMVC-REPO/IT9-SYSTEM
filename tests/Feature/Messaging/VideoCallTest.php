<?php

use App\Enums\VideoCallStatus;
use App\Events\VideoCallInitiated;
use App\Events\VideoCallSignal;
use App\Events\VideoCallStatusChanged;
use App\Models\User;
use App\Models\VideoCall;
use Illuminate\Support\Facades\Event;

function createVideoCallRecord(User $caller, User $receiver, array $overrides = []): VideoCall
{
    return VideoCall::query()->create(array_merge([
        'caller_id' => $caller->getKey(),
        'receiver_id' => $receiver->getKey(),
        'conversation_key' => VideoCall::conversationKeyFor($caller->getKey(), $receiver->getKey()),
        'status' => VideoCallStatus::Pending,
        'started_at' => null,
        'ended_at' => null,
        'created_at' => now(),
    ], $overrides));
}

test('authenticated users can initiate a call', function () {
    $caller = User::factory()->create();
    $receiver = User::factory()->create();

    Event::fake();

    $this->actingAs($caller)
        ->postJson(route('calls.initiate'), [
            'receiver_id' => $receiver->getKey(),
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'id',
            'caller_id',
            'receiver_id',
            'conversation_key',
            'status',
            'realtime_available',
            'message',
        ])
        ->assertJsonPath('caller_id', $caller->getKey())
        ->assertJsonPath('receiver_id', $receiver->getKey())
        ->assertJsonPath('conversation_key', VideoCall::conversationKeyFor($caller->getKey(), $receiver->getKey()))
        ->assertJsonPath('status', VideoCallStatus::Pending->value)
        ->assertJsonPath('realtime_available', true);

    $call = VideoCall::query()->first();

    expect($call)->not->toBeNull()
        ->and($call->caller_id)->toBe($caller->getKey())
        ->and($call->receiver_id)->toBe($receiver->getKey())
        ->and($call->conversation_key)->toBe(VideoCall::conversationKeyFor($caller->getKey(), $receiver->getKey()))
        ->and($call->status)->toBe(VideoCallStatus::Pending);

    Event::assertDispatched(VideoCallInitiated::class, fn (VideoCallInitiated $event) => $event->videoCall->is($call));
});

test('users cannot call themselves', function () {
    $user = User::factory()->create();

    Event::fake();

    $this->actingAs($user)
        ->postJson(route('calls.initiate'), [
            'receiver_id' => $user->getKey(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('receiver_id');

    expect(VideoCall::query()->count())->toBe(0);
    Event::assertNotDispatched(VideoCallInitiated::class);
});

test('receiver can accept a call', function () {
    $caller = User::factory()->create();
    $receiver = User::factory()->create();
    $call = createVideoCallRecord($caller, $receiver);

    Event::fake();

    $this->actingAs($receiver)
        ->postJson(route('calls.answer', $call))
        ->assertSuccessful();

    $call->refresh();

    expect($call->status)->toBe(VideoCallStatus::Active)
        ->and($call->started_at)->not->toBeNull();

    Event::assertDispatched(VideoCallStatusChanged::class, fn (VideoCallStatusChanged $event) => $event->videoCall->is($call));
});

test('receiver can decline a call', function () {
    $caller = User::factory()->create();
    $receiver = User::factory()->create();
    $call = createVideoCallRecord($caller, $receiver);

    Event::fake();

    $this->actingAs($receiver)
        ->postJson(route('calls.decline', $call))
        ->assertSuccessful();

    $call->refresh();

    expect($call->status)->toBe(VideoCallStatus::Declined)
        ->and($call->ended_at)->not->toBeNull();

    Event::assertDispatched(VideoCallStatusChanged::class, fn (VideoCallStatusChanged $event) => $event->videoCall->is($call));
});

test('caller can end a call', function () {
    $caller = User::factory()->create();
    $receiver = User::factory()->create();
    $call = createVideoCallRecord($caller, $receiver, [
        'status' => VideoCallStatus::Active,
        'started_at' => now()->subMinute(),
    ]);

    Event::fake();

    $this->actingAs($caller)
        ->postJson(route('calls.end', $call))
        ->assertSuccessful();

    $call->refresh();

    expect($call->status)->toBe(VideoCallStatus::Ended)
        ->and($call->ended_at)->not->toBeNull();

    Event::assertDispatched(VideoCallStatusChanged::class, fn (VideoCallStatusChanged $event) => $event->videoCall->is($call));
});

test('third parties cannot signal on a call they are not part of', function () {
    $caller = User::factory()->create();
    $receiver = User::factory()->create();
    $thirdParty = User::factory()->create();
    $call = createVideoCallRecord($caller, $receiver);

    Event::fake();

    $this->actingAs($thirdParty)
        ->postJson(route('calls.signal', $call), [
            'signal_data' => [
                'type' => 'offer',
                'sdp' => 'fake-sdp',
            ],
        ])
        ->assertForbidden();

    Event::assertNotDispatched(VideoCallSignal::class);
});

test('video call client configures public stun servers and media permission feedback', function () {
    $client = file_get_contents(resource_path('js/app.js'));

    expect($client)
        ->toContain('stun:stun.l.google.com:19302')
        ->toContain('stun:stun1.l.google.com:19302')
        ->toContain('iceServers: videoCallIceServers')
        ->toContain('Camera or microphone access was denied')
        ->toContain('this.flushPendingSignals();')
        ->toContain('window.conversationVideoCallControl');
});

test('conversation keeps video call alpine controls stable during livewire refreshes', function () {
    $conversation = file_get_contents(resource_path('views/pages/messages/⚡conversation.blade.php'));

    preg_match('/<button\s+type="button"[\s\S]*?data-video-call-control[\s\S]*?<\/button>/', $conversation, $videoCallButton);

    expect($conversation)
        ->toContain('wire:key="conversation-video-call-{{ $otherUserId }}"')
        ->toContain('wire:ignore.self')
        ->toContain('data-conversation-video-call')
        ->toContain('x-init="$el.__conversationVideoCall = $data; init()"')
        ->toContain('x-data="window.conversationVideoCall({');

    expect($videoCallButton[0] ?? '')
        ->toContain('x-data="window.conversationVideoCallControl()"')
        ->toContain('x-on:click="startCall()"')
        ->not->toContain('wire:ignore');
});

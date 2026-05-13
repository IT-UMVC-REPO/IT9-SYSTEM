<?php

use App\Enums\VideoCallStatus;
use App\Events\VideoCallInitiated;
use App\Events\VideoCallSignal;
use App\Events\VideoCallStatusChanged;
use App\Models\User;
use App\Models\VideoCall;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

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

function messagingBladeSource(string $file): string
{
    $path = glob(resource_path('views/pages/messages/*'.$file.'.blade.php'))[0] ?? null;

    expect($path)->not->toBeNull();

    return file_get_contents($path);
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

test('call initiation retries pusher when the default broadcaster is unavailable', function () {
    $caller = User::factory()->create();
    $receiver = User::factory()->create();

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.pusher.key' => 'ably-app.key',
        'broadcasting.connections.pusher.secret' => 'ably-secret',
        'broadcasting.connections.pusher.app_id' => 'ably-app',
    ]);

    $factory = new class
    {
        public int $queueAttempts = 0;

        /**
         * @var array<int, string|null>
         */
        public array $connections = [];

        /**
         * @var array<int, array{event: string, payload: array<string, mixed>}>
         */
        public array $broadcasts = [];

        public function queue(object $event): void
        {
            $this->queueAttempts++;

            throw new RuntimeException('default broadcaster unavailable');
        }

        public function connection(?string $name = null): BroadcasterContract
        {
            $this->connections[] = $name;

            return new class($this) implements BroadcasterContract
            {
                public function __construct(private object $factory) {}

                public function auth($request): mixed
                {
                    return null;
                }

                public function validAuthenticationResponse($request, $result): mixed
                {
                    return $result;
                }

                public function broadcast(array $channels, $event, array $payload = []): void
                {
                    $this->factory->broadcasts[] = [
                        'event' => $event,
                        'payload' => $payload,
                    ];
                }
            };
        }
    };

    $this->app->instance(BroadcastFactory::class, $factory);

    $this->actingAs($caller)
        ->postJson(route('calls.initiate'), [
            'receiver_id' => $receiver->getKey(),
        ])
        ->assertCreated()
        ->assertJsonPath('realtime_available', true);

    expect($factory->queueAttempts)->toBe(1)
        ->and($factory->connections)->toBe(['pusher'])
        ->and($factory->broadcasts[0]['event'])->toBe('VideoCallInitiated')
        ->and($factory->broadcasts[0]['payload']['receiver_id'])->toBe($receiver->getKey());
});

test('call initiation keeps the pending call when realtime initiation broadcast is unavailable', function () {
    $caller = User::factory()->create();
    $receiver = User::factory()->create();

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.pusher.key' => 'ably-app.key',
        'broadcasting.connections.pusher.secret' => 'ably-secret',
        'broadcasting.connections.pusher.app_id' => 'ably-app',
    ]);

    $factory = new class
    {
        public int $queueAttempts = 0;

        /**
         * @var array<int, string|null>
         */
        public array $connections = [];

        public function queue(object $event): void
        {
            $this->queueAttempts++;

            throw new RuntimeException('default broadcaster unavailable');
        }

        public function connection(?string $name = null): BroadcasterContract
        {
            $this->connections[] = $name;

            return new class implements BroadcasterContract
            {
                public function auth($request): mixed
                {
                    return null;
                }

                public function validAuthenticationResponse($request, $result): mixed
                {
                    return $result;
                }

                public function broadcast(array $channels, $event, array $payload = []): void
                {
                    throw new RuntimeException('pusher fallback unavailable');
                }
            };
        }
    };

    $this->app->instance(BroadcastFactory::class, $factory);

    $this->actingAs($caller)
        ->postJson(route('calls.initiate'), [
            'receiver_id' => $receiver->getKey(),
        ])
        ->assertCreated()
        ->assertJsonPath('realtime_available', false)
        ->assertJsonPath('message', 'Video call service is unavailable right now.');

    $call = VideoCall::query()->first();

    expect($factory->queueAttempts)->toBe(1)
        ->and($factory->connections)->toBe(['pusher'])
        ->and($call)->not->toBeNull()
        ->and($call->status)->toBe(VideoCallStatus::Pending)
        ->and($call->ended_at)->toBeNull();
});

test('direct call initiation broadcasts on conversation and receiver notification channels', function () {
    $caller = User::factory()->create([
        'name' => 'Caller Mina',
    ]);
    $receiver = User::factory()->create();
    $call = createVideoCallRecord($caller, $receiver);
    $event = new VideoCallInitiated($call);

    $channels = collect($event->broadcastOn())
        ->map(fn ($channel): string => $channel->name)
        ->all();

    expect($channels)
        ->toContain('private-messaging.'.$call->conversation_key)
        ->toContain('private-calls.'.$receiver->getKey())
        ->and($event->broadcastAs())->toBe('VideoCallInitiated')
        ->and($event->broadcastWith())->toMatchArray([
            'call_id' => $call->getKey(),
            'caller_id' => $caller->getKey(),
            'caller_name' => 'Caller Mina',
            'receiver_id' => $receiver->getKey(),
            'conversation_key' => $call->conversation_key,
            'is_group_call' => false,
        ]);
});

test('conversation polling surfaces pending incoming calls when the initiation broadcast is missed', function () {
    $caller = User::factory()->create();
    $receiver = User::factory()->create();
    $call = createVideoCallRecord($caller, $receiver);

    Livewire::actingAs($receiver)
        ->test('pages::messages.conversation', ['conversationReference' => (string) $caller->getKey()])
        ->assertSet('incomingCallId', null)
        ->call('refreshThread')
        ->assertSet('incomingCallId', $call->getKey())
        ->assertDispatched('conversation-auto-answer');
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

test('participants can send opaque peer signal payloads', function () {
    $caller = User::factory()->create();
    $receiver = User::factory()->create();
    $call = createVideoCallRecord($caller, $receiver);
    $signalData = [
        'type' => 'offer',
        'sdp' => 'v=0',
    ];

    Event::fake();

    $this->actingAs($caller)
        ->postJson(route('calls.signal', $call), [
            'signal_data' => $signalData,
        ])
        ->assertSuccessful();

    Event::assertDispatched(
        VideoCallSignal::class,
        fn (VideoCallSignal $event) => $event->videoCall->is($call)
            && $event->senderId === $caller->getKey()
            && $event->signalData === $signalData,
    );
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

test('guests cannot read call ice server configuration', function () {
    $this->getJson(route('calls.ice-servers'))
        ->assertUnauthorized();
});

test('authenticated users receive stun-only ice server configuration by default', function () {
    config([
        'webrtc.stun_urls' => [
            'stun:stun.example.test:19302',
            'stun:stun2.example.test:19302',
        ],
        'webrtc.turn_urls' => [],
        'webrtc.turn_username' => null,
        'webrtc.turn_credential' => null,
        'webrtc.turn_shared_secret' => null,
        'webrtc.ice_transport_policy' => 'all',
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('calls.ice-servers'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'ice_servers')
        ->assertJsonPath('ice_servers.0.urls.0', 'stun:stun.example.test:19302')
        ->assertJsonPath('ice_servers.1.urls.0', 'stun:stun2.example.test:19302')
        ->assertJsonPath('ice_transport_policy', 'all');

    expect($response->json('ice_servers.0'))->not->toHaveKey('username')
        ->and($response->json('ice_servers.1'))->not->toHaveKey('credential');
});

test('authenticated users receive static turn credentials when configured', function () {
    config([
        'webrtc.stun_urls' => ['stun.example.test:19302'],
        'webrtc.turn_urls' => [
            'turn.example.test:3478?transport=udp',
            'turns:turn.example.test:5349?transport=tcp',
        ],
        'webrtc.turn_username' => 'static-user',
        'webrtc.turn_credential' => 'static-secret',
        'webrtc.turn_shared_secret' => null,
        'webrtc.ice_transport_policy' => 'relay',
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('calls.ice-servers'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'ice_servers')
        ->assertJsonPath('ice_servers.0.urls.0', 'stun:stun.example.test:19302')
        ->assertJsonPath('ice_servers.1.urls.0', 'turn:turn.example.test:3478?transport=udp')
        ->assertJsonPath('ice_servers.1.urls.1', 'turns:turn.example.test:5349?transport=tcp')
        ->assertJsonPath('ice_servers.1.username', 'static-user')
        ->assertJsonPath('ice_servers.1.credential', 'static-secret')
        ->assertJsonPath('ice_transport_policy', 'relay');
});

test('shared-secret turn credentials are temporary and preferred over static credentials', function () {
    Carbon::setTestNow('2026-05-01 10:00:00');

    try {
        $user = User::factory()->create();
        $username = now()->addSeconds(600)->getTimestamp().':'.$user->getKey();
        $credential = base64_encode(hash_hmac('sha1', $username, 'turn-shared-secret', true));

        config([
            'webrtc.stun_urls' => ['stun:stun.example.test:19302'],
            'webrtc.turn_urls' => ['turn:turn.example.test:3478?transport=udp'],
            'webrtc.turn_username' => 'static-user',
            'webrtc.turn_credential' => 'static-secret',
            'webrtc.turn_shared_secret' => 'turn-shared-secret',
            'webrtc.turn_ttl' => 600,
            'webrtc.ice_transport_policy' => 'all',
        ]);

        $this->actingAs($user)
            ->getJson(route('calls.ice-servers'))
            ->assertSuccessful()
            ->assertJsonPath('ice_servers.1.urls.0', 'turn:turn.example.test:3478?transport=udp')
            ->assertJsonPath('ice_servers.1.username', $username)
            ->assertJsonPath('ice_servers.1.credential', $credential)
            ->assertJsonPath('ice_transport_policy', 'all');
    } finally {
        Carbon::setTestNow();
    }
});

test('video call signaling uses ably pusher-compatible echo credentials', function () {
    $client = file_get_contents(resource_path('js/echo.js'));
    $app = file_get_contents(resource_path('js/app.js'));
    $broadcasting = file_get_contents(config_path('broadcasting.php'));

    expect($client)
        ->toContain('Broadcasting: Pusher-compatible realtime transport')
        ->toContain("broadcaster: 'pusher'")
        ->toContain('VITE_REVERB_APP_KEY')
        ->toContain('VITE_REVERB_HOST')
        ->toContain('VITE_REVERB_PORT')
        ->toContain('VITE_REVERB_SCHEME')
        ->toContain("cluster: import.meta.env.VITE_REVERB_APP_CLUSTER ?? 'mt1'")
        ->toContain('httpHost: realtimeHost')
        ->toContain('disableStats: true')
        ->toContain("enabledTransports: ['ws', 'wss']")
        ->not->toContain("broadcaster: 'reverb'")
        ->not->toContain('VITE_PUSHER_APP_KEY')
        ->not->toContain('VITE_PUSHER_APP_CLUSTER')
        ->and($broadcasting)
        ->toContain("\$pusherCluster = env('PUSHER_APP_CLUSTER') ?: 'mt1';")
        ->toContain("'host' => env('PUSHER_HOST') ?: ('api-'.\$pusherCluster.'.pusher.com')")
        ->toContain('PUSHER_CONNECT_TIMEOUT')
        ->toContain('PUSHER_TIMEOUT')
        ->toContain('REVERB_CONNECT_TIMEOUT')
        ->toContain('REVERB_TIMEOUT')
        ->and($app)
        ->toContain("import './echo';");
});

test('video call signaling events stay synchronous and broadcast through pusher', function () {
    $eventFiles = [
        app_path('Events/VideoCallInitiated.php'),
        app_path('Events/VideoCallSignal.php'),
        app_path('Events/VideoCallStatusChanged.php'),
        app_path('Events/GroupCallInitiated.php'),
        app_path('Events/GroupCallSignal.php'),
        app_path('Events/GroupCallStatusChanged.php'),
    ];

    foreach ($eventFiles as $eventFile) {
        $source = file_get_contents($eventFile);

        expect($source)
            ->toContain('ShouldBroadcastNow')
            ->toContain('InteractsWithBroadcasting')
            ->toContain("\$this->broadcastVia('pusher');")
            ->not->toContain('ShouldBroadcast,');
    }
});

test('video call client uses native rtc peer connection and server-provided ice configuration', function () {
    $app = file_get_contents(resource_path('js/app.js'));
    $ringtone = file_get_contents(resource_path('js/ringtone.js'));
    $videoCall = file_get_contents(resource_path('js/video-call.js'));
    $groupCall = file_get_contents(resource_path('js/group-call.js'));
    $groupCallPip = file_get_contents(resource_path('js/group-call-pip.js'));
    $videoCallControl = file_get_contents(resource_path('js/video-call-control.js'));

    expect($app)
        ->toContain("import { RingtonePlayer } from './ringtone';")
        ->toContain('window.sukiRingtone')
        ->toContain('window.conversationVideoCall')
        ->toContain('window.groupConversationVideoCall')
        ->toContain('window.conversationVideoCallControl')
        ->toContain("import { sukiGroupCallPip } from './group-call-pip';")
        ->toContain('window.sukiGroupCallPip')
        ->toContain('window.sukiMessageScroller')
        ->and($ringtone)
        ->toContain("new Audio('/sound/reader.mp3')")
        ->toContain('loop = true')
        ->toContain('volume = 0.7')
        ->and($videoCall)
        ->toContain('new RTCPeerConnection(peerConnectionOptions(this.iceServers, this.iceTransportPolicy))')
        ->toContain('await this.loadIceConfiguration();')
        ->toContain('allowRealtimeUnavailable: true')
        ->toContain('iceTransportPolicy')
        ->toContain("bundlePolicy: 'max-bundle'")
        ->toContain("rtcpMuxPolicy: 'require'")
        ->toContain('iceCandidatePoolSize: 4')
        ->toContain('waitForIceGathering(peer, timeoutMs = 8000)')
        ->toContain('peer.localDescription ?? preferredOffer')
        ->toContain('peer.localDescription ?? preferredAnswer')
        ->toContain('peer.onicecandidate')
        ->toContain('iceCandidateSignal(event.candidate)')
        ->toContain('localDescriptionSignal(localDescription)')
        ->toContain('isSessionDescriptionSignal(signalData)')
        ->toContain("signal_data: { type: 'renegotiate' }")
        ->toContain('politePeer')
        ->toContain('makingOffer')
        ->toContain('payload?.ice_transport_policy')
        ->toContain('export const videoCallConnectingWarningDelay = 8000;')
        ->toContain('showTurnWarning')
        ->toContain('setRemoteDescription(new RTCSessionDescription')
        ->toContain('new RTCIceCandidate')
        ->toContain('iceServers: iceServers ?? videoCallIceServers')
        ->toContain('Calls across different networks need TURN credentials in .env.')
        ->toContain('realtimeEnabled')
        ->toContain('Camera or microphone access was denied')
        ->toContain('getBestVideoConstraints')
        ->toContain('hasMultipleCameras')
        ->toContain('switchCamera')
        ->toContain('sender.replaceTrack(newVideoTrack)')
        ->toContain('Could not switch cameras. Your current camera is still active.')
        ->toContain('preferCodecs')
        ->toContain('setMaxBitrate')
        ->toContain('requestPictureInPicture')
        ->toContain('persistentPipVideo')
        ->toContain('keepAliveOnNavigate')
        ->toContain('ensurePersistentPipVideo')
        ->toContain('setRemoteStream')
        ->toContain('peer.restartIce?.();')
        ->toContain('callStatusLabel()')
        ->toContain('Connection interrupted. Attempting recovery...')
        ->toContain('new MediaStream([event.track])')
        ->toContain('remoteVideoActive')
        ->toContain('const videoMaxKbps = isMobile ? 900 : 1800;')
        ->toContain('scaleResolutionDownBy = 1')
        ->not->toContain('void this.endCall(this.connectionFailureMessage());')
        ->and($groupCall)
        ->toContain('peerConnectionOptions')
        ->toContain('videoCallDisabledReason()')
        ->toContain('new RTCPeerConnection(peerConnectionOptions(this.iceServers, this.iceTransportPolicy))')
        ->toContain('peer.localDescription ?? preferredOffer')
        ->toContain('peer.localDescription ?? preferredAnswer')
        ->toContain('groupCallErrorMessage')
        ->toContain('video: false, audio: true')
        ->toContain('video: true, audio: false')
        ->toContain('allowRealtimeUnavailable: true')
        ->toContain('Could not start the group call.')
        ->toContain('formattedCallDuration')
        ->toContain('showCallChrome')
        ->toContain('toggleMicrophone')
        ->toContain('toggleCamera')
        ->toContain('switchCamera')
        ->toContain('updateCameraCapabilities')
        ->toContain('normalizeParticipantIds')
        ->toContain('Number(this.authUserId) < Number(participantId)')
        ->toContain('this.normalizeParticipantIds(payload.participants ?? [])')
        ->toContain('this.shouldInitiatePeerConnection(participantId)')
        ->toContain('group-call-join')
        ->toContain('safeHandleGroupSignal')
        ->toContain('safeSendGroupSignal')
        ->toContain('peerSignalQueues')
        ->toContain('peerReconnectTimers')
        ->toContain('createPeerState')
        ->toContain('negotiateGroupPeer')
        ->toContain('schedulePeerReconnect')
        ->toContain('peer.onicecandidate')
        ->toContain('iceCandidateSignal(event.candidate)')
        ->toContain('localDescriptionSignal(localDescription)')
        ->toContain('isSessionDescriptionSignal(signalData)')
        ->toContain("type: 'renegotiate'")
        ->toContain('refreshParticipantVideoSources')
        ->toContain('gridStyle(participantCount')
        ->toContain('const activeParticipantIds = new Set([')
        ->toContain('...this.peerConnections.keys()')
        ->toContain('window.setTimeout(() => attachSources(60), 50)')
        ->toContain('const isDescription = isSessionDescriptionSignal(signalData);')
        ->toContain("this.createPeerConnection(senderId, signalData.type === 'offer' ? false : this.shouldInitiatePeerConnection(senderId))")
        ->toContain('element.play().catch(() => {})')
        ->toContain('catch(() => {})')
        ->toContain('Could not sync the group call. Please check your realtime connection.')
        ->toContain('group-call-local-grid-video')
        ->toContain('group-tile-video')
        ->toContain('hasMultipleCameras')
        ->toContain("this.callStatus = 'ringing'")
        ->toContain('declineGroupCall')
        ->toContain('remoteVideoActive')
        ->toContain('setMaxBitrate')
        ->toContain('window.__activeGroupCall')
        ->toContain('window.sukiGroupCallPip.enter')
        ->toContain('window.sukiGroupCallPip?.sync(this)')
        ->toContain('keepAliveOnNavigate')
        ->toContain('new MediaStream([event.track])')
        ->toContain('this.remoteStreams = new Map(this.remoteStreams)')
        ->not->toContain('requestPictureInPicture')
        ->and($groupCallPip)
        ->toContain('data-pip-grid')
        ->toContain('window.__activeGroupCall = call')
        ->toContain('toggleMicrophone')
        ->toContain('toggleCamera')
        ->toContain('Waiting for others to join...')
        ->and($videoCallControl)
        ->toContain('conversationVideoCallControl')
        ->toContain('$el.closest(\'[data-conversation-video-call]\')?.__conversationVideoCall')
        ->and($videoCall)
        ->not->toContain('localStorage')
        ->not->toContain('sessionStorage')
        ->not->toContain("import Peer from '@thaunknown/simple-peer';")
        ->not->toContain('new Peer({')
        ->not->toContain('trickle: false')
        ->not->toContain('sanitizeIncomingSdp')
        ->not->toContain('console.log')
        ->not->toContain('}).catch(() => navigator.mediaDevices.getUserMedia({ video: true, audio: true }))')
        ->and($groupCall)
        ->not->toContain('localStorage')
        ->not->toContain('sessionStorage')
        ->not->toContain("import Peer from '@thaunknown/simple-peer';")
        ->not->toContain('new Peer({')
        ->not->toContain('trickle: false')
        ->not->toContain('sanitizeIncomingSdp')
        ->not->toContain('console.log');
});

test('conversation keeps video call alpine controls stable during livewire refreshes', function () {
    $conversation = messagingBladeSource('conversation');
    $conversationComponent = file_get_contents(app_path('Livewire/Pages/Messages/Conversation.php'));
    $groupConversationComponent = file_get_contents(app_path('Livewire/Pages/Messages/GroupConversation.php'));

    expect($conversation)
        ->toContain('wire:key="conversation-video-call-{{ $otherUserId }}"')
        ->toContain('wire:ignore.self')
        ->toContain('data-conversation-video-call')
        ->toContain('realtimeEnabled: @js($realtimeEnabled)')
        ->toContain('x-effect="$wire.$set(\'callInProgress\', callStatus !== \'idle\' && callStatus !== \'ended\', false)"')
        ->toContain('x-on:livewire:navigating.window="keepAliveOnNavigate()"')
        ->toContain('x-on:click="keepAliveOnNavigate()"')
        ->toContain("iceServers: @js(route('calls.ice-servers'))")
        ->toContain("callStatus === 'active' || callStatus === 'connecting'")
        ->toContain('callStatusLabel()')
        ->toContain('lg:h-[calc(100dvh-52px)]')
        ->toContain('x-show="showTurnWarning"')
        ->toContain('Cross-network calls require TURN credentials')
        ->toContain('px-3 py-2 pb-3')
        ->toContain('conversation-call-local-background-video')
        ->toContain('transform: scaleX(-1)')
        ->toContain('Device settings')
        ->toContain('sukiMessageScroller()')
        ->toContain('x-on:message-sent.window="scrollToBottom()"')
        ->not->toContain('arrows-pointing-out')
        ->not->toContain('request'.'Full'.'screen')
        ->toContain('formattedCallDuration()')
        ->toContain('toggleMicrophone()')
        ->toContain('toggleCamera()')
        ->toContain('phone-x-mark')
        ->toContain('Camera off')
        ->toContain('Picture in picture')
        ->toContain("endCall(callStatus === 'calling' ? 'Call cancelled.' : 'Call ended.')")
        ->toContain('bg-white/60')
        ->toContain('...window.conversationVideoCall({')
        ->toContain('x-bind:disabled="callStatus !== \'idle\' || !supportsVideoCalling()"')
        ->not->toContain('Cancel call')
        ->not->toContain('LOCAL PREVIEW')
        ->not->toContain('CALL STATUS')
        ->not->toContain('reverbEnabled')
        ->and($conversationComponent)
        ->toContain('public bool $callInProgress = false;')
        ->toContain('if ($this->callInProgress) {')
        ->toContain('$this->skipRender();')
        ->and($groupConversationComponent)
        ->toContain('public bool $callInProgress = false;')
        ->toContain('if ($this->callInProgress) {')
        ->toContain('$this->skipRender();');
});

test('group conversation call overlay uses desktop tiles and a mobile filmstrip', function () {
    $groupConversation = messagingBladeSource('group-conversation');

    expect($groupConversation)
        ->toContain('participantSummaries: @js($this->groupParticipantSummaries())')
        ->toContain('x-effect="$wire.$set(\'callInProgress\', callStatus !== \'idle\' && callStatus !== \'ended\', false)"')
        ->toContain('x-on:beforeunload.window="disposeOnLeave({ force: true })"')
        ->toContain('x-on:livewire:navigating.window="keepAliveOnNavigate()"')
        ->toContain("conversation: @js(route('messages.group', ['groupId' => \$groupId]))")
        ->toContain('z-[100]')
        ->toContain('group-call-local-background-video')
        ->toContain('group-call-local-grid-video')
        ->toContain('group-call-speaker-video')
        ->toContain('group-call-local-thumbnail-video')
        ->toContain('participant.thumbnailElementId')
        ->toContain('thumbnailParticipants()')
        ->toContain('selectSpeaker(participant.id)')
        ->toContain('participant.tileElementId')
        ->toContain('isMobileViewport: window.innerWidth < 1024')
        ->toContain('viewportWidth: window.innerWidth')
        ->toContain('viewportHeight: window.innerHeight')
        ->toContain("gridStyle(remoteParticipants.length, viewportWidth, viewportHeight) + ' gap: 12px;'")
        ->toContain('x-effect="$el.volume = Number(volume);"')
        ->toContain('wire:ignore autoplay playsinline')
        ->not->toContain('gridLayoutClass(remoteParticipants.length)')
        ->not->toContain("return 'grid grid-cols-3 grid-rows-2';")
        ->not->toContain('$data.remoteStreams?.get(participant.id)')
        ->not->toContain('if ($el.srcObject !== s) $el.srcObject = s;')
        ->toContain('scale-x-[-1]')
        ->toContain('callPreviewStyle()')
        ->toContain('remoteParticipants.length === 0')
        ->toContain('rounded-[1.75rem] border border-white/10 bg-neutral-900')
        ->toContain('pb-[max(7.5rem,env(safe-area-inset-bottom))]')
        ->toContain('pt-[max(5.25rem,env(safe-area-inset-top))]')
        ->toContain('flex w-max gap-2')
        ->toContain('group-call-join')
        ->toContain('A group call is in progress')
        ->toContain('Device settings')
        ->toContain('sukiMessageScroller()')
        ->toContain('x-on:group-message-sent.window="scrollToBottom()"')
        ->not->toContain('arrows-pointing-out')
        ->not->toContain('request'.'Full'.'screen')
        ->toContain('switchCamera()')
        ->toContain('Waiting for others to join...')
        ->toContain('activeParticipantCount()')
        ->toContain('phone-x-mark')
        ->toContain('bg-black/55')
        ->toContain('incomingCallId')
        ->toContain('lg:h-[calc(100dvh-52px)]')
        ->toContain("x-bind:title=\"supportsVideoCalling() ? @js(__('Start group call')) : videoCallDisabledReason()\"")
        ->toContain('px-3 py-2 pb-2')
        ->toContain('group-call-join\', { callId: @js($incomingCallId) }')
        ->toContain('callStatus === \'ringing\'')
        ->toContain('declineGroupCall()')
        ->toContain('remoteVideoActive.get(participant.id)')
        ->toContain('Camera off')
        ->toContain('Picture in picture')
        ->toContain("callStatus === 'active' || callStatus === 'connecting' || callStatus === 'ended'")
        ->not->toContain('min-h-[40vh]')
        ->not->toContain('group-conversation-auto-answer')
        ->not->toContain('LOCAL PREVIEW')
        ->not->toContain('CALL STATUS');
});

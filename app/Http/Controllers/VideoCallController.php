<?php

namespace App\Http\Controllers;

use App\Enums\VideoCallStatus;
use App\Events\GroupCallInitiated;
use App\Events\GroupCallSignal;
use App\Events\GroupCallStatusChanged;
use App\Events\VideoCallInitiated;
use App\Events\VideoCallSignal;
use App\Events\VideoCallStatusChanged;
use App\Models\ConversationGroup;
use App\Models\ConversationGroupMember;
use App\Models\VideoCall;
use App\Models\VideoCallParticipant;
use App\Services\GroupMessageService;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class VideoCallController extends Controller
{
    public function iceServers(Request $request): JsonResponse
    {
        return response()->json([
            'ice_servers' => $this->iceServersFor($request),
            'ice_transport_policy' => $this->iceTransportPolicy(),
        ]);
    }

    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $caller = $request->user();
        $receiverId = (int) $validated['receiver_id'];

        if ($caller->getKey() === $receiverId) {
            throw ValidationException::withMessages([
                'receiver_id' => __('You cannot call yourself.'),
            ]);
        }

        $videoCall = VideoCall::query()->create([
            'caller_id' => $caller->getKey(),
            'receiver_id' => $receiverId,
            'conversation_key' => VideoCall::conversationKeyFor($caller->getKey(), $receiverId),
            'status' => VideoCallStatus::Pending,
        ]);

        $broadcasted = $this->dispatchBroadcastSafely(
            new VideoCallInitiated($videoCall),
            'VideoCallInitiated broadcast failed (Reverb may be down): ',
        );

        return response()->json([
            ...$this->callPayload($videoCall),
            'realtime_available' => $broadcasted,
            'message' => $broadcasted ? null : __('Video call service is unavailable right now.'),
        ], 201);
    }

    public function signal(Request $request, VideoCall $call): JsonResponse
    {
        abort_if($call->is_group_call, 404);

        $validated = $request->validate([
            'signal_data' => ['required', 'array'],
        ]);

        $userId = $request->user()->getKey();

        abort_if(! $this->isParticipant($call, $userId), 403);

        $broadcasted = $this->dispatchBroadcastSafely(
            new VideoCallSignal($call, $userId, $validated['signal_data']),
            'VideoCallSignal broadcast failed (Reverb may be down): ',
        );

        return $this->broadcastResponse($broadcasted);
    }

    public function answer(Request $request, VideoCall $call): JsonResponse
    {
        abort_if($call->is_group_call, 404);
        abort_if($call->receiver_id !== $request->user()->getKey(), 403);

        $call->forceFill([
            'status' => VideoCallStatus::Active,
            'started_at' => now(),
        ])->save();

        $call->refresh();

        $broadcasted = $this->dispatchBroadcastSafely(
            new VideoCallStatusChanged($call),
            'VideoCallStatusChanged broadcast failed while answering a call (Reverb may be down): ',
        );

        return $this->broadcastResponse($broadcasted);
    }

    public function decline(Request $request, VideoCall $call): JsonResponse
    {
        abort_if($call->is_group_call, 404);
        abort_if($call->receiver_id !== $request->user()->getKey(), 403);

        $call->forceFill([
            'status' => VideoCallStatus::Declined,
            'ended_at' => now(),
        ])->save();

        $call->refresh();

        $broadcasted = $this->dispatchBroadcastSafely(
            new VideoCallStatusChanged($call),
            'VideoCallStatusChanged broadcast failed while declining a call (Reverb may be down): ',
        );

        return $this->broadcastResponse($broadcasted);
    }

    public function end(Request $request, VideoCall $call): JsonResponse
    {
        abort_if($call->is_group_call, 404);
        abort_if(! $this->isParticipant($call, $request->user()->getKey()), 403);

        $call->forceFill([
            'status' => VideoCallStatus::Ended,
            'ended_at' => now(),
        ])->save();

        $call->refresh();

        $broadcasted = $this->dispatchBroadcastSafely(
            new VideoCallStatusChanged($call),
            'VideoCallStatusChanged broadcast failed while ending a call (Reverb may be down): ',
        );

        return $this->broadcastResponse($broadcasted);
    }

    public function initiateGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => ['required', 'integer', 'exists:conversation_groups,id'],
        ]);

        $group = ConversationGroup::query()->findOrFail((int) $validated['group_id']);
        $userId = $request->user()->getKey();

        abort_unless($this->isGroupMember($group->getKey(), $userId), 403);

        $this->expireStaleGroupCalls($group->getKey());

        $videoCall = VideoCall::query()->create([
            'caller_id' => $userId,
            'receiver_id' => null,
            'group_id' => $group->getKey(),
            'is_group_call' => true,
            'conversation_key' => 'group-'.$group->getKey(),
            'status' => VideoCallStatus::Pending,
        ]);

        VideoCallParticipant::query()->create([
            'video_call_id' => $videoCall->getKey(),
            'user_id' => $userId,
        ]);

        $broadcasted = $this->dispatchBroadcastSafely(
            new GroupCallInitiated($videoCall),
            'GroupCallInitiated broadcast failed (Pusher may be unavailable): ',
        );

        if ($broadcasted) {
            GroupMessageService::dispatchSystemMessage(
                $group->getKey(),
                'call_started',
                $userId,
                __(':name started a group call.', ['name' => $request->user()->name]),
            );
        }

        return response()->json([
            ...$this->groupCallPayload($videoCall),
            'realtime_available' => $broadcasted,
            'message' => $broadcasted ? null : __('Group video call service is unavailable right now.'),
        ], 201);
    }

    public function signalGroup(Request $request, VideoCall $call): JsonResponse
    {
        abort_unless($call->is_group_call && $call->group_id !== null, 404);

        $validated = $request->validate([
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'signal_data' => ['required', 'array'],
        ]);

        $userId = $request->user()->getKey();

        abort_unless($this->isGroupMember($call->group_id, $userId), 403);

        $recipientId = isset($validated['recipient_id']) ? (int) $validated['recipient_id'] : null;

        if ($recipientId !== null) {
            abort_unless($this->isGroupMember($call->group_id, $recipientId), 403);
        }

        $broadcasted = $this->dispatchBroadcastSafely(
            new GroupCallSignal($call, $userId, $recipientId, $validated['signal_data']),
            'GroupCallSignal broadcast failed (Pusher may be unavailable): ',
        );

        return $this->broadcastResponse($broadcasted);
    }

    public function answerGroup(Request $request, VideoCall $call): JsonResponse
    {
        abort_unless($call->is_group_call && $call->group_id !== null, 404);

        $userId = $request->user()->getKey();

        abort_unless($this->isGroupMember($call->group_id, $userId), 403);

        $this->expireStaleGroupCalls((int) $call->group_id);
        $call->refresh();

        abort_if($call->status === VideoCallStatus::Ended || $call->status === VideoCallStatus::Declined, 409);

        $alreadyJoined = VideoCallParticipant::query()
            ->where('video_call_id', $call->getKey())
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->exists();

        if (! $alreadyJoined && $this->activeGroupParticipantCount($call) >= 6) {
            throw ValidationException::withMessages([
                'call' => __('This group call is full.'),
            ]);
        }

        $call->forceFill([
            'status' => VideoCallStatus::Active,
            'started_at' => $call->started_at ?? now(),
        ])->save();

        VideoCallParticipant::query()->updateOrCreate(
            [
                'video_call_id' => $call->getKey(),
                'user_id' => $userId,
            ],
            [
                'joined_at' => now(),
                'left_at' => null,
            ],
        );

        $call->refresh();

        if (! $alreadyJoined) {
            GroupMessageService::dispatchSystemMessage(
                (int) $call->group_id,
                'user_joined',
                $userId,
                __(':name joined the call.', ['name' => $request->user()->name]),
            );
        }

        $broadcasted = $this->dispatchBroadcastSafely(
            new GroupCallStatusChanged($call),
            'GroupCallStatusChanged broadcast failed while joining a group call (Pusher may be unavailable): ',
        );

        return response()->json([
            ...$this->groupCallPayload($call),
            'realtime_available' => $broadcasted,
            'message' => $broadcasted ? null : __('Group video call service is unavailable right now.'),
        ], $broadcasted ? 200 : 503);
    }

    public function endGroup(Request $request, VideoCall $call): JsonResponse
    {
        abort_unless($call->is_group_call && $call->group_id !== null, 404);

        $userId = $request->user()->getKey();

        abort_unless($this->isGroupMember($call->group_id, $userId), 403);

        $leftParticipantCount = VideoCallParticipant::query()
            ->where('video_call_id', $call->getKey())
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->update(['left_at' => now()]);

        if ($leftParticipantCount > 0) {
            GroupMessageService::dispatchSystemMessage(
                (int) $call->group_id,
                'user_left',
                $userId,
                __(':name left the call.', ['name' => $request->user()->name]),
            );
        }

        if ($this->activeGroupParticipantCount($call) === 0) {
            $call->forceFill([
                'status' => VideoCallStatus::Ended,
                'ended_at' => now(),
            ])->save();

            GroupMessageService::dispatchSystemMessage(
                (int) $call->group_id,
                'call_ended',
                $userId,
                __('Group call ended.'),
            );
        }

        $call->refresh();

        $broadcasted = $this->dispatchBroadcastSafely(
            new GroupCallStatusChanged($call),
            'GroupCallStatusChanged broadcast failed while leaving a group call (Pusher may be unavailable): ',
        );

        return response()->json([
            ...$this->groupCallPayload($call),
            'realtime_available' => $broadcasted,
            'message' => $broadcasted ? null : __('Group video call service is unavailable right now.'),
        ], $broadcasted ? 200 : 503);
    }

    private function isParticipant(VideoCall $call, int $userId): bool
    {
        return in_array($userId, [$call->caller_id, $call->receiver_id], true);
    }

    private function isGroupMember(int $groupId, int $userId): bool
    {
        return ConversationGroupMember::query()
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function activeGroupParticipantCount(VideoCall $call): int
    {
        return VideoCallParticipant::query()
            ->where('video_call_id', $call->getKey())
            ->whereNull('left_at')
            ->count();
    }

    private function expireStaleGroupCalls(int $groupId): void
    {
        VideoCall::query()
            ->where('group_id', $groupId)
            ->where('is_group_call', true)
            ->whereIn('status', [
                VideoCallStatus::Active->value,
                VideoCallStatus::Pending->value,
            ])
            ->where(function (Builder $query): void {
                $query
                    ->where('created_at', '<', now()->subMinutes(90))
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('status', VideoCallStatus::Pending->value)
                            ->where('created_at', '<', now()->subMinutes(2));
                    })
                    ->orWhereDoesntHave('participants', function (Builder $query): void {
                        $query->whereNull('left_at');
                    });
            })
            ->update([
                'status' => VideoCallStatus::Ended->value,
                'ended_at' => now(),
            ]);
    }

    /**
     * @return array<int, array{urls: array<int, string>, username?: string, credential?: string}>
     */
    private function iceServersFor(Request $request): array
    {
        $iceServers = array_map(
            static fn (string $url): array => ['urls' => [$url]],
            $this->configuredUrls('stun_urls'),
        );

        $turnUrls = $this->configuredUrls('turn_urls');
        $turnCredentials = $this->turnCredentials($request);

        if ($turnUrls !== [] && $turnCredentials !== null) {
            $iceServers[] = [
                'urls' => $turnUrls,
                ...$turnCredentials,
            ];
        }

        return $iceServers;
    }

    /**
     * @return array<int, string>
     */
    private function configuredUrls(string $key): array
    {
        $configuredValue = config("webrtc.{$key}", []);

        if (is_string($configuredValue)) {
            $configuredValue = explode(',', $configuredValue);
        }

        if (! is_array($configuredValue)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $url): string => $this->normalizeIceUrl(trim((string) $url), $key),
            $configuredValue,
        )));
    }

    private function normalizeIceUrl(string $url, string $key): string
    {
        if ($url === '' || preg_match('/^(stun|turns?):/i', $url) === 1) {
            return $url;
        }

        return match ($key) {
            'stun_urls' => 'stun:'.$url,
            'turn_urls' => 'turn:'.$url,
            default => $url,
        };
    }

    /**
     * @return array{username: string, credential: string}|null
     */
    private function turnCredentials(Request $request): ?array
    {
        $sharedSecret = (string) config('webrtc.turn_shared_secret', '');

        if (filled($sharedSecret)) {
            $ttl = max(60, (int) config('webrtc.turn_ttl', 3600));
            $username = now()->addSeconds($ttl)->getTimestamp().':'.$request->user()->getKey();

            return [
                'username' => $username,
                'credential' => base64_encode(hash_hmac('sha1', $username, $sharedSecret, true)),
            ];
        }

        $username = config('webrtc.turn_username');
        $credential = config('webrtc.turn_credential');

        if (! filled($username) || ! filled($credential)) {
            return null;
        }

        return [
            'username' => (string) $username,
            'credential' => (string) $credential,
        ];
    }

    private function iceTransportPolicy(): string
    {
        $policy = (string) config('webrtc.ice_transport_policy', 'all');

        return in_array($policy, ['all', 'relay'], true) ? $policy : 'all';
    }

    /**
     * @return array{id: int, caller_id: int, receiver_id: int|null, conversation_key: string, status: string}
     */
    private function callPayload(VideoCall $call): array
    {
        return [
            'id' => $call->getKey(),
            'caller_id' => $call->caller_id,
            'receiver_id' => $call->receiver_id,
            'conversation_key' => $call->conversation_key,
            'status' => $call->status->value,
        ];
    }

    /**
     * @return array{id: int, caller_id: int, group_id: int|null, status: string, participants: array<int, int>}
     */
    private function groupCallPayload(VideoCall $call): array
    {
        return [
            'id' => $call->getKey(),
            'caller_id' => $call->caller_id,
            'group_id' => $call->group_id,
            'status' => $call->status->value,
            'participants' => $call->participants()
                ->whereNull('left_at')
                ->pluck('user_id')
                ->values()
                ->all(),
        ];
    }

    private function broadcastResponse(bool $broadcasted): JsonResponse
    {
        return response()->json([
            'status' => $broadcasted ? 'ok' : 'realtime_unavailable',
            'realtime_available' => $broadcasted,
            'message' => $broadcasted ? null : __('Video call service is unavailable right now.'),
        ], $broadcasted ? 200 : 503);
    }

    private function dispatchBroadcastSafely(object $event, string $warningPrefix): bool
    {
        try {
            event($event);

            return true;
        } catch (Throwable $broadcastException) {
            Log::warning($warningPrefix.$broadcastException->getMessage());
        }

        if (! $this->shouldRetryCallBroadcastViaPusher()) {
            return false;
        }

        try {
            $this->broadcastEventNow($event, 'pusher');

            return true;
        } catch (Throwable $pusherException) {
            Log::warning($warningPrefix.'Pusher fallback failed: '.$pusherException->getMessage());

            return false;
        }
    }

    private function shouldRetryCallBroadcastViaPusher(): bool
    {
        if (config('broadcasting.default') === 'pusher') {
            return false;
        }

        return filled(config('broadcasting.connections.pusher.key'))
            && filled(config('broadcasting.connections.pusher.secret'))
            && filled(config('broadcasting.connections.pusher.app_id'));
    }

    private function broadcastEventNow(object $event, string $connection): void
    {
        $channels = Arr::wrap($event->broadcastOn());

        if ($channels === []) {
            return;
        }

        $name = method_exists($event, 'broadcastAs')
            ? $event->broadcastAs()
            : $event::class;

        $payload = method_exists($event, 'broadcastWith')
            ? $event->broadcastWith()
            : [];

        app(BroadcastFactory::class)->connection($connection)->broadcast(
            $channels,
            $name,
            $payload ?? [],
        );
    }
}

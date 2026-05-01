<?php

namespace App\Http\Controllers;

use App\Enums\VideoCallStatus;
use App\Events\VideoCallInitiated;
use App\Events\VideoCallSignal;
use App\Events\VideoCallStatusChanged;
use App\Models\VideoCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

        if (! $broadcasted) {
            $videoCall->forceFill([
                'status' => VideoCallStatus::Ended,
                'ended_at' => now(),
            ])->save();

            $videoCall->refresh();
        }

        return response()->json([
            ...$this->callPayload($videoCall),
            'realtime_available' => $broadcasted,
            'message' => $broadcasted ? null : __('Video call service is unavailable right now.'),
        ], $broadcasted ? 201 : 503);
    }

    public function signal(Request $request, VideoCall $call): JsonResponse
    {
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

    private function isParticipant(VideoCall $call, int $userId): bool
    {
        return in_array($userId, [$call->caller_id, $call->receiver_id], true);
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
     * @return array{id: int, caller_id: int, receiver_id: int, conversation_key: string, status: string}
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
        } catch (\Throwable $broadcastException) {
            Log::warning($warningPrefix.$broadcastException->getMessage());

            return false;
        }
    }
}

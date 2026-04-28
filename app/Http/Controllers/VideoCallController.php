<?php

namespace App\Http\Controllers;

use App\Enums\VideoCallStatus;
use App\Events\VideoCallInitiated;
use App\Events\VideoCallSignal;
use App\Events\VideoCallStatusChanged;
use App\Models\VideoCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoCallController extends Controller
{
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $caller = $request->user();
        $receiverId = (int) $validated['receiver_id'];

        abort_if($caller->getKey() === $receiverId, 403);

        $videoCall = VideoCall::query()->create([
            'caller_id' => $caller->getKey(),
            'receiver_id' => $receiverId,
            'conversation_key' => VideoCall::conversationKeyFor($caller->getKey(), $receiverId),
            'status' => VideoCallStatus::Pending,
        ]);

        event(new VideoCallInitiated($videoCall));

        return response()->json($videoCall, 201);
    }

    public function signal(Request $request, VideoCall $call): JsonResponse
    {
        $validated = $request->validate([
            'signal_data' => ['required', 'array'],
        ]);

        $userId = $request->user()->getKey();

        abort_if(! $this->isParticipant($call, $userId), 403);

        event(new VideoCallSignal($call, $userId, $validated['signal_data']));

        return response()->json(['status' => 'ok']);
    }

    public function answer(Request $request, VideoCall $call): JsonResponse
    {
        abort_if($call->receiver_id !== $request->user()->getKey(), 403);

        $call->forceFill([
            'status' => VideoCallStatus::Active,
            'started_at' => now(),
        ])->save();

        event(new VideoCallStatusChanged($call->fresh()));

        return response()->json(['status' => 'ok']);
    }

    public function decline(Request $request, VideoCall $call): JsonResponse
    {
        abort_if($call->receiver_id !== $request->user()->getKey(), 403);

        $call->forceFill([
            'status' => VideoCallStatus::Declined,
            'ended_at' => now(),
        ])->save();

        event(new VideoCallStatusChanged($call->fresh()));

        return response()->json(['status' => 'ok']);
    }

    public function end(Request $request, VideoCall $call): JsonResponse
    {
        abort_if(! $this->isParticipant($call, $request->user()->getKey()), 403);

        $call->forceFill([
            'status' => VideoCallStatus::Ended,
            'ended_at' => now(),
        ])->save();

        event(new VideoCallStatusChanged($call->fresh()));

        return response()->json(['status' => 'ok']);
    }

    private function isParticipant(VideoCall $call, int $userId): bool
    {
        return in_array($userId, [$call->caller_id, $call->receiver_id], true);
    }
}

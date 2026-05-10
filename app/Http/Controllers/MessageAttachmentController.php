<?php

namespace App\Http\Controllers;

use App\Models\ConversationGroupMember;
use App\Models\GroupMessageAttachment;
use App\Models\MessageAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageAttachmentController extends Controller
{
    public function show(MessageAttachment $attachment): StreamedResponse|RedirectResponse
    {
        $attachment->loadMissing('message:id,sender_id,receiver_id');

        abort_if(
            $attachment->message === null
            || ! in_array(auth()->id(), [$attachment->message->sender_id, $attachment->message->receiver_id], true),
            403,
        );

        return $this->serveStoredAttachment($attachment->path, $attachment->name, $attachment->mime);
    }

    public function showGroup(GroupMessageAttachment $attachment): StreamedResponse|RedirectResponse
    {
        $attachment->loadMissing('groupMessage:id,group_id');

        abort_if($attachment->groupMessage === null, 404);

        $isMember = ConversationGroupMember::query()
            ->where('group_id', $attachment->groupMessage->group_id)
            ->where('user_id', auth()->id())
            ->exists();

        abort_if(! $isMember, 403);

        return $this->serveStoredAttachment($attachment->path, $attachment->name, $attachment->mime);
    }

    private function serveStoredAttachment(string $path, ?string $name, ?string $mime): StreamedResponse|RedirectResponse
    {
        $isExternalPath = Str::startsWith($path, ['http://', 'https://', '//']);
        $storagePath = $this->storagePathFromAttachmentPath($path);

        if ($storagePath === null) {
            if ($isExternalPath) {
                return redirect()->away($path);
            }

            abort(404);
        }

        if (! Storage::disk('public')->exists($storagePath)) {
            if ($isExternalPath) {
                return redirect()->away($path);
            }

            abort(404);
        }

        $stream = Storage::disk('public')->readStream($storagePath);

        abort_if($stream === false, 404);

        $fileName = $this->attachmentFileName($name, $storagePath);
        $fallbackName = Str::ascii($fileName) ?: 'attachment';

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                $fileName,
                $fallbackName,
            ),
            'Content-Type' => $mime ?: Storage::disk('public')->mimeType($storagePath) ?: 'application/octet-stream',
        ]);
    }

    private function storagePathFromAttachmentPath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            $url = Str::startsWith($path, '//') ? 'https:'.$path : $path;
            $urlPath = parse_url($url, PHP_URL_PATH);

            if (! is_string($urlPath) || $urlPath === '') {
                return null;
            }

            $path = rawurldecode(ltrim($urlPath, '/'));
        } else {
            $path = ltrim($path, '/');
        }

        if (Str::startsWith($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        $path = str_replace('\\', '/', $path);

        if ($path === '' || in_array('..', explode('/', $path), true)) {
            return null;
        }

        return $path;
    }

    private function attachmentFileName(?string $name, string $storagePath): string
    {
        $fileName = trim((string) ($name ?: basename($storagePath)));

        return str_replace(['"', "\r", "\n"], '', $fileName) ?: 'attachment';
    }
}

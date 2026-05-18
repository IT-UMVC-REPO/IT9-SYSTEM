<?php

namespace App\Jobs;

use App\Mail\AdminContactNotification;
use App\Models\ContactMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class NotifyAdminOfContactMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $contactMessageId,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $contactMessage = ContactMessage::query()->find($this->contactMessageId);
        $adminAddress = (string) config('mail.admin_address');

        if ($contactMessage === null || blank($adminAddress)) {
            return;
        }

        Mail::to($adminAddress)
            ->send(new AdminContactNotification($contactMessage));
    }
}

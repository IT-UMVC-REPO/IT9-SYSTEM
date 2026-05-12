<?php

namespace App\Notifications;

use App\Mail\EmailVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VerifyEmailWithCode extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly string $code) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): EmailVerification
    {
        return (new EmailVerification(
            user: $notifiable,
            verificationUrl: $notifiable->emailVerificationUrl(),
            verificationCode: $this->code,
        ))->to($notifiable->email, $notifiable->name);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}

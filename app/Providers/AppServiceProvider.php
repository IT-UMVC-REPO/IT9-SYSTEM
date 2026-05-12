<?php

namespace App\Providers;

use App\Enums\AuditEvent;
use App\Mail\Smtp2goTransport;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addNamespace('pages', viewPath: resource_path('views/pages'), classNamespace: 'App\\Livewire\\Pages');

        Mail::extend('smtp2go', function (): Smtp2goTransport {
            return new Smtp2goTransport(
                apiKey: (string) config('services.smtp2go.key', ''),
                senderName: (string) config('mail.from.name', config('app.name')),
                senderEmail: (string) config('mail.from.address', 'noreply@SukiMarket.app'),
            );
        });

        Event::listen(Registered::class, function (Registered $event): void {
            AuditLogger::log(AuditEvent::UserRegistered, "New account: {$event->user->email}", $event->user, $event->user->getKey());
        });

        Event::listen(Verified::class, function (Verified $event): void {
            AuditLogger::log(AuditEvent::UserEmailVerified, "{$event->user->name} verified their email.", $event->user, $event->user->getKey());
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user !== null) {
                AuditLogger::log(AuditEvent::UserLoggedOut, "{$event->user->name} logged out.", $event->user, $event->user->getKey());
            }
        });

        if (app()->environment('production') || str_contains(config('app.url'), 'https')) {
            URL::forceScheme('https');
        }
    }
}

<?php

namespace App\Providers;

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

        if (app()->environment('production') || str_contains(config('app.url'), 'https')) {
            URL::forceScheme('https');
        }
    }
}

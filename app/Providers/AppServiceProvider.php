<?php

namespace App\Providers;

use App\Services\BrevoApiTransport;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // One pager for every list (search and filters stay in its links; see the component).
        Paginator::defaultView('components.pagination');

        Password::defaults(function () {
            return Password::min(8);
        });

        Mail::extend('brevo_api', function (array $config = []) {
            return new BrevoApiTransport(
                $config['key'] ?? config('services.brevo.key'),
                isset($config['timeout']) ? (float) $config['timeout'] : null,
                isset($config['connect_timeout']) ? (float) $config['connect_timeout'] : null
            );
        });
    }
}

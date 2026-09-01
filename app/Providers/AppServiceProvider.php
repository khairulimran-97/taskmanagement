<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Passport::authorizationView(fn ($parameters) => view('mcp.authorize', $parameters));

        // Env carries the PEMs single-line with literal "\n" — restore real newlines
        foreach (['passport.private_key', 'passport.public_key'] as $key) {
            if (is_string(config($key))) {
                config([$key => str_replace('\\n', "\n", config($key))]);
            }
        }
    }
}

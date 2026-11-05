<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // OAuth metadata must match the public URL exactly, whatever Host header arrives.
        URL::forceRootUrl(config('app.url'));
        URL::forceScheme(parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https');

        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('oauth', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // The global limit also slows down password guessing spread over many addresses.
        RateLimiter::for('oauth-login', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(60)->by('all'),
        ]);
    }
}

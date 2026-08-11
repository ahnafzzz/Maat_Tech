<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        if ($this->app->environment('production')) {
            Request::setTrustedProxies(
                ['0.0.0.0/0', '::/0'],
                Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO
            );
        }

        $request = $this->app->bound('request') ? $this->app->make('request') : null;
        if ($this->app->environment('production') && $request) {
            $isSecureRequest = $request->isSecure() || $request->header('X-Forwarded-Proto') === 'https';
            if ($isSecureRequest) {
                URL::forceRootUrl($request->getSchemeAndHttpHost());
                URL::forceScheme('https');
            }
        }

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}

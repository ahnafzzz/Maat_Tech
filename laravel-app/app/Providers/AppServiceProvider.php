<?php

namespace App\Providers;

use App\Models\StorefrontNavigationLink;
use App\Models\StorefrontSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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

        View::composer('layouts.storefront', function ($view): void {
            $view->with([
                'storefrontSettings' => StorefrontSetting::current(),
                'headerLinks' => StorefrontNavigationLink::visible('header')->get(),
                'footerLinks' => StorefrontNavigationLink::visible('footer')->get()->groupBy('column'),
            ]);
        });
        View::composer(['storefront-page', 'product', 'checkout', 'auth.*'], function ($view): void {
            $view->with('storefrontSettings', StorefrontSetting::current());
        });

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute(20)->by('admin-login-ip|'.$request->ip()),
            Limit::perMinute(5)->by('admin-login-account-ip|'.hash('sha256', strtolower((string) $request->input('admin_id'))).'|'.$request->ip()),
        ]);
        RateLimiter::for('admin-two-factor', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('admin-invitation-accept', fn (Request $request) => Limit::perMinute(5)
            ->by((string) $request->route('selector').'|'.$request->ip()));
        RateLimiter::for('admin-invitation-resend', fn (Request $request) => Limit::perHour(3)
            ->by((string) $request->user('admin')?->id.'|'.(string) $request->route('requestItem')));
        RateLimiter::for('cart-mutation', fn (Request $request) => Limit::perMinute(30)
            ->by((string) ($request->user('web')?->id ?? $request->ip())));
        RateLimiter::for('checkout-start', fn (Request $request) => Limit::perMinute(12)
            ->by((string) ($request->user('web')?->id ?? $request->ip())));
        RateLimiter::for('checkout', fn (Request $request) => [
            Limit::perMinute(6)->by('checkout-owner|'.(string) ($request->user('web')?->id ?? $request->ip())),
            Limit::perHour(30)->by('checkout-ip|'.$request->ip()),
        ]);
    }
}

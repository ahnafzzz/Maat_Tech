<?php

use App\Http\Middleware\AdminAuth;
use App\Http\Middleware\ApiAdminAuth;
use App\Http\Middleware\EnsureUserOwnsOrder;
use App\Http\Middleware\EnforceCanonicalHost;
use App\Http\Middleware\EnsureAdminTwoFactorEnabled;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Leave the proxy addresses config-driven so tests and deployments can set
        // a precise allow-list without trusting every reverse proxy by default.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->prepend(EnforceCanonicalHost::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'admin.auth' => AdminAuth::class,
            'api.admin.auth' => ApiAdminAuth::class,
            'admin.2fa' => EnsureAdminTwoFactorEnabled::class,
            'ensure.order.owner' => EnsureUserOwnsOrder::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

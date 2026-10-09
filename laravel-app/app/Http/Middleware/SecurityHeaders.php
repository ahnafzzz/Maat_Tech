<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $response = $next($request);
        $nonce = Vite::cspNonce();
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://connect.facebook.net",
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            "img-src 'self' data: blob: https://www.facebook.com",
            "media-src 'self' blob:",
            "connect-src 'self' https://www.facebook.com https://connect.facebook.net",
            "worker-src 'none'",
            "manifest-src 'self'",
        ];
        if (app()->environment('production')) {
            $directives[] = 'upgrade-insecure-requests';
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', implode('; ', $directives));
        }
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($this->isPrivate($request)) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }

    private function isPrivate(Request $request): bool
    {
        return $request->user() !== null
            || $request->user('admin') !== null
            || $request->is('admin', 'admin/*', 'cart', 'cart/*', 'checkout', 'orders', 'dashboard', 'wishlist', 'login', 'register', 'forgot-password', 'reset-password/*');
    }
}

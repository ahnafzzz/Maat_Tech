<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $canonicalHost = (string) config('site.canonical_host');
        $redirectHosts = (array) config('site.redirect_hosts', []);
        $host = strtolower($request->getHost());

        if ($canonicalHost === '' || (! hash_equals($canonicalHost, $host) && ! in_array($host, $redirectHosts, true))) {
            abort(400, 'Unrecognized host.');
        }

        if (! $request->isSecure() || ! hash_equals($canonicalHost, $host)) {
            return redirect()->away('https://'.$canonicalHost.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}

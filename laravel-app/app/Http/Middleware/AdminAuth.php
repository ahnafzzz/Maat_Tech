<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        if (! Auth::guard('admin')->user()->isActive()) {
            Auth::guard('admin')->logout();

            return redirect()->route('admin.login')->withErrors(['admin_id' => 'Your administrator account is inactive.']);
        }

        return $next($request);
    }
}

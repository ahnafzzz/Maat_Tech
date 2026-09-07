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

        $admin = Auth::guard('admin')->user();
        $sessionVersion = $request->session()->get('admin_session_version');

        if (! is_string($sessionVersion) || ! is_string($admin->session_version) || ! hash_equals($admin->session_version, $sessionVersion)) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['admin_id' => 'Your administrator session has expired. Sign in again.']);
        }

        return $next($request);
    }
}

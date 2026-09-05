<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiAdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return response()->json(['message' => 'Active administrator access required.'], Auth::guard('web')->check() ? 403 : 401);
        }

        if (! $admin->isActive()) {
            return response()->json(['message' => 'Your administrator account is inactive.'], 403);
        }

        return $next($request);
    }
}

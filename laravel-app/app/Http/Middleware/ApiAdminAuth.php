<?php

namespace App\Http\Middleware;

use App\Services\AdminSessionVersion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiAdminAuth
{
    public function __construct(private readonly AdminSessionVersion $sessionVersion) {}

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return response()->json(['message' => 'Active administrator access required.'], Auth::guard('web')->check() ? 403 : 401);
        }

        if (! $admin->isActive()) {
            return response()->json(['message' => 'Your administrator account is inactive.'], 403);
        }

        if (! $this->sessionVersion->isCurrent($request, $admin)) {
            $this->sessionVersion->forget($request);

            return response()->json(['message' => 'Your administrator session has expired. Sign in again.'], 401);
        }

        return $next($request);
    }
}

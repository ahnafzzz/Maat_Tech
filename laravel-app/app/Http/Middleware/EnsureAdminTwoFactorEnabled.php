<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();
        $enrollmentPending = filter_var(
            $request->session()->get('admin_two_factor_enrollment_pending', false),
            FILTER_VALIDATE_BOOL,
        );
        if (! $admin?->two_factor_enabled || $enrollmentPending) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Administrator two-factor enrollment is required.'], 403);
            }

            return redirect()->route('admin.dashboard')
                ->withErrors(['current_password' => 'Enable and verify administrator two-factor authentication before changing protected data.']);
        }

        return $next($request);
    }
}

<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminSessionVersion
{
    public const SESSION_KEY = 'admin_session_version';

    public function establish(Request $request, Admin $admin): void
    {
        $request->session()->put(self::SESSION_KEY, $admin->session_version);
    }

    public function isCurrent(Request $request, Admin $admin): bool
    {
        $sessionVersion = $request->session()->get(self::SESSION_KEY);

        return is_string($sessionVersion)
            && is_string($admin->session_version)
            && hash_equals($admin->session_version, $sessionVersion);
    }

    public function forget(Request $request): void
    {
        Auth::guard('admin')->logoutCurrentDevice();
        $request->session()->forget(self::SESSION_KEY);
    }
}

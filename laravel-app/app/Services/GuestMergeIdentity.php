<?php

namespace App\Services;

use Illuminate\Http\Request;

class GuestMergeIdentity
{
    public const SESSION_KEY = 'guest_cart_merge_key';

    public function currentOrCreate(Request $request): string
    {
        $key = $request->session()->get(self::SESSION_KEY);
        if (! is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1) {
            $key = hash('sha256', "legacy-cart-merge\0".$request->session()->getId());
            $request->session()->put(self::SESSION_KEY, $key);
        }

        return $key;
    }

    public function markChanged(Request $request): void
    {
        if ($request->session()->has('cart') || $request->session()->has('wishlist')) {
            $request->session()->put(self::SESSION_KEY, bin2hex(random_bytes(32)));

            return;
        }

        $request->session()->forget(self::SESSION_KEY);
    }
}

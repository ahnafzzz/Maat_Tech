<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CartMergeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class CustomerAuthController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, CartMergeService $cartMergeService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $mergeSnapshot = $cartMergeService->capture($request);
        $user = DB::transaction(function () use ($cartMergeService, $mergeSnapshot, $validated): User {
            $user = User::create($validated);
            $cartMergeService->applySnapshot($user, $mergeSnapshot);

            return $user;
        }, 3);

        Auth::login($user);
        $request->session()->regenerate();
        $cartMergeService->cleanupSnapshot($request, $mergeSnapshot);

        return redirect()->route('dashboard')->with('status', 'Account created. Your guest cart and wishlist were merged.');
    }

    public function login(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request, CartMergeService $cartMergeService): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($validated, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The supplied credentials are not valid.'])->onlyInput('email');
        }

        try {
            $mergeSnapshot = $cartMergeService->capture($request);
            $cartMergeService->applySnapshot($request->user(), $mergeSnapshot);
            $request->session()->regenerate();
            $cartMergeService->cleanupSnapshot($request, $mergeSnapshot);
        } catch (Throwable $exception) {
            Auth::logout();

            throw $exception;
        }

        return redirect()->intended(route('dashboard'))->with('status', 'Signed in successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Signed out successfully.');
    }
}

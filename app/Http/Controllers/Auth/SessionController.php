<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Push\PushSubscriptionController;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();
        $fallback = ! $user->profile_completed_at ? route('profile.setup') : ($user->activeSpaceId() ? route('dashboard') : route('onboarding'));

        return redirect()->intended($fallback);
    }

    public function destroy(Request $request): RedirectResponse
    {
        // This device should stop receiving the account's notifications.
        if ($hash = $request->session()->get(PushSubscriptionController::SESSION_KEY)) {
            $request->user()?->pushSubscriptions()->where('endpoint_hash', $hash)->delete();
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Drops the encryption key of the browser's page history, so the Back button
        // cannot reopen screens from the session that just ended.
        Inertia::clearHistory();

        return redirect()->route('home');
    }
}

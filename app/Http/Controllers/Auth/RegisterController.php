<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            // Only the first part of the email for now; the person types their real name on the next step.
            'name' => Str::limit(Str::before($request->validated('email'), '@'), 60, ''),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            // The browser's zone is a better default than the server's, when sent.
            'timezone' => $request->validated('timezone') ?: config('usandus.default_timezone'),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('profile.setup');
    }
}

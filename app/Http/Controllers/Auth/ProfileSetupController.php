<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileSetupController extends Controller
{
    public const GENDERS = ['perempuan', 'laki-laki', 'lainnya'];

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->profile_completed_at) {
            return redirect()->route($user->activeSpaceId() ? 'dashboard' : 'onboarding');
        }

        return Inertia::render('Auth/ProfileSetup', [
            // The name is empty on purpose: before this step it is only the first part of the email.
            'profile' => ['name' => '', 'nickname' => $user->nickname ?? '', 'gender' => $user->gender ?? '', 'birthday' => $user->birthday?->toDateString() ?? '', 'timezone' => $user->timezone, 'avatarUrl' => $user->avatarUrl(), 'avatarFrame' => $user->avatar_frame],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'nickname' => ['nullable', 'string', 'max:30'],
            'gender' => ['required', 'in:'.implode(',', self::GENDERS)],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            'timezone' => ['nullable', 'timezone:all'],
        ], [
            'name.required' => 'Isi namamu dulu.',
            'gender.required' => 'Pilih salah satu.',
            'gender.in' => 'Pilih salah satu.',
        ]);

        $user = $request->user();
        $user->update([
            'name' => trim($data['name']),
            'nickname' => filled($data['nickname'] ?? null) ? trim($data['nickname']) : null,
            'gender' => $data['gender'],
            'birthday' => $data['birthday'] ?? null,
            'timezone' => $data['timezone'] ?: $user->timezone,
            'profile_completed_at' => now(),
        ]);

        return redirect()->route($user->activeSpaceId() ? 'dashboard' : 'onboarding');
    }
}

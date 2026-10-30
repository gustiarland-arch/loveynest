<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Push\FcmSender;
use App\Services\Push\WebPushSender;
use App\Support\NativeApp;
use App\Services\Reminders\ReminderDispatcher;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Index', [
            'profile' => ['name' => $user->name, 'email' => $user->email, 'timezone' => $user->timezone, 'avatarUrl' => $user->avatarUrl(), 'avatarFrame' => $user->avatar_frame, 'card' => $user->profile_card, 'nickname' => $user->nickname, 'gender' => $user->gender, 'birthday' => $user->birthday?->toDateString()],
            'couple' => $user->activeSpace() ? ['partner' => $user->partner()?->name] : null,
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    /** Settings home: a list of sections (appearance, notifications, couple, privacy, about). */
    public function hub(): Response
    {
        return Inertia::render('Pengaturan/Index');
    }

    public function appearance(): Response
    {
        return Inertia::render('Pengaturan/Tampilan');
    }

    public function about(): Response
    {
        return Inertia::render('Pengaturan/Tentang');
    }

    public function couple(Request $request): Response
    {
        $user = $request->user();
        $space = $user->activeSpace();

        return Inertia::render('Pengaturan/Pasangan', [
            'couple' => $space ? [
                'name' => $space->name,
                'partner' => $user->partner()?->name,
                'anniversary' => $space->anniversary_date?->toDateString(),
                'metDate' => $space->met_date?->toDateString(),
                'theme' => $space->theme,
            ] : null,
        ]);
    }

    public function notifications(Request $request, NotificationPreferences $preferences): Response
    {
        $user = $request->user();

        return Inertia::render('Pengaturan/Notifikasi', [
            'notifications' => [
                'preferences' => $preferences->all($user),
                'digest' => $user->digest,
                'quiet' => ['start' => $user->quiet_start ? substr($user->quiet_start, 0, 5) : null, 'end' => $user->quiet_end ? substr($user->quiet_end, 0, 5) : null],
                'push' => [
                    // The Android app is reached through FCM, so VAPID problems do not concern it.
                    'configured' => NativeApp::is($request) ? FcmSender::isConfigured() : WebPushSender::isConfigured(),
                    'problem' => NativeApp::is($request) ? null : WebPushSender::problem(),
                    'publicKey' => config('usandus.vapid.public_key'),
                    'devices' => $user->pushSubscriptions()->active()->count(),
                ],
                // Lets the user see whether reminders can go out at all.
                'schedulerLastRun' => ReminderDispatcher::lastRun()?->toIso8601ZuluString(),
            ],
        ]);
    }

    public function updateNotifications(Request $request, NotificationPreferences $preferences): RedirectResponse
    {
        $rules = ['preferences' => ['required', 'array']];
        foreach (NotificationPreference::TYPES as $type) {
            $rules["preferences.{$type}"] = ['sometimes', 'array'];
            foreach (['in_app', 'push', 'show_preview'] as $flag) {
                $rules["preferences.{$type}.{$flag}"] = ['sometimes', 'boolean'];
            }
        }
        $data = $request->validate($rules);

        // validate() drops keys without rules, so unknown types never get here.
        $preferences->update($request->user(), array_map(
            fn (array $flags) => array_map(fn ($v) => (bool) $v, $flags),
            $data['preferences'] ?? [],
        ));

        return back()->with('success', 'Pengaturan notifikasi tersimpan.');
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $data = $request->validate(['theme' => ['required', 'in:rose,lavender,sage,biru,tosca,jingga,emas,magenta']]);
        $space = $request->user()->activeSpace();
        abort_unless($space, 404);
        $space->forceFill(['theme' => $data['theme']])->save();

        return back()->with('success', 'Tema ruang tersimpan.');
    }

    public function updateDigest(Request $request): RedirectResponse
    {
        $data = $request->validate(['digest' => ['required', 'in:off,daily,weekly']]);
        $request->user()->update(['digest' => $data['digest']]);

        return back()->with('success', 'Ringkasan tersimpan.');
    }

    public function updateQuietHours(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'start' => ['nullable', 'required_with:end', 'date_format:H:i'],
            'end' => ['nullable', 'required_with:start', 'date_format:H:i', 'different:start'],
        ]);
        $request->user()->update(['quiet_start' => $data['start'] ?? null, 'quiet_end' => $data['end'] ?? null]);

        return back()->with('success', 'Jam tenang tersimpan.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'timezone' => ['required', 'timezone:all'],
            'nickname' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:perempuan,laki-laki,lainnya'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
        ]);

        $request->user()->update([
            'name' => trim($data['name']), 'timezone' => $data['timezone'],
            'nickname' => filled($data['nickname'] ?? null) ? trim($data['nickname']) : null, 'birthday' => $data['birthday'] ?? null,
            'gender' => $data['gender'] ?? null,
        ]);

        return back()->with('success', 'Profil tersimpan.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        // Other devices signed in with the old password are signed out.
        Auth::logoutOtherDevices($data['password']);
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return back()->with('success', 'Password diperbarui.');
    }

    /** Which home-screen cards show, and in what order. */
    public function updateDashboard(Request $request): RedirectResponse
    {
        $keys = array_keys(config('usandus.dashboard_widgets'));
        $data = $request->validate([
            'order' => ['present', 'array', 'max:20'], 'order.*' => ['string', 'distinct', 'in:'.implode(',', $keys)],
            'hidden' => ['present', 'array', 'max:20'], 'hidden.*' => ['string', 'distinct', 'in:'.implode(',', $keys)],
        ]);
        $request->user()->update(['dashboard_prefs' => ['order' => array_values($data['order']), 'hidden' => array_values($data['hidden'])]]);

        return back()->with('success', 'Tampilan beranda tersimpan.');
    }
}

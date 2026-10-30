<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Notifications\CoupleEnded;
use App\Services\Couple\CoupleSpaceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Devices, a copy of your data, and closing the account. */
class AccountController extends Controller
{
    /** Tables that hold your own records, and the column that says they are yours. */
    private const EXPORT = [
        'tasks' => ['owner_user_id', 'created_by'], 'task_subtasks' => null, 'events' => ['owner_user_id', 'created_by'],
        'journal_entries' => ['owner_user_id', 'created_by'], 'habits' => ['owner_user_id', 'created_by'], 'habit_checkins' => ['user_id'],
        'goals' => ['owner_user_id', 'created_by'], 'list_items' => ['owner_user_id', 'created_by'], 'moods' => ['user_id'],
        'daily_answers' => ['user_id'], 'chat_messages' => ['sender_id'], 'love_notes' => ['sender_id'], 'checkins' => ['user_id'],
        'quiz_answers' => ['user_id'], 'challenge_completions' => ['user_id'], 'date_expenses' => ['paid_by'],
        'playlist_items' => ['added_by'], 'dreams' => ['created_by'], 'notification_preferences' => ['user_id'], 'reflections' => ['user_id'], 'agreements' => ['created_by'], 'rewards' => ['created_by'], 'event_rsvps' => ['user_id'],
    ];

    public function show(Request $request): Response
    {
        $user = $request->user();
        $current = $request->session()->getId();

        return Inertia::render('Settings/Privacy', [
            'sessions' => DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->limit(20)->get()
                ->map(fn ($s) => ['id' => $s->id, 'label' => $this->deviceLabel((string) $s->user_agent, $s->id === $current ? $request->cookie('ln_app') === '1' : null), 'ip' => $s->ip_address, 'last' => CarbonImmutable::createFromTimestamp($s->last_activity)->toIso8601String(), 'current' => $s->id === $current])->values(),
            'pushDevices' => $user->pushSubscriptions()->active()->orderByDesc('id')->get()->map(fn (PushSubscription $p) => ['id' => $p->id, 'label' => $this->pushLabel((string) $p->device_label), 'last' => $p->last_success_at?->toIso8601String()])->values(),
            'inCouple' => (bool) $user->activeSpaceId(),
            'partner' => $user->partner()?->firstName(),
        ]);
    }

    public function revokeSession(Request $request, string $session): RedirectResponse
    {
        abort_if($session === $request->session()->getId(), 422, 'Ini perangkat yang sedang kamu pakai.');
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', $session)->delete();

        return back()->with('success', 'Perangkat dikeluarkan.');
    }

    public function revokePush(Request $request, int $device): RedirectResponse
    {
        $request->user()->pushSubscriptions()->whereKey($device)->firstOrFail()->delete();

        return back()->with('success', 'Perangkat notifikasi dihapus.');
    }

    public function logoutEverywhere(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Kamu keluar dari semua perangkat.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $data = [
            'diekspor_pada' => now()->toIso8601String(),
            'catatan' => 'Salinan data yang kamu tulis sendiri di LoveyNest. Foto dan berkas tidak ikut, hanya catatannya.',
            'profil' => $user->only(['name', 'email', 'nickname', 'birthday', 'timezone', 'created_at']),
        ];
        foreach (self::EXPORT as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if ($columns === null) {
                $data[$table] = DB::table($table)->whereIn('task_id', DB::table('tasks')->where('owner_user_id', $user->id)->orWhere('created_by', $user->id)->select('id'))->limit(5000)->get();

                continue;
            }
            $columns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
            if (! $columns) {
                continue;
            }
            $data[$table] = DB::table($table)->where(function ($q) use ($columns, $user) {
                foreach ($columns as $i => $c) {
                    $i === 0 ? $q->where($c, $user->id) : $q->orWhere($c, $user->id);
                }
            })->limit(5000)->get();
        }

        $name = 'lovey-nest-data-'.now()->format('Ymd').'.json';

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $name, ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    public function destroy(Request $request, CoupleSpaceService $spaces): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', 'in:HAPUS'],
        ], ['confirmation.in' => 'Ketik HAPUS (huruf besar) untuk memastikan.', 'confirmation.required' => 'Ketik HAPUS (huruf besar) untuk memastikan.']);
        $user = $request->user();

        // Your partner keeps the shared space; it just ends, like leaving does.
        $others = $user->activeSpaceId() ? $spaces->dissolve($user) : [];
        foreach ($others as $other) {
            $other->notify(new CoupleEnded($user->firstName()));
        }

        $files = array_filter([$user->avatar_path, $user->background_path]);
        $id = $user->id;

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        DB::transaction(function () use ($id) {
            DB::table('sessions')->where('user_id', $id)->delete();
            DB::table('notifications')->where('notifiable_id', $id)->where('notifiable_type', (new \App\Models\User)->getMorphClass())->delete();
            // Explicit so it also works where the database cannot cascade this table (MySQL).
            DB::table('couple_members')->where('user_id', $id)->delete();
            \App\Models\User::whereKey($id)->delete();
        });
        Storage::disk('local')->delete($files);
        Log::channel('audit')->info('account.deleted', ['user' => $id]);

        return redirect('/')->with('success', 'Akunmu sudah dihapus.');
    }

    /** iPhones only allow push for an app installed on the home screen, so a push device on iOS is that app. */
    private function pushLabel(string $label): string
    {
        return str_ends_with($label, 'iOS') ? 'Aplikasi layar utama · iPhone' : ($label ?: 'Perangkat');
    }

    /** @param bool|null $installed whether this very session runs as the installed app (null: unknown, for other sessions) */
    private function deviceLabel(string $agent, ?bool $installed = null): string
    {
        $os = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Perangkat lain',
        };
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Peramban',
        };

        if ($installed) {
            return "Aplikasi layar utama di {$os}";
        }
        if ($installed === false && $os === 'iOS') {
            return "Tab {$browser} di iOS";
        }

        return "{$browser} di {$os}";
    }
}

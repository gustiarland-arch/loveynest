<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\E2eDevice;
use App\Models\E2eKeyWrap;
use App\Models\E2ePasswordWrap;
use App\Models\E2eSpaceKey;
use App\Models\User;
use App\Support\E2E;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Key exchange for end-to-end encryption. Only public keys and keys locked for a
 * specific device pass through here; nothing stored lets the server open content.
 */
class E2eController extends Controller
{
    public const MAX_DEVICES = 10;

    /** Registers this browser's public key (the same key again returns the same device). */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'public_key' => ['required', 'string', 'max:400', function ($attribute, $value, $fail) {
                $jwk = json_decode((string) $value, true);
                $ok = is_array($jwk) && ($jwk['kty'] ?? null) === 'EC' && ($jwk['crv'] ?? null) === 'P-256'
                    && is_string($jwk['x'] ?? null) && is_string($jwk['y'] ?? null) && ! isset($jwk['d']);
                $ok ?: $fail('Kunci perangkat tidak valid.');
            }],
            'label' => ['nullable', 'string', 'max:80'],
        ]);
        $user = $request->user();
        $jwk = json_decode($data['public_key'], true);
        $public = json_encode(['kty' => 'EC', 'crv' => 'P-256', 'x' => $jwk['x'], 'y' => $jwk['y']]);

        $device = E2eDevice::where('user_id', $user->id)->where('public_key', $public)->first();
        if (! $device) {
            abort_if(E2eDevice::where('user_id', $user->id)->count() >= self::MAX_DEVICES, 422, 'Sudah ada '.self::MAX_DEVICES.' perangkat. Hapus perangkat lama di Profil > Privasi.');
            $device = E2eDevice::create(['user_id' => $user->id, 'public_key' => $public, 'label' => $data['label'] ?? null, 'last_seen_at' => now()]);
        }

        return response()->json(['id' => $device->id]);
    }

    public function status(Request $request): JsonResponse
    {
        $data = $request->validate(['device' => ['required', 'integer']]);
        $user = $request->user();
        $device = E2eDevice::where('user_id', $user->id)->find($data['device']);
        $spaceId = $user->activeSpaceId();
        if (! $device) {
            return response()->json(['device' => null, 'spaceId' => $spaceId]);
        }
        $device->forceFill(['last_seen_at' => now()])->save();

        $active = $spaceId ? E2eSpaceKey::where('couple_space_id', $spaceId)->latest('id')->first() : null;
        $keys = $spaceId ? E2eKeyWrap::where('e2e_device_id', $device->id)
            ->whereHas('spaceKey', fn ($q) => $q->where('couple_space_id', $spaceId))
            ->with('spaceKey:id,key_id')->get()
            ->map(fn (E2eKeyWrap $w) => ['key_id' => $w->spaceKey->key_id, 'wrapped' => $w->wrapped])->values() : collect();

        return response()->json([
            'device' => $device->id,
            'spaceId' => $spaceId,
            'activeKeyId' => $active?->key_id,
            // Whether the login password can open the current key (a wrap made with it is stored).
            'passwordWrap' => $active ? E2ePasswordWrap::where('user_id', $user->id)->where('e2e_space_key_id', $active->id)->exists() : false,
            'keys' => $keys,
            // Devices of the two of you that wait for the current key; one that has it can let them in.
            'pending' => $active && $keys->contains('key_id', $active->key_id) ? $this->pending($user, $active, $device->id) : [],
            'devices' => E2eDevice::where('user_id', $user->id)->orderByDesc('last_seen_at')->get()
                ->map(fn (E2eDevice $d) => [
                    'id' => $d->id,
                    'label' => $d->label,
                    'current' => $d->id === $device->id,
                    'hasKey' => $active ? $d->wraps()->where('e2e_space_key_id', $active->id)->exists() : false,
                    'lastSeen' => $d->last_seen_at?->toIso8601ZuluString(),
                ])->values(),
        ]);
    }

    /** Starts the space's first key, or a new one when nobody can open the old one any more. */
    public function createKey(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device' => ['required', 'integer'],
            'key_id' => ['required', 'string', 'regex:/^[A-Za-z0-9]{8}$/'],
            'wrapped' => ['required', 'string', 'max:2000'],
            'reset' => ['sometimes', 'boolean'],
        ]);
        $user = $request->user();
        $spaceId = $user->activeSpaceId();
        abort_unless($spaceId, 404);
        $device = E2eDevice::where('user_id', $user->id)->findOrFail($data['device']);

        try {
            $created = DB::transaction(function () use ($data, $user, $spaceId, $device) {
                // Two first devices at the same moment: the lock makes the second one see the first key.
                \App\Models\CoupleSpace::whereKey($spaceId)->lockForUpdate()->first();
                if (E2eSpaceKey::where('couple_space_id', $spaceId)->exists() && ! ($data['reset'] ?? false)) {
                    return false;
                }
                $key = E2eSpaceKey::create(['couple_space_id' => $spaceId, 'key_id' => $data['key_id'], 'created_by' => $user->id]);
                E2eKeyWrap::create(['e2e_space_key_id' => $key->id, 'e2e_device_id' => $device->id, 'wrapped' => $data['wrapped'], 'wrapped_by' => $user->id]);

                return true;
            });
        } catch (QueryException) {
            $created = false;
        }
        abort_unless($created, 409, 'Kunci berdua sudah ada.');

        return response()->json(['ok' => true], 201);
    }

    /** A device that holds the key locks it for devices the two of you approved on screen. */
    public function share(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device' => ['required', 'integer'],
            'wraps' => ['required', 'array', 'min:1', 'max:40'],
            'wraps.*.key_id' => ['required', 'string', 'regex:/^[A-Za-z0-9]{8}$/'],
            'wraps.*.device_id' => ['required', 'integer'],
            'wraps.*.wrapped' => ['required', 'string', 'max:2000'],
        ]);
        $user = $request->user();
        $spaceId = $user->activeSpaceId();
        abort_unless($spaceId, 404);
        $mine = E2eDevice::where('user_id', $user->id)->findOrFail($data['device']);
        $memberIds = $user->activeSpace()->activeMembers()->pluck('users.id');

        foreach ($data['wraps'] as $wrap) {
            $key = E2eSpaceKey::where('couple_space_id', $spaceId)->where('key_id', $wrap['key_id'])->first();
            $target = E2eDevice::whereIn('user_id', $memberIds)->find($wrap['device_id']);
            // Only a device that has this key itself may pass it on.
            if (! $key || ! $target || ! $key->wraps()->where('e2e_device_id', $mine->id)->exists()) {
                continue;
            }
            E2eKeyWrap::updateOrCreate(
                ['e2e_space_key_id' => $key->id, 'e2e_device_id' => $target->id],
                ['wrapped' => $wrap['wrapped'], 'wrapped_by' => $user->id],
            );
        }

        return response()->json(['ok' => true]);
    }

    /** After checking the login password, hands back the keys locked with it; the browser opens them itself. */
    public function unlock(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:200']]);
        $user = $request->user();
        $spaceId = $user->activeSpaceId();
        abort_unless($spaceId, 404);
        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Password salah.'], 422);
        }

        $wraps = E2ePasswordWrap::where('user_id', $user->id)
            ->whereHas('spaceKey', fn ($q) => $q->where('couple_space_id', $spaceId))
            ->with('spaceKey:id,key_id')->get()
            ->map(fn (E2ePasswordWrap $w) => [
                'key_id' => $w->spaceKey->key_id,
                'salt' => $w->salt,
                'iterations' => $w->iterations,
                'wrapped' => $w->wrapped,
            ])->values();

        return response()->json(['wraps' => $wraps]);
    }

    /** A device that has the key stores it locked with the login password (checked here, so it matches the real one). */
    public function savePasswordWraps(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:200'],
            'wraps' => ['required', 'array', 'min:1', 'max:40'],
            'wraps.*.key_id' => ['required', 'string', 'regex:/^[A-Za-z0-9]{8}$/'],
            'wraps.*.salt' => ['required', 'string', 'max:64'],
            'wraps.*.iterations' => ['required', 'integer', 'min:100000', 'max:2000000'],
            'wraps.*.wrapped' => ['required', 'string', 'max:2000'],
        ]);
        $user = $request->user();
        $spaceId = $user->activeSpaceId();
        abort_unless($spaceId, 404);
        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Password salah.'], 422);
        }

        foreach ($data['wraps'] as $wrap) {
            $key = E2eSpaceKey::where('couple_space_id', $spaceId)->where('key_id', $wrap['key_id'])->first();
            if (! $key) {
                continue;
            }
            E2ePasswordWrap::updateOrCreate(
                ['user_id' => $user->id, 'e2e_space_key_id' => $key->id],
                ['salt' => $wrap['salt'], 'kdf' => 'pbkdf2-sha256', 'iterations' => $wrap['iterations'], 'wrapped' => $wrap['wrapped']],
            );
        }

        return response()->json(['ok' => true]);
    }

    /** Removes one of your devices, or turns away a device that is still waiting for the key. */
    public function destroy(Request $request, int $device): JsonResponse
    {
        $user = $request->user();
        $row = E2eDevice::find($device);
        $memberIds = $user->activeSpace()?->activeMembers()->pluck('users.id') ?? collect([$user->id]);
        $allowed = $row && ($row->user_id === $user->id || ($memberIds->contains($row->user_id) && ! $row->wraps()->exists()));
        abort_unless($allowed, 404);
        $row->delete();

        return response()->json(['ok' => true]);
    }

    /** @return list<array<string, mixed>> */
    private function pending(User $user, E2eSpaceKey $active, int $currentDevice): array
    {
        $members = $user->activeSpace()->activeMembers()->get(['users.id', 'users.name']);
        /** @var Collection<int, E2eDevice> $devices */
        $devices = E2eDevice::whereIn('user_id', $members->pluck('id'))
            ->where('id', '!=', $currentDevice)
            ->whereDoesntHave('wraps', fn ($q) => $q->where('e2e_space_key_id', $active->id))
            ->where('updated_at', '>', now()->subDays(30))
            ->orderBy('id')->limit(10)->get();

        return $devices->map(fn (E2eDevice $d) => [
            'id' => $d->id,
            'publicKey' => $d->public_key,
            'label' => $d->label,
            'mine' => $d->user_id === $user->id,
            'name' => $members->firstWhere('id', $d->user_id)?->name,
            'createdAt' => $d->created_at->toIso8601ZuluString(),
        ])->values()->all();
    }

    /** Shared with every page: enough to know whether a key exists and whether someone waits. */
    public static function shared(?User $user): ?array
    {
        if (! E2E::enabled() || ! $user) {
            return null;
        }
        $spaceId = $user->activeSpaceId();

        return [
            'spaceId' => $spaceId,
            'keyId' => $spaceId ? E2eSpaceKey::where('couple_space_id', $spaceId)->latest('id')->value('key_id') : null,
        ];
    }
}

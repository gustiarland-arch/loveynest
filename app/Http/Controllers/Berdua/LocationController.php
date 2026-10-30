<?php

namespace App\Http\Controllers\Berdua;

use App\Support\E2E;
use App\Http\Controllers\Controller;
use App\Models\LocationShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Opt-in live location between the two partners. Nobody else can read it. */
class LocationController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($request->user()->activeSpaceId(), 404);

        return Inertia::render('Berdua/Location', ['state' => $this->state($request)]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()->activeSpaceId(), 404);

        return response()->json($this->state($request));
    }

    /** Starts sharing, or updates the position when one is given. */
    public function share(Request $request): JsonResponse
    {
        $user = $request->user();
        $spaceId = $user->activeSpaceId();
        abort_unless($spaceId && $user->partner(), 404);

        if (E2E::enabled()) {
            // The position is locked in the browser; the server only knows that and when it was shared.
            $data = $request->validate(['sealed' => ['nullable', ...E2E::text(120)]]);
            $values = ['couple_space_id' => $spaceId];
            if (! empty($data['sealed'])) {
                $values += ['sealed' => $data['sealed'], 'lat' => null, 'lng' => null, 'accuracy' => null, 'located_at' => now()];
            }
            LocationShare::updateOrCreate(['user_id' => $user->id], $values);

            return response()->json($this->state($request));
        }

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $values = ['couple_space_id' => $spaceId];
        if (isset($data['lat'])) {
            $values += ['lat' => round((float) $data['lat'], 5), 'lng' => round((float) $data['lng'], 5), 'accuracy' => $data['accuracy'] ?? null, 'located_at' => now()];
        }
        LocationShare::updateOrCreate(['user_id' => $user->id], $values);

        return response()->json($this->state($request));
    }

    public function stop(Request $request): JsonResponse
    {
        LocationShare::where('user_id', $request->user()->id)->delete();

        return response()->json($this->state($request));
    }

    /** @return array<string, mixed> */
    private function state(Request $request): array
    {
        $user = $request->user();
        $partner = $user->partner();
        $mine = LocationShare::where('user_id', $user->id)->first();
        $theirs = $partner ? LocationShare::where('user_id', $partner->id)->where('couple_space_id', $user->activeSpaceId())->first() : null;

        if (E2E::enabled()) {
            // Opened and measured in the browser.
            $locked = fn (?LocationShare $s) => $s && $s->sealed !== null && $s->located_at ? ['sealed' => $s->sealed, 'secondsAgo' => max(0, (int) $s->located_at->diffInSeconds(now()))] : null;

            return [
                'sharing' => (bool) $mine,
                'partnerName' => $partner?->firstName(),
                'partnerSharing' => (bool) $theirs,
                'me' => $locked($mine),
                'partner' => $locked($theirs),
                'distance' => null,
            ];
        }

        $pos = fn (?LocationShare $s) => $s && $s->lat !== null ? [
            'lat' => $s->lat, 'lng' => $s->lng, 'accuracy' => $s->accuracy,
            'secondsAgo' => max(0, (int) $s->located_at->diffInSeconds(now())),
        ] : null;
        $me = $pos($mine);
        $other = $pos($theirs);

        return [
            'sharing' => (bool) $mine,
            'partnerName' => $partner?->firstName(),
            'partnerSharing' => (bool) $theirs,
            'me' => $me,
            'partner' => $other,
            'distance' => $me && $other ? LocationShare::metres($me['lat'], $me['lng'], $other['lat'], $other['lng']) : null,
        ];
    }
}

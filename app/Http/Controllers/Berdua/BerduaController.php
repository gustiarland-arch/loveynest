<?php

namespace App\Http\Controllers\Berdua;

use App\Http\Controllers\Controller;
use App\Models\DailyAnswer;
use App\Models\Nudge;
use App\Notifications\PartnerNudge;
use App\Services\Couple\CoupleFacts;
use App\Services\Couple\DailyQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BerduaController extends Controller
{
    /** The hub: every extra feature, plus today's nudges. */
    public function index(Request $request, CoupleFacts $facts, DailyQuestion $questions): Response
    {
        $user = $request->user();
        $partner = $user->partner();
        $today = CarbonImmutable::now($user->timezone)->startOfDay()->toDateString();

        return Inertia::render('Berdua/Index', [
            'hasCouple' => (bool) $user->activeSpaceId(),
            'hasPartner' => (bool) $user->partner(),
            'partner' => $user->partner() ? ['firstName' => $user->partner()->firstName()] : null,
            'facts' => $facts->for($user),
            'received' => $facts->nudgesToday($user),
            // Today's question as a card; the answers themselves stay on the question page.
            'question' => $partner ? [
                'text' => $questions->text($questions->indexFor(CarbonImmutable::now($user->timezone)->startOfDay())),
                'answered' => DailyAnswer::where('user_id', $user->id)->where('answer_date', $today)->exists(),
                'partnerAnswered' => DailyAnswer::where('user_id', $partner->id)->where('answer_date', $today)->exists(),
            ] : null,
        ]);
    }

    /** One tap sends a hug, a "miss you" and so on; limited so it cannot become spam. */
    public function nudge(Request $request): JsonResponse
    {
        $data = $request->validate(['kind' => ['required', Rule::in(Nudge::KINDS)]]);
        $user = $request->user();
        $partner = $user->partner();
        abort_unless($partner, 404);

        if (! Cache::add("nudge:{$user->id}:{$data['kind']}", 1, 30)) {
            return response()->json(['message' => 'Tunggu sebentar sebelum mengirim yang sama lagi.'], 429);
        }

        $nudge = Nudge::create(['couple_space_id' => $user->activeSpaceId(), 'sender_id' => $user->id, 'kind' => $data['kind']]);

        try {
            $partner->notify(new PartnerNudge($user->firstName(), $data['kind']));
        } catch (Throwable $e) {
            Log::warning('notification.failed', ['type' => 'nudge', 'error' => $e::class]);
        }

        return response()->json(['ok' => true, 'id' => $nudge->id]);
    }

    /** What the partner sent that has not been shown yet (newest day only, so a long absence is not a flood). */
    public function pendingNudges(Request $request): JsonResponse
    {
        $user = $request->user();
        $partner = $user->partner();
        if (! $partner) {
            return response()->json(['items' => []]);
        }

        $items = Nudge::where('sender_id', $partner->id)->where('couple_space_id', $user->activeSpaceId())
            ->whereNull('seen_at')->where('created_at', '>=', now()->subDay())
            ->orderBy('id')->limit(4)->get(['id', 'kind'])
            ->map(fn ($n) => ['id' => $n->id, 'kind' => $n->kind, 'from' => $partner->firstName()])->all();

        return response()->json(['items' => $items]);
    }

    public function seenNudge(Request $request, Nudge $nudge): JsonResponse
    {
        $user = $request->user();
        abort_unless($nudge->couple_space_id === $user->activeSpaceId() && $nudge->sender_id !== $user->id, 404);

        $nudge->forceFill(['seen_at' => now()])->save();

        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers\Berdua;

use App\Http\Controllers\Controller;
use App\Models\ArenaScore;
use App\Models\ArenaSession;
use App\Models\CoupleSpace;
use App\Models\User;
use App\Notifications\ArenaInvite;
use App\Support\ArenaGames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Throwable;

/**
 * The Arena: five light games, alone or as a pair. A pair game starts with an invitation; the partner accepts
 * and both land in the game. Everything shared (who is ready, scores, whose turn) lives in one row per session
 * and is polled, so it runs on plain shared hosting without websockets.
 */
class ArenaController extends Controller
{
    /** An unanswered invitation is forgotten after this many minutes. */
    private const INVITE_MINUTES = 10;

    /** A running game counts as alive while one of you keeps it open (every poll touches it); after this many seconds it is forgotten. */
    private const ACTIVE_SECONDS = 75;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $space = $this->space($request);
        $partner = $user->partner();
        $scores = ArenaScore::where('couple_space_id', $space->id)->get();

        $best = fn (string $game, ?int $userId) => $userId ? $scores->where('game', $game)->where('user_id', $userId)->sortByDesc('score')->first() : null;

        $games = collect(ArenaGames::GAMES)->map(fn (array $meta, string $key) => [
            'key' => $key,
            'title' => $meta['title'],
            'kind' => $meta['kind'],
            'mine' => ($b = $best($key, $user->id)) ? ['score' => $b->score, 'stars' => $b->stars] : null,
            'partner' => ($b = $best($key, $partner?->id)) ? ['score' => $b->score, 'stars' => $b->stars] : null,
            'plays' => $scores->where('game', $key)->where('user_id', $user->id)->count(),
        ])->values();

        $stars = fn (?int $id) => $id ? collect(ArenaGames::keys())->sum(fn ($g) => $best($g, $id)?->stars ?? 0) : 0;
        $wins = fn (?int $id) => $id ? $scores->where('user_id', $id)->where('won', true)->count() : 0;

        return Inertia::render('Arena/Index', [
            'partner' => $partner ? ['id' => $partner->id, 'name' => $partner->firstName(), 'avatarUrl' => $partner->avatarUrl(), 'avatarFrame' => $partner->avatar_frame] : null,
            'me' => ['id' => $user->id, 'name' => $user->firstName(), 'avatarUrl' => $user->avatarUrl(), 'avatarFrame' => $user->avatar_frame],
            'games' => $games,
            'stars' => ['mine' => $stars($user->id), 'partner' => $stars($partner?->id)],
            'wins' => ['mine' => $wins($user->id), 'partner' => $wins($partner?->id)],
        ]);
    }

    /** Playing alone (or, for the question cards, passing one phone around). */
    public function play(Request $request, string $game): Response
    {
        abort_unless(ArenaGames::has($game), 404);
        $user = $request->user();
        $space = $this->space($request);
        $seed = random_int(1, 2000000000);

        return Inertia::render('Arena/Play', [
            'game' => $this->gameProps($game),
            'mode' => 'solo',
            'seed' => $seed,
            'photos' => $this->photoIds($space, $seed, 8),
            'me' => $this->person($user),
            'partner' => ($p = $user->partner()) ? $this->person($p) : null,
            'session' => null,
        ]);
    }

    /** What the polling bar needs: is there an invitation for me, did mine get an answer, is a game running. */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $space = $this->space($request);
        $base = ArenaSession::where('couple_space_id', $space->id);

        $incoming = (clone $base)->where('guest_id', $user->id)->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(self::INVITE_MINUTES))->latest('id')->with('host')->first();
        $outgoing = (clone $base)->where('host_id', $user->id)->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(self::INVITE_MINUTES))->latest('id')->first();
        $active = (clone $base)->where('status', 'active')->where(fn ($q) => $q->where('host_id', $user->id)->orWhere('guest_id', $user->id))
            ->where('updated_at', '>=', now()->subSeconds(self::ACTIVE_SECONDS))->latest('id')->first();
        $declined = (clone $base)->where('host_id', $user->id)->where('status', 'declined')
            ->where('updated_at', '>=', now()->subSeconds(40))->latest('id')->first();

        return response()->json([
            'incoming' => $incoming ? ['id' => $incoming->id, 'game' => $incoming->game, 'title' => ArenaGames::title($incoming->game), 'from' => $incoming->host->firstName()] : null,
            'outgoing' => $outgoing ? ['id' => $outgoing->id, 'game' => $outgoing->game, 'title' => ArenaGames::title($outgoing->game)] : null,
            'active' => $active ? ['id' => $active->id, 'game' => $active->game, 'title' => ArenaGames::title($active->game)] : null,
            'declined' => $declined ? ['id' => $declined->id, 'title' => ArenaGames::title($declined->game)] : null,
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate(['game' => ['required', 'string']]);
        abort_unless(ArenaGames::has($data['game']), 422);

        $user = $request->user();
        $space = $this->space($request);
        $partner = $user->partner();
        abort_unless($partner, 422, 'Ajak pasanganmu bergabung dulu.');

        $session = DB::transaction(function () use ($user, $partner, $space, $data) {
            // One game at a time per couple: a running one is offered instead of starting another.
            $running = ArenaSession::where('couple_space_id', $space->id)->where('status', 'active')
                ->where('updated_at', '>=', now()->subSeconds(self::ACTIVE_SECONDS))->lockForUpdate()->latest('id')->first();
            if ($running) {
                return ['running' => $running];
            }

            // An older invitation of ours is replaced; one the partner sent us is answered, not duplicated.
            $theirs = ArenaSession::where('couple_space_id', $space->id)->where('guest_id', $user->id)->where('status', 'pending')
                ->where('created_at', '>=', now()->subMinutes(self::INVITE_MINUTES))->lockForUpdate()->latest('id')->first();
            if ($theirs) {
                return ['incoming' => $theirs];
            }
            ArenaSession::where('couple_space_id', $space->id)->where('host_id', $user->id)->where('status', 'pending')->update(['status' => 'cancelled']);

            $seed = random_int(1, 2000000000);

            return ['created' => ArenaSession::create([
                'couple_space_id' => $space->id, 'game' => $data['game'], 'host_id' => $user->id, 'guest_id' => $partner->id,
                'status' => 'pending', 'seed' => $seed, 'state' => $this->initialState($data['game'], $user->id, $partner->id, $space, $seed),
            ])];
        });

        if (isset($session['running'])) {
            return response()->json(['running' => $session['running']->id], 409);
        }
        if (isset($session['incoming'])) {
            return response()->json(['incoming' => $session['incoming']->id], 409);
        }

        $created = $session['created'];
        // The invitation is saved; a notification problem must not turn it into an error.
        try {
            $partner->notify(new ArenaInvite($user->firstName(), ArenaGames::title($created->game)));
        } catch (Throwable $e) {
            Log::warning('notification.failed', ['type' => 'arena_invite', 'error' => $e::class]);
        }

        return response()->json(['id' => $created->id]);
    }

    public function accept(Request $request, int $session): JsonResponse
    {
        $user = $request->user();

        $result = DB::transaction(function () use ($request, $session, $user) {
            $s = $this->mine($request, $session, true);
            abort_unless($s->guest_id === $user->id, 403);
            if ($s->status === 'active') {
                return $s;
            }
            abort_unless($s->status === 'pending' && $s->created_at->gte(now()->subMinutes(self::INVITE_MINUTES)), 409, 'Ajakan ini sudah tidak berlaku.');
            // Anything else we were playing is over once a new game starts.
            ArenaSession::where('couple_space_id', $s->couple_space_id)->where('status', 'active')->where('id', '!=', $s->id)->update(['status' => 'ended', 'ended_at' => now()]);
            $s->update(['status' => 'active', 'accepted_at' => now()]);

            return $s;
        });

        return response()->json(['id' => $result->id]);
    }

    public function decline(Request $request, int $session): JsonResponse
    {
        $s = $this->mine($request, $session);
        abort_unless($s->guest_id === $request->user()->id && $s->status === 'pending', 409);
        $s->update(['status' => 'declined']);

        return response()->json(['ok' => true]);
    }

    public function cancel(Request $request, int $session): JsonResponse
    {
        $s = $this->mine($request, $session);
        abort_unless($s->host_id === $request->user()->id, 403);
        if ($s->status === 'pending') {
            $s->update(['status' => 'cancelled']);
        }

        return response()->json(['ok' => true]);
    }

    /** The shared game screen for the two of you. */
    public function session(Request $request, int $session): Response|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $s = $this->mine($request, $session);
        if ($s->status !== 'active') {
            return redirect('/arena');
        }
        $partner = User::findOrFail($s->otherId($user));
        $state = $s->state ?? [];

        return Inertia::render('Arena/Play', [
            'game' => $this->gameProps($s->game),
            'mode' => 'duo',
            'seed' => ($s->seed + ($state['round'] ?? 1) * 7919) % 2000000000,
            'photos' => $state['photos'] ?? [],
            'me' => $this->person($user),
            'partner' => $this->person($partner),
            'session' => ['id' => $s->id, 'host' => $s->host_id === $user->id],
        ]);
    }

    public function state(Request $request, int $session): JsonResponse
    {
        $s = $this->mine($request, $session);
        $s->touchQuietly();

        return response()->json($this->payload($s, $request->user()));
    }

    /**
     * Voice chat between the two of you: the browsers talk to each other directly (WebRTC); these two calls only
     * pass the small connection notes (offer, answer, candidates) from one to the other. Notes live a few minutes in
     * the cache, nothing about them is kept, and the sound itself never touches the server.
     */
    public function voiceSend(Request $request, int $session): JsonResponse
    {
        $s = $this->mine($request, $session);
        $data = $request->validate([
            'type' => ['required', 'in:hello,offer,answer,ice,bye'],
            'data' => ['nullable', 'string', 'max:12000'],
        ]);
        $key = 'arena.voice.'.$s->id.'.'.$s->otherId($request->user());
        $next = Cache::lock($key.'.lock', 3)->block(2, function () use ($key, $data) {
            $list = Cache::get($key, ['n' => 0, 'items' => []]);
            $list['n']++;
            $list['items'][] = ['id' => $list['n'], 'type' => $data['type'], 'data' => $data['data'] ?? null];
            $list['items'] = array_slice($list['items'], -60);
            Cache::put($key, $list, now()->addMinutes(5));

            return $list['n'];
        });

        return response()->json(['ok' => true, 'id' => $next]);
    }

    public function voicePoll(Request $request, int $session): JsonResponse
    {
        $s = $this->mine($request, $session);
        $after = max(0, (int) $request->query('after', 0));
        $list = Cache::get('arena.voice.'.$s->id.'.'.$request->user()->id, ['n' => 0, 'items' => []]);
        $s->touchQuietly();

        return response()->json([
            'last' => $list['n'],
            'items' => array_values(array_filter($list['items'], fn ($i) => $i['id'] > $after)),
        ]);
    }

    /** A move in a pair game. Every action is checked against the current round, so a double tap or a late request changes nothing. */
    public function act(Request $request, int $session): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:ready,finish,again,pick,next,leave'],
            'round' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'choice' => ['nullable', 'integer', 'min:0', 'max:2'],
            'index' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $user = $request->user();

        $s = DB::transaction(function () use ($request, $session, $data, $user) {
            $s = $this->mine($request, $session, true);
            if ($s->status !== 'active') {
                return $s;
            }
            $st = $s->state ?? [];
            $meta = ArenaGames::GAMES[$s->game];

            if ($data['type'] === 'leave') {
                $this->recordTalk($s, $st);
                $s->update(['status' => 'ended', 'ended_at' => now()]);

                return $s;
            }

            if ($meta['kind'] === 'race') {
                $st = $this->raceAction($s, $st, $data, $user);
            } elseif ($s->game === 'ngobrol') {
                $st = $this->talkAction($s, $st, $data);
            } else {
                $st = $this->penaltyAction($s, $st, $data, $user);
            }
            $s->state = $st;
            $s->save();

            return $s;
        });

        return response()->json($this->payload($s, $user));
    }

    /** A finished solo game. */
    public function score(Request $request): JsonResponse
    {
        $data = $request->validate(['game' => ['required', 'string'], 'score' => ['required', 'integer', 'min:0', 'max:100000'], 'won' => ['nullable', 'boolean']]);
        abort_unless(ArenaGames::has($data['game']), 422);
        $user = $request->user();
        $space = $this->space($request);

        $score = ArenaGames::clamp($data['game'], (int) $data['score']);
        $previous = ArenaScore::where('couple_space_id', $space->id)->where('user_id', $user->id)->where('game', $data['game'])->max('score');
        $row = ArenaScore::create([
            'couple_space_id' => $space->id, 'user_id' => $user->id, 'game' => $data['game'], 'score' => $score,
            'stars' => ArenaGames::stars($data['game'], $score), 'won' => $data['won'] ?? null,
        ]);

        return response()->json(['stars' => $row->stars, 'best' => max((int) $previous, $score), 'isBest' => $previous === null || $score > (int) $previous]);
    }

    // ---------------------------------------------------------------------------------------------------------

    /** Side-by-side games: both press ready, a shared countdown starts, each plays the same board and posts a score. */
    private function raceAction(ArenaSession $s, array $st, array $data, User $user): array
    {
        $meta = ArenaGames::GAMES[$s->game];
        $phase = $this->racePhase($st, $meta);

        switch ($data['type']) {
            case 'ready':
                if ($phase !== 'lobby') {
                    break;
                }
                $st['ready'][$user->id] = true;
                if (count($st['ready']) >= 2) {
                    $st['start_at'] = (int) round(microtime(true) * 1000) + 4500;
                }
                break;

            case 'finish':
                if ($phase !== 'play' || isset($st['scores'][$user->id])) {
                    break;
                }
                $score = ArenaGames::clamp($s->game, (int) ($data['score'] ?? 0));
                $row = ArenaScore::create([
                    'couple_space_id' => $s->couple_space_id, 'user_id' => $user->id, 'game' => $s->game, 'score' => $score,
                    'stars' => ArenaGames::stars($s->game, $score), 'arena_session_id' => $s->id,
                ]);
                $st['scores'][$user->id] = ['score' => $score, 'stars' => $row->stars, 'row' => $row->id];
                if (count($st['scores']) >= 2) {
                    $this->settle($st['scores']);
                }
                break;

            case 'again':
                // Only the round that is on screen can be replayed, so two taps at once start one new round.
                if ($phase !== 'result' || (int) ($data['round'] ?? -1) !== (int) $st['round']) {
                    break;
                }
                $st['round']++;
                $st['ready'] = [];
                $st['start_at'] = null;
                $st['scores'] = [];
                $st['photos'] = $this->photoIds($s->space ?? CoupleSpace::find($s->couple_space_id), $s->seed + $st['round'], 8);
                break;
        }

        return $st;
    }

    /** Marks the winner on both score rows once both results are in. */
    private function settle(array $scores): void
    {
        $ids = array_keys($scores);
        [$a, $b] = [$scores[$ids[0]]['score'], $scores[$ids[1]]['score']];
        if ($a === $b) {
            return;
        }
        ArenaScore::where('id', $scores[$ids[0]]['row'])->update(['won' => $a > $b]);
        ArenaScore::where('id', $scores[$ids[1]]['row'])->update(['won' => $b > $a]);
    }

    private function racePhase(array $st, array $meta): string
    {
        if (empty($st['start_at'])) {
            return 'lobby';
        }
        $now = (int) round(microtime(true) * 1000);
        if (count($st['scores'] ?? []) >= 2 || $now > $st['start_at'] + $meta['duration'] + 20000) {
            return 'result';
        }

        return 'play';
    }

    /** Question cards: both see the same card, either of you moves on to the next one. */
    private function talkAction(ArenaSession $s, array $st, array $data): array
    {
        if ($data['type'] === 'next' && (int) ($data['index'] ?? -1) === (int) $st['index']) {
            $st['index']++;
            $st['turn'] = $st['turn'] === $s->host_id ? $s->guest_id : $s->host_id;
        }

        return $st;
    }

    private function recordTalk(ArenaSession $s, array $st): void
    {
        if ($s->game !== 'ngobrol' || ($st['index'] ?? 0) < 3) {
            return;
        }
        $score = ArenaGames::clamp('ngobrol', (int) $st['index']);
        foreach ([$s->host_id, $s->guest_id] as $id) {
            ArenaScore::create(['couple_space_id' => $s->couple_space_id, 'user_id' => $id, 'game' => 'ngobrol', 'score' => $score, 'stars' => ArenaGames::stars('ngobrol', $score), 'arena_session_id' => $s->id]);
        }
    }

    /** Penalty shoot-out: each kick one of you shoots (left, middle, right) and the other dives; a goal only if you picked different sides. */
    private function penaltyAction(ArenaSession $s, array $st, array $data, User $user): array
    {
        $total = ArenaGames::KICKS_EACH * 2;

        if ($data['type'] === 'pick') {
            if ($st['kick'] > $total || (int) ($data['round'] ?? -1) !== (int) $st['kick'] || isset($st['picks'][$user->id]) || ! isset($data['choice'])) {
                return $st;
            }
            $st['picks'][$user->id] = (int) $data['choice'];
            if (count($st['picks']) >= 2) {
                $shooter = $this->shooterId($s, $st['kick']);
                $keeper = $shooter === $s->host_id ? $s->guest_id : $s->host_id;
                $goal = $st['picks'][$shooter] !== $st['picks'][$keeper];
                $st['log'][] = ['kick' => $st['kick'], 'shooter' => $shooter, 'shot' => $st['picks'][$shooter], 'dive' => $st['picks'][$keeper], 'goal' => $goal];
                if ($goal) {
                    $st['goals'][$shooter] = ($st['goals'][$shooter] ?? 0) + 1;
                }
                $st['picks'] = [];
                $st['kick']++;
                if ($st['kick'] > $total) {
                    $this->finishPenalty($s, $st);
                }
            }
        } elseif ($data['type'] === 'again' && $st['kick'] > $total && (int) ($data['round'] ?? -1) === (int) $st['round']) {
            $st = ['round' => $st['round'] + 1, 'kick' => 1, 'picks' => [], 'log' => [], 'goals' => [$s->host_id => 0, $s->guest_id => 0]];
        }

        return $st;
    }

    private function finishPenalty(ArenaSession $s, array $st): void
    {
        $a = (int) ($st['goals'][$s->host_id] ?? 0);
        $b = (int) ($st['goals'][$s->guest_id] ?? 0);
        foreach ([[$s->host_id, $a, $b], [$s->guest_id, $b, $a]] as [$id, $mine, $theirs]) {
            ArenaScore::create([
                'couple_space_id' => $s->couple_space_id, 'user_id' => $id, 'game' => 'penalti', 'score' => $mine,
                'stars' => ArenaGames::stars('penalti', $mine), 'won' => $mine === $theirs ? null : $mine > $theirs, 'arena_session_id' => $s->id,
            ]);
        }
    }

    /** The host shoots on odd kicks, the guest on even ones. */
    private function shooterId(ArenaSession $s, int $kick): int
    {
        return $kick % 2 === 1 ? $s->host_id : $s->guest_id;
    }

    /** The state as one player is allowed to see it (the partner's pick stays hidden until the kick is taken). */
    private function payload(ArenaSession $s, User $me): array
    {
        $st = $s->state ?? [];
        $meta = ArenaGames::GAMES[$s->game];
        $other = $s->otherId($me);
        $who = fn (int $id) => $id === $me->id ? 'me' : 'partner';

        $out = [
            'status' => $s->status, 'game' => $s->game, 'now' => (int) round(microtime(true) * 1000),
            'round' => (int) ($st['round'] ?? 1), 'seed' => ($s->seed + ($st['round'] ?? 1) * 7919) % 2000000000,
        ];
        if ($s->status !== 'active') {
            return $out;
        }

        if ($meta['kind'] === 'race') {
            $phase = $this->racePhase($st, $meta);
            $scores = $st['scores'] ?? [];
            $mineScore = $scores[$me->id] ?? null;
            $theirScore = $scores[$other] ?? null;
            $out += [
                'phase' => $phase, 'startAt' => $st['start_at'] ?? null, 'duration' => $meta['duration'],
                'ready' => ['me' => isset($st['ready'][$me->id]), 'partner' => isset($st['ready'][$other])],
                'photos' => $st['photos'] ?? [],
                'mine' => $mineScore ? ['score' => $mineScore['score'], 'stars' => $mineScore['stars']] : null,
                // The partner's number is shown once the round is over.
                'partnerDone' => (bool) $theirScore,
                'partnerScore' => $phase === 'result' && $theirScore ? ['score' => $theirScore['score'], 'stars' => $theirScore['stars']] : null,
                'winner' => $phase === 'result' && $mineScore && $theirScore ? ($mineScore['score'] === $theirScore['score'] ? 'draw' : ($mineScore['score'] > $theirScore['score'] ? 'me' : 'partner')) : null,
            ];
        } elseif ($s->game === 'ngobrol') {
            $out += ['index' => (int) $st['index'], 'turn' => $who((int) $st['turn'])];
        } else {
            $total = ArenaGames::KICKS_EACH * 2;
            $kick = (int) $st['kick'];
            $over = $kick > $total;
            $out += [
                'kick' => $kick, 'total' => $total, 'over' => $over,
                'shooter' => $over ? null : $who($this->shooterId($s, $kick)),
                'picked' => ['me' => isset($st['picks'][$me->id]), 'partner' => isset($st['picks'][$other])],
                'goals' => ['me' => (int) ($st['goals'][$me->id] ?? 0), 'partner' => (int) ($st['goals'][$other] ?? 0)],
                'log' => collect($st['log'] ?? [])->map(fn ($l) => ['kick' => $l['kick'], 'shooter' => $who($l['shooter']), 'shot' => $l['shot'], 'dive' => $l['dive'], 'goal' => $l['goal']])->values(),
            ];
        }

        return $out;
    }

    private function initialState(string $game, int $hostId, int $guestId, CoupleSpace $space, int $seed): array
    {
        if (ArenaGames::GAMES[$game]['kind'] === 'race') {
            return ['round' => 1, 'ready' => [], 'start_at' => null, 'scores' => [], 'photos' => $this->photoIds($space, $seed + 1, 8)];
        }
        if ($game === 'ngobrol') {
            return ['round' => 1, 'index' => 0, 'turn' => $hostId];
        }

        return ['round' => 1, 'kick' => 1, 'picks' => [], 'log' => [], 'goals' => [$hostId => 0, $guestId => 0]];
    }

    /** Photos from your shared albums for the memory games, in an order both of you get. */
    private function photoIds(CoupleSpace $space, int $seed, int $count): array
    {
        $ids = DB::table('photos')->join('albums', 'albums.id', '=', 'photos.album_id')
            ->where('albums.couple_space_id', $space->id)->where('albums.scope', 'shared')
            ->whereNotNull('photos.thumb_path')->orderByDesc('photos.id')->limit(120)->pluck('photos.id')->all();

        return array_slice((new Randomizer(new Mt19937($seed)))->shuffleArray($ids), 0, $count);
    }

    private function gameProps(string $game): array
    {
        $meta = ArenaGames::GAMES[$game];

        return ['key' => $game, 'title' => $meta['title'], 'kind' => $meta['kind'], 'duration' => $meta['duration'], 'kicks' => ArenaGames::KICKS_EACH,
            'cards' => $game === 'ngobrol' ? array_values(trans('arena.cards', [], 'id')) : []];
    }

    /** @return array{id: int, name: string, avatarUrl: ?string, avatarFrame: ?string} */
    private function person(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->firstName(), 'avatarUrl' => $user->avatarUrl(), 'avatarFrame' => $user->avatar_frame];
    }

    /** A session of this couple that I take part in; anyone else gets a 404, as if it did not exist. */
    private function mine(Request $request, int $id, bool $lock = false): ArenaSession
    {
        $user = $request->user();
        $query = ArenaSession::where('id', $id)->where('couple_space_id', $user->activeSpaceId());
        $s = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($s && $s->includes($user), 404);

        return $s;
    }

    private function space(Request $request): CoupleSpace
    {
        $space = $request->user()->activeSpace();
        abort_unless($space, 404);

        return $space;
    }
}

<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatReaction;
use App\Models\LoveNote;
use App\Support\E2E;
use App\Support\LocalDateTime;
use App\Models\User;
use App\Events\ChatTyping;
use App\Services\Albums\PhotoService;
use App\Services\Chat\ChatService;
use App\Services\Chat\Presence;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

class ChatController extends Controller
{
    /** Voice note types by detected mime: [file extension, mime we serve it as]. */
    private const AUDIO_TYPES = [
        'audio/webm' => ['webm', 'audio/webm'],
        'video/webm' => ['webm', 'audio/webm'],
        'audio/mp4' => ['m4a', 'audio/mp4'],
        'video/mp4' => ['m4a', 'audio/mp4'],
        'audio/x-m4a' => ['m4a', 'audio/mp4'],
        'audio/m4a' => ['m4a', 'audio/mp4'],
        'audio/aac' => ['m4a', 'audio/mp4'],
        'audio/ogg' => ['ogg', 'audio/ogg'],
        'application/ogg' => ['ogg', 'audio/ogg'],
        'audio/mpeg' => ['mp3', 'audio/mpeg'],
    ];

    public function __construct(private ChatService $chat, private Presence $presence) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $conversation = $this->chat->conversationFor($user);
        $partner = $user->partner();

        if (! $conversation || ! $partner) {
            return Inertia::render('Chat/Show', ['partner' => null, 'messages' => [], 'hasMore' => false, 'partnerLastRead' => 0]);
        }

        $pageSize = config('usandus.chat.page_size');
        $messages = $this->chat->history($conversation, null, $pageSize + 1);
        $hasMore = $messages->count() > $pageSize;

        return Inertia::render('Chat/Show', [
            'partner' => ['id' => $partner->id, 'name' => $partner->name, 'firstName' => $partner->firstName(), 'avatarUrl' => $partner->avatarUrl(), 'avatarFrame' => $partner->avatar_frame],
            'messages' => $this->present($hasMore ? $messages->slice(1)->values() : $messages, $user),
            'hasMore' => $hasMore,
            'partnerLastRead' => $this->chat->lastReadId($conversation, $partner->id),
            'presence' => $this->presence->summary($partner->id),
            'muted' => $this->chat->isMuted($conversation, $user->id),
            'pinned' => $this->present($conversation->messages()->whereNotNull('pinned_at')->whereNull('deleted_at')->orderByDesc('pinned_at')->get(), $user),
            'stickers' => collect(config('usandus.chat.stickers'))->map(fn ($v, $k) => ['key' => $k, 'emoji' => $v[0], 'label' => $v[1]])->values()->all(),
            'editMinutes' => (int) config('usandus.chat.edit_window_minutes'),
            'maxLength' => config('usandus.chat.max_length'),
            'spaceId' => $conversation->couple_space_id,
        ]);
    }

    /** Polling (`after`) and history (`before`). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
            'before' => ['nullable', 'integer', 'min:1'],
        ]);
        $user = $request->user();
        $conversation = $this->conversationOrFail($user);
        $partner = $user->partner();

        if (isset($data['before'])) {
            $pageSize = config('usandus.chat.page_size');
            $messages = $this->chat->history($conversation, (int) $data['before'], $pageSize + 1);
            $hasMore = $messages->count() > $pageSize;

            return response()->json([
                'messages' => $this->present($hasMore ? $messages->slice(1)->values() : $messages, $user),
                'hasMore' => $hasMore,
            ]);
        }

        return response()->json([
            'messages' => $this->present($this->chat->since($conversation, (int) ($data['after'] ?? 0)), $user),
            'partnerLastRead' => $partner ? $this->chat->lastReadId($conversation, $partner->id) : 0,
            'reactions' => $this->chat->recentReactions($conversation, $user),
            'states' => $this->chat->recentStates($conversation),
            'presence' => $partner ? [
                ...$this->presence->summary($partner->id),
                'typing' => $this->presence->isTyping($conversation->id, $partner->id),
            ] : null,
        ]);
    }

    public function store(Request $request, PhotoService $photos): JsonResponse
    {
        $sealed = E2E::enabled();
        $data = $request->validate([
            'body' => ['nullable', ...E2E::text(config('usandus.chat.max_length'))],
            'sticker' => ['nullable', 'string', Rule::in(array_keys(config('usandus.chat.stickers')))],
            'client_uuid' => ['required', 'uuid'],
            'reply_to' => ['nullable', 'integer'],
            'audio' => $sealed ? ['nullable', 'file', 'max:8192', E2E::fileRule()] : ['nullable', 'file', 'max:6144', 'mimetypes:'.implode(',', array_keys(self::AUDIO_TYPES))],
            'audio_type' => ['nullable', 'string', 'max:60'],
            'audio_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            ...($sealed ? PhotoService::sealedRules('photo', false) : [
                'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:25600', 'dimensions:min_width=64,min_height=64,max_width=12000,max_height=12000'],
            ]),
        ], [
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.mimetypes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto maksimal 25 MB.',
            'photo.dimensions' => 'Foto terlalu kecil atau terlalu besar.',
            'photo.uploaded' => 'Foto gagal diunggah. Coba foto yang lebih kecil.',
            'audio.mimetypes' => 'Format pesan suara tidak didukung.',
            'audio.max' => 'Pesan suara terlalu besar (maksimal 6 MB).',
            'audio.uploaded' => 'Pesan suara gagal diunggah. Coba rekam yang lebih pendek.',
        ]);
        $body = trim($data['body'] ?? '');
        $file = $request->file('photo');
        $sticker = $data['sticker'] ?? null;
        $voice = $request->file('audio');
        if ($sticker) {
            // A sticker stands alone.
            $body = '';
            $file = null;
            $voice = null;
        }
        if ($voice) {
            // A voice note stands alone too.
            $body = '';
            $file = null;
        }
        if ($body === '' && ! $file && ! $sticker && ! $voice) {
            return response()->json(['message' => 'Pesan tidak boleh kosong.', 'errors' => ['body' => ['Pesan tidak boleh kosong.']]], 422);
        }

        $user = $request->user();
        $conversation = $this->conversationOrFail($user);

        $photo = null;
        if ($file) {
            try {
                $photo = $sealed
                    ? $photos->storeSealed($file, $request->file('photo_thumb'), (int) $data['photo_width'], (int) $data['photo_height'], 'chat-photos')
                    : $photos->store($file, 'chat-photos');
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage(), 'errors' => ['photo' => [$e->getMessage()]]], 422);
            }
        }

        $audio = null;
        if ($voice) {
            // A locked voice note keeps its real type inside the lock; the name only says it is locked.
            $type = $sealed
                ? ['lne', self::AUDIO_TYPES[strtolower(explode(';', (string) ($data['audio_type'] ?? ''))[0])][1] ?? 'audio/mp4']
                : self::AUDIO_TYPES[(string) $voice->getMimeType()] ?? null;
            $name = $type ? 'chat-audio/'.Str::random(40).'.'.$type[0] : '';
            try {
                $saved = $type !== null && Storage::disk('local')->putFileAs('chat-audio', $voice, basename($name));
            } catch (Throwable) {
                $saved = false;
            }
            if (! $saved) {
                return response()->json(['message' => 'Pesan suara tidak bisa disimpan.', 'errors' => ['audio' => ['Pesan suara tidak bisa disimpan.']]], 422);
            }
            $audio = ['path' => $name, 'mime' => $type[1], 'seconds' => (int) ($data['audio_seconds'] ?? 0) ?: null];
        }

        $result = $this->chat->send($conversation, $user, $body, strtolower($data['client_uuid']), isset($data['reply_to']) ? (int) $data['reply_to'] : null, $photo, $sticker, $audio);

        return response()->json(['message' => $this->present(collect([$result['message']]), $user)[0]], $result['created'] ? 201 : 200);
    }

    /** "Typing…" for the partner's open chat; a short-lived hint, never stored. */
    public function typing(Request $request): JsonResponse
    {
        $data = $request->validate(['typing' => ['required', 'boolean']]);
        $user = $request->user();
        $conversation = $this->conversationOrFail($user);

        if ($data['typing']) {
            $this->presence->typing($conversation->id, $user->id);
        } else {
            $this->presence->stopTyping($conversation->id, $user->id);
        }

        if (config('usandus.realtime.enabled')) {
            try {
                ChatTyping::dispatch($conversation->couple_space_id, $user->id, (bool) $data['typing']);
            } catch (Throwable $e) {
                Log::warning('chat.broadcast_failed', ['error' => $e::class]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /** Chat photos are only served to the two members of the conversation. */
    public function photo(Request $request, int $message, string $variant): HttpResponse
    {
        abort_unless(in_array($variant, ['thumb', 'full'], true), 404);
        $conversation = $this->conversationOrFail($request->user());
        $row = $conversation->messages()->whereKey($message)->whereNotNull('photo_path')->first();
        abort_unless($row, 404);

        $path = $variant === 'thumb' ? $row->photo_thumb_path : $row->photo_path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, E2E::fileHeaders($path, 'image/jpeg', 'private, max-age=86400'));
    }

    /** Voice notes are only served to the two members of the conversation. */
    public function audio(Request $request, int $message): HttpResponse
    {
        $conversation = $this->conversationOrFail($request->user());
        $row = $conversation->messages()->whereKey($message)->whereNotNull('audio_path')->first();
        abort_unless($row && Storage::disk('local')->exists($row->audio_path), 404);
        $mime = in_array($row->audio_mime, array_column(self::AUDIO_TYPES, 1), true) ? $row->audio_mime : 'application/octet-stream';

        // A file response understands Range requests, which Safari needs to play audio.
        return response()->file(Storage::disk('local')->path($row->audio_path), str_ends_with($row->audio_path, '.lne')
            ? E2E::fileHeaders($row->audio_path, $mime, 'private, max-age=86400')
            : [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }

    public function update(Request $request, int $message): JsonResponse
    {
        $data = $request->validate(['body' => ['required', ...E2E::text(config('usandus.chat.max_length'))]]);
        $body = trim($data['body']);
        abort_if($body === '', 422, 'Pesan tidak boleh kosong.');
        $user = $request->user();
        $edited = $this->chat->edit($this->conversationOrFail($user), $user, $message, $body);
        abort_unless($edited, 403, 'Pesan hanya bisa diubah '.config('usandus.chat.edit_window_minutes').' menit pertama.');

        return response()->json(['message' => $this->present(collect([$edited]), $user)[0]]);
    }

    public function destroy(Request $request, int $message): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->chat->delete($this->conversationOrFail($user), $user, $message), 404);

        return response()->json(['ok' => true]);
    }

    public function pin(Request $request, int $message): JsonResponse
    {
        $user = $request->user();
        $result = $this->chat->togglePin($this->conversationOrFail($user), $user, $message);
        abort_if($result === null, 404);
        abort_if($result === 'limit', 422, 'Maksimal '.config('usandus.chat.max_pinned').' pesan yang disematkan. Lepas satu dulu.');

        return response()->json(['result' => $result]);
    }

    public function mute(Request $request): JsonResponse
    {
        $data = $request->validate(['muted' => ['required', 'boolean']]);
        $user = $request->user();
        $this->chat->setMuted($this->conversationOrFail($user), $user, (bool) $data['muted']);

        return response()->json(['muted' => (bool) $data['muted']]);
    }

    /** Love notes: a message written now that arrives in the chat at a chosen time. */
    public function loveNotes(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->conversationOrFail($user);

        return response()->json(['notes' => LoveNote::where('couple_space_id', $conversation->couple_space_id)->where('sender_id', $user->id)
            ->whereNull('delivered_at')->orderBy('deliver_at')->limit(30)->get()
            ->map(fn (LoveNote $n) => ['id' => $n->id, 'body' => $n->body, 'deliver_at' => $n->deliver_at->toIso8601ZuluString()])->all()]);
    }

    public function scheduleLoveNote(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->conversationOrFail($user);
        $data = $request->validate([
            'body' => ['required', ...E2E::text(500)],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ]);
        $at = LocalDateTime::toUtc($data['date'], $data['time'], $user->timezone);
        abort_if($at->lte(now()->addMinute()), 422, 'Pilih waktu yang masih akan datang.');
        abort_if($at->gt(now()->addYear()), 422, 'Maksimal satu tahun ke depan.');
        abort_if(LoveNote::where('couple_space_id', $conversation->couple_space_id)->where('sender_id', $user->id)->whereNull('delivered_at')->count() >= 30, 422, 'Maksimal 30 love note terjadwal.');

        $note = LoveNote::create(['couple_space_id' => $conversation->couple_space_id, 'sender_id' => $user->id, 'body' => trim($data['body']), 'deliver_at' => $at]);

        return response()->json(['id' => $note->id], 201);
    }

    public function cancelLoveNote(Request $request, int $note): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->conversationOrFail($user);
        $deleted = LoveNote::whereKey($note)->where('couple_space_id', $conversation->couple_space_id)->where('sender_id', $user->id)->whereNull('delivered_at')->delete();
        abort_unless($deleted, 404);

        return response()->json(['ok' => true]);
    }

    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        if (E2E::enabled()) {
            // Locked text cannot be searched here: the browser gets the recent messages and searches after opening them.
            $found = $this->chat->recentText($this->conversationOrFail($user));

            return response()->json(['messages' => collect($this->present($found, $user))->map(fn ($m) => [
                'id' => $m['id'], 'body' => $m['body'], 'mine' => $m['mine'], 'created_at' => $m['created_at'],
            ])->all()]);
        }
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']], [], ['q' => 'kata kunci']);
        $found = $this->chat->search($this->conversationOrFail($user), trim($data['q']));

        return response()->json(['messages' => collect($this->present($found, $user))->map(fn ($m) => [
            'id' => $m['id'], 'body' => Str::limit($m['body'], 140), 'mine' => $m['mine'], 'created_at' => $m['created_at'],
        ])->all()]);
    }

    public function react(Request $request, int $message): JsonResponse
    {
        $data = $request->validate(['emoji' => ['required', 'string', fn ($attr, $value, $fail) => ChatReaction::isEmoji((string) $value) ?: $fail('Emoji tidak valid.')]]);
        $user = $request->user();
        abort_unless($this->chat->react($this->conversationOrFail($user), $user, $message, $data['emoji']), 404);

        return response()->json(['reactions' => $this->chat->recentReactions($this->conversationOrFail($user), $user)]);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['message_id' => ['required', 'integer', 'min:1']]);
        $user = $request->user();

        return response()->json(['lastRead' => $this->chat->markRead($this->conversationOrFail($user), $user, (int) $data['message_id'])]);
    }

    private function conversationOrFail(User $user): ChatConversation
    {
        $conversation = $this->chat->conversationFor($user);
        abort_unless($conversation && $user->partner(), 404);

        return $conversation;
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $messages, User $user): array
    {
        $messages = new EloquentCollection($messages->all());
        $messages->loadMissing(['replyTo', 'reactions']);
        $reactions = $this->chat->summarize($messages->pluck('reactions')->flatten(), $user, $messages->pluck('id')->all());

        return $messages->map(fn (ChatMessage $m) => [
            'reply' => $m->replyTo ? ['id' => $m->replyTo->id, 'body' => E2E::limit(ChatService::preview($m->replyTo), 90), 'mine' => $m->replyTo->sender_id === $user->id] : null,
            'photo' => $m->photo_path ? [
                'thumb' => "/chat/photos/{$m->id}/thumb",
                'full' => "/chat/photos/{$m->id}/full",
                'width' => $m->photo_width,
                'height' => $m->photo_height,
            ] : null,
            'audio' => $m->audio_path ? ['url' => "/chat/audio/{$m->id}", 'seconds' => $m->audio_seconds] : null,
            'reactions' => $reactions[$m->id] ?? [],
            'id' => $m->id,
            'body' => $m->body,
            'sticker' => $m->sticker ? ['key' => $m->sticker, 'emoji' => config("usandus.chat.stickers.{$m->sticker}.0")] : null,
            'edited' => $m->edited_at !== null,
            'deleted' => $m->deleted_at !== null,
            'pinned' => $m->pinned_at !== null,
            'mine' => $m->sender_id === $user->id,
            'client_uuid' => $m->sender_id === $user->id ? $m->client_uuid : null,
            'created_at' => $m->created_at->toIso8601ZuluString('millisecond'),
        ])->values()->all();
    }
}

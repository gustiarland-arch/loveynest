<?php

namespace App\Services\Chat;

use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatReaction;
use App\Models\User;
use App\Notifications\NewChatMessage;
use App\Services\Couple\CoupleSpaceService;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * One private conversation per couple space. The conversation is always
 * looked up from the signed-in user's active space, never from client input,
 * so there is no conversation id a stranger could guess.
 *
 * Message bodies are never logged.
 */
class ChatService
{
    public function conversationFor(User $user): ?ChatConversation
    {
        $spaceId = $user->activeSpaceId();

        return $spaceId ? ChatConversation::where('couple_space_id', $spaceId)->first() : null;
    }

    /**
     * Idempotent: retrying with the same client UUID returns the original
     * message instead of creating a duplicate.
     *
     * @param  array{path: string, thumb_path: string, width: int, height: int}|null  $photo  already stored by PhotoService
     * @return array{message: ChatMessage, created: bool}
     */
    public function send(ChatConversation $conversation, User $sender, string $body, string $clientUuid, ?int $replyTo = null, ?array $photo = null, ?string $sticker = null, ?array $audio = null): array
    {
        $existing = $this->findByClientUuid($conversation, $sender, $clientUuid);
        if ($existing) {
            $this->discardPhoto($photo);
            $this->discardAudio($audio);

            return ['message' => $existing, 'created' => false];
        }

        try {
            $message = DB::transaction(function () use ($conversation, $sender, $body, $clientUuid, $replyTo, $photo, $sticker, $audio) {
                $message = $conversation->messages()->create([
                    'sender_id' => $sender->id,
                    'client_uuid' => $clientUuid,
                    'body' => $body,
                    'photo_path' => $photo['path'] ?? null,
                    'photo_thumb_path' => $photo['thumb_path'] ?? null,
                    'photo_width' => $photo['width'] ?? null,
                    'photo_height' => $photo['height'] ?? null,
                    'sticker' => $sticker,
                    'audio_path' => $audio['path'] ?? null,
                    'audio_mime' => $audio['mime'] ?? null,
                    'audio_seconds' => $audio['seconds'] ?? null,
                    // Only a message of this same conversation can be replied to.
                    'reply_to_id' => $replyTo && $conversation->messages()->whereKey($replyTo)->exists() ? $replyTo : null,
                ]);
                // Your own message counts as read by you.
                $this->markRead($conversation, $sender, $message->id);

                return $message;
            });
        } catch (QueryException $e) {
            // Two retries raced each other; the first one won.
            if (CoupleSpaceService::isUniqueViolation($e) && ($existing = $this->findByClientUuid($conversation, $sender, $clientUuid))) {
                $this->discardPhoto($photo);
                $this->discardAudio($audio);

                return ['message' => $existing, 'created' => false];
            }
            throw $e;
        }

        app(Presence::class)->stopTyping($conversation->id, $sender->id);
        $this->broadcast($conversation, $message);
        $this->notifyPartner($conversation, $sender, $message);

        return ['message' => $message, 'created' => true];
    }

    /** @param  array{path: string}|null  $audio */
    private function discardAudio(?array $audio): void
    {
        if ($audio) {
            Storage::disk('local')->delete($audio['path']);
        }
    }

    /** A retried upload whose message already exists must not leave a stray file. */
    private function discardPhoto(?array $photo): void
    {
        if ($photo) {
            Storage::disk('local')->delete([$photo['path'], $photo['thumb_path']]);
        }
    }

    /** Short text for previews: replies and notifications. */
    public static function preview(ChatMessage $message): string
    {
        if ($message->deleted_at) {
            return 'Pesan dihapus';
        }
        if ($message->sticker) {
            return (config("usandus.chat.stickers.{$message->sticker}.0") ?? '').' Stiker';
        }

        return $message->body !== '' ? $message->body : ($message->photo_path ? '📷 Foto' : ($message->audio_path ? '🎤 Pesan suara' : ''));
    }

    /**
     * Live nudge for the partner's open chat. A socket server that is down
     * must never break sending: polling still delivers the message.
     */
    private function broadcast(ChatConversation $conversation, ChatMessage $message): void
    {
        if (! config('usandus.realtime.enabled')) {
            return;
        }

        try {
            ChatMessageSent::dispatch($conversation->couple_space_id, $message->id);
        } catch (Throwable $e) {
            Log::warning('chat.broadcast_failed', ['error' => $e::class]);
        }
    }

    /**
     * Sets, changes or removes (same emoji again) the user's reaction.
     * Returns false when the message is not in this conversation.
     */
    public function react(ChatConversation $conversation, User $user, int $messageId, string $emoji): bool
    {
        $message = $conversation->messages()->find($messageId);
        if (! $message) {
            return false;
        }

        $existing = ChatReaction::where('chat_message_id', $message->id)->where('user_id', $user->id)->first();
        if ($existing && $existing->emoji === $emoji) {
            $existing->delete();
        } else {
            ChatReaction::updateOrCreate(['chat_message_id' => $message->id, 'user_id' => $user->id], ['emoji' => $emoji]);
            $this->notifyReaction($conversation, $user, $message, $emoji);
        }

        $this->broadcast($conversation, $message);

        return true;
    }

    /**
     * Reactions of the latest messages, so that a reaction on an older
     * message reaches an open chat through polling.
     *
     * @return array<int, list<array{emoji: string, count: int, mine: bool}>>
     */
    public function recentReactions(ChatConversation $conversation, User $user, int $window = 60): array
    {
        $ids = $conversation->messages()->latest('id')->limit($window)->pluck('id');

        return $this->summarize(ChatReaction::whereIn('chat_message_id', $ids)->get(), $user, $ids->all());
    }

    /**
     * @param  iterable<ChatReaction>  $reactions
     * @param  list<int>  $messageIds
     * @return array<int, list<array{emoji: string, count: int, mine: bool}>>
     */
    public function summarize(iterable $reactions, User $user, array $messageIds): array
    {
        $byMessage = collect($reactions)->groupBy('chat_message_id');
        $out = [];
        foreach ($messageIds as $id) {
            $out[$id] = ($byMessage[$id] ?? collect())->groupBy('emoji')
                ->map(fn ($rows, $emoji) => ['emoji' => $emoji, 'count' => $rows->count(), 'mine' => $rows->contains('user_id', $user->id)])
                ->values()->all();
        }

        return $out;
    }

    /**
     * A few seconds of cooldown stop a burst of messages from buzzing repeatedly: later messages inside it are
     * covered by the first notification (same tag, so it is replaced).
     */
    private function notifyPartner(ChatConversation $conversation, User $sender, ChatMessage $message): void
    {
        $partner = $sender->partner();
        if (! $partner) {
            return;
        }
        if ($conversation->readStates()->where('user_id', $partner->id)->where('muted', true)->exists()) {
            return;
        }
        if (! Cache::add("chat-notify:{$conversation->id}:{$partner->id}", true, config('usandus.notifications.chat_push_cooldown_seconds'))) {
            return;
        }

        // The message is already saved; a notification problem must not turn it into an error.
        try {
            $preview = app(NotificationPreferences::class)->for($partner, 'chat_message')['show_preview'] ? self::preview($message) : null;
            $partner->notify(new NewChatMessage($sender->firstName(), $preview));
        } catch (Throwable $e) {
            Log::warning('notification.failed', ['type' => 'chat_message', 'error' => $e::class]);
        }
    }

    /** Tell the author when their message gets a reaction (not for your own, not while muted, at most one per minute). */
    private function notifyReaction(ChatConversation $conversation, User $actor, ChatMessage $message, string $emoji): void
    {
        $author = $message->sender_id !== $actor->id ? User::find($message->sender_id) : null;
        if (! $author || $conversation->readStates()->where('user_id', $author->id)->where('muted', true)->exists()) {
            return;
        }
        if (! Cache::add("chat-react:{$conversation->id}:{$author->id}", true, 60)) {
            return;
        }
        try {
            $author->notify(new \App\Notifications\ChatReaction($actor->firstName(), $emoji));
        } catch (Throwable $e) {
            Log::warning('notification.failed', ['type' => 'chat_reaction', 'error' => $e::class]);
        }
    }

    /** Newest page, or the page before a cursor. Returned oldest first. */
    public function history(ChatConversation $conversation, ?int $beforeId, int $limit): Collection
    {
        return $conversation->messages()->with(['replyTo', 'reactions'])
            ->when($beforeId, fn ($q) => $q->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function since(ChatConversation $conversation, int $afterId, int $limit = 100): Collection
    {
        return $conversation->messages()->with(['replyTo', 'reactions'])->where('id', '>', $afterId)->orderBy('id')->limit($limit)->get();
    }

    /**
     * Read markers only move forward and only to messages that exist in this
     * conversation.
     */
    public function markRead(ChatConversation $conversation, User $user, int $messageId): int
    {
        $latest = (int) $conversation->messages()->where('id', '<=', $messageId)->max('id');
        if ($latest === 0) {
            return $this->lastReadId($conversation, $user->id);
        }

        DB::statement(
            'INSERT INTO chat_read_states (chat_conversation_id, user_id, last_read_message_id, updated_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), VALUES(last_read_message_id)), updated_at = VALUES(updated_at)',
            [$conversation->id, $user->id, $latest, now()],
        );

        return $this->lastReadId($conversation, $user->id);
    }

    public function lastReadId(ChatConversation $conversation, int $userId): int
    {
        return (int) $conversation->readStates()->where('user_id', $userId)->value('last_read_message_id');
    }

    public function unreadCount(ChatConversation $conversation, User $user): int
    {
        return $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('id', '>', $this->lastReadId($conversation, $user->id))
            ->count();
    }

    /** Own text messages can be edited for a limited time. Returns null when not allowed. */
    public function edit(ChatConversation $conversation, User $user, int $messageId, string $body): ?ChatMessage
    {
        $message = $conversation->messages()->whereKey($messageId)->where('sender_id', $user->id)->whereNull('deleted_at')->first();
        $window = (int) config('usandus.chat.edit_window_minutes');
        if (! $message || $message->sticker || $message->created_at->lt(now()->subMinutes($window))) {
            return null;
        }
        $message->forceFill(['body' => $body, 'edited_at' => now()])->save();
        $this->broadcast($conversation, $message);

        return $message;
    }

    /** Deleting keeps a "message deleted" placeholder, but removes the text, photo and reactions for good. */
    public function delete(ChatConversation $conversation, User $user, int $messageId): bool
    {
        $message = $conversation->messages()->whereKey($messageId)->where('sender_id', $user->id)->whereNull('deleted_at')->first();
        if (! $message) {
            return false;
        }
        $files = array_filter([$message->photo_path, $message->photo_thumb_path, $message->audio_path]);
        DB::transaction(function () use ($message) {
            $message->reactions()->delete();
            $message->forceFill([
                'body' => '', 'photo_path' => null, 'photo_thumb_path' => null, 'photo_width' => null, 'photo_height' => null,
                'audio_path' => null, 'audio_mime' => null, 'audio_seconds' => null,
                'sticker' => null, 'deleted_at' => now(), 'pinned_at' => null, 'pinned_by' => null,
            ])->save();
        });
        if ($files) {
            Storage::disk('local')->delete($files);
        }
        $this->broadcast($conversation, $message);

        return true;
    }

    /** Pin or unpin. Returns 'pinned', 'unpinned', 'limit' or null when the message is not here. */
    public function togglePin(ChatConversation $conversation, User $user, int $messageId): ?string
    {
        $message = $conversation->messages()->whereKey($messageId)->whereNull('deleted_at')->first();
        if (! $message) {
            return null;
        }
        if ($message->pinned_at) {
            $message->forceFill(['pinned_at' => null, 'pinned_by' => null])->save();

            return 'unpinned';
        }
        if ($conversation->messages()->whereNotNull('pinned_at')->count() >= (int) config('usandus.chat.max_pinned')) {
            return 'limit';
        }
        $message->forceFill(['pinned_at' => now(), 'pinned_by' => $user->id])->save();

        return 'pinned';
    }

    public function setMuted(ChatConversation $conversation, User $user, bool $muted): void
    {
        DB::table('chat_read_states')->updateOrInsert(
            ['chat_conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['muted' => $muted],
        );
    }

    public function isMuted(ChatConversation $conversation, int $userId): bool
    {
        return $conversation->readStates()->where('user_id', $userId)->where('muted', true)->exists();
    }

    /** @return Collection<int, ChatMessage> */
    public function search(ChatConversation $conversation, string $term, int $limit = 30): Collection
    {
        $like = '%'.addcslashes($term, '\\%_').'%';

        return $conversation->messages()->whereNull('deleted_at')->where('body', 'like', $like)->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * The latest text messages for searching in the browser (E2E: the server cannot read them).
     *
     * @return Collection<int, ChatMessage>
     */
    public function recentText(ChatConversation $conversation, int $limit = 1500): Collection
    {
        return $conversation->messages()->whereNull('deleted_at')->where('body', '!=', '')->orderByDesc('id')->limit($limit)->get();
    }

    /** Current text/flags of the latest messages, so edits, deletions and pins on older messages reach an open chat. */
    public function recentStates(ChatConversation $conversation, int $window = 60): array
    {
        return $conversation->messages()->latest('id')->limit($window)
            ->get(['id', 'body', 'edited_at', 'deleted_at', 'pinned_at', 'photo_path', 'audio_path', 'sticker'])
            ->mapWithKeys(fn (ChatMessage $m) => [$m->id => [
                'body' => $m->body,
                'edited' => $m->edited_at !== null,
                'deleted' => $m->deleted_at !== null,
                'pinned' => $m->pinned_at !== null,
                'photo' => $m->photo_path !== null,
                'audio' => $m->audio_path !== null,
            ]])->all();
    }

    private function findByClientUuid(ChatConversation $conversation, User $sender, string $clientUuid): ?ChatMessage
    {
        return $conversation->messages()->where('sender_id', $sender->id)->where('client_uuid', $clientUuid)->first();
    }
}

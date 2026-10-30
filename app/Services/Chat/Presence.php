<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Cache;

/**
 * "Online" and "typing" are short-lived hints kept in the cache, never in a
 * table: losing them (cache cleared, server restarted) only hides a dot.
 */
class Presence
{
    /** Seen within this many seconds counts as online. */
    public const ONLINE_SECONDS = 90;

    /** A typing signal is shown for this many seconds after the last key. */
    public const TYPING_SECONDS = 6;

    /** Do not rewrite the cache more often than this per user. */
    private const TOUCH_EVERY = 30;

    public function touch(int $userId): void
    {
        $now = time();
        $last = (int) Cache::get($this->seenKey($userId), 0);
        if ($now - $last >= self::TOUCH_EVERY) {
            Cache::put($this->seenKey($userId), $now, now()->addDay());
        }
    }

    public function lastSeen(int $userId): ?int
    {
        $seen = Cache::get($this->seenKey($userId));

        return $seen ? (int) $seen : null;
    }

    public function isOnline(int $userId): bool
    {
        $seen = $this->lastSeen($userId);

        return $seen !== null && time() - $seen <= self::ONLINE_SECONDS;
    }

    public function typing(int $conversationId, int $userId): void
    {
        Cache::put($this->typingKey($conversationId, $userId), true, self::TYPING_SECONDS);
    }

    public function stopTyping(int $conversationId, int $userId): void
    {
        Cache::forget($this->typingKey($conversationId, $userId));
    }

    public function isTyping(int $conversationId, int $userId): bool
    {
        return (bool) Cache::get($this->typingKey($conversationId, $userId));
    }

    /** @return array{online: bool, lastSeen: ?string} */
    public function summary(int $userId): array
    {
        $seen = $this->lastSeen($userId);

        return [
            'online' => $this->isOnline($userId),
            'lastSeen' => $seen ? gmdate('Y-m-d\TH:i:s\Z', $seen) : null,
        ];
    }

    private function seenKey(int $userId): string
    {
        return "presence:seen:{$userId}";
    }

    private function typingKey(int $conversationId, int $userId): string
    {
        return "presence:typing:{$conversationId}:{$userId}";
    }
}

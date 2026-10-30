<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatReaction extends Model
{
    /** The quick reactions shown first in the chat. */
    public const EMOJI = ['❤️', '😂', '😮', '😢', '👍', '🔥'];

    /** Any single emoji is accepted (picked from the full list); plain text never is. */
    public static function isEmoji(string $value): bool
    {
        return mb_strlen($value) <= 16
            && preg_match('/^(?:\p{So}|\p{Sk}|\x{200D}|\x{FE0F}|\x{20E3}|[0-9#*])+$/u', $value) === 1
            && preg_match('/\p{So}|\x{20E3}/u', $value) === 1;
    }

    protected $fillable = ['chat_message_id', 'user_id', 'emoji'];
}

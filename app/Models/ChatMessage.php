<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['chat_conversation_id', 'sender_id', 'client_uuid', 'body', 'reply_to_id', 'photo_path', 'photo_thumb_path', 'photo_width', 'photo_height', 'sticker', 'audio_path', 'audio_mime', 'audio_seconds'];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'edited_at' => 'datetime', 'deleted_at' => 'datetime', 'pinned_at' => 'datetime'];
    }

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** @return BelongsTo<ChatMessage, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /** @return HasMany<ChatReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(ChatReaction::class);
    }
}

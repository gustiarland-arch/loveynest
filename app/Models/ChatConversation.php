<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['couple_space_id'])]
class ChatConversation extends Model
{
    /** @return BelongsTo<CoupleSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(CoupleSpace::class, 'couple_space_id');
    }

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /** @return HasMany<ChatReadState, $this> */
    public function readStates(): HasMany
    {
        return $this->hasMany(ChatReadState::class);
    }
}

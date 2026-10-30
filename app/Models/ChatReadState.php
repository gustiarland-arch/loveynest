<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatReadState extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    public const CREATED_AT = null;

    protected $fillable = ['chat_conversation_id', 'user_id', 'last_read_message_id'];
}

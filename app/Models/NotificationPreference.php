<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    public const TYPES = ['chat_message', 'event_reminder', 'task_reminder', 'partner_activity', 'couple_update'];

    protected $fillable = ['user_id', 'type', 'in_app', 'push', 'show_preview'];

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'push' => 'boolean',
            'show_preview' => 'boolean',
        ];
    }
}

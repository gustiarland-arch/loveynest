<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

/** Database notification with millisecond timestamps, so newest-first is exact. */
class AppNotification extends DatabaseNotification
{
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** What the UI needs; the stored URL is only used server-side by "open". */
    public function toView(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->data['type'] ?? $this->type,
            'title' => $this->data['title'] ?? '',
            'body' => $this->data['body'] ?? '',
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}

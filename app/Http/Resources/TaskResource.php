<?php

namespace App\Http\Resources;

use App\Models\Task;
use App\Support\LocalDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $due = LocalDateTime::fromUtc($this->due_at, $this->due_timezone ?? $request->user()->timezone, $this->due_has_time);

        return [
            'id' => $this->id,
            'space' => $this->scope->space(),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_at' => $this->due_at?->toIso8601String(),
            'due_has_time' => $this->due_has_time,
            'due_date' => $due['date'],
            'due_time' => $due['time'],
            'overdue' => $this->isOverdue(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            // Who did what only matters, and is only shown, in a shared space.
            'created_by' => $this->isShared() ? $this->creator?->firstName() : null,
            'category' => $this->category,
            'repeat' => $this->repeat,
            'subtasks' => $this->relationLoaded('subtasks') ? $this->subtasks->map(fn ($s) => ['id' => $s->id, 'title' => $s->title, 'done' => $s->done])->values()->all() : [],
            'assignee' => $this->isShared() && $this->assignee_id && $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->firstName(),
                'avatarUrl' => $this->assignee->avatarUrl(), 'avatarFrame' => $this->assignee->avatar_frame,
            ] : null,
            'completed_by' => $this->isShared() && $this->completed_by ? $this->completer?->firstName() : null,
        ];
    }
}

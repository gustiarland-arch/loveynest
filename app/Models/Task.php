<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSpace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use BelongsToSpace;

    public const STATUSES = ['todo', 'in_progress', 'done'];

    public const PRIORITIES = ['low', 'normal', 'high'];

    protected $fillable = ['title', 'description', 'status', 'priority', 'due_at', 'due_has_time', 'due_timezone', 'assignee_id', 'category', 'repeat'];

    protected $attributes = ['status' => 'todo', 'priority' => 'normal', 'due_has_time' => false];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'due_has_time' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public const REPEATS = ['none', 'daily', 'weekly', 'monthly'];

    /** @return HasMany<TaskSubtask, $this> */
    public function subtasks(): HasMany
    {
        return $this->hasMany(TaskSubtask::class)->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone() && $this->due_at !== null && $this->due_at->isPast();
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', 'done');
    }

    /** Open tasks: dated first (soonest first), then by priority, newest last. */
    public function scopeAgendaOrder(Builder $query): void
    {
        $query->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderByRaw("FIELD(priority, 'high', 'normal', 'low')")
            ->orderByDesc('id');
    }
}

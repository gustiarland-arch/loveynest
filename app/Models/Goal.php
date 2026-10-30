<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSpace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    use BelongsToSpace;

    public const STATUSES = ['planned', 'active', 'done', 'cancelled'];

    protected $fillable = ['title', 'note', 'category', 'status', 'target_date', 'progress'];

    protected function casts(): array
    {
        return ['target_date' => 'date'];
    }

    /** @return HasMany<GoalEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(GoalEvent::class)->orderByDesc('id');
    }
}

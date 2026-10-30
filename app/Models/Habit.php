<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSpace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habit extends Model
{
    use BelongsToSpace;

    public const PERIODS = ['daily', 'weekly', 'monthly'];

    protected $fillable = ['title', 'period', 'target'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    /** @return HasMany<HabitCheckin, $this> */
    public function checkins(): HasMany
    {
        return $this->hasMany(HabitCheckin::class);
    }
}

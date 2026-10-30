<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mood extends Model
{
    public const LEVELS = [1, 2, 3, 4, 5];

    protected $fillable = ['user_id', 'mood_date', 'level', 'note', 'share', 'wants_company'];

    public const SHARES = ['full', 'level', 'private'];

    protected function casts(): array
    {
        return ['mood_date' => 'date:Y-m-d', 'level' => 'integer', 'wants_company' => 'boolean'];
    }

    /** What the partner is allowed to see of this entry, or null when it is private. */
    public function forPartner(): ?array
    {
        if ($this->share === 'private') {
            return null;
        }

        return [
            'date' => $this->mood_date->toDateString(),
            'level' => $this->level,
            'note' => $this->share === 'full' ? $this->note : null,
            'company' => $this->wants_company,
        ];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSpace;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use BelongsToSpace;

    public const KINDS = ['journal', 'letter'];

    protected $fillable = ['title', 'body', 'entry_date'];

    protected $attributes = ['kind' => 'journal'];

    protected function casts(): array
    {
        return ['entry_date' => 'date:Y-m-d', 'read_at' => 'datetime'];
    }

    public function isLetter(): bool
    {
        return $this->kind === 'letter';
    }
}

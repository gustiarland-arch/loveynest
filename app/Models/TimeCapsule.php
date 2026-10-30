<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A note sealed until a date. The body is never sent to the browser before then. */
class TimeCapsule extends Model
{
    protected $fillable = ['title', 'body', 'open_on'];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['open_on' => 'date:Y-m-d'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoboothShot extends Model
{
    protected $fillable = ['position', 'user_id', 'path'];

    /** @return BelongsTo<PhotoboothSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PhotoboothSession::class, 'photobooth_session_id');
    }
}

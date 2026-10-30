<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Access always follows the album: there is no photo-level ownership. */
class Photo extends Model
{
    protected $fillable = ['album_id', 'uploaded_by', 'path', 'thumb_path', 'width', 'height', 'caption', 'location', 'taken_on', 'is_favorite', 'event_id'];

    protected function casts(): array
    {
        return ['taken_on' => 'date:Y-m-d', 'is_favorite' => 'boolean'];
    }

    /** @return BelongsTo<Album, $this> */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

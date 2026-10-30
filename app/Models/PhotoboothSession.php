<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Square shots, as many as the layout holds (2 to 6). In "turns" mode the starter
 * takes the 1st, 3rd, 5th shot and the partner the 2nd, 4th, 6th; in "solo" mode one phone takes all.
 * The shots are stored as taken; filters and the finished picture are made in the browser.
 */
class PhotoboothSession extends Model
{
    /** Layout key => number of shots. Keep in step with LAYOUTS in resources/js/lib/booth/themes.js. */
    public const LAYOUTS = ['strip' => 4, 'trio' => 3, 'duo' => 2, 'grid' => 4, 'collage' => 6];

    public const MODES = ['turns', 'solo'];

    /** Theme keys. Keep in step with THEMES in resources/js/lib/booth/themes.js. */
    public const THEMES = ['korean-booth', 'pink-romance', 'lavender-dream', 'y2k-retro', 'vintage-film', 'polaroid', 'cute-couple', 'date-night', 'anniversary', 'mono', 'scrapbook'];

    protected $fillable = ['mode', 'layout', 'theme'];

    public function shotCount(): int
    {
        return self::LAYOUTS[$this->layout] ?? 4;
    }

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'saved_at' => 'datetime'];
    }

    /** @return HasMany<PhotoboothShot, $this> */
    public function shots(): HasMany
    {
        return $this->hasMany(PhotoboothShot::class)->orderBy('position');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Photo, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    /** Whose turn a position is: the starter on even positions, the other member on odd ones. */
    public function ownerOf(int $position, ?int $secondId): ?int
    {
        return $this->mode === 'solo' || $position % 2 === 0 ? $this->created_by : $secondId;
    }

    /** The member who did not start the round, seen from either phone. */
    public function secondId(User $viewer): ?int
    {
        return $this->created_by === $viewer->id ? $viewer->partner()?->id : $viewer->id;
    }
}

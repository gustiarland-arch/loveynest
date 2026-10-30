<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** A date the couple counts toward (birthday, trip, anniversary). Always shared. */
class Milestone extends Model
{
    protected $fillable = ['title', 'event_date', 'repeat_yearly'];

    protected function casts(): array
    {
        return ['event_date' => 'date:Y-m-d', 'repeat_yearly' => 'boolean'];
    }

    /** The next date to count down to: this year's or next year's for yearly ones. */
    public function nextDate(CarbonImmutable $today): ?CarbonImmutable
    {
        $date = CarbonImmutable::parse($this->event_date->toDateString());
        if (! $this->repeat_yearly) {
            return $date->gte($today) ? $date : null;
        }

        $next = $date->setYear($today->year);

        // 29 Feb falls back to 28 Feb in common years (setYear already clamps).
        return $next->lt($today) ? $date->setYear($today->year + 1) : $next;
    }
}

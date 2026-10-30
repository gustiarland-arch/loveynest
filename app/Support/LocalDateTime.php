<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Converts the date and time a person typed (in their zone) to UTC for storage.
 */
class LocalDateTime
{
    public static function toUtc(string $date, ?string $time, string $timezone, bool $endOfDay = false): CarbonImmutable
    {
        $local = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.($time ? $time.':00' : ($endOfDay ? '23:59:59' : '00:00:00')), $timezone);

        return $local->utc();
    }

    /** @return array{date: string, time: ?string} */
    public static function fromUtc(?\DateTimeInterface $utc, string $timezone, bool $withTime = true): array
    {
        if (! $utc) {
            return ['date' => '', 'time' => null];
        }
        $local = CarbonImmutable::instance($utc)->setTimezone($timezone);

        return ['date' => $local->format('Y-m-d'), 'time' => $withTime ? $local->format('H:i') : null];
    }
}

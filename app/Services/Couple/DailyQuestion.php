<?php

namespace App\Services\Couple;

use Carbon\CarbonImmutable;

/** One question per calendar day, the same for both of them. */
class DailyQuestion
{
    public function indexFor(CarbonImmutable $date): int
    {
        $count = count(trans('questions.list', [], 'id'));

        return (int) floor($date->setTimezone('UTC')->startOfDay()->timestamp / 86400) % max(1, $count);
    }

    public function text(int $index): string
    {
        $list = trans('questions.list', [], 'id');

        return $list[$index % max(1, count($list))] ?? '';
    }
}

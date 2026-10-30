<?php

namespace App\Services\Growth;

use App\Models\Habit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Progress for a habit in its current period, plus a streak that forgives:
 * one missed day (or period) never resets it. Nothing here punishes a gap.
 */
class HabitStats
{
    public function periodStart(string $period, CarbonImmutable $today): CarbonImmutable
    {
        return match ($period) {
            'weekly' => $today->startOfWeek(),
            'monthly' => $today->startOfMonth(),
            default => $today,
        };
    }

    /**
     * @param  Collection<int, string>  $dates  Y-m-d check-in dates of one person for one habit
     * @return array{count: int, today: bool, week: list<bool>, streak: int}
     */
    public function forPerson(Habit $habit, Collection $dates, CarbonImmutable $today): array
    {
        $set = $dates->flip();
        $start = $this->periodStart($habit->period, $today);
        $count = $dates->filter(fn ($d) => $d >= $start->toDateString() && $d <= $today->toDateString())->count();

        $week = [];
        for ($i = 6; $i >= 0; $i--) {
            $week[] = $set->has($today->subDays($i)->toDateString());
        }

        return [
            'count' => $count,
            'today' => $set->has($today->toDateString()),
            'week' => $week,
            'streak' => $this->streak($habit, $set, $today),
        ];
    }

    /** Consecutive met periods, ending now; a single missed period is forgiven. */
    private function streak(Habit $habit, Collection $set, CarbonImmutable $today): int
    {
        $streak = 0;
        $misses = 0;
        $cursor = $this->periodStart($habit->period, $today);
        // Look back at most a year; the current period is only counted when it is already met.
        for ($i = 0; $i < 370; $i++) {
            $end = match ($habit->period) {
                'weekly' => $cursor->endOfWeek(),
                'monthly' => $cursor->endOfMonth(),
                default => $cursor,
            };
            $n = 0;
            for ($d = $cursor; $d->lte($end) && $d->lte($today); $d = $d->addDay()) {
                $n += $set->has($d->toDateString()) ? 1 : 0;
            }
            $met = $n >= ($habit->period === 'daily' ? 1 : $habit->target);

            if ($met) {
                $streak++;
                $misses = 0;
            } elseif ($i > 0) {
                // The running period may still be completed; earlier ones forgive one miss.
                if (++$misses > 1) {
                    break;
                }
            }
            $cursor = match ($habit->period) {
                'weekly' => $cursor->subWeek(),
                'monthly' => $cursor->subMonthNoOverflow()->startOfMonth(),
                default => $cursor->subDay(),
            };
        }

        return $streak;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Digest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class SendDigests extends Command
{
    protected $signature = 'digest:send';

    protected $description = 'Kirim ringkasan harian atau mingguan bagi yang mengaktifkannya (dijalankan scheduler tiap jam)';

    public function handle(): int
    {
        $sent = 0;
        User::where('digest', '!=', 'off')->orderBy('id')->chunkById(100, function ($users) use (&$sent) {
            foreach ($users as $user) {
                $now = CarbonImmutable::now($user->timezone);
                $weekly = $user->digest === 'weekly';
                if ($now->hour < 8 || ($weekly && ! $now->isMonday()) || $user->digest_sent_on?->toDateString() === $now->toDateString()) {
                    continue;
                }
                // Claim the day first so overlapping runs never send twice.
                if (User::whereKey($user->id)->where(fn ($q) => $q->whereNull('digest_sent_on')->orWhere('digest_sent_on', '!=', $now->toDateString()))->update(['digest_sent_on' => $now->toDateString()]) === 0) {
                    continue;
                }
                $end = ($weekly ? $now->addDays(6) : $now)->endOfDay()->utc();
                $tasks = Task::query()->visibleTo($user)->open()->whereNotNull('due_at')->where('due_at', '<=', $end)->count();
                $events = Event::query()->visibleTo($user)->overlapping($now->startOfDay()->utc(), $end)->count();
                if ($tasks + $events === 0) {
                    continue;
                }
                try {
                    $user->notify(new Digest($tasks, $events, $weekly));
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
        $this->info("Ringkasan terkirim: {$sent}");

        return self::SUCCESS;
    }
}

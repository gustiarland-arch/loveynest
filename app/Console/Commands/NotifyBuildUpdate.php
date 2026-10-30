<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AppUpdated;
use App\Support\BuildId;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class NotifyBuildUpdate extends Command
{
    protected $signature = 'usandus:kabari-build';

    protected $description = 'Kabari semua orang sekali saja saat versi web baru terpasang di server (dijalankan scheduler tiap 5 menit)';

    public function handle(): int
    {
        $now = BuildId::current();
        $seen = Cache::get('usandus.build.seen');
        if ($seen === $now) {
            return self::SUCCESS;
        }
        Cache::forever('usandus.build.seen', $now);
        // The first run only learns which build is installed; nobody is told about it.
        if ($seen === null || $now === 'dev') {
            return self::SUCCESS;
        }

        $sent = 0;
        User::query()->whereNotNull('profile_completed_at')->orderBy('id')->chunkById(100, function ($users) use (&$sent) {
            foreach ($users as $user) {
                try {
                    $user->notify(new AppUpdated);
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                }
            }
        });
        $this->info("Pembaruan dikabarkan ke {$sent} orang.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\CoupleSpace;
use App\Services\Ai\QuoteWriter;
use Illuminate\Console\Command;

class GenerateDailyQuotes extends Command
{
    protected $signature = 'usandus:kutipan';

    protected $description = 'Tulis kutipan hari ini dengan AI untuk tiap pasangan yang belum punya. Tanpa kunci AI, kutipan bawaan dipakai.';

    public function handle(QuoteWriter $quotes): int
    {
        $done = 0;
        CoupleSpace::query()->where('status', 'active')->each(function (CoupleSpace $space) use ($quotes, &$done) {
            $user = $space->activeMembers()->first();
            if ($user && $quotes->ensure($user)) {
                $done++;
            }
        });
        $this->info("{$done} kutipan siap.");

        return self::SUCCESS;
    }
}

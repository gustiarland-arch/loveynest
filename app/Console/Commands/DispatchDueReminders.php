<?php

namespace App\Console\Commands;

use App\Services\Reminders\ReminderDispatcher;
use Illuminate\Console\Command;

class DispatchDueReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Kirim pengingat acara dan tugas yang sudah jatuh tempo (dijalankan scheduler tiap menit)';

    public function handle(ReminderDispatcher $dispatcher): int
    {
        $stats = $dispatcher->dispatch();
        $this->line(sprintf('terkirim=%d dilewati=%d gagal=%d dipulihkan=%d', $stats['sent'], $stats['skipped'], $stats['failed'], $stats['recovered']));

        return self::SUCCESS;
    }
}

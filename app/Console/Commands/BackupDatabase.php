<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupException;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'usandus:backup
        {--list : Tampilkan daftar backup yang ada}
        {--verify= : Periksa checksum sebuah file backup tanpa memulihkannya}';

    protected $description = 'Buat backup database (SQL terkompresi + checksum) di storage/app/private/backups';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            if ($this->option('list')) {
                $rows = array_map(fn ($b) => [$b['file'], number_format($b['size'] / 1024, 1).' KB', $b['created_at']], $backup->list());
                $rows ? $this->table(['File', 'Ukuran', 'Waktu'], $rows) : $this->line('Belum ada backup.');

                return self::SUCCESS;
            }

            if ($file = $this->option('verify')) {
                $manifest = $backup->verify($backup->resolve($file));
                $this->info("Backup utuh: {$manifest['file']} (".count($manifest['tables']).' tabel, dibuat '.$manifest['created_at'].').');

                return self::SUCCESS;
            }

            $result = $backup->create();
            $this->info('Backup selesai: '.$result['file']);
            $this->line(sprintf('%d tabel, %s KB, sha256 %s', $result['tables'], number_format($result['size'] / 1024, 1), substr($result['sha256'], 0, 16).'…'));

            return self::SUCCESS;
        } catch (BackupException $e) {
            $this->error('Backup gagal: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

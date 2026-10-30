<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupException;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Command;

/**
 * Restores a backup over the current database. A safety backup of the
 * current state is taken first, so a wrong choice can be undone.
 */
class RestoreDatabase extends Command
{
    protected $signature = 'usandus:restore
        {file : Nama file backup (lihat: php artisan usandus:backup --list)}
        {--force : Jangan minta konfirmasi}
        {--no-safety-backup : Lewati backup pengaman sebelum memulihkan}';

    protected $description = 'Pulihkan database dari file backup (data saat ini akan diganti)';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $path = $backup->resolve($this->argument('file'));
            $manifest = $backup->verify($path);
            $this->line("Backup: {$manifest['file']} · dibuat {$manifest['created_at']} · versi aplikasi {$manifest['app_version']}");

            if (! $this->option('force') && ! $this->confirm('Semua data saat ini di database "'.$manifest['database'].'" akan diganti isi backup ini. Lanjutkan?')) {
                $this->line('Dibatalkan.');

                return self::FAILURE;
            }

            if (! $this->option('no-safety-backup')) {
                $safety = $backup->create('sebelum-restore');
                $this->line('Backup pengaman dibuat: '.basename($safety['file']));
            }

            $backup->restore($path);
            // A backup from an older version may lack newer tables or columns.
            $this->call('migrate', ['--force' => true]);
            $this->info('Database berhasil dipulihkan.');

            return self::SUCCESS;
        } catch (BackupException $e) {
            $this->error('Restore gagal: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

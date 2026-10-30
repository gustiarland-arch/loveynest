<?php

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Database backup and restore with the MySQL/MariaDB client tools that ship
 * with XAMPP. Each backup is a gzipped SQL dump plus a small JSON manifest
 * (checksum, table list) used to verify it before any restore.
 *
 * Credentials go through a temporary option file, never the command line,
 * so they do not show up in the process list.
 */
class DatabaseBackup
{
    private ?string $credentialsPath = null;

    public function directory(): string
    {
        return config('usandus.backup.path');
    }

    /** @return array{file: string, size: int, tables: int, sha256: string} */
    public function create(string $label = 'manual'): array
    {
        File::ensureDirectoryExists($this->directory(), 0700);
        $name = sprintf('usandus-%s-%s', Carbon::now(config('usandus.default_timezone'))->format('Ymd-His'), preg_replace('/[^a-z0-9-]/', '', $label) ?: 'manual');
        $sqlPath = $this->directory()."/{$name}.sql";
        $gzPath = "{$sqlPath}.gz";

        try {
            $this->run([
                config('usandus.backup.mysqldump'),
                '--defaults-extra-file='.$this->credentialsFile(),
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--result-file='.$sqlPath,
                $this->connection()['database'],
            ]);

            $this->assertCompleteDump($sqlPath);
            $tables = $this->tablesInDump($sqlPath);
            $expected = $this->currentTables();
            if ($missing = array_diff($expected, $tables)) {
                throw new BackupException('Backup tidak lengkap, tabel hilang: '.implode(', ', $missing));
            }

            $this->gzip($sqlPath, $gzPath);
            $manifest = [
                'file' => basename($gzPath),
                'created_at' => Carbon::now()->toIso8601String(),
                'database' => $this->connection()['database'],
                'app_version' => config('usandus.version'),
                'tables' => $tables,
                'size' => filesize($gzPath),
                'sha256' => hash_file('sha256', $gzPath),
            ];
            File::put($this->manifestPath($gzPath), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            chmod($this->manifestPath($gzPath), 0600);
        } catch (Throwable $e) {
            File::delete([$sqlPath, $gzPath, $this->manifestPath($gzPath)]);
            Log::error('backup.failed', ['error' => $e->getMessage()]);
            throw $e instanceof BackupException ? $e : new BackupException($e->getMessage(), previous: $e);
        } finally {
            File::delete($sqlPath);
        }

        Log::channel('audit')->info('backup.created', ['file' => $manifest['file'], 'size' => $manifest['size']]);
        $this->prune();

        return ['file' => $gzPath, 'size' => $manifest['size'], 'tables' => count($tables), 'sha256' => $manifest['sha256']];
    }

    /**
     * Checks a backup without touching the database.
     *
     * @return array<string, mixed> the manifest
     */
    public function verify(string $gzPath): array
    {
        $manifestPath = $this->manifestPath($gzPath);
        if (! is_file($gzPath) || ! is_file($manifestPath)) {
            throw new BackupException('File backup atau manifest-nya tidak ditemukan.');
        }
        $manifest = json_decode(File::get($manifestPath), true);
        if (! is_array($manifest) || ! hash_equals((string) ($manifest['sha256'] ?? ''), hash_file('sha256', $gzPath))) {
            throw new BackupException('Checksum tidak cocok: file backup rusak atau sudah diubah.');
        }

        return $manifest;
    }

    /** Replaces the current database with the backup's contents. */
    public function restore(string $gzPath): void
    {
        $this->verify($gzPath);
        $sqlPath = tempnam(sys_get_temp_dir(), 'usandus-restore-');

        try {
            $this->gunzip($gzPath, $sqlPath);
            $this->assertCompleteDump($sqlPath);

            $input = fopen($sqlPath, 'rb');
            $this->run([
                config('usandus.backup.mysql'),
                '--defaults-extra-file='.$this->credentialsFile(),
                '--default-character-set=utf8mb4',
                $this->connection()['database'],
            ], $input);
            fclose($input);
        } finally {
            File::delete($sqlPath);
        }

        DB::reconnect();
        Log::channel('audit')->info('backup.restored', ['file' => basename($gzPath)]);
    }

    /** @return list<array{file: string, path: string, size: int, created_at: string}> newest first */
    public function list(): array
    {
        return collect(File::glob($this->directory().'/usandus-*.sql.gz'))
            ->sortDesc()
            ->map(fn (string $path) => [
                'file' => basename($path),
                'path' => $path,
                'size' => filesize($path),
                'created_at' => Carbon::createFromTimestamp(filemtime($path))->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    public function resolve(string $file): string
    {
        // Only names inside the backup directory; no paths from the outside.
        $path = $this->directory().'/'.basename($file);

        return is_file($path) ? $path : throw new BackupException("Backup {$file} tidak ditemukan di ".$this->directory());
    }

    private function prune(): void
    {
        foreach (array_slice($this->list(), max(1, (int) config('usandus.backup.keep'))) as $old) {
            File::delete([$old['path'], $this->manifestPath($old['path'])]);
        }
    }

    /** @param resource|null $input */
    private function run(array $command, $input = null): void
    {
        $process = new Process($command, base_path(), null, $input, 600);
        try {
            $process->run();
        } catch (Throwable $e) {
            throw new BackupException('Tidak bisa menjalankan '.basename($command[0]).': '.$e->getMessage());
        } finally {
            File::delete($this->credentialsPath ?? '');
            $this->credentialsPath = null;
        }

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: 'kode keluar '.$process->getExitCode();
            throw new BackupException(basename($command[0]).' gagal: '.$error);
        }
    }

    private function credentialsFile(): string
    {
        $c = $this->connection();
        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'usandus-db-');
        chmod($this->credentialsPath, 0600);
        $quote = fn ($v) => '"'.addcslashes((string) $v, '"\\').'"';
        File::put($this->credentialsPath, implode("\n", array_filter([
            '[client]',
            'user='.$quote($c['username']),
            'password='.$quote($c['password'] ?? ''),
            $c['unix_socket'] ?? null ? 'socket='.$quote($c['unix_socket']) : 'host='.$quote($c['host']),
            'port='.(int) $c['port'],
        ]))."\n");

        return $this->credentialsPath;
    }

    /** @return array<string, mixed> */
    private function connection(): array
    {
        return config('database.connections.'.config('database.default'));
    }

    private function assertCompleteDump(string $sqlPath): void
    {
        $handle = fopen($sqlPath, 'rb');
        fseek($handle, -256, SEEK_END);
        $tail = (string) fread($handle, 256);
        fclose($handle);
        if (! str_contains($tail, 'Dump completed')) {
            throw new BackupException('Dump tidak lengkap (penanda "Dump completed" tidak ada).');
        }
    }

    /** @return list<string> */
    private function tablesInDump(string $sqlPath): array
    {
        $tables = [];
        $handle = fopen($sqlPath, 'rb');
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^CREATE TABLE `([^`]+)`/', $line, $m)) {
                $tables[] = $m[1];
            }
        }
        fclose($handle);

        return $tables;
    }

    /** @return list<string> */
    private function currentTables(): array
    {
        return DB::connection()->getSchemaBuilder()->getTableListing($this->connection()['database'], schemaQualified: false);
    }

    private function gzip(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = gzopen($to, 'wb6');
        while (! feof($in)) {
            gzwrite($out, fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
        chmod($to, 0600);
    }

    private function gunzip(string $from, string $to): void
    {
        $in = gzopen($from, 'rb');
        $out = fopen($to, 'wb');
        while (! gzeof($in)) {
            fwrite($out, gzread($in, 1 << 20));
        }
        gzclose($in);
        fclose($out);
    }

    private function manifestPath(string $gzPath): string
    {
        return preg_replace('/\.sql\.gz$/', '.json', $gzPath);
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Turns on live chat: writes the Reverb keys to .env and switches the
 * broadcaster to Reverb. Secrets are never printed.
 */
class GenerateRealtimeKeys extends Command
{
    protected $signature = 'usandus:realtime
        {--force : Ganti kunci yang sudah ada}
        {--off : Matikan chat langsung (kembali ke mode berkala)}';

    protected $description = 'Aktifkan chat realtime (Reverb) dan simpan kuncinya di .env';

    public function handle(): int
    {
        $path = $this->laravel->environmentFilePath();
        if (! is_file($path) || ! is_writable($path)) {
            $this->error('File .env tidak ditemukan atau tidak bisa ditulis: '.$path);

            return self::FAILURE;
        }
        $env = file_get_contents($path);

        if ($this->option('off')) {
            $this->write($env, $path, ['BROADCAST_CONNECTION' => 'log']);
            $this->info('Chat langsung dimatikan. Chat tetap jalan lewat pengecekan berkala.');

            return self::SUCCESS;
        }

        $hasKeys = preg_match('/^REVERB_APP_KEY=\S+/m', $env) && preg_match('/^REVERB_APP_SECRET=\S+/m', $env);
        if ($hasKeys && ! $this->option('force')) {
            $values = ['BROADCAST_CONNECTION' => 'reverb'];
        } else {
            $values = [
                'BROADCAST_CONNECTION' => 'reverb',
                'REVERB_APP_ID' => (string) random_int(100000, 999999),
                'REVERB_APP_KEY' => Str::lower(Str::random(20)),
                'REVERB_APP_SECRET' => Str::lower(Str::random(32)),
            ];
        }
        $values += [
            'REVERB_HOST' => '127.0.0.1',
            'REVERB_PORT' => '8080',
            'REVERB_SCHEME' => 'http',
            'REVERB_SERVER_HOST' => '0.0.0.0',
            'REVERB_SERVER_PORT' => '8080',
        ];

        $this->write($env, $path, $values);
        $this->info('Chat langsung aktif. Jalankan "php artisan reverb:start" di jendela terminal terpisah.');
        $this->line('Jalankan "php artisan config:clear" bila konfigurasi sedang di-cache.');

        return self::SUCCESS;
    }

    /** @param array<string, string> $values */
    private function write(string $env, string $path, array $values): void
    {
        foreach ($values as $name => $value) {
            $env = preg_match("/^{$name}=.*$/m", $env)
                ? preg_replace("/^{$name}=.*$/m", "{$name}={$value}", $env)
                : rtrim($env)."\n{$name}={$value}\n";
        }
        file_put_contents($path, $env);
    }
}

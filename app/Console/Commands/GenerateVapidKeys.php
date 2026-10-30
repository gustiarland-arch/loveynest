<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Creates the VAPID key pair for Web Push and writes it to .env. The private
 * key is never printed unless asked for explicitly.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'usandus:vapid
        {--force : Ganti kunci yang sudah ada (semua perangkat harus mengaktifkan notifikasi ulang)}
        {--show : Tampilkan kunci tanpa menulis ke .env}';

    protected $description = 'Buat pasangan kunci VAPID untuk push notification dan simpan di .env';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        if ($this->option('show')) {
            $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
            $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

            return self::SUCCESS;
        }

        $path = $this->laravel->environmentFilePath();
        if (! is_file($path) || ! is_writable($path)) {
            $this->error('File .env tidak ditemukan atau tidak bisa ditulis: '.$path);

            return self::FAILURE;
        }

        $env = file_get_contents($path);
        if (preg_match('/^VAPID_PRIVATE_KEY=\S+/m', $env) && ! $this->option('force')) {
            $this->warn('Kunci VAPID sudah ada. Pakai --force untuk menggantinya; perangkat yang sudah berlangganan harus mengaktifkan notifikasi lagi.');

            return self::FAILURE;
        }

        foreach (['VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']] as $name => $value) {
            $env = preg_match("/^{$name}=.*$/m", $env)
                ? preg_replace("/^{$name}=.*$/m", "{$name}={$value}", $env)
                : rtrim($env)."\n{$name}={$value}\n";
        }
        file_put_contents($path, $env);

        $this->info('Kunci VAPID tersimpan di .env.');
        $this->line('Jalankan "php artisan config:clear" bila konfigurasi sedang di-cache.');

        return self::SUCCESS;
    }
}

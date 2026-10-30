<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Services\Push\FcmSender;
use App\Services\Push\WebPushSender;
use App\Services\Reminders\ReminderDispatcher;
use Illuminate\Console\Command;

/** Tells you in plain words why a phone is not getting notifications. */
class CheckNotifications extends Command
{
    protected $signature = 'usandus:cek-notifikasi';

    protected $description = 'Periksa setelan server untuk notifikasi di HP (kunci VAPID, HTTPS, antrean, cron)';

    public function handle(): int
    {
        $problems = 0;
        $say = function (bool $ok, string $good, string $bad) use (&$problems) {
            $ok ? $this->line("  [OK]   {$good}") : $this->line("  [GAGAL] {$bad}");
            $problems += $ok ? 0 : 1;
        };

        $this->info('Pemeriksaan notifikasi LoveyNest');
        $say(WebPushSender::isConfigured(), 'Kunci VAPID sudah ada.', 'Kunci VAPID belum ada. Jalankan: php artisan usandus:vapid lalu php artisan config:clear');
        if (FcmSender::isConfigured()) {
            $this->line('  [OK]   Kunci Firebase ada: aplikasi Android bisa menerima notifikasi.');
        } else {
            $this->line('  [INFO] Kunci Firebase belum ada (storage/app/firebase-service-account.json). Aplikasi Android belum menerima notifikasi.');
        }
        $say(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL memakai https.', 'APP_URL harus diawali https:// (notifikasi di HP wajib HTTPS). Ubah di file .env.');

        $queue = (string) config('queue.default');
        if ($queue === 'sync') {
            $this->line('  [OK]   Antrean "sync": notifikasi dikirim langsung saat ada pesan.');
        } else {
            $this->line("  [INFO] Antrean \"{$queue}\": notifikasi menunggu cron berjalan tiap menit. Untuk kiriman langsung, isi QUEUE_CONNECTION=sync di .env.");
        }

        $last = ReminderDispatcher::lastRun();
        $fresh = $last !== null && $last->diffInMinutes(now()) <= 5;
        $say($fresh, 'Cron berjalan (terakhir: '.($last?->toDateTimeString() ?? '-').').', 'Cron belum berjalan. Pengingat acara dan tugas butuh cron tiap menit: php artisan schedule:run');

        $active = PushSubscription::query()->active()->count();
        $say($active > 0, "Ada {$active} perangkat yang sudah mengaktifkan notifikasi.", 'Belum ada perangkat yang aktif. Di HP: Pengaturan > Notifikasi > Aktifkan (iPhone: pasang ke Layar Utama dulu).');

        $this->newLine();
        $this->line($problems === 0 ? 'Semua beres.' : "{$problems} hal perlu diperbaiki.");

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }
}

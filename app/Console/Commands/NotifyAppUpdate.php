<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Services\Push\FcmSender;
use Illuminate\Console\Command;

class NotifyAppUpdate extends Command
{
    protected $signature = 'usandus:kabari-update';

    protected $description = 'Kirim notifikasi "ada versi baru" ke semua HP Android yang memakai aplikasi LoveyNest';

    public function handle(FcmSender $fcm): int
    {
        if (! FcmSender::isConfigured()) {
            $this->error('Kunci Firebase belum ada (storage/app/firebase-service-account.json).');

            return self::FAILURE;
        }

        $name = (string) config('usandus.android.version_name');
        $code = (int) config('usandus.android.version_code');
        // Phones already on this version do not need telling.
        $devices = PushSubscription::query()->active()->where('content_encoding', FcmSender::ENCODING)->get()->filter(fn ($row) => (int) $row->public_key < $code)->values();

        $result = $fcm->send($devices, [
            'title' => 'LoveyNest versi baru',
            'body' => "Versi {$name} sudah tersedia. Buka aplikasi lalu ketuk Perbarui.",
            'url' => '/dashboard',
            'tag' => 'app-update',
            'kind' => 'update',
        ], 7 * 86400);

        $this->info("Terkirim ke {$result['sent']} perangkat ({$result['failed']} gagal, {$result['expired']} kedaluwarsa).");

        return self::SUCCESS;
    }
}

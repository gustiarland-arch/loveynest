<?php

namespace App\Console\Commands;

use App\Services\Photobooth\PhotoboothService;
use Illuminate\Console\Command;

class PrunePhotobooth extends Command
{
    protected $signature = 'photobooth:prune';

    protected $description = 'Hapus foto mentah photobooth yang sudah lewat sehari setelah disimpan atau terbengkalai tiga hari. Foto di album tidak disentuh.';

    public function handle(PhotoboothService $booth): int
    {
        $count = $booth->prune();
        $this->info("{$count} sesi photobooth dibersihkan.");

        return self::SUCCESS;
    }
}

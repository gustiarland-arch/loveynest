<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/** One command that checks the hosting for everything LoveyNest needs, in plain words. */
class CheckServer extends Command
{
    protected $signature = 'usandus:cek-server';

    protected $description = 'Periksa hosting: versi PHP, ekstensi, batas unggah, folder, database, dan koneksi keluar';

    public function handle(): int
    {
        $problems = 0;
        $say = function (bool $ok, string $good, string $bad, bool $fatal = true) use (&$problems) {
            if ($ok) {
                $this->line("  [OK]    {$good}");
            } else {
                $this->line('  ['.($fatal ? 'GAGAL' : 'CATATAN')."] {$bad}");
                $problems += $fatal ? 1 : 0;
            }
        };
        $bytes = function (string $v): int {
            $n = (int) $v;
            return match (strtoupper(substr(trim($v), -1))) {
                'G' => $n * 1073741824, 'M' => $n * 1048576, 'K' => $n * 1024, default => $n,
            };
        };

        $this->info('Pemeriksaan server LoveyNest');
        $say(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP '.PHP_VERSION, 'PHP '.PHP_VERSION.' terlalu lama; butuh 8.3 atau lebih baru (cPanel > Select PHP Version).');
        foreach (['mbstring', 'gd', 'openssl', 'curl', 'pdo_mysql', 'xml', 'ctype', 'json', 'tokenizer', 'gmp'] as $ext) {
            $say(extension_loaded($ext), "Ekstensi {$ext} aktif.", "Ekstensi {$ext} belum aktif (cPanel > Select PHP Version > Extensions).", $ext !== 'gmp');
        }
        $say(extension_loaded('fileinfo'), 'Ekstensi fileinfo aktif.', 'fileinfo mati; aplikasi memakai pembaca cadangan, unggahan tetap jalan.', false);
        $say(extension_loaded('exif'), 'Ekstensi exif aktif.', 'Ekstensi exif belum aktif; foto HP bisa tersimpan miring (cPanel > Select PHP Version > Extensions).', false);

        $upload = $bytes((string) ini_get('upload_max_filesize'));
        $post = $bytes((string) ini_get('post_max_size'));
        $say($upload >= 8 * 1048576, 'Batas unggah per file '.ini_get('upload_max_filesize').'.', 'Batas unggah per file hanya '.ini_get('upload_max_filesize').'. Foto besar dikecilkan otomatis, tapi sebaiknya naikkan upload_max_filesize ke 32M (MultiPHP INI Editor).', false);
        $say($post >= 12 * 1048576, 'Batas satu permintaan '.ini_get('post_max_size').'.', 'post_max_size hanya '.ini_get('post_max_size').'. Naikkan ke 40M.', false);
        $say($bytes((string) ini_get('memory_limit')) >= 128 * 1048576 || (int) ini_get('memory_limit') === -1, 'memory_limit '.ini_get('memory_limit').'.', 'memory_limit '.ini_get('memory_limit').' kecil untuk foto; naikkan ke 256M.', false);

        foreach (['storage/app/private', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $path = base_path($dir);
            $say(is_dir($path) && is_writable($path), "Folder {$dir} bisa ditulis.", "Folder {$dir} tidak bisa ditulis. Ubah izinnya ke 775 di File Manager.");
        }

        try {
            DB::select('select 1');
            $say(true, 'Database tersambung.', '');
            $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))->keys()
                ->diff(DB::table('migrations')->pluck('migration'))->count();
            $say($pending === 0, 'Semua migrasi sudah dijalankan.', "{$pending} migrasi belum dijalankan. Jalankan: php artisan migrate --force");
        } catch (Throwable $e) {
            $say(false, '', 'Database tidak tersambung: '.mb_substr($e->getMessage(), 0, 120));
        }

        // A half-copied build is the usual cause of a blank screen after an update: the manifest names files that are not there.
        $manifest = public_path('build/manifest.json');
        if (is_file($manifest)) {
            $entries = json_decode((string) file_get_contents($manifest), true) ?: [];
            $files = [];
            foreach ($entries as $entry) {
                foreach (array_merge([$entry['file'] ?? null], $entry['css'] ?? [], $entry['assets'] ?? []) as $file) {
                    if ($file) {
                        $files[$file] = true;
                    }
                }
            }
            $missing = array_values(array_filter(array_keys($files), fn ($f) => ! is_file(public_path('build/'.$f))));
            $say($missing === [], 'Semua '.count($files).' file tampilan (public/build) lengkap.', count($missing).' file tampilan hilang di public/build, mis. '.($missing[0] ?? '').'. Unggah ulang seluruh folder public/build.');
        } else {
            $say(false, '', 'File public/build/manifest.json tidak ada. Unggah folder public/build.');
        }
        $say(! is_file(public_path('hot')), 'Tidak ada file public/hot.', 'File public/hot ada dan membuat aplikasi mencari server pengembangan (layar kosong). Hapus file public/hot.');

        // The domain may be served from another folder (cPanel's public_html) that holds its own copy of the build.
        $served = base_path('../public_html/build/manifest.json');
        if (is_file($served) && is_file($manifest)) {
            $say(md5_file($served) === md5_file($manifest), 'public_html/build sama dengan public/build.', 'public_html/build berbeda dari public/build. Salin ulang folder public/build ke public_html/build, kalau tidak layar bisa kosong.');
        }
        $say(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL memakai https.', 'APP_URL harus diawali https:// di file .env.');
        $say(! config('usandus.e2e.enabled') || str_starts_with((string) config('app.url'), 'https://'), config('usandus.e2e.enabled') ? 'Enkripsi end-to-end aktif (butuh https di HP).' : 'Enkripsi end-to-end dimatikan (E2E_ENABLED=false).', 'Enkripsi end-to-end aktif tapi APP_URL bukan https; di http biasa HP tidak bisa mengunci isi, jadi menyimpan akan ditolak.');
        $say(! config('app.debug'), 'APP_DEBUG mati.', 'APP_DEBUG=true di server publik membocorkan data; ubah ke false.', false);

        $this->line('  [CATATAN] Angka PHP di atas dibaca dari Terminal. Batas unggah untuk web diatur di MultiPHP INI Editor dan bisa berbeda.');
        $this->line('  [CATATAN] Pengingat, love note, notifikasi HP, dan ringkasan butuh Cron Jobs tiap menit:');
        $this->line('            * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');

        $this->newLine();
        $this->line('Koneksi keluar (dibutuhkan kuis AI dan notifikasi):');
        foreach (['https://api.groq.com/openai/v1/models' => 'Groq (kuis AI)', 'https://generativelanguage.googleapis.com/' => 'Gemini (kuis AI)', 'https://fcm.googleapis.com/' => 'Google (notifikasi Android)', 'https://web.push.apple.com/' => 'Apple (notifikasi iPhone)'] as $url => $label) {
            try {
                $r = Http::timeout(8)->get($url);
                $say(true, "{$label}: tersambung (kode {$r->status()}).", '');
            } catch (\Illuminate\Http\Client\RequestException $e) {
                $say(true, "{$label}: tersambung (kode {$e->response->status()}).", '');
            } catch (Throwable $e) {
                $say(false, '', "{$label}: tidak tersambung (".mb_substr($e->getMessage(), 0, 80).'). Hosting mungkin memblokir koneksi keluar.', false);
            }
        }

        $this->newLine();
        $this->line($problems === 0 ? 'Semua yang wajib sudah beres.' : "{$problems} hal wajib perlu diperbaiki.");

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }
}

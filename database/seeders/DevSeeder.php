<?php

namespace Database\Seeders;

use App\Enums\Scope;
use App\Models\Task;
use App\Models\User;
use App\Services\Calendar\EventService;
use App\Services\Couple\CoupleSpaceService;
use App\Services\Couple\InvitationService;
use App\Services\Tasks\TaskService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DEMO DATA FOR LOCAL DEVELOPMENT ONLY.
 *
 *   php artisan db:seed --class=DevSeeder
 *
 * Creates two linked demo accounts (Alya & Raka) and an unlinked third one
 * (Tamu). Passwords are random and printed once, unless USANDUS_DEMO_PASSWORD
 * is set. Safe to run again: existing demo accounts are reused and their
 * content is only created the first time.
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DevSeeder hanya boleh dijalankan saat APP_ENV=local.');
        }

        $password = env('USANDUS_DEMO_PASSWORD') ?: Str::password(14, symbols: false);

        $alya = $this->user('Alya (demo)', 'alya.demo@usandus.test', $password);
        $raka = $this->user('Raka (demo)', 'raka.demo@usandus.test', $password);
        $this->user('Tamu (demo)', 'tamu.demo@usandus.test', $password);

        if (! $alya->activeSpaceId()) {
            app(CoupleSpaceService::class)->create($alya, 'Rumah Kecil (demo)');
            $code = app(InvitationService::class)->issue($alya->refresh())['code'];
            app(InvitationService::class)->accept($raka, $code);
        }

        if (! Task::where('created_by', $alya->id)->exists()) {
            $this->content($alya->refresh(), $raka->refresh());
        }

        $this->command?->warn('Akun demo (data contoh, bukan untuk produksi):');
        foreach (['alya.demo@usandus.test', 'raka.demo@usandus.test', 'tamu.demo@usandus.test'] as $email) {
            $this->command?->line("  {$email}");
        }
        $this->command?->line('  Password: '.(env('USANDUS_DEMO_PASSWORD') ? '(dari USANDUS_DEMO_PASSWORD)' : $password));
    }

    private function user(string $name, string $email, string $password): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'password' => $password, 'timezone' => 'Asia/Jakarta'])->save();

        return $user;
    }

    private function content(User $alya, User $raka): void
    {
        $tasks = app(TaskService::class);
        $events = app(EventService::class);
        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $at = fn (int $days, string $time) => $today->addDays($days)->setTimeFromTimeString($time)->utc();

        $tasks->create($alya, Scope::Personal, ['title' => 'Contoh: perpanjang SIM', 'priority' => 'high', 'due_at' => $at(3, '23:59:59'), 'due_timezone' => 'Asia/Jakarta']);
        $tasks->create($alya, Scope::Personal, ['title' => 'Contoh: baca 20 halaman buku', 'priority' => 'low']);
        $tasks->create($alya, Scope::Personal, ['title' => 'Contoh: kirim laporan', 'priority' => 'normal', 'due_at' => $at(-1, '17:00'), 'due_has_time' => true, 'due_timezone' => 'Asia/Jakarta']);
        $tasks->create($raka, Scope::Personal, ['title' => 'Contoh: servis motor', 'priority' => 'normal']);

        $tasks->create($alya, Scope::Shared, ['title' => 'Contoh: belanja mingguan', 'priority' => 'normal', 'due_at' => $at(0, '23:59:59'), 'due_timezone' => 'Asia/Jakarta']);
        $tasks->create($raka, Scope::Shared, ['title' => 'Contoh: pesan tiket liburan', 'priority' => 'high', 'due_at' => $at(5, '20:00'), 'due_has_time' => true, 'due_timezone' => 'Asia/Jakarta']);
        $done = $tasks->create($raka, Scope::Shared, ['title' => 'Contoh: bayar tagihan internet', 'priority' => 'normal']);
        $tasks->update($done, $alya, ['status' => 'done']);

        $events->create($alya, Scope::Shared, ['title' => 'Contoh: makan malam berdua', 'starts_at' => $at(2, '19:00'), 'ends_at' => $at(2, '21:00'), 'timezone' => 'Asia/Jakarta', 'reminder_minutes' => 60]);
        $events->create($raka, Scope::Shared, ['title' => 'Contoh: ulang tahun Ibu', 'starts_at' => $at(9, '00:00'), 'ends_at' => $at(9, '23:59:59'), 'all_day' => true, 'timezone' => 'Asia/Jakarta']);
        $events->create($alya, Scope::Personal, ['title' => 'Contoh: yoga pagi', 'starts_at' => $at(1, '06:30'), 'ends_at' => $at(1, '07:30'), 'timezone' => 'Asia/Jakarta', 'reminder_minutes' => 30]);
    }
}

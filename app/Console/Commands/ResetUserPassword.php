<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Local replacement for "forgot password" while no mail server is configured:
 * whoever runs the server sets a temporary password and hands it over in person.
 */
class ResetUserPassword extends Command
{
    protected $signature = 'usandus:reset-password {email : Email akun}';

    protected $description = 'Atur password sementara untuk akun (pengganti lupa password tanpa email)';

    public function handle(): int
    {
        $user = User::where('email', Str::lower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('Akun dengan email tersebut tidak ditemukan.');

            return self::FAILURE;
        }

        $password = Str::password(14, symbols: false);
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

        // Sign the account out everywhere.
        DB::table('sessions')->where('user_id', $user->id)->delete();
        Log::channel('audit')->info('password.reset_by_admin', ['user_id' => $user->id]);

        $this->info("Password sementara untuk {$user->email}:");
        $this->line($password);
        $this->comment('Berikan langsung kepada pemilik akun, lalu minta dia menggantinya setelah masuk.');

        return self::SUCCESS;
    }
}

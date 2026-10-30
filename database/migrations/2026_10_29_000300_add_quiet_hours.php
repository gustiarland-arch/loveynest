<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Push is held back between these two local times. In-app notifications still arrive.
        Schema::table('users', function (Blueprint $table) {
            $table->time('quiet_start')->nullable()->after('birthday');
            $table->time('quiet_end')->nullable()->after('quiet_start');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['quiet_start', 'quiet_end']));
    }
};

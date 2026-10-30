<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('digest', ['off', 'daily', 'weekly'])->default('off')->after('quiet_end');
            $table->date('digest_sent_on')->nullable()->after('digest');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['digest', 'digest_sent_on']));
    }
};

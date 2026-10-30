<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The look is a theme key now (frames, stickers and text are drawn in the browser); the old `frame` colour stays for rows made earlier.
        Schema::table('photobooth_sessions', function (Blueprint $table) {
            $table->string('theme', 24)->default('pink-romance')->after('frame');
        });
    }

    public function down(): void
    {
        Schema::table('photobooth_sessions', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};

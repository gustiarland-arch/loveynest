<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('audio_path')->nullable()->after('sticker');
            $table->string('audio_mime', 40)->nullable()->after('audio_path');
            $table->unsignedSmallInteger('audio_seconds')->nullable()->after('audio_mime');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['audio_path', 'audio_mime', 'audio_seconds']);
        });
    }
};

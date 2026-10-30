<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            // Same private-disk layout as album photos; a message may be a photo with an empty body.
            $table->string('photo_path')->nullable()->after('body');
            $table->string('photo_thumb_path')->nullable()->after('photo_path');
            $table->unsignedSmallInteger('photo_width')->nullable()->after('photo_thumb_path');
            $table->unsignedSmallInteger('photo_height')->nullable()->after('photo_width');
        });

        Schema::table('tasks', function (Blueprint $table) {
            // Only for shared tasks: which member of the couple takes it. Null = both of us.
            $table->foreignId('assignee_id')->nullable()->after('priority')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_id');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'photo_thumb_path', 'photo_width', 'photo_height']);
        });
    }
};

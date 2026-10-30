<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('repeat', ['none', 'weekly', 'monthly', 'yearly'])->default('none')->after('reminder_minutes');
            $table->date('repeat_until')->nullable()->after('repeat');
            $table->enum('category', ['kencan', 'keluarga', 'kerja', 'lainnya'])->nullable()->after('repeat_until');
            // How the date went, filled in afterwards. Only used for shared "kencan" events.
            $table->unsignedTinyInteger('rating')->nullable()->after('category');
            $table->string('rating_note', 200)->nullable()->after('rating');
            $table->index(['repeat', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['repeat', 'starts_at']);
            $table->dropColumn(['repeat', 'repeat_until', 'category', 'rating', 'rating_note']);
        });
    }
};

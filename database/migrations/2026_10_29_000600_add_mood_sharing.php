<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moods', function (Blueprint $table) {
            // What the partner may see of this entry. Existing rows keep today's behaviour ('full').
            $table->enum('share', ['full', 'level', 'private'])->default('full')->after('note');
            $table->boolean('wants_company')->default(false)->after('share');
        });
    }

    public function down(): void
    {
        Schema::table('moods', fn (Blueprint $table) => $table->dropColumn(['share', 'wants_company']));
    }
};

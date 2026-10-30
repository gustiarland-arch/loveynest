<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// Removes the custom-avatar columns (and stored drawings) if an earlier package added them.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'avatar_art_path')) {
            foreach (DB::table('users')->whereNotNull('avatar_art_path')->pluck('avatar_art_path') as $path) {
                Storage::disk('local')->delete($path);
                Storage::disk('public')->delete($path);
            }
        }
        foreach (['avatar_config', 'avatar_art_path', 'avatar_use'] as $col) {
            if (Schema::hasColumn('users', $col)) {
                Schema::table('users', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }

    public function down(): void
    {
    }
};

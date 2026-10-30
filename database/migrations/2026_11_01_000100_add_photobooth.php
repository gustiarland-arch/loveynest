<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A four-shot photobooth round. The finished strip is a normal photo in the shared "Photobooth" album.
        Schema::create('photobooth_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 10)->default('turns');
            $table->string('layout', 10)->default('strip');
            $table->string('frame', 10)->default('blush');
            $table->foreignId('photo_id')->nullable()->constrained('photos')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
            $table->index(['couple_space_id', 'saved_at']);
        });

        Schema::create('photobooth_shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photobooth_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->timestamps();
            $table->unique(['photobooth_session_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photobooth_shots');
        Schema::dropIfExists('photobooth_sessions');
    }
};

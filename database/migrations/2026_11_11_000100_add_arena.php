<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arena_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->string('game', 24);
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained('users')->cascadeOnDelete();
            // pending (invited) -> active (accepted) -> ended; or declined / cancelled
            $table->string('status', 12)->default('pending');
            $table->unsignedInteger('seed');
            $table->text('state')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['couple_space_id', 'status']);
            $table->index(['host_id', 'status']);
            $table->index(['guest_id', 'status']);
        });

        Schema::create('arena_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game', 24);
            $table->unsignedInteger('score');
            $table->unsignedTinyInteger('stars')->default(1);
            $table->boolean('won')->nullable();
            $table->foreignId('arena_session_id')->nullable()->constrained('arena_sessions')->nullOnDelete();
            $table->timestamps();
            $table->index(['couple_space_id', 'game', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_scores');
        Schema::dropIfExists('arena_sessions');
    }
};

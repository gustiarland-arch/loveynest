<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('remind_at');
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'skipped', 'cancelled'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('claimed_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'event_id', 'remind_at']);
            $table->unique(['user_id', 'task_id', 'remind_at']);
            $table->index(['status', 'remind_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE reminders ADD CONSTRAINT reminders_source_check CHECK ((event_id IS NULL) <> (task_id IS NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $this->spaceColumns($table);
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->enum('status', ['todo', 'in_progress', 'done'])->default('todo');
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->dateTime('due_at')->nullable();
            // false: due_at is the end of the due date in the creator's zone.
            $table->boolean('due_has_time')->default(false);
            $table->string('due_timezone', 64)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_user_id', 'status', 'due_at']);
            $table->index(['couple_space_id', 'status', 'due_at']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $this->spaceColumns($table);
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('timezone', 64);
            $table->unsignedSmallInteger('reminder_minutes')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'starts_at']);
            $table->index(['couple_space_id', 'starts_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['tasks', 'events'] as $table) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_scope_check CHECK (
                    (scope = 'personal' AND owner_user_id IS NOT NULL AND couple_space_id IS NULL)
                    OR (scope = 'shared' AND couple_space_id IS NOT NULL AND owner_user_id IS NULL)
                )");
            }
            DB::statement('ALTER TABLE events ADD CONSTRAINT events_range_check CHECK (ends_at IS NULL OR ends_at >= starts_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('tasks');
    }

    private function spaceColumns(Blueprint $table): void
    {
        $table->enum('scope', ['personal', 'shared']);
        $table->foreignId('owner_user_id')->nullable()->constrained('users')->cascadeOnDelete();
        $table->foreignId('couple_space_id')->nullable()->constrained()->cascadeOnDelete();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 40)->nullable()->after('name');
            $table->date('birthday')->nullable()->after('nickname');
            // Dashboard widgets: ordered list of keys and the hidden ones, as JSON.
            $table->json('dashboard_prefs')->nullable()->after('background_veil');
        });

        Schema::table('couple_spaces', function (Blueprint $table) {
            $table->date('met_date')->nullable()->after('anniversary_date');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('sticker');
            $table->timestamp('deleted_at')->nullable()->after('edited_at');
            $table->timestamp('pinned_at')->nullable()->after('deleted_at');
            $table->foreignId('pinned_by')->nullable()->after('pinned_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('chat_read_states', function (Blueprint $table) {
            $table->boolean('muted')->default(false)->after('last_read_message_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->after('priority');
            $table->enum('repeat', ['none', 'daily', 'weekly', 'monthly'])->default('none')->after('category');
        });

        Schema::create('task_subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->boolean('done')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });

        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $this->spaceColumns($table);
            $table->string('title', 80);
            $table->enum('period', ['daily', 'weekly', 'monthly'])->default('daily');
            // How many check-ins fill one period.
            $table->unsignedTinyInteger('target')->default(1);
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'archived_at']);
            $table->index(['couple_space_id', 'archived_at']);
        });

        Schema::create('habit_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('done_on');
            $table->timestamps();

            $table->unique(['habit_id', 'user_id', 'done_on']);
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $this->spaceColumns($table);
            $table->string('title', 120);
            $table->text('note')->nullable();
            $table->string('category', 30)->nullable();
            $table->enum('status', ['planned', 'active', 'done', 'cancelled'])->default('planned');
            $table->date('target_date')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();

            $table->index(['owner_user_id', 'status']);
            $table->index(['couple_space_id', 'status']);
        });

        Schema::create('goal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 12)->nullable();
            $table->string('to_status', 12);
            $table->unsignedTinyInteger('progress');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('date_expenses', function (Blueprint $table) {
            // The payer's share of the cost; the rest is owed by the other person.
            $table->unsignedTinyInteger('payer_share')->default(50)->after('amount');
            $table->dateTime('settled_at')->nullable()->after('spent_on');
            $table->dateTime('voided_at')->nullable()->after('settled_at');
            $table->string('note', 200)->nullable()->after('title');
        });

        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 80);
            $table->enum('kind', ['tabungan', 'liburan', 'lainnya'])->default('tabungan');
            $table->unsignedBigInteger('target_amount');
            $table->unsignedBigInteger('saved_amount')->default(0);
            $table->date('deadline')->nullable();
            $table->timestamps();

            $table->index('couple_space_id');
        });

        Schema::create('challenge_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('challenge_key', 40);
            $table->date('done_on');
            $table->timestamps();

            $table->unique(['couple_space_id', 'user_id', 'challenge_key', 'done_on'], 'challenge_once_a_day');
        });

        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedTinyInteger('feeling');
            $table->string('appreciation', 300)->nullable();
            $table->string('improve', 300)->nullable();
            // Written for yourself until you choose to share it.
            $table->boolean('shared')->default(false);
            $table->timestamps();

            $table->unique(['couple_space_id', 'user_id', 'week_start']);
        });

        Schema::create('love_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('body', 500);
            $table->dateTime('deliver_at');
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['delivered_at', 'deliver_at']);
        });

        Schema::create('dreams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('note')->nullable();
            $table->enum('category', ['tempat', 'makanan', 'film', 'pengalaman', 'liburan', 'belajar', 'lainnya'])->default('pengalaman');
            $table->enum('status', ['wish', 'planned', 'active', 'done', 'cancelled'])->default('wish');
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->date('target_date')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('image_path')->nullable();
            $table->string('image_thumb_path')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['couple_space_id', 'status']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['habits', 'goals'] as $table) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_scope_check CHECK (
                    (scope = 'personal' AND owner_user_id IS NOT NULL AND couple_space_id IS NULL)
                    OR (scope = 'shared' AND couple_space_id IS NOT NULL AND owner_user_id IS NULL)
                )");
            }
        }
    }

    public function down(): void
    {
        foreach (['dreams', 'love_notes', 'checkins', 'challenge_completions', 'savings_goals', 'goal_events', 'goals', 'habit_checkins', 'habits', 'task_subtasks'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('date_expenses', fn (Blueprint $table) => $table->dropColumn(['payer_share', 'settled_at', 'voided_at', 'note']));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn(['category', 'repeat']));
        Schema::table('chat_read_states', fn (Blueprint $table) => $table->dropColumn('muted'));
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_by');
            $table->dropColumn(['edited_at', 'deleted_at', 'pinned_at']);
        });
        Schema::table('couple_spaces', fn (Blueprint $table) => $table->dropColumn('met_date'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['nickname', 'birthday', 'dashboard_prefs']));
    }

    private function spaceColumns(Blueprint $table): void
    {
        $table->enum('scope', ['personal', 'shared']);
        $table->foreignId('owner_user_id')->nullable()->constrained('users')->cascadeOnDelete();
        $table->foreignId('couple_space_id')->nullable()->constrained()->cascadeOnDelete();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    }
};

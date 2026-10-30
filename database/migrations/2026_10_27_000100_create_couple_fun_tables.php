<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couple_spaces', function (Blueprint $table) {
            $table->date('anniversary_date')->nullable();
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 80);
            $table->date('event_date');
            $table->boolean('repeat_yearly')->default(true);
            $table->timestamps();

            $table->index(['couple_space_id', 'event_date']);
        });

        // Wishlist and bucket list: personal (My Space) or shared (Our Space).
        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['personal', 'shared']);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('couple_space_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('kind', ['wishlist', 'bucket']);
            $table->string('title', 120);
            $table->string('note', 300)->nullable();
            $table->dateTime('done_at')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'kind', 'done_at']);
            $table->index(['couple_space_id', 'kind', 'done_at']);
        });

        Schema::create('daily_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('answer_date');
            $table->unsignedSmallInteger('question_index');
            $table->string('answer', 500);
            $table->timestamps();

            $table->unique(['user_id', 'answer_date']);
            $table->index(['couple_space_id', 'answer_date']);
        });

        Schema::create('time_capsules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->date('open_on');
            $table->timestamps();

            $table->index(['couple_space_id', 'open_on']);
        });

        Schema::create('nudges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->timestamps();

            $table->index(['couple_space_id', 'created_at']);
        });

        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question', 120);
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['couple_space_id', 'created_at']);
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained('poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_id', 'user_id']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE list_items ADD CONSTRAINT list_items_scope_check CHECK (
                (scope = 'personal' AND owner_user_id IS NOT NULL AND couple_space_id IS NULL)
                OR (scope = 'shared' AND couple_space_id IS NOT NULL AND owner_user_id IS NULL)
            )");
        }
    }

    public function down(): void
    {
        foreach (['poll_votes', 'poll_options', 'polls', 'nudges', 'time_capsules', 'daily_answers', 'list_items', 'milestones'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('couple_spaces', function (Blueprint $table) {
            $table->dropColumn('anniversary_date');
        });
    }
};

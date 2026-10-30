<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Own backdrop: a built-in preset or an uploaded photo, plus how strong the veil over it is.
            $table->string('background_preset', 20)->nullable()->after('avatar_path');
            $table->string('background_path')->nullable()->after('background_preset');
            $table->tinyInteger('background_veil')->nullable()->after('background_path');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            // Key of a built-in sticker (config usandus.stickers); the body is empty then.
            $table->string('sticker', 32)->nullable()->after('photo_height');
        });

        Schema::table('couple_spaces', function (Blueprint $table) {
            $table->unsignedInteger('date_budget')->nullable()->after('anniversary_date');
        });

        Schema::create('playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->string('artist', 120)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('note', 200)->nullable();
            $table->boolean('is_ours')->default(false);
            $table->timestamps();

            $table->index(['couple_space_id', 'id']);
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('question_index');
            // About yourself, and your guess of what your partner answers about themselves.
            $table->unsignedTinyInteger('self_choice');
            $table->unsignedTinyInteger('guess_choice');
            $table->timestamps();

            $table->unique(['couple_space_id', 'user_id', 'question_index']);
        });

        Schema::create('date_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 80);
            $table->unsignedInteger('amount');
            $table->enum('category', ['makan', 'jalan', 'hadiah', 'nonton', 'lainnya'])->default('lainnya');
            $table->date('spent_on');
            $table->timestamps();

            $table->index(['couple_space_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_expenses');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('playlist_items');
        Schema::table('couple_spaces', fn (Blueprint $table) => $table->dropColumn('date_budget'));
        Schema::table('chat_messages', fn (Blueprint $table) => $table->dropColumn('sticker'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['background_preset', 'background_path', 'background_veil']));
    }
};

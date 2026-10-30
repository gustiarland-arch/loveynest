<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One mood per person per day; saving again the same day replaces it.
        Schema::create('moods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('mood_date');
            $table->unsignedTinyInteger('level');
            $table->string('note', 140)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'mood_date']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $this->spaceColumns($table);
            // A letter is always shared and is written to the partner.
            $table->enum('kind', ['journal', 'letter'])->default('journal');
            $table->string('title', 120);
            $table->text('body');
            $table->date('entry_date');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'kind', 'entry_date']);
            $table->index(['couple_space_id', 'kind', 'entry_date']);
        });

        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $this->spaceColumns($table);
            $table->string('title', 80);
            $table->string('description', 300)->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'updated_at']);
            $table->index(['couple_space_id', 'updated_at']);
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 120);
            $table->string('thumb_path', 120);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->timestamps();

            $table->index(['album_id', 'id']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE moods ADD CONSTRAINT moods_level_check CHECK (level BETWEEN 1 AND 5)');
            foreach (['journal_entries', 'albums'] as $table) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_scope_check CHECK (
                    (scope = 'personal' AND owner_user_id IS NOT NULL AND couple_space_id IS NULL)
                    OR (scope = 'shared' AND couple_space_id IS NOT NULL AND owner_user_id IS NULL)
                )");
            }
            DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT journal_letter_check CHECK (kind = 'journal' OR scope = 'shared')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
        Schema::dropIfExists('albums');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('moods');
    }

    private function spaceColumns(Blueprint $table): void
    {
        $table->enum('scope', ['personal', 'shared']);
        $table->foreignId('owner_user_id')->nullable()->constrained('users')->cascadeOnDelete();
        $table->foreignId('couple_space_id')->nullable()->constrained()->cascadeOnDelete();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    }
};

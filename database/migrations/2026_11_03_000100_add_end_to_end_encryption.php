<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Columns that hold locked text from now on; a locked token is longer than the plain text, so they become TEXT. */
    public const SEALED = [
        'activity_logs' => ['subject_title' => true],
        'agreements' => ['title' => false, 'note' => true],
        'albums' => ['title' => false, 'description' => true],
        'checkins' => ['appreciation' => true, 'improve' => true],
        'daily_answers' => ['answer' => false],
        'date_expenses' => ['title' => false, 'note' => true],
        'dreams' => ['title' => false],
        'events' => ['title' => false, 'rating_note' => true],
        'goals' => ['title' => false],
        'habits' => ['title' => false],
        'journal_entries' => ['title' => false],
        'list_items' => ['title' => false, 'note' => true],
        'love_notes' => ['body' => false],
        'milestones' => ['title' => false],
        'moods' => ['note' => true],
        'photos' => ['caption' => true, 'location' => true],
        'playlist_items' => ['title' => false, 'artist' => true, 'url' => true, 'note' => true],
        'poll_options' => ['label' => false],
        'polls' => ['question' => false],
        'reflections' => ['went_well' => true, 'to_improve' => true, 'next_month' => true],
        'rewards' => ['title' => false],
        'savings_goals' => ['title' => false],
        'task_subtasks' => ['title' => false],
        'tasks' => ['title' => false],
        'time_capsules' => ['title' => false],
    ];

    public function up(): void
    {
        // Public keys of each phone or browser. The private key never leaves the device.
        Schema::create('e2e_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('public_key');
            $table->string('label', 80)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // One key per couple space (a new one after a reset); only its id is known here.
        Schema::create('e2e_space_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->char('key_id', 8);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('key_id');
            $table->index(['couple_space_id', 'id']);
        });

        // The space key, locked separately for each approved device with that device's public key.
        Schema::create('e2e_key_wraps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('e2e_space_key_id')->constrained()->cascadeOnDelete();
            $table->foreignId('e2e_device_id')->constrained()->cascadeOnDelete();
            $table->text('wrapped');
            $table->foreignId('wrapped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['e2e_space_key_id', 'e2e_device_id']);
        });

        foreach (self::SEALED as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column => $nullable) {
                    $table->text($column)->nullable($nullable)->change();
                }
            });
        }

        // Shared location is one locked JSON value; the plain columns stay for servers with E2E off.
        Schema::table('location_shares', function (Blueprint $table) {
            $table->text('sealed')->nullable()->after('accuracy');
        });
    }

    public function down(): void
    {
        Schema::table('location_shares', function (Blueprint $table) {
            $table->dropColumn('sealed');
        });
        Schema::dropIfExists('e2e_key_wraps');
        Schema::dropIfExists('e2e_space_keys');
        Schema::dropIfExists('e2e_devices');
    }
};

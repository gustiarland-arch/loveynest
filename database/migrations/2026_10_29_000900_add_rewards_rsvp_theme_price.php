<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('list_items', fn (Blueprint $table) => $table->unsignedInteger('price')->nullable()->after('note'));
        Schema::table('couple_spaces', fn (Blueprint $table) => $table->enum('theme', ['rose', 'lavender', 'sage'])->default('rose')->after('date_budget'));

        // Treats the two of you promise each other, made up by you.
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->timestamp('given_at')->nullable();
            $table->timestamps();

            $table->index(['couple_space_id', 'given_at']);
        });

        Schema::create('event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['yes', 'maybe', 'no']);
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_rsvps');
        Schema::dropIfExists('rewards');
        Schema::table('couple_spaces', fn (Blueprint $table) => $table->dropColumn('theme'));
        Schema::table('list_items', fn (Blueprint $table) => $table->dropColumn('price'));
    }
};

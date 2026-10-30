<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couple_spaces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'dissolved'])->default('active')->index();
            $table->timestamp('dissolved_at')->nullable();
            $table->foreignId('dissolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('couple_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            // Only set while the membership is active, so the unique indexes
            // below enforce "one active space per user" and "two active
            // members per space" at the database level.
            $table->unsignedBigInteger('active_user_id')->nullable()->storedAs('IF(left_at IS NULL, user_id, NULL)');
            $table->unsignedTinyInteger('active_slot')->nullable()->storedAs('IF(left_at IS NULL, slot, NULL)');
            $table->timestamps();

            $table->unique('active_user_id');
            $table->unique(['couple_space_id', 'active_slot']);
            $table->index(['couple_space_id', 'left_at']);
        });

        Schema::create('couple_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['couple_space_id', 'used_at', 'revoked_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE couple_members ADD CONSTRAINT couple_members_slot_check CHECK (slot IN (1, 2))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('couple_invitations');
        Schema::dropIfExists('couple_members');
        Schema::dropIfExists('couple_spaces');
    }
};

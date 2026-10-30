<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The couple key locked with a key the browser derives from the login password.
        // Only the locked result and a random salt are stored, never the password or the key.
        Schema::create('e2e_password_wraps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('e2e_space_key_id')->constrained()->cascadeOnDelete();
            $table->string('salt', 64);
            $table->string('kdf', 16)->default('pbkdf2-sha256');
            $table->unsignedInteger('iterations')->default(600000);
            $table->text('wrapped');
            $table->timestamps();
            $table->unique(['user_id', 'e2e_space_key_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e2e_password_wraps');
    }
};

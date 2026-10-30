<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('topic', 80)->nullable();
            $table->string('question', 200);
            $table->text('choices'); // JSON list of 3-4 short strings
            $table->timestamps();
            $table->index('couple_space_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_quiz_questions');
    }
};

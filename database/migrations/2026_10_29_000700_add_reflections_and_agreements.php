<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('month_start');
            $table->string('went_well', 500)->nullable();
            $table->string('to_improve', 500)->nullable();
            $table->string('next_month', 500)->nullable();
            // Yours alone until you share it.
            $table->boolean('shared')->default(false);
            $table->timestamps();

            $table->unique(['couple_space_id', 'user_id', 'month_start']);
        });

        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->string('note', 300)->nullable();
            $table->enum('status', ['active', 'paused', 'done'])->default('active');
            $table->timestamps();

            $table->index(['couple_space_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
        Schema::dropIfExists('reflections');
    }
};

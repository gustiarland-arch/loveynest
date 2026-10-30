<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('nudges', 'seen_at')) {
            Schema::table('nudges', function (Blueprint $table) {
                $table->timestamp('seen_at')->nullable();
            });
        }

        if (! Schema::hasTable('daily_quotes')) {
            Schema::create('daily_quotes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('couple_space_id')->constrained()->cascadeOnDelete();
                $table->date('quote_date');
                $table->string('body', 300);
                $table->timestamps();
                $table->unique(['couple_space_id', 'quote_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_quotes');
        if (Schema::hasColumn('nudges', 'seen_at')) {
            Schema::table('nudges', function (Blueprint $table) {
                $table->dropColumn('seen_at');
            });
        }
    }
};

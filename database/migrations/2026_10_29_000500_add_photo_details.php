<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('caption', 200)->nullable()->after('height');
            $table->string('location', 120)->nullable()->after('caption');
            $table->date('taken_on')->nullable()->after('location');
            $table->boolean('is_favorite')->default(false)->after('taken_on');
            $table->foreignId('event_id')->nullable()->after('is_favorite')->constrained()->nullOnDelete();
        });
        // Chosen cover. Kept as a plain id: the controller clears it when that photo goes.
        Schema::table('albums', fn (Blueprint $table) => $table->unsignedBigInteger('cover_photo_id')->nullable()->after('description'));
    }

    public function down(): void
    {
        Schema::table('albums', fn (Blueprint $table) => $table->dropColumn('cover_photo_id'));
        Schema::table('photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn(['caption', 'location', 'taken_on', 'is_favorite']);
        });
    }
};

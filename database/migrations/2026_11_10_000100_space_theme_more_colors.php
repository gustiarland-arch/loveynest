<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The accent was an enum of three colours; a plain string lets new colours be added without another migration.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE couple_spaces MODIFY theme VARCHAR(20) NOT NULL DEFAULT 'rose'");
        }
    }

    public function down(): void
    {
        //
    }
};

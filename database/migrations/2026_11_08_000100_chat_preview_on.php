<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Chat notifications now show the message itself (opened on the phone), as both of you asked. Each can still switch it off. */
    public function up(): void
    {
        DB::table('notification_preferences')->where('type', 'chat_message')->update(['show_preview' => true]);
    }

    public function down(): void
    {
        // Nothing to undo: the old value per person is not kept.
    }
};

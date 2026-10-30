<?php

namespace App\Console\Commands;

use App\Models\LoveNote;
use App\Models\User;
use App\Services\Chat\ChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class DeliverLoveNotes extends Command
{
    protected $signature = 'chat:deliver-notes';

    protected $description = 'Kirim love note terjadwal ke chat saat waktunya tiba (dijalankan scheduler tiap menit)';

    public function handle(ChatService $chat): int
    {
        $sent = 0;
        LoveNote::whereNull('delivered_at')->where('deliver_at', '<=', now())->orderBy('id')->limit(50)->get()->each(function (LoveNote $note) use ($chat, &$sent) {
            // Claim the note first so two overlapping runs never send it twice.
            if (LoveNote::whereKey($note->id)->whereNull('delivered_at')->update(['delivered_at' => now()]) === 0) {
                return;
            }
            try {
                $sender = User::find($note->sender_id);
                $conversation = $sender ? $chat->conversationFor($sender) : null;
                if ($sender && $conversation && $conversation->couple_space_id === $note->couple_space_id && $sender->partner()) {
                    $chat->send($conversation, $sender, '💌 '.$note->body, (string) Str::uuid());
                    $sent++;
                }
            } catch (Throwable $e) {
                report($e);
                LoveNote::whereKey($note->id)->update(['delivered_at' => null]);
            }
        });
        $this->line("terkirim={$sent}");

        return self::SUCCESS;
    }
}

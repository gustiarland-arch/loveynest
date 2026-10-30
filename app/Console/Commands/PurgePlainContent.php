<?php

namespace App\Console\Commands;

use App\Models\CoupleSpace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * After switching end-to-end encryption on, content written before it is still plain text on the
 * server. This removes it for good: everything a couple space (and its two members) made before the
 * space's first E2E key. Without --hapus it only counts.
 */
class PurgePlainContent extends Command
{
    protected $signature = 'usandus:e2e-hapus-lama {--hapus : Benar-benar menghapus (tanpa ini hanya menghitung)} {--force : Tanpa pertanyaan konfirmasi}';

    protected $description = 'Hapus isi lama yang dibuat sebelum enkripsi end-to-end aktif (tidak bisa dibatalkan)';

    /** Tables whose rows belong to a space (or to one member when the space column is empty). */
    private const SPACE_TABLES = [
        'tasks' => 'created_by', 'events' => 'created_by', 'journal_entries' => 'created_by', 'albums' => 'created_by',
        'checkins' => 'user_id', 'daily_answers' => 'user_id', 'list_items' => 'created_by', 'love_notes' => 'sender_id',
        'milestones' => 'created_by', 'polls' => 'created_by', 'reflections' => 'user_id', 'rewards' => 'created_by',
        'savings_goals' => 'created_by', 'time_capsules' => 'created_by', 'dreams' => 'created_by', 'goals' => 'created_by',
        'habits' => 'created_by', 'playlist_items' => null, 'date_expenses' => null, 'agreements' => 'created_by',
        'activity_logs' => null, 'location_shares' => 'user_id', 'photobooth_sessions' => 'created_by',
    ];

    public function handle(): int
    {
        $delete = (bool) $this->option('hapus');
        $spaces = DB::table('e2e_space_keys')->select('couple_space_id', DB::raw('MIN(created_at) as since'))->groupBy('couple_space_id')->get();
        if ($spaces->isEmpty()) {
            $this->warn('Belum ada ruang berdua yang memakai enkripsi end-to-end. Buka aplikasinya dulu lewat https.');

            return self::SUCCESS;
        }

        if ($delete && ! $this->option('force') && ! $this->confirm('Isi lama (chat, foto, jurnal, tugas, dan lainnya) dihapus permanen. Lanjutkan?')) {
            return self::FAILURE;
        }

        foreach ($spaces as $row) {
            $space = CoupleSpace::find($row->couple_space_id);
            if (! $space) {
                continue;
            }
            $members = DB::table('couple_members')->where('couple_space_id', $space->id)->pluck('user_id');
            $this->line("Ruang #{$space->id}: isi sebelum {$row->since}");
            $counts = DB::transaction(fn () => $this->purge($space->id, $members->all(), $row->since, $delete));
            foreach ($counts as $what => $n) {
                if ($n) {
                    $this->line(sprintf('  %-22s %d', $what, $n));
                }
            }
        }

        $this->info($delete ? 'Selesai. Isi lama sudah dihapus.' : 'Ini hanya hitungan. Jalankan dengan --hapus untuk menghapus.');

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function purge(int $spaceId, array $members, string $since, bool $delete): array
    {
        $counts = [];
        $files = [];

        $old = fn (string $table, ?string $owner) => DB::table($table)->where('created_at', '<', $since)
            ->where(fn ($q) => $owner
                ? $q->where('couple_space_id', $spaceId)->orWhere(fn ($p) => $p->whereNull('couple_space_id')->whereIn($owner, $members))
                : $q->where('couple_space_id', $spaceId));

        // Files first, while the rows that point to them still exist.
        $albumIds = DB::table('albums')->where(fn ($q) => $q->where('couple_space_id', $spaceId)->orWhere(fn ($p) => $p->whereNull('couple_space_id')->whereIn('created_by', $members)))->pluck('id');
        $photos = DB::table('photos')->whereIn('album_id', $albumIds)->where('created_at', '<', $since);
        foreach ($photos->get(['path', 'thumb_path']) as $p) {
            $files[] = $p->path;
            $files[] = $p->thumb_path;
        }
        $counts['photos'] = $photos->count();
        $conversation = DB::table('chat_conversations')->where('couple_space_id', $spaceId)->value('id');
        $messages = DB::table('chat_messages')->where('chat_conversation_id', $conversation)->where('created_at', '<', $since);
        foreach ($messages->get(['photo_path', 'photo_thumb_path', 'audio_path']) as $m) {
            array_push($files, $m->photo_path, $m->photo_thumb_path, $m->audio_path);
        }
        $counts['chat_messages'] = $messages->count();
        foreach ($old('dreams', 'created_by')->get(['image_path', 'image_thumb_path']) as $d) {
            array_push($files, $d->image_path, $d->image_thumb_path);
        }
        $sessions = $old('photobooth_sessions', 'created_by')->pluck('id');
        $files = array_merge($files, DB::table('photobooth_shots')->whereIn('photobooth_session_id', $sessions)->pluck('path')->all());

        foreach (self::SPACE_TABLES as $table => $owner) {
            $counts[$table] = $old($table, $owner)->count();
        }
        $counts['moods'] = DB::table('moods')->whereIn('user_id', $members)->where('created_at', '<', $since)->count();
        $counts['notifications'] = DB::table('notifications')->where('notifiable_type', 'App\\Models\\User')->whereIn('notifiable_id', $members)->where('created_at', '<', $since)->count();

        if (! $delete) {
            return $counts;
        }

        $photoIds = (clone $photos)->pluck('id');
        DB::table('albums')->whereIn('cover_photo_id', $photoIds)->update(['cover_photo_id' => null]);
        DB::table('photobooth_sessions')->whereIn('photo_id', $photoIds)->update(['photo_id' => null]);
        $photos->delete();
        $messageIds = (clone $messages)->pluck('id');
        DB::table('chat_messages')->whereIn('reply_to_id', $messageIds)->update(['reply_to_id' => null]);
        DB::table('chat_messages')->where('chat_conversation_id', $conversation)->where('created_at', '<', $since)->delete();
        foreach (self::SPACE_TABLES as $table => $owner) {
            $old($table, $owner)->delete();
        }
        DB::table('moods')->whereIn('user_id', $members)->where('created_at', '<', $since)->delete();
        DB::table('notifications')->where('notifiable_type', 'App\\Models\\User')->whereIn('notifiable_id', $members)->where('created_at', '<', $since)->delete();
        Storage::disk('local')->delete(array_values(array_unique(array_filter($files))));

        return $counts;
    }
}

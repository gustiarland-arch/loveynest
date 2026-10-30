<?php

namespace App\Services\Journal;

use App\Enums\Scope;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use Illuminate\Support\Facades\DB;

class JournalService
{
    public function __construct(private ActivityRecorder $activity) {}

    /** A letter is always shared, whatever scope was asked for. */
    public function create(User $user, Scope $scope, string $kind, array $attributes): JournalEntry
    {
        return DB::transaction(function () use ($user, $scope, $kind, $attributes) {
            $entry = new JournalEntry($attributes);
            $entry->kind = $kind;
            $entry->assignSpace($user, $kind === 'letter' ? Scope::Shared : $scope);
            $entry->save();

            if ($entry->isShared()) {
                $this->activity->record($entry->couple_space_id, $user, $entry->isLetter() ? 'letter.sent' : 'journal.created', 'journal', $entry->id, $entry->title);
            }

            return $entry;
        });
    }

    public function update(JournalEntry $entry, array $attributes): JournalEntry
    {
        $entry->fill($attributes)->save();

        return $entry;
    }

    /** The partner opening a letter marks it as read; the writer opening it does not. */
    public function markRead(JournalEntry $entry, User $reader): void
    {
        if ($entry->isLetter() && $entry->read_at === null && $entry->created_by !== $reader->id) {
            $entry->forceFill(['read_at' => now()])->save();
        }
    }
}

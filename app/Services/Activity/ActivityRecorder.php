<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;
use App\Models\CoupleSpace;
use App\Models\User;
use App\Notifications\PartnerActivity;
use App\Notifications\PartnerJoined;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records shared-space activity for the Our Space feed. Personal items are
 * never passed here, so nothing private can surface in the feed.
 */
class ActivityRecorder
{
    public function record(int $spaceId, ?User $actor, string $action, ?string $subjectType = null, ?int $subjectId = null, ?string $subjectTitle = null): ActivityLog
    {
        $log = ActivityLog::create([
            'couple_space_id' => $spaceId,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_title' => $subjectTitle,
        ]);

        if ($actor) {
            $this->notifyPartner($spaceId, $actor, $action, (string) $subjectTitle);
        }

        return $log;
    }

    /**
     * Tells the other member about changes worth knowing. Edits and deletions
     * stay in the feed only, so the partner is not pinged for every tweak.
     */
    private function notifyPartner(int $spaceId, User $actor, string $action, string $subjectTitle): void
    {
        $notification = match ($action) {
            'task.created', 'task.completed', 'event.created', 'letter.sent', 'photo.added' => new PartnerActivity($actor->firstName(), $action, $subjectTitle),
            'member.joined' => new PartnerJoined($actor->firstName()),
            default => null,
        };
        if (! $notification) {
            return;
        }

        // A notification problem must never undo the task or event that caused it.
        try {
            CoupleSpace::find($spaceId)?->activeMembers()
                ->where('users.id', '!=', $actor->id)
                ->get()
                ->each->notify($notification);
        } catch (Throwable $e) {
            Log::warning('notification.failed', ['type' => $notification->type(), 'error' => $e::class]);
        }
    }
}

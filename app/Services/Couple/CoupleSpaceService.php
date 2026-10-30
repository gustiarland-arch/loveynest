<?php

namespace App\Services\Couple;

use App\Exceptions\CoupleException;
use App\Models\CoupleInvitation;
use App\Models\CoupleMember;
use App\Models\CoupleSpace;
use App\Models\Event;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CoupleSpaceService
{
    public function create(User $user, ?string $name = null): CoupleSpace
    {
        try {
            return DB::transaction(function () use ($user, $name) {
                User::whereKey($user->id)->lockForUpdate()->first();
                if ($user->activeMembership()->lockForUpdate()->exists()) {
                    throw new CoupleException('Kamu sudah tergabung dalam ruang pasangan.');
                }

                $space = CoupleSpace::create(['name' => $name ?: null, 'created_by' => $user->id]);

                CoupleMember::create([
                    'couple_space_id' => $space->id,
                    'user_id' => $user->id,
                    'slot' => 1,
                    'joined_at' => now(),
                ]);

                return $space;
            }, attempts: 3);
        } catch (QueryException $e) {
            // The unique index on active_user_id is the last line of defence
            // against two simultaneous "create" requests.
            if (self::isUniqueViolation($e) || self::isDeadlock($e)) {
                throw new CoupleException('Kamu sudah tergabung dalam ruang pasangan.');
            }
            throw $e;
        }
    }

    public static function isDeadlock(QueryException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [1213, 1205], true);
    }

    public static function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000' && ($e->errorInfo[1] ?? null) === 1062;
    }

    /**
     * Ends the space for both members. Nothing is deleted: shared tasks,
     * events and chat stay in the database but become unreachable, because
     * access always goes through an active membership. Pending shared
     * reminders are cancelled; personal data is untouched.
     *
     * @return list<User> the other members who lost access
     */
    public function dissolve(User $user): array
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $membership = $user->activeMembership()->lockForUpdate()->first();
            if (! $membership) {
                throw new CoupleException('Kamu belum tergabung dalam ruang pasangan.');
            }

            $space = CoupleSpace::whereKey($membership->couple_space_id)->lockForUpdate()->first();
            $others = $space->activeMembers()->where('users.id', '!=', $user->id)->get()->all();

            CoupleMember::where('couple_space_id', $space->id)->whereNull('left_at')->update(['left_at' => now()]);
            $space->forceFill(['status' => 'dissolved', 'dissolved_at' => now(), 'dissolved_by' => $user->id])->save();

            \App\Models\LocationShare::where('couple_space_id', $space->id)->delete();
            CoupleInvitation::where('couple_space_id', $space->id)->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            Reminder::where('status', 'pending')
                ->where(fn ($q) => $q
                    ->whereIn('event_id', Event::where('couple_space_id', $space->id)->select('id'))
                    ->orWhereIn('task_id', Task::where('couple_space_id', $space->id)->select('id')))
                ->update(['status' => 'cancelled']);

            Log::channel('audit')->info('couple.dissolved', ['space' => $space->id, 'by' => $user->id]);

            return $others;
        }, attempts: 3);
    }
}

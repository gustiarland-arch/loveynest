<?php

namespace App\Services\Couple;

use App\Exceptions\CoupleException;
use App\Models\ChatConversation;
use App\Models\CoupleInvitation;
use App\Models\CoupleMember;
use App\Models\CoupleSpace;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use App\Support\InviteCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class InvitationService
{
    public function __construct(private ActivityRecorder $activity) {}

    /**
     * Creates a fresh single-use code for the user's space and revokes older ones.
     *
     * @return array{code: string, invitation: CoupleInvitation}
     */
    public function issue(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $membership = $user->activeMembership()->lockForUpdate()->first();
            if (! $membership) {
                throw new CoupleException('Buat ruang pasangan terlebih dahulu.');
            }

            $space = CoupleSpace::whereKey($membership->couple_space_id)->lockForUpdate()->firstOrFail();
            if ($space->isFull()) {
                throw new CoupleException('Ruang kalian sudah lengkap berdua.');
            }

            $this->revokeOpenInvitations($space);

            do {
                $code = InviteCode::generate(config('usandus.invitations.length'));
                $hash = InviteCode::hash($code);
            } while (CoupleInvitation::where('code_hash', $hash)->exists());

            $invitation = CoupleInvitation::create([
                'couple_space_id' => $space->id,
                'created_by' => $user->id,
                'code_hash' => $hash,
                'expires_at' => now()->addHours(config('usandus.invitations.ttl_hours')),
            ]);

            return ['code' => $code, 'invitation' => $invitation];
        });
    }

    public function revoke(User $user): void
    {
        $spaceId = $user->activeSpaceId();
        if ($spaceId) {
            $this->revokeOpenInvitations(CoupleSpace::findOrFail($spaceId));
        }
    }

    /**
     * Read-only check used for the confirmation screen. Raises the same errors
     * that accepting would, so the user is not asked to confirm a dead code.
     */
    public function preview(User $user, string $code): CoupleInvitation
    {
        $invitation = CoupleInvitation::with(['space', 'creator'])
            ->where('code_hash', InviteCode::hash($code))
            ->first();

        $this->assertJoinable($user, $invitation, $invitation?->space);

        return $invitation;
    }

    public function accept(User $user, string $code): CoupleSpace
    {
        try {
            return DB::transaction(function () use ($user, $code) {
                // Lock order: user, invitation, space. Every join path uses the
                // same order, so concurrent joins serialise instead of deadlocking.
                User::whereKey($user->id)->lockForUpdate()->first();
                $invitation = CoupleInvitation::where('code_hash', InviteCode::hash($code))->lockForUpdate()->first();
                $space = $invitation ? CoupleSpace::whereKey($invitation->couple_space_id)->lockForUpdate()->first() : null;

                $this->assertJoinable($user, $invitation, $space);

                $takenSlots = $space->members()->whereNull('left_at')->lockForUpdate()->pluck('slot')->all();
                $slot = in_array(1, $takenSlots, true) ? 2 : 1;

                CoupleMember::create([
                    'couple_space_id' => $space->id,
                    'user_id' => $user->id,
                    'slot' => $slot,
                    'joined_at' => now(),
                ]);

                $invitation->forceFill(['used_at' => now(), 'used_by' => $user->id])->save();
                $this->revokeOpenInvitations($space);

                ChatConversation::firstOrCreate(['couple_space_id' => $space->id]);
                $this->activity->record($space->id, $user, 'member.joined');

                return $space;
            }, attempts: 3);
        } catch (QueryException $e) {
            if (CoupleSpaceService::isUniqueViolation($e) || CoupleSpaceService::isDeadlock($e)) {
                throw new CoupleException('Ruang ini sudah lengkap berdua, atau kamu sudah tergabung di ruang lain.');
            }
            throw $e;
        }
    }

    private function assertJoinable(User $user, ?CoupleInvitation $invitation, ?CoupleSpace $space): void
    {
        if (! $invitation || ! $space) {
            throw new CoupleException('Kode undangan tidak ditemukan. Periksa lagi penulisannya.');
        }
        if ($user->activeMembership()->exists()) {
            throw new CoupleException('Kamu sudah tergabung dalam ruang pasangan.');
        }
        if ($invitation->used_at) {
            throw new CoupleException('Kode undangan ini sudah dipakai. Minta pasanganmu membuat kode baru.');
        }
        if ($invitation->revoked_at) {
            throw new CoupleException('Kode undangan ini sudah tidak berlaku. Minta pasanganmu membuat kode baru.');
        }
        if ($invitation->expires_at->isPast()) {
            throw new CoupleException('Kode undangan ini sudah kedaluwarsa. Minta pasanganmu membuat kode baru.');
        }
        if (! $space->isActive() || $space->isFull()) {
            throw new CoupleException('Ruang ini sudah lengkap berdua.');
        }
    }

    private function revokeOpenInvitations(CoupleSpace $space): void
    {
        $space->invitations()->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}

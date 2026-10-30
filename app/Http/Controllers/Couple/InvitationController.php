<?php

namespace App\Http\Controllers\Couple;

use App\Exceptions\CoupleException;
use App\Http\Controllers\Controller;
use App\Models\CoupleInvitation;
use App\Services\Couple\InvitationService;
use App\Support\InviteCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * The plain code exists only in the creator's session; the database holds its hash.
     *
     * @param  array{code: string, invitation: CoupleInvitation}  $issued
     */
    public static function rememberCode(Request $request, array $issued): void
    {
        $request->session()->put('invite', ['id' => $issued['invitation']->id, 'code' => $issued['code']]);
    }

    public function show(Request $request): Response
    {
        $user = $request->user();
        $space = $user->activeSpace();
        $members = $space->activeMembers()->get(\App\Models\User::pictureColumns());

        $invite = null;
        $stored = $request->session()->get('invite');
        if ($stored && $members->count() < 2) {
            $invitation = CoupleInvitation::whereKey($stored['id'])->where('couple_space_id', $space->id)->first();
            if ($invitation?->isUsable()) {
                $invite = ['code' => InviteCode::format($stored['code']), 'expires_at' => $invitation->expires_at->toIso8601String()];
            }
        }

        return Inertia::render('Onboarding/Invite', [
            'space' => ['name' => $space->name],
            'members' => $members->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'avatarUrl' => $m->avatarUrl(), 'avatarFrame' => $m->avatar_frame])->values(),
            'invite' => $invite,
            'hasOpenInvite' => $space->invitations()->usable()->exists(),
        ]);
    }

    public function store(Request $request, InvitationService $invitations): RedirectResponse
    {
        try {
            self::rememberCode($request, $invitations->issue($request->user()));
        } catch (CoupleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('couple.invite')->with('success', 'Kode undangan baru sudah dibuat.');
    }

    public function destroy(Request $request, InvitationService $invitations): RedirectResponse
    {
        $invitations->revoke($request->user());
        $request->session()->forget('invite');

        return redirect()->route('couple.invite')->with('success', 'Kode undangan dibatalkan.');
    }
}
